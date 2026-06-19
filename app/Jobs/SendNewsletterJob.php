<?php

namespace App\Jobs;

use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceNewsletterRecipient;
use App\Models\MarketplaceEmailLog;
use App\Models\ServiceOrder;
use App\Services\NewsletterRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    protected MarketplaceNewsletter $newsletter;
    protected int $batchSize;

    public function __construct(MarketplaceNewsletter $newsletter, ?int $batchSize = null)
    {
        $this->newsletter = $newsletter;
        // Throttle resolution: per-marketplace setting overrides the
        // global config. Default 25 emails per batch — slow enough that
        // the queue worker doesn't saturate the DB / PHP-FPM pool that
        // the public ambilet.ro front-end also depends on (the 3s nav
        // cache curl was timing out mid-blast).
        $this->batchSize = $batchSize
            ?? $this->resolveBatchSize($newsletter);
    }

    protected function resolveBatchSize(MarketplaceNewsletter $newsletter): int
    {
        $perMarketplace = $newsletter->marketplaceClient?->settings['newsletter_throttle']['batch_size'] ?? null;
        $resolved = $perMarketplace ?? config('newsletter.throttle.batch_size', 25);
        return max(1, (int) $resolved);
    }

    protected function resolveBatchDelaySeconds(): int
    {
        $perMarketplace = $this->newsletter->marketplaceClient?->settings['newsletter_throttle']['batch_delay_seconds'] ?? null;
        $resolved = $perMarketplace ?? config('newsletter.throttle.batch_delay_seconds', 8);
        return max(1, (int) $resolved);
    }

    public function handle(): void
    {
        $newsletter = $this->newsletter->fresh();
        $marketplace = $newsletter->marketplaceClient;

        // Transition scheduled newsletters to sending state
        if ($newsletter->status === 'scheduled') {
            $newsletter->startSending();
        }

        // Check if newsletter is in a sendable state
        if (!in_array($newsletter->status, ['sending'])) {
            return;
        }

        // Check if SMTP is configured
        if (!$marketplace->hasSmtpConfigured()) {
            $newsletter->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
            \Log::error("Newsletter {$newsletter->id} failed: SMTP not configured for marketplace {$marketplace->id}");
            return;
        }

        // Get pending recipients
        $recipients = MarketplaceNewsletterRecipient::where('newsletter_id', $newsletter->id)
            ->where('status', 'pending')
            ->limit($this->batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            // All done
            $newsletter->markCompleted();
            $this->completeServiceOrder($newsletter);
            return;
        }

        // Get SMTP transport
        $transport = $marketplace->getSmtpTransport();

        if (!$transport) {
            $newsletter->update(['status' => 'failed']);
            return;
        }

        foreach ($recipients as $recipient) {
            // Last-line defense: skip recipients whose customer is now flagged
            // as email_suppressed. Audience filters at newsletter creation time
            // (ServiceOrderController::buildAudienceBaseQuery) already strip
            // these out, but a newsletter scheduled BEFORE the customer was
            // flagged would still carry them in the recipients table. Mark
            // them as skipped (not 'sent') so the sent-count stays honest.
            $customer = $recipient->customer;
            if ($customer && !empty($customer->email_suppressed)) {
                $recipient->markFailed('skipped:email_suppressed:' . ($customer->email_suppression_reason ?? 'unknown'));
                continue;
            }
            try {
                $this->sendToRecipient($newsletter, $recipient, $transport, $marketplace);
            } catch (\Exception $e) {
                $recipient->markFailed($e->getMessage());
                \Log::error("Failed to send newsletter to {$recipient->email}: {$e->getMessage()}");
            }
        }

        // Check if there are more recipients
        $remaining = MarketplaceNewsletterRecipient::where('newsletter_id', $newsletter->id)
            ->where('status', 'pending')
            ->count();

        if ($remaining > 0) {
            // Dispatch next batch with a configurable delay so the
            // overall throughput stays bounded — protects the DB / PHP-
            // FPM pool that the public site shares with the queue worker.
            // Don't pass $this->batchSize through: re-reading the throttle
            // on each iteration lets the admin tighten the knob mid-blast
            // (e.g. when the site starts struggling) and have the in-
            // flight chain pick up the change on the next batch.
            static::dispatch($newsletter)
                ->delay(now()->addSeconds($this->resolveBatchDelaySeconds()));
        } else {
            $newsletter->markCompleted();
            $this->completeServiceOrder($newsletter);
        }
    }

    protected function sendToRecipient(
        MarketplaceNewsletter $newsletter,
        MarketplaceNewsletterRecipient $recipient,
        $transport,
        $marketplace
    ): void {
        // Personalize content
        $customer = $recipient->customer;
        $variables = [
            'customer_name' => $customer?->full_name ?? 'Subscriber',
            'customer_email' => $recipient->email,
            'marketplace_name' => $marketplace->name,
            'unsubscribe_url' => $this->generateUnsubscribeUrl($recipient),
        ];

        $subject = $this->replaceVariables($newsletter->subject, $variables);

        // Use section renderer if body_sections is set, otherwise fall back to body_html.
        // The renderer now handles BOTH click instrumentation and the
        // open-tracking pixel itself (see NewsletterRenderer::render +
        // instrumentLinks + wrapInEmailTemplate). Passing the recipient
        // through lets the HMAC token bind the click/open to a specific
        // marketplace_newsletter_recipients row. The legacy
        // wrapLinksWithTracking + generateTrackingPixel helpers below
        // are kept only for the body_html backward-compat path.
        if (!empty($newsletter->body_sections)) {
            $renderer = new NewsletterRenderer();
            $bodyHtml = $renderer->render($newsletter, $customer, $recipient);
            $bodyHtml = $this->replaceVariables($bodyHtml, $variables);
        } else {
            $bodyHtml = $this->replaceVariables($newsletter->body_html, $variables);
            $bodyHtml = $this->wrapLinksWithTracking($bodyHtml, $recipient);
            $bodyHtml .= $this->generateTrackingPixel($recipient);
        }

        // Create email
        $email = (new Email())
            ->from(new Address($newsletter->from_email, $newsletter->from_name))
            ->to($recipient->email)
            ->subject($subject)
            ->html($bodyHtml);

        if ($newsletter->reply_to) {
            $email->replyTo($newsletter->reply_to);
        }

        if ($newsletter->body_text) {
            $email->text($this->replaceVariables($newsletter->body_text, $variables));
        }

        // Send
        $transport->send($email);

        // Mark as sent
        $recipient->markSent();

        // Log the email
        MarketplaceEmailLog::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_customer_id' => $customer?->id,
            'template_slug' => 'newsletter',
            'to_email' => $recipient->email,
            'to_name' => $customer?->full_name,
            'from_email' => $newsletter->from_email,
            'from_name' => $newsletter->from_name,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => [
                'newsletter_id' => $newsletter->id,
                'recipient_id' => $recipient->id,
            ],
        ]);
    }

    protected function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $val = $value ?? '';
            // Plain placeholder — body text / non-href contexts.
            $content = str_replace('{{' . $key . '}}', $val, $content);
            // WYSIWYG editors (Filament's RichEditor in particular)
            // URL-encode characters inside href attributes when saving,
            // so `<a href="{{unsubscribe_url}}">` becomes
            // `<a href="%7B%7Bunsubscribe_url%7D%7D">` in the stored
            // HTML. Without this second pass the encoded form survives
            // through to the rendered email and the recipient clicks a
            // dead link.
            $content = str_replace('%7B%7B' . $key . '%7D%7D', $val, $content);
            $content = str_replace('%7b%7b' . $key . '%7d%7d', $val, $content);
        }
        return $content;
    }

    protected function generateUnsubscribeUrl(MarketplaceNewsletterRecipient $recipient): string
    {
        $token = hash('sha256', $recipient->id . $recipient->email . config('app.key'));
        return url("/api/marketplace-client/newsletter/unsubscribe?id={$recipient->id}&token={$token}");
    }

    /**
     * Wrap all <a href="..."> links in the HTML with click-tracking redirect URLs.
     * Excludes unsubscribe links and already-tracked links.
     */
    protected function wrapLinksWithTracking(string $html, MarketplaceNewsletterRecipient $recipient): string
    {
        $token = hash('sha256', $recipient->id . 'click' . config('app.key'));
        $trackBase = url('/api/marketplace-client/newsletter/track/click');

        return preg_replace_callback(
            '/<a\s([^>]*?)href=["\']([^"\']+)["\']/i',
            function ($matches) use ($recipient, $token, $trackBase) {
                $attrs = $matches[1];
                $originalUrl = $matches[2];

                // Skip unsubscribe links, tracking links, and mailto/tel
                if (
                    str_contains($originalUrl, '/newsletter/unsubscribe') ||
                    str_contains($originalUrl, '/newsletter/track/') ||
                    str_starts_with($originalUrl, 'mailto:') ||
                    str_starts_with($originalUrl, 'tel:') ||
                    str_starts_with($originalUrl, '#')
                ) {
                    return $matches[0];
                }

                $trackUrl = $trackBase . '?' . http_build_query([
                    'id' => $recipient->id,
                    'token' => $token,
                    'url' => $originalUrl,
                ]);

                return '<a ' . $attrs . 'href="' . htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );
    }

    protected function generateTrackingPixel(MarketplaceNewsletterRecipient $recipient): string
    {
        $token = hash('sha256', $recipient->id . 'open' . config('app.key'));
        $url = url("/api/marketplace-client/newsletter/track/open?id={$recipient->id}&token={$token}");
        return '<img src="' . $url . '" width="1" height="1" style="display:none;" alt="" />';
    }

    /**
     * Complete the linked ServiceOrder after all emails are sent
     */
    protected function completeServiceOrder(MarketplaceNewsletter $newsletter): void
    {
        try {
            $serviceOrderId = $newsletter->target_lists['service_order_id'] ?? null;
            if (!$serviceOrderId) return;

            $serviceOrder = ServiceOrder::find($serviceOrderId);
            if (!$serviceOrder) return;

            $sentCount = $newsletter->recipients()->where('status', 'sent')->count();
            $serviceOrder->update([
                'executed_at' => now(),
                'sent_count' => $sentCount,
                'service_end_date' => now()->toDateString(),
                'status' => ServiceOrder::STATUS_COMPLETED,
            ]);

            // Notify organizer about results
            $serviceOrder->markResultsAvailable();
        } catch (\Exception $e) {
            \Log::warning('Failed to complete service order after newsletter', [
                'newsletter_id' => $newsletter->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("SendNewsletterJob failed for newsletter {$this->newsletter->id}: {$exception->getMessage()}");

        // Mark newsletter as failed if job completely fails
        $this->newsletter->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }
}

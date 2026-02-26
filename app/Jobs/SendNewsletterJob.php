<?php

namespace App\Jobs;

use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceNewsletterRecipient;
use App\Models\MarketplaceEmailLog;
use App\Models\ServiceOrder;
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

    public function __construct(MarketplaceNewsletter $newsletter, int $batchSize = 50)
    {
        $this->newsletter = $newsletter;
        $this->batchSize = $batchSize;
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
            // Dispatch next batch with a small delay
            static::dispatch($newsletter, $this->batchSize)->delay(now()->addSeconds(5));
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
        $bodyHtml = $this->replaceVariables($newsletter->body_html, $variables);

        // Wrap all links with click tracking (before adding tracking pixel)
        $bodyHtml = $this->wrapLinksWithTracking($bodyHtml, $recipient);

        // Add tracking pixel
        $trackingPixel = $this->generateTrackingPixel($recipient);
        $bodyHtml .= $trackingPixel;

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
            $content = str_replace("{{" . $key . "}}", $value ?? '', $content);
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

<?php

namespace App\Jobs;

use App\Http\Controllers\Api\TenantClient\DemoStorefrontController;
use App\Models\TenantNewsletter;
use App\Models\TenantNewsletterSubscriber;
use App\Services\TenantMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Trimite o campanie de newsletter a tenantului abonaților activi (filtrați pe limbă),
 * câte un email pe abonat, fiecare cu linkul lui de dezabonare. Prin TenantMailService
 * (SMTP-ul tenantului sau cel din core). Cu $testEmail trimite doar o probă, fără să
 * schimbe starea campaniei.
 */
class SendTenantNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public int $newsletterId, public ?string $testEmail = null)
    {
    }

    public function handle(TenantMailService $mail): void
    {
        $nl = TenantNewsletter::with('tenant')->find($this->newsletterId);
        if (! $nl || ! $nl->tenant) {
            return;
        }
        if ($this->testEmail) {
            $fake = new TenantNewsletterSubscriber(['email' => $this->testEmail, 'locale' => $nl->locale === 'en' ? 'en' : 'ro']);
            $fake->token = 'test';
            $this->sendOne($mail, $nl, $fake, '[TEST] ');
            return;
        }
        if ($nl->status === 'sent') {
            return;
        }

        $sent = 0;
        $failed = 0;
        $nl->forceFill(['status' => 'sending', 'recipients_count' => $nl->audience()->count()])->save();
        $nl->audience()->orderBy('id')->chunkById(200, function ($subs) use ($mail, $nl, &$sent, &$failed) {
            foreach ($subs as $sub) {
                $this->sendOne($mail, $nl, $sub) ? $sent++ : $failed++;
            }
            $nl->forceFill(['sent_count' => $sent, 'failed_count' => $failed])->save();
            usleep(200000); // un ritm blând pentru SMTP-ul tenantului
        });
        $nl->forceFill(['status' => $sent === 0 && $failed > 0 ? 'failed' : 'sent', 'sent_count' => $sent, 'failed_count' => $failed, 'sent_at' => now()])->save();
    }

    private function sendOne(TenantMailService $mail, TenantNewsletter $nl, TenantNewsletterSubscriber $sub, string $prefix = ''): bool
    {
        $tenant = $nl->tenant;
        try {
            $en = $sub->locale === 'en';
            $name = $tenant->public_name ?: $tenant->name;
            $site = DemoStorefrontController::siteUrl($tenant);
            $unsub = $site ? $site . '/dezabonare?token=' . urlencode((string) $sub->token) . ($en ? '&lang=en' : '') : null;
            $hello = $sub->first_name ? '<p style="margin:0 0 14px;font-size:16px;color:#1B2140;">' . ($en ? 'Hi ' : 'Salut, ') . e($sub->first_name) . '!</p>' : '';
            $preheader = $nl->preheader ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($nl->preheader) . '</div>' : '';
            $body = $preheader . $hello . '<div style="font-size:16px;color:#1B2140;line-height:1.65;">' . self::cleanHtml((string) $nl->body) . '</div>';
            $html = DemoStorefrontController::mailLayout($tenant, $nl->subject, $body, $nl->button_label ?: null, $nl->button_url ?: null, '',
                $unsub ? ($en ? 'You receive this email because you subscribed to news from ' . e($name) . '. ' : 'Primești acest email pentru că te-ai abonat la noutățile ' . e($name) . '. ')
                    . '<a href="' . e($unsub) . '" style="color:#7A84A8;">' . ($en ? 'Unsubscribe' : 'Dezabonează-te') . '</a>' : '');
            $mail->send($tenant, function ($m) use ($sub, $html, $name, $nl, $prefix, $unsub) {
                $m->to($sub->email)->from(config('mail.from.address'), $name)->subject($prefix . $nl->subject)->html($html);
                if ($unsub) {
                    $m->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsub . '>');
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('Tenant newsletter send failed', ['newsletter_id' => $nl->id, 'email' => $sub->email, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** HTML-ul din editor fără scripturi, iframe-uri și atribute on*. */
    private static function cleanHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';
        return preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html) ?? '';
    }
}

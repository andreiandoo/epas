<?php

namespace App\Jobs;

use App\Models\MarketplaceEmailLog;
use App\Services\MarketplaceEmailService;
use App\Support\EmailRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class ResendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    protected MarketplaceEmailLog $emailLog;

    public function __construct(MarketplaceEmailLog $emailLog)
    {
        $this->emailLog = $emailLog;
    }

    public function handle(): void
    {
        $log = $this->emailLog;
        $marketplace = $log->marketplaceClient;

        // Resends must follow the same routing rules as the original send: if
        // the original was a transactional template, retry through the
        // transactional provider; otherwise (e.g. newsletters) stay on primary.
        $useTransactional = EmailRouting::isTransactional($log->template_slug);

        $hasPrimary = $marketplace->hasMailConfigured();
        $hasTransactional = $marketplace->hasTransactionalMailConfigured();

        if (!$useTransactional && !$hasPrimary) {
            $log->markFailed('Primary mail not configured for marketplace');
            return;
        }
        if ($useTransactional && !$hasTransactional && !$hasPrimary) {
            $log->markFailed('Neither transactional nor primary mail is configured for marketplace');
            return;
        }

        try {
            $transport = $useTransactional
                ? $marketplace->getTransactionalMailTransport()
                : $marketplace->getMailTransport();

            if (!$transport) {
                $log->markFailed('Could not create mail transport');
                return;
            }

            // Use the original log's from-address when present; otherwise pick
            // the correct default for this routing decision.
            $fromAddress = $log->from_email
                ?? ($useTransactional
                    ? $marketplace->getTransactionalEmailFromAddress()
                    : $marketplace->getEmailFromAddress());
            $fromName = $log->from_name
                ?? ($useTransactional
                    ? $marketplace->getTransactionalEmailFromName()
                    : $marketplace->getEmailFromName());

            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->to(new Address($log->to_email, $log->to_name ?? ''))
                ->subject($log->subject)
                ->html($log->body_html);

            if ($log->body_text) {
                $email->text($log->body_text);
            }

            $transport->send($email);

            $log->markSent();

        } catch (\Exception $e) {
            \Log::error("ResendEmailJob failed for log {$log->id}: {$e->getMessage()}");
            $log->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("ResendEmailJob completely failed for log {$this->emailLog->id}: {$exception->getMessage()}");
        $this->emailLog->markFailed('Job failed after all retries: ' . $exception->getMessage());
    }
}

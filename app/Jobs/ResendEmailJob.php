<?php

namespace App\Jobs;

use App\Models\MarketplaceEmailLog;
use App\Services\MarketplaceEmailService;
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

        if (!$marketplace->hasSmtpConfigured()) {
            $log->markFailed('SMTP not configured for marketplace');
            return;
        }

        try {
            $transport = $marketplace->getSmtpTransport();

            if (!$transport) {
                $log->markFailed('Could not create SMTP transport');
                return;
            }

            $email = (new Email())
                ->from(new Address(
                    $log->from_email ?? $marketplace->getEmailFromAddress(),
                    $log->from_name ?? $marketplace->getEmailFromName()
                ))
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

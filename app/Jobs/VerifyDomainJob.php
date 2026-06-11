<?php

namespace App\Jobs;

use App\Models\DomainVerification;
use App\Services\DomainVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public DomainVerification $verification
    ) {}

    public function handle(DomainVerificationService $service): void
    {
        if ($this->verification->isVerified()) {
            Log::info('Domain already verified, skipping', [
                'verification_id' => $this->verification->id,
            ]);
            return;
        }

        if ($this->verification->isExpired()) {
            Log::info('Domain verification expired', [
                'verification_id' => $this->verification->id,
            ]);
            return;
        }

        $result = $service->verify($this->verification);

        Log::info('Domain verification attempt completed', [
            'verification_id' => $this->verification->id,
            'domain' => $this->verification->domain->domain,
            'result' => $result ? 'success' : 'failed',
            'attempts' => $this->verification->attempts,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Domain verification job failed', [
            'verification_id' => $this->verification->id,
            'error' => $exception->getMessage(),
        ]);

        $this->verification->markAsFailed('Job failed: ' . $exception->getMessage());
    }
}

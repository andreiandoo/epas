<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\PackageGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 600;

    public function __construct(
        public Domain $domain,
        public bool $regenerate = false
    ) {}

    public function handle(PackageGeneratorService $service): void
    {
        if (!$this->domain->isVerified()) {
            Log::warning('Cannot generate package for unverified domain', [
                'domain_id' => $this->domain->id,
                'domain' => $this->domain->domain,
            ]);
            return;
        }

        Log::info('Starting package generation', [
            'domain_id' => $this->domain->id,
            'domain' => $this->domain->domain,
            'regenerate' => $this->regenerate,
        ]);

        if ($this->regenerate) {
            $package = $service->regenerateForDomain($this->domain);
        } else {
            $package = $service->generate($this->domain);
        }

        Log::info('Package generation completed', [
            'package_id' => $package->id,
            'version' => $package->version,
            'size' => $package->getFileSizeFormatted(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Package generation job failed', [
            'domain_id' => $this->domain->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

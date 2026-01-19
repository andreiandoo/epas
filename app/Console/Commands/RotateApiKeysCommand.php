<?php

namespace App\Console\Commands;

use App\Services\Api\ApiKeyRotationService;
use Illuminate\Console\Command;

/**
 * Rotate API Keys Command
 *
 * Helps manage API key rotation from CLI
 */
class RotateApiKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-keys:rotate
                            {--key-id= : Specific API key ID to rotate}
                            {--tenant-id= : Rotate all keys for a tenant}
                            {--grace-period=7 : Grace period in days for old key}
                            {--check : Check for keys needing rotation}
                            {--expire : Expire deprecated keys past grace period}';

    /**
     * The console command description.
     */
    protected $description = 'Rotate API keys with grace period';

    /**
     * Execute the console command.
     */
    public function handle(ApiKeyRotationService $rotationService): int
    {
        // Check mode
        if ($this->option('check')) {
            $this->checkKeysForRotation($rotationService);
            return 0;
        }

        // Expire mode
        if ($this->option('expire')) {
            $expired = $rotationService->expireDeprecatedKeys();
            $this->info("Expired {$expired} deprecated API keys");
            return 0;
        }

        $gracePeriod = (int) $this->option('grace-period');

        // Rotate specific key
        if ($keyId = $this->option('key-id')) {
            return $this->rotateKey($rotationService, (int) $keyId, $gracePeriod);
        }

        // Rotate all tenant keys
        if ($tenantId = $this->option('tenant-id')) {
            return $this->rotateTenantKeys($rotationService, $tenantId, $gracePeriod);
        }

        $this->error('Please specify --key-id, --tenant-id, --check, or --expire');
        return 1;
    }

    /**
     * Rotate a specific key
     */
    protected function rotateKey(ApiKeyRotationService $service, int $keyId, int $gracePeriod): int
    {
        try {
            $result = $service->rotateKey($keyId, $gracePeriod);

            $this->info('✓ API key rotated successfully!');
            $this->line('');
            $this->line("New API Key: {$result['api_key']}");
            $this->warn('⚠ Save this key securely - it will not be shown again!');
            $this->line('');
            $this->line("Old key expires: {$result['old_key_expires_at']}");
            $this->line("Grace period: {$result['grace_period_days']} days");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to rotate key: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Rotate all keys for a tenant
     */
    protected function rotateTenantKeys(ApiKeyRotationService $service, string $tenantId, int $gracePeriod): int
    {
        if (!$this->confirm("Rotate ALL API keys for tenant {$tenantId}?")) {
            return 0;
        }

        $results = $service->rotateAllTenantKeys($tenantId, $gracePeriod);

        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();

        $this->info("Rotated {$successful} keys successfully");

        if ($failed > 0) {
            $this->error("Failed to rotate {$failed} keys");
            foreach ($results as $result) {
                if (!$result['success']) {
                    $this->line("  Key {$result['old_key_id']}: {$result['error']}");
                }
            }
        }

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Check keys needing rotation
     */
    protected function checkKeysForRotation(ApiKeyRotationService $service): void
    {
        $warnings = $service->checkKeysForRotation();

        if (empty($warnings)) {
            $this->info('✓ No API keys need rotation');
            return;
        }

        $this->warn("Found {$warnings->count()} API keys needing attention:");
        $this->line('');

        $this->table(
            ['Key ID', 'Tenant ID', 'Name', 'Issue', 'Recommendation'],
            collect($warnings)->map(function ($w) {
                return [
                    $w['api_key_id'],
                    $w['tenant_id'],
                    $w['name'],
                    isset($w['age_days']) ? "{$w['age_days']} days old" : "Expires in {$w['expires_in_days']} days",
                    $w['recommendation'],
                ];
            })
        );
    }
}

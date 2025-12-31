<?php

namespace App\Console\Commands;

use App\Services\Api\TenantApiKeyService;
use App\Services\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTenantApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:generate-api-key
                            {tenant_id : The tenant ID}
                            {--name= : Descriptive name for the API key}
                            {--scopes=* : Permission scopes (default: *)}
                            {--rate-limit=1000 : Requests per hour}
                            {--expires= : Expiration date (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new API key for a tenant';

    /**
     * Execute the console command.
     */
    public function handle(TenantApiKeyService $apiKeyService, AuditService $auditService): int
    {
        $tenantId = $this->argument('tenant_id');

        // Verify tenant exists
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantId}");
            return Command::FAILURE;
        }

        $name = $this->option('name') ?? "API Key generated at " . now()->toDateTimeString();
        $scopes = $this->option('scopes') ?: ['*'];
        $rateLimit = (int) $this->option('rate-limit');
        $expires = $this->option('expires');

        $expiresAt = null;
        if ($expires) {
            try {
                $expiresAt = new \DateTime($expires);
            } catch (\Exception $e) {
                $this->error("Invalid expiration date format. Use Y-m-d format.");
                return Command::FAILURE;
            }
        }

        // Generate the key
        $result = $apiKeyService->generateKey($tenantId, [
            'name' => $name,
            'scopes' => $scopes,
            'rate_limit' => $rateLimit,
            'expires_at' => $expiresAt,
        ]);

        // Log to audit trail
        $auditService->logApiKeyCreation(
            $tenantId,
            $result['key_id'],
            [
                'type' => 'system',
                'id' => null,
                'name' => 'Console Command',
            ],
            $scopes
        );

        $this->info('API Key generated successfully!');
        $this->newLine();
        $this->line("Key ID: {$result['key_id']}");
        $this->line("API Key: {$result['api_key']}");
        $this->newLine();
        $this->warn('IMPORTANT: Save this API key. It will not be shown again.');
        $this->newLine();
        $this->line("Scopes: " . implode(', ', $scopes));
        $this->line("Rate Limit: {$rateLimit} requests/hour");
        if ($expiresAt) {
            $this->line("Expires: " . $expiresAt->format('Y-m-d H:i:s'));
        }

        return Command::SUCCESS;
    }
}

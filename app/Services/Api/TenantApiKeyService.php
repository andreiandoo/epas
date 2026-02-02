<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tenant API Key Service
 *
 * Manages tenant API keys for microservices with:
 * - Secure key generation
 * - Scope management
 * - IP whitelisting
 * - Usage tracking
 */
class TenantApiKeyService
{
    /**
     * Generate a new API key for a tenant
     *
     * @param string $tenantId Tenant ID
     * @param array $options Configuration options
     * @return array {api_key: string, key_id: string}
     */
    public function generateKey(string $tenantId, array $options = []): array
    {
        // Generate a secure random API key
        $plainKey = 'epas_' . Str::random(40);
        $hashedKey = hash('sha256', $plainKey);

        // Prepare scopes
        $scopes = $options['scopes'] ?? ['*']; // Default to full access

        // Create API key record
        $keyId = (string) Str::uuid();

        DB::table('tenant_api_keys')->insert([
            'id' => $keyId,
            'tenant_id' => $tenantId,
            'name' => $options['name'] ?? 'Default API Key',
            'api_key' => $hashedKey,
            'scopes' => json_encode($scopes),
            'status' => 'active',
            'rate_limit' => $options['rate_limit'] ?? 1000,
            'allowed_ips' => isset($options['allowed_ips']) ? json_encode($options['allowed_ips']) : null,
            'expires_at' => $options['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'api_key' => $plainKey, // Return only once - won't be stored in plain text
            'key_id' => $keyId,
        ];
    }

    /**
     * Revoke an API key
     *
     * @param string $keyId API key ID
     * @return bool
     */
    public function revokeKey(string $keyId): bool
    {
        $updated = DB::table('tenant_api_keys')
            ->where('id', $keyId)
            ->update([
                'status' => 'revoked',
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Update API key scopes
     *
     * @param string $keyId API key ID
     * @param array $scopes New scopes
     * @return bool
     */
    public function updateScopes(string $keyId, array $scopes): bool
    {
        $updated = DB::table('tenant_api_keys')
            ->where('id', $keyId)
            ->update([
                'scopes' => json_encode($scopes),
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Update rate limit
     *
     * @param string $keyId API key ID
     * @param int $rateLimit Requests per hour
     * @return bool
     */
    public function updateRateLimit(string $keyId, int $rateLimit): bool
    {
        $updated = DB::table('tenant_api_keys')
            ->where('id', $keyId)
            ->update([
                'rate_limit' => $rateLimit,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Update allowed IPs whitelist
     *
     * @param string $keyId API key ID
     * @param array|null $allowedIps Array of IP addresses or null to disable
     * @return bool
     */
    public function updateAllowedIps(string $keyId, ?array $allowedIps): bool
    {
        $updated = DB::table('tenant_api_keys')
            ->where('id', $keyId)
            ->update([
                'allowed_ips' => $allowedIps ? json_encode($allowedIps) : null,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Get all API keys for a tenant
     *
     * @param string $tenantId Tenant ID
     * @return array
     */
    public function getKeys(string $tenantId): array
    {
        $keys = DB::table('tenant_api_keys')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $keys->map(function ($key) {
            return [
                'id' => $key->id,
                'name' => $key->name,
                'scopes' => json_decode($key->scopes, true),
                'status' => $key->status,
                'rate_limit' => $key->rate_limit,
                'allowed_ips' => json_decode($key->allowed_ips, true),
                'expires_at' => $key->expires_at,
                'last_used_at' => $key->last_used_at,
                'last_used_ip' => $key->last_used_ip,
                'total_requests' => $key->total_requests,
                'created_at' => $key->created_at,
            ];
        })->toArray();
    }

    /**
     * Get usage statistics for an API key
     *
     * @param string $keyId API key ID
     * @param int $days Number of days to retrieve
     * @return array
     */
    public function getUsageStats(string $keyId, int $days = 7): array
    {
        $key = DB::table('tenant_api_keys')
            ->where('id', $keyId)
            ->first();

        if (!$key) {
            return [];
        }

        // Get detailed usage if tracking is enabled
        if (config('microservices.api.track_detailed_usage', false)) {
            $since = now()->subDays($days);

            $usage = DB::table('tenant_api_usage')
                ->where('api_key_id', $keyId)
                ->where('created_at', '>=', $since)
                ->selectRaw('
                    DATE(created_at) as date,
                    COUNT(*) as requests,
                    AVG(response_time_ms) as avg_response_time,
                    SUM(CASE WHEN response_status >= 200 AND response_status < 300 THEN 1 ELSE 0 END) as successful_requests,
                    SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as failed_requests
                ')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get()
                ->toArray();

            $endpointStats = DB::table('tenant_api_usage')
                ->where('api_key_id', $keyId)
                ->where('created_at', '>=', $since)
                ->select('endpoint', DB::raw('COUNT(*) as requests'))
                ->groupBy('endpoint')
                ->orderBy('requests', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            return [
                'total_requests' => $key->total_requests,
                'last_used_at' => $key->last_used_at,
                'last_used_ip' => $key->last_used_ip,
                'daily_usage' => $usage,
                'top_endpoints' => $endpointStats,
            ];
        }

        // Return basic stats if detailed tracking is disabled
        return [
            'total_requests' => $key->total_requests,
            'last_used_at' => $key->last_used_at,
            'last_used_ip' => $key->last_used_ip,
        ];
    }

    /**
     * Get available scopes
     *
     * @return array
     */
    public function getAvailableScopes(): array
    {
        return [
            '*' => 'Full access to all microservices',
            'whatsapp:send' => 'Send WhatsApp messages',
            'whatsapp:templates' => 'Manage WhatsApp templates',
            'whatsapp:*' => 'Full WhatsApp access',
            'efactura:submit' => 'Submit invoices to ANAF',
            'efactura:status' => 'Check invoice status',
            'efactura:*' => 'Full eFactura access',
            'accounting:invoice' => 'Create accounting invoices',
            'accounting:export' => 'Export accounting data',
            'accounting:*' => 'Full accounting access',
            'insurance:quote' => 'Generate insurance quotes',
            'insurance:policy' => 'Manage insurance policies',
            'insurance:*' => 'Full insurance access',
            'webhooks:manage' => 'Manage webhook configurations',
            'metrics:read' => 'Read microservice metrics',
        ];
    }

    /**
     * Cleanup old usage records
     *
     * @param int $retentionDays Number of days to retain
     * @return int Number of records deleted
     */
    public function cleanupUsage(int $retentionDays = 90): int
    {
        if (!config('microservices.api.track_detailed_usage', false)) {
            return 0;
        }

        $cutoffDate = now()->subDays($retentionDays);

        return DB::table('tenant_api_usage')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}

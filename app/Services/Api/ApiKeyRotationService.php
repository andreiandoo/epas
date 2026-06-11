<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API Key Rotation Service
 *
 * Provides secure API key rotation with:
 * - Automatic key rotation schedules
 * - Grace period for old keys
 * - Rotation notifications
 * - Key expiration warnings
 * - Audit trail
 */
class ApiKeyRotationService
{
    /**
     * Rotate an API key
     *
     * @param int $apiKeyId
     * @param int $gracePeriodDays Days to keep old key active
     * @return array New key information
     */
    public function rotateKey(int $apiKeyId, int $gracePeriodDays = 7): array
    {
        $oldKey = DB::table('tenant_api_keys')->where('id', $apiKeyId)->first();

        if (!$oldKey) {
            throw new \Exception('API key not found');
        }

        DB::beginTransaction();

        try {
            // Generate new API key
            $newApiKey = $this->generateSecureKey();
            $hashedKey = hash('sha256', $newApiKey);

            // Create new key record
            $newKeyId = DB::table('tenant_api_keys')->insertGetId([
                'tenant_id' => $oldKey->tenant_id,
                'name' => $oldKey->name . ' (Rotated ' . now()->format('Y-m-d') . ')',
                'api_key' => $hashedKey,
                'scopes' => $oldKey->scopes,
                'rate_limit' => $oldKey->rate_limit,
                'allowed_ips' => $oldKey->allowed_ips,
                'expires_at' => $oldKey->expires_at,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
                'rotated_from_id' => $oldKey->id,
            ]);

            // Mark old key as deprecated with grace period
            $expiresAt = now()->addDays($gracePeriodDays);
            DB::table('tenant_api_keys')
                ->where('id', $apiKeyId)
                ->update([
                    'status' => 'deprecated',
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                    'deprecated_at' => now(),
                    'replacement_key_id' => $newKeyId,
                ]);

            // Log rotation in audit
            $this->logRotation($oldKey->tenant_id, $oldKey->id, $newKeyId, $gracePeriodDays);

            // Notify tenant of rotation
            $this->notifyRotation($oldKey->tenant_id, $oldKey->name, $newApiKey, $expiresAt);

            DB::commit();

            return [
                'success' => true,
                'new_key_id' => $newKeyId,
                'api_key' => $newApiKey, // Return plaintext only once!
                'old_key_expires_at' => $expiresAt->toIso8601String(),
                'grace_period_days' => $gracePeriodDays,
                'message' => "API key rotated successfully. Old key will expire on {$expiresAt->format('Y-m-d H:i:s')}",
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API key rotation failed', [
                'api_key_id' => $apiKeyId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a secure random API key
     *
     * @return string
     */
    protected function generateSecureKey(): string
    {
        // Format: prefix_environment_randomString
        $prefix = config('app.name', 'app');
        $env = app()->environment();
        $random = Str::random(40);

        return "{$prefix}_{$env}_{$random}";
    }

    /**
     * Check for keys that need rotation warnings
     *
     * @return array Keys needing attention
     */
    public function checkKeysForRotation(): array
    {
        $warnings = [];

        // Find keys older than 90 days
        $oldKeys = DB::table('tenant_api_keys')
            ->where('status', 'active')
            ->where('created_at', '<=', now()->subDays(90))
            ->whereNull('rotated_from_id')
            ->get();

        foreach ($oldKeys as $key) {
            $age = now()->diffInDays($key->created_at);
            $warnings[] = [
                'api_key_id' => $key->id,
                'tenant_id' => $key->tenant_id,
                'name' => $key->name,
                'age_days' => $age,
                'recommendation' => $age > 180 ? 'urgent' : 'recommended',
            ];
        }

        // Find deprecated keys about to expire
        $expiring = DB::table('tenant_api_keys')
            ->where('status', 'deprecated')
            ->whereBetween('expires_at', [now(), now()->addDays(3)])
            ->get();

        foreach ($expiring as $key) {
            $daysLeft = now()->diffInDays($key->expires_at, false);
            $warnings[] = [
                'api_key_id' => $key->id,
                'tenant_id' => $key->tenant_id,
                'name' => $key->name,
                'expires_in_days' => $daysLeft,
                'recommendation' => 'switch_to_new_key',
                'replacement_key_id' => $key->replacement_key_id,
            ];
        }

        return $warnings;
    }

    /**
     * Auto-expire deprecated keys past grace period
     *
     * @return int Number of keys expired
     */
    public function expireDeprecatedKeys(): int
    {
        $expired = DB::table('tenant_api_keys')
            ->where('status', 'deprecated')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        if ($expired > 0) {
            Log::info("Expired {$expired} deprecated API keys");
        }

        return $expired;
    }

    /**
     * Rotate all keys for a tenant
     *
     * @param string $tenantId
     * @param int $gracePeriodDays
     * @return array Results
     */
    public function rotateAllTenantKeys(string $tenantId, int $gracePeriodDays = 7): array
    {
        $keys = DB::table('tenant_api_keys')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('rotated_from_id')
            ->get();

        $results = [];

        foreach ($keys as $key) {
            try {
                $result = $this->rotateKey($key->id, $gracePeriodDays);
                $results[] = [
                    'old_key_id' => $key->id,
                    'new_key_id' => $result['new_key_id'],
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'old_key_id' => $key->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Log key rotation to audit trail
     *
     * @param string $tenantId
     * @param int $oldKeyId
     * @param int $newKeyId
     * @param int $gracePeriod
     * @return void
     */
    protected function logRotation(
        string $tenantId,
        int $oldKeyId,
        int $newKeyId,
        int $gracePeriod
    ): void {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'action' => 'api_key_rotated',
            'resource_type' => 'api_key',
            'resource_id' => $oldKeyId,
            'changes' => json_encode([
                'old_key_id' => $oldKeyId,
                'new_key_id' => $newKeyId,
                'grace_period_days' => $gracePeriod,
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Notify tenant of key rotation
     *
     * @param string $tenantId
     * @param string $keyName
     * @param string $newKey
     * @param \Carbon\Carbon $oldKeyExpires
     * @return void
     */
    protected function notifyRotation(
        string $tenantId,
        string $keyName,
        string $newKey,
        \Carbon\Carbon $oldKeyExpires
    ): void {
        // Get tenant notification preferences
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant) {
            return;
        }

        // Create notification
        DB::table('tenant_notifications')->insert([
            'tenant_id' => $tenantId,
            'type' => 'api_key_rotated',
            'title' => 'API Key Rotated',
            'message' => "Your API key '{$keyName}' has been rotated. Please update your integration with the new key.",
            'data' => json_encode([
                'key_name' => $keyName,
                'new_key' => substr($newKey, 0, 20) . '...', // Partial key for identification
                'old_key_expires_at' => $oldKeyExpires->toIso8601String(),
                'action_required' => true,
            ]),
            'priority' => 'high',
            'created_at' => now(),
        ]);

        // Send email if notifications enabled
        if (config('microservices.notifications.email_enabled')) {
            // Queue email notification
            \Illuminate\Support\Facades\Mail::to($tenant->email)
                ->queue(new \App\Mail\ApiKeyRotatedNotification(
                    $keyName,
                    $newKey,
                    $oldKeyExpires
                ));
        }
    }

    /**
     * Get rotation history for a tenant
     *
     * @param string $tenantId
     * @param int $limit
     * @return array
     */
    public function getRotationHistory(string $tenantId, int $limit = 50): array
    {
        return DB::table('audit_logs')
            ->where('tenant_id', $tenantId)
            ->where('action', 'api_key_rotated')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'date' => $log->created_at,
                    'changes' => json_decode($log->changes, true),
                    'ip_address' => $log->ip_address,
                ];
            })
            ->toArray();
    }
}

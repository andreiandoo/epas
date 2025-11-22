<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * Audit Service
 *
 * Provides comprehensive audit logging for:
 * - Administrative actions
 * - Microservice operations
 * - API key management
 * - Webhook configuration
 * - Feature flag changes
 * - Sensitive data access
 */
class AuditService
{
    /**
     * Log an audit event
     *
     * @param array $data Audit log data
     * @return string Audit log ID
     */
    public function log(array $data): string
    {
        $logId = (string) Str::uuid();

        DB::table('audit_logs')->insert([
            'id' => $logId,
            'tenant_id' => $data['tenant_id'] ?? null,
            'actor_type' => $data['actor_type'] ?? 'system',
            'actor_id' => $data['actor_id'] ?? null,
            'actor_name' => $data['actor_name'] ?? 'System',
            'action' => $data['action'],
            'resource_type' => $data['resource_type'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'changes' => isset($data['changes']) ? json_encode($data['changes']) : null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'severity' => $data['severity'] ?? 'low',
            'created_at' => now(),
        ]);

        return $logId;
    }

    /**
     * Log microservice activation
     *
     * @param string $tenantId
     * @param string $microserviceId
     * @param array $actor
     * @param Request|null $request
     * @return string
     */
    public function logMicroserviceActivation(
        string $tenantId,
        string $microserviceId,
        array $actor,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => 'microservice.activated',
            'resource_type' => 'microservice',
            'resource_id' => $microserviceId,
            'metadata' => [
                'microservice_id' => $microserviceId,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'medium',
        ]);
    }

    /**
     * Log microservice deactivation
     *
     * @param string $tenantId
     * @param string $microserviceId
     * @param array $actor
     * @param Request|null $request
     * @return string
     */
    public function logMicroserviceDeactivation(
        string $tenantId,
        string $microserviceId,
        array $actor,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => 'microservice.deactivated',
            'resource_type' => 'microservice',
            'resource_id' => $microserviceId,
            'metadata' => [
                'microservice_id' => $microserviceId,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'medium',
        ]);
    }

    /**
     * Log webhook creation/update
     *
     * @param string $tenantId
     * @param string $webhookId
     * @param string $action 'created' or 'updated'
     * @param array $actor
     * @param array|null $changes
     * @param Request|null $request
     * @return string
     */
    public function logWebhookChange(
        string $tenantId,
        string $webhookId,
        string $action,
        array $actor,
        ?array $changes = null,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => "webhook.{$action}",
            'resource_type' => 'webhook',
            'resource_id' => $webhookId,
            'changes' => $changes,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'medium',
        ]);
    }

    /**
     * Log API key creation
     *
     * @param string $tenantId
     * @param string $apiKeyId
     * @param array $actor
     * @param array $scopes
     * @param Request|null $request
     * @return string
     */
    public function logApiKeyCreation(
        string $tenantId,
        string $apiKeyId,
        array $actor,
        array $scopes,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => 'api_key.created',
            'resource_type' => 'api_key',
            'resource_id' => $apiKeyId,
            'metadata' => [
                'scopes' => $scopes,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'high',
        ]);
    }

    /**
     * Log API key revocation
     *
     * @param string $tenantId
     * @param string $apiKeyId
     * @param array $actor
     * @param Request|null $request
     * @return string
     */
    public function logApiKeyRevocation(
        string $tenantId,
        string $apiKeyId,
        array $actor,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => 'api_key.revoked',
            'resource_type' => 'api_key',
            'resource_id' => $apiKeyId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'high',
        ]);
    }

    /**
     * Log feature flag change
     *
     * @param string|null $tenantId
     * @param string $flagKey
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param array $actor
     * @param Request|null $request
     * @return string
     */
    public function logFeatureFlagChange(
        ?string $tenantId,
        string $flagKey,
        $oldValue,
        $newValue,
        array $actor,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => 'feature_flag.changed',
            'resource_type' => 'feature_flag',
            'resource_id' => $flagKey,
            'changes' => [
                'flag' => $flagKey,
                'old' => $oldValue,
                'new' => $newValue,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'medium',
        ]);
    }

    /**
     * Log configuration change
     *
     * @param string $tenantId
     * @param string $configKey
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param array $actor
     * @param Request|null $request
     * @return string
     */
    public function logConfigChange(
        string $tenantId,
        string $configKey,
        $oldValue,
        $newValue,
        array $actor,
        ?Request $request = null
    ): string {
        return $this->log([
            'tenant_id' => $tenantId,
            'actor_type' => $actor['type'] ?? 'user',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? 'Unknown',
            'action' => 'config.changed',
            'resource_type' => 'config',
            'resource_id' => $configKey,
            'changes' => [
                'key' => $configKey,
                'old' => $oldValue,
                'new' => $newValue,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'severity' => 'medium',
        ]);
    }

    /**
     * Get audit logs with filtering
     *
     * @param array $filters
     * @return array
     */
    public function getLogs(array $filters = []): array
    {
        $query = DB::table('audit_logs');

        // Apply filters
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        if (isset($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        if (isset($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (isset($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        // Ordering and pagination
        $query->orderBy('created_at', 'desc');

        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        $query->limit($limit)->offset($offset);

        return $query->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'tenant_id' => $log->tenant_id,
                'actor_type' => $log->actor_type,
                'actor_id' => $log->actor_id,
                'actor_name' => $log->actor_name,
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'metadata' => json_decode($log->metadata, true),
                'changes' => json_decode($log->changes, true),
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'severity' => $log->severity,
                'created_at' => $log->created_at,
            ];
        })->toArray();
    }

    /**
     * Cleanup old audit logs
     *
     * @param int $retentionDays
     * @return int Number of deleted records
     */
    public function cleanup(int $retentionDays = 365): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        return DB::table('audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}

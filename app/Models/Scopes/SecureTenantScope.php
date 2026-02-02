<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

/**
 * SecureTenantScope - Enhanced global scope for multi-tenancy
 *
 * SECURITY FIX: Replaces TenantScope with secure implementation
 *
 * Changes from original TenantScope:
 * - REMOVED: request()->has('_tenant_id') - allowed tenant bypass via query param
 * - ADDED: request attributes check (set only by validated middleware)
 * - ADDED: Logging for queries without tenant context
 *
 * Usage: Replace TenantScope with SecureTenantScope in models
 */
class SecureTenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if tenant_id column exists
        if (!$this->hasTenantIdColumn($builder)) {
            return;
        }

        $tenantId = $this->getCurrentTenantId();

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        } else {
            // Log warning when query is made without tenant context
            // This helps identify potential security issues during development
            if (config('app.debug')) {
                Log::debug('Query without tenant context', [
                    'model' => get_class($model),
                    'table' => $model->getTable(),
                    'trace' => $this->getSimplifiedTrace(),
                ]);
            }
        }
    }

    /**
     * Get current tenant ID from secure sources only
     *
     * SECURITY: Only accepts tenant_id from:
     * 1. Authenticated user's tenant_id
     * 2. Request attributes (set by validated middleware)
     *
     * DOES NOT accept:
     * - Query parameters
     * - Request body
     * - Headers (unless processed by middleware first)
     */
    protected function getCurrentTenantId(): ?int
    {
        // Priority 1: Request attributes (set by validated middleware)
        // This is the most secure method as middleware has already validated the tenant
        if (request() && request()->attributes->has('tenant_id')) {
            return (int) request()->attributes->get('tenant_id');
        }

        // Priority 2: Request tenant object (set by middleware)
        if (request() && request()->attributes->has('tenant')) {
            $tenant = request()->attributes->get('tenant');
            if ($tenant && isset($tenant->id)) {
                return (int) $tenant->id;
            }
        }

        // Priority 3: Authenticated user's tenant_id (for web sessions)
        if (auth()->check()) {
            $user = auth()->user();
            if (isset($user->tenant_id)) {
                return (int) $user->tenant_id;
            }
        }

        // Priority 4: Session (only for web requests with valid sessions)
        // Be careful: session can be manipulated if session fixation is possible
        if (request() && !request()->is('api/*') && session()->has('authenticated_tenant_id')) {
            return (int) session()->get('authenticated_tenant_id');
        }

        // No tenant context available
        return null;
    }

    /**
     * Check if the model has tenant_id column
     */
    protected function hasTenantIdColumn(Builder $builder): bool
    {
        $model = $builder->getModel();
        $table = $model->getTable();

        // Cache schema check results for performance
        static $columnCache = [];

        if (!isset($columnCache[$table])) {
            try {
                $columnCache[$table] = \Schema::hasColumn($table, 'tenant_id');
            } catch (\Exception $e) {
                $columnCache[$table] = false;
            }
        }

        return $columnCache[$table];
    }

    /**
     * Get simplified stack trace for debugging
     */
    protected function getSimplifiedTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        return array_map(function ($frame) {
            return ($frame['class'] ?? '') . '::' . ($frame['function'] ?? '') .
                   ' (' . basename($frame['file'] ?? '') . ':' . ($frame['line'] ?? 0) . ')';
        }, array_slice($trace, 3, 5));
    }
}

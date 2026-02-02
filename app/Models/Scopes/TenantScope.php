<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope - Global scope for multi-tenancy
 *
 * Automatically filters queries by tenant_id based on authenticated user's tenant.
 * Can be disabled per-query using withoutGlobalScope(TenantScope::class)
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if tenant_id column exists and user is authenticated
        if (!$this->hasTenantIdColumn($builder) || !$this->getCurrentTenantId()) {
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $this->getCurrentTenantId());
    }

    /**
     * Get current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        // Try to get tenant_id from authenticated user
        if (auth()->check() && isset(auth()->user()->tenant_id)) {
            return auth()->user()->tenant_id;
        }

        // Try to get from session (for API requests)
        if (session()->has('tenant_id')) {
            return session()->get('tenant_id');
        }

        // SECURITY FIX: Removed _tenant_id from query params - was a critical vulnerability!
        // Previously allowed bypass via ?_tenant_id=X
        // Now only accepts tenant_id from validated middleware (request attributes)
        if (request() && request()->attributes->has('tenant_id')) {
            return request()->attributes->get('tenant_id');
        }

        return null;
    }

    /**
     * Check if the model has tenant_id column
     */
    protected function hasTenantIdColumn(Builder $builder): bool
    {
        $model = $builder->getModel();
        $table = $model->getTable();

        try {
            return \Schema::hasColumn($table, 'tenant_id');
        } catch (\Exception $e) {
            return false;
        }
    }
}

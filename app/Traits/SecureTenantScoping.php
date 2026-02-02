<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Secure Tenant Scoping Trait
 *
 * SECURITY FIX: Ensures all queries are properly scoped to the current tenant
 * Prevents IDOR and cross-tenant data access vulnerabilities
 *
 * Usage:
 * 1. Add this trait to models that need tenant isolation
 * 2. The trait automatically applies tenant_id filtering
 * 3. All queries will be scoped to the current tenant
 *
 * Fixes:
 * - IDOR vulnerabilities where tenant_id is not checked
 * - Cross-tenant data leakage
 * - Bypassing tenant isolation via direct ID access
 */
trait SecureTenantScoping
{
    /**
     * Boot the trait
     */
    protected static function bootSecureTenantScoping(): void
    {
        // Add global scope for tenant isolation
        static::addGlobalScope(new TenantScope());

        // Automatically set tenant_id on create
        static::creating(function ($model) {
            if (!$model->tenant_id && $tenantId = static::getCurrentTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });

        // Prevent cross-tenant updates
        static::updating(function ($model) {
            $tenantId = static::getCurrentTenantId();

            if ($tenantId && $model->tenant_id !== $tenantId) {
                Log::alert('Cross-tenant update attempt blocked', [
                    'model' => get_class($model),
                    'model_id' => $model->id,
                    'model_tenant_id' => $model->tenant_id,
                    'current_tenant_id' => $tenantId,
                    'ip' => request()->ip(),
                ]);

                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have permission to modify this resource.'
                );
            }
        });

        // Prevent cross-tenant deletes
        static::deleting(function ($model) {
            $tenantId = static::getCurrentTenantId();

            if ($tenantId && $model->tenant_id !== $tenantId) {
                Log::alert('Cross-tenant delete attempt blocked', [
                    'model' => get_class($model),
                    'model_id' => $model->id,
                    'model_tenant_id' => $model->tenant_id,
                    'current_tenant_id' => $tenantId,
                    'ip' => request()->ip(),
                ]);

                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have permission to delete this resource.'
                );
            }
        });
    }

    /**
     * Get the current tenant ID from the request context
     */
    protected static function getCurrentTenantId(): ?int
    {
        // Try to get from request attributes (set by middleware)
        if (request() && request()->attributes->has('tenant_id')) {
            return request()->attributes->get('tenant_id');
        }

        // Try to get tenant object
        if (request() && request()->attributes->has('tenant')) {
            $tenant = request()->attributes->get('tenant');
            return $tenant->id ?? null;
        }

        return null;
    }

    /**
     * Scope query to a specific tenant (explicit override)
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }

    /**
     * Scope query to current request's tenant
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = static::getCurrentTenantId();

        if (!$tenantId) {
            // If no tenant context, return empty result for safety
            Log::warning('Query attempted without tenant context', [
                'model' => get_class($this),
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
            ]);

            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }

    /**
     * Find a model by ID with tenant verification
     *
     * Use this instead of find() for secure lookups
     */
    public static function findSecure(int $id): ?static
    {
        $tenantId = static::getCurrentTenantId();

        if (!$tenantId) {
            Log::warning('Secure find attempted without tenant context', [
                'model' => static::class,
                'id' => $id,
            ]);
            return null;
        }

        return static::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Find a model by ID with tenant verification, or fail
     */
    public static function findSecureOrFail(int $id): static
    {
        $model = static::findSecure($id);

        if (!$model) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'Resource not found or access denied.'
            );
        }

        return $model;
    }

    /**
     * Check if a model belongs to the current tenant
     */
    public function belongsToCurrentTenant(): bool
    {
        $tenantId = static::getCurrentTenantId();

        return $tenantId && $this->tenant_id === $tenantId;
    }

    /**
     * Verify access to this model and throw if unauthorized
     */
    public function verifyTenantAccess(): void
    {
        if (!$this->belongsToCurrentTenant()) {
            Log::alert('Unauthorized cross-tenant access attempt', [
                'model' => get_class($this),
                'model_id' => $this->id,
                'model_tenant_id' => $this->tenant_id,
                'current_tenant_id' => static::getCurrentTenantId(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You do not have permission to access this resource.'
            );
        }
    }
}

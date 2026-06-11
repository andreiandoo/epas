<?php

namespace App\Traits;

use App\Models\Scopes\MarketplaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Secure Marketplace Client Scoping Trait
 *
 * SECURITY FIX: Ensures all queries are properly scoped to the current marketplace client
 * Prevents IDOR and cross-marketplace data access vulnerabilities
 *
 * Usage:
 * 1. Add this trait to models that need marketplace isolation
 * 2. The trait automatically applies marketplace_client_id filtering
 *
 * Fixes:
 * - IDOR in MarketplaceClient controllers
 * - Cross-marketplace data leakage
 */
trait SecureMarketplaceScoping
{
    /**
     * Boot the trait
     */
    protected static function bootSecureMarketplaceScoping(): void
    {
        // Add global scope for marketplace isolation
        static::addGlobalScope(new MarketplaceScope());

        // Automatically set marketplace_client_id on create
        static::creating(function ($model) {
            if (!$model->marketplace_client_id && $clientId = static::getCurrentMarketplaceClientId()) {
                $model->marketplace_client_id = $clientId;
            }
        });

        // Prevent cross-marketplace updates
        static::updating(function ($model) {
            $clientId = static::getCurrentMarketplaceClientId();

            if ($clientId && $model->marketplace_client_id !== $clientId) {
                Log::alert('Cross-marketplace update attempt blocked', [
                    'model' => get_class($model),
                    'model_id' => $model->id,
                    'model_marketplace_client_id' => $model->marketplace_client_id,
                    'current_marketplace_client_id' => $clientId,
                    'ip' => request()->ip(),
                ]);

                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have permission to modify this resource.'
                );
            }
        });

        // Prevent cross-marketplace deletes
        static::deleting(function ($model) {
            $clientId = static::getCurrentMarketplaceClientId();

            if ($clientId && $model->marketplace_client_id !== $clientId) {
                Log::alert('Cross-marketplace delete attempt blocked', [
                    'model' => get_class($model),
                    'model_id' => $model->id,
                    'model_marketplace_client_id' => $model->marketplace_client_id,
                    'current_marketplace_client_id' => $clientId,
                    'ip' => request()->ip(),
                ]);

                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have permission to delete this resource.'
                );
            }
        });
    }

    /**
     * Get the current marketplace client ID from the request context
     */
    protected static function getCurrentMarketplaceClientId(): ?int
    {
        // Try to get from request attributes (set by middleware)
        if (request() && request()->attributes->has('marketplace_client_id')) {
            return request()->attributes->get('marketplace_client_id');
        }

        // Try to get marketplace_client object
        if (request() && request()->attributes->has('marketplace_client')) {
            $client = request()->attributes->get('marketplace_client');
            return $client->id ?? null;
        }

        return null;
    }

    /**
     * Scope query to a specific marketplace client
     */
    public function scopeForMarketplaceClient(Builder $query, int $clientId): Builder
    {
        return $query->where($this->getTable() . '.marketplace_client_id', $clientId);
    }

    /**
     * Scope query to current request's marketplace client
     */
    public function scopeForCurrentMarketplaceClient(Builder $query): Builder
    {
        $clientId = static::getCurrentMarketplaceClientId();

        if (!$clientId) {
            Log::warning('Query attempted without marketplace client context', [
                'model' => get_class($this),
            ]);

            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getTable() . '.marketplace_client_id', $clientId);
    }

    /**
     * Find a model by ID with marketplace client verification
     */
    public static function findSecureMarketplace(int $id): ?static
    {
        $clientId = static::getCurrentMarketplaceClientId();

        if (!$clientId) {
            Log::warning('Secure marketplace find attempted without client context', [
                'model' => static::class,
                'id' => $id,
            ]);
            return null;
        }

        return static::where('id', $id)
            ->where('marketplace_client_id', $clientId)
            ->first();
    }

    /**
     * Find a model by ID with marketplace client verification, or fail
     */
    public static function findSecureMarketplaceOrFail(int $id): static
    {
        $model = static::findSecureMarketplace($id);

        if (!$model) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'Resource not found or access denied.'
            );
        }

        return $model;
    }

    /**
     * Check if a model belongs to the current marketplace client
     */
    public function belongsToCurrentMarketplaceClient(): bool
    {
        $clientId = static::getCurrentMarketplaceClientId();

        return $clientId && $this->marketplace_client_id === $clientId;
    }

    /**
     * Verify access to this model and throw if unauthorized
     */
    public function verifyMarketplaceClientAccess(): void
    {
        if (!$this->belongsToCurrentMarketplaceClient()) {
            Log::alert('Unauthorized cross-marketplace access attempt', [
                'model' => get_class($this),
                'model_id' => $this->id,
                'model_marketplace_client_id' => $this->marketplace_client_id,
                'current_marketplace_client_id' => static::getCurrentMarketplaceClientId(),
                'ip' => request()->ip(),
            ]);

            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You do not have permission to access this resource.'
            );
        }
    }
}

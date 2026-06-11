<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceScope - Global scope for marketplace client isolation
 *
 * Automatically filters queries by marketplace_client_id
 * Ensures data isolation between different marketplace clients
 */
class MarketplaceScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if marketplace_client_id column exists
        if (!$this->hasMarketplaceClientIdColumn($builder)) {
            return;
        }

        $clientId = $this->getCurrentMarketplaceClientId();

        if ($clientId) {
            $builder->where($model->getTable() . '.marketplace_client_id', $clientId);
        }
    }

    /**
     * Get current marketplace client ID from secure sources only
     */
    protected function getCurrentMarketplaceClientId(): ?int
    {
        // Priority 1: Request attributes (set by validated middleware)
        if (request() && request()->attributes->has('marketplace_client_id')) {
            return (int) request()->attributes->get('marketplace_client_id');
        }

        // Priority 2: Request marketplace_client object
        if (request() && request()->attributes->has('marketplace_client')) {
            $client = request()->attributes->get('marketplace_client');
            if ($client && isset($client->id)) {
                return (int) $client->id;
            }
        }

        // Priority 3: Authenticated marketplace admin
        if (auth('marketplace_admin')->check()) {
            $admin = auth('marketplace_admin')->user();
            if (isset($admin->marketplace_client_id)) {
                return (int) $admin->marketplace_client_id;
            }
        }

        return null;
    }

    /**
     * Check if the model has marketplace_client_id column
     */
    protected function hasMarketplaceClientIdColumn(Builder $builder): bool
    {
        $model = $builder->getModel();
        $table = $model->getTable();

        static $columnCache = [];

        if (!isset($columnCache[$table])) {
            try {
                $columnCache[$table] = \Schema::hasColumn($table, 'marketplace_client_id');
            } catch (\Exception $e) {
                $columnCache[$table] = false;
            }
        }

        return $columnCache[$table];
    }
}

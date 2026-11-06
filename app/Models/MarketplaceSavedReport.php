<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Raport salvat din panoul marketplace: numele și coloanele alese.
 * Comun pentru toți adminii marketplace-ului.
 */
class MarketplaceSavedReport extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'entity',
        'name',
        'columns',
        'sort_order',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'columns' => 'array',
        'sort_order' => 'integer',
    ];

    public function scopeFor(Builder $query, int $marketplaceClientId, string $entity): Builder
    {
        return $query->where('marketplace_client_id', $marketplaceClientId)
            ->where('entity', $entity)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}

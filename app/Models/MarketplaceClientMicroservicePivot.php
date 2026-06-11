<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for marketplace_client_microservices table
 * Used via the BelongsToMany relationship to ensure proper JSON casting
 */
class MarketplaceClientMicroservicePivot extends Pivot
{
    protected $table = 'marketplace_client_microservices';

    protected $casts = [
        'settings' => 'array',
        'usage_stats' => 'array',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];
}

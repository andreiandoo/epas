<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketplaceContactTag extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'name',
        'color',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceCustomer::class,
            'marketplace_customer_tags',
            'tag_id',
            'marketplace_customer_id'
        )->withTimestamps();
    }
}

<?php

namespace App\Models;

use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceTodoCategory extends Model
{
    use SecureMarketplaceScoping;

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'color',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cat) {
            if (!$cat->slug) {
                $cat->slug = Str::slug($cat->name);
            }
        });
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(MarketplaceTodo::class);
    }
}

<?php

namespace App\Models\Integrations\GoogleAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsConversionAction extends Model
{
    protected $fillable = [
        'connection_id',
        'conversion_action_id',
        'name',
        'category',
        'counting_type',
        'is_primary',
        'is_enabled',
        'trigger_on',
        'value_settings',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_enabled' => 'boolean',
        'value_settings' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConnection::class, 'connection_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(GoogleAdsConversion::class, 'conversion_action_id');
    }

    public function getResourceNameAttribute(): string
    {
        return "customers/{$this->connection->customer_id}/conversionActions/{$this->conversion_action_id}";
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}

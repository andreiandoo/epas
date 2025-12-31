<?php

namespace App\Models\Integrations\LinkedInAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkedInAdsConversionRule extends Model
{
    protected $fillable = [
        'connection_id',
        'conversion_rule_id',
        'name',
        'conversion_type',
        'attribution_type',
        'is_enabled',
        'trigger_on',
        'value_settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'value_settings' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(LinkedInAdsConnection::class, 'connection_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(LinkedInAdsConversion::class, 'conversion_rule_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('conversion_type', $type);
    }
}

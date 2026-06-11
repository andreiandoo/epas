<?php

namespace App\Models\Integrations\LinkedInAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedInAdsConversion extends Model
{
    protected $fillable = [
        'connection_id',
        'conversion_rule_id',
        'conversion_id',
        'conversion_time',
        'conversion_value',
        'currency_code',
        'user_data',
        'li_fat_id',
        'click_id',
        'status',
        'error_message',
        'sent_at',
        'api_response',
        'correlation_type',
        'correlation_id',
    ];

    protected $casts = [
        'conversion_time' => 'datetime',
        'conversion_value' => 'decimal:2',
        'user_data' => 'array',
        'sent_at' => 'datetime',
        'api_response' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(LinkedInAdsConnection::class, 'connection_id');
    }

    public function conversionRule(): BelongsTo
    {
        return $this->belongsTo(LinkedInAdsConversionRule::class, 'conversion_rule_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}

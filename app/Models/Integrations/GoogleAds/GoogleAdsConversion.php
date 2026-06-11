<?php

namespace App\Models\Integrations\GoogleAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsConversion extends Model
{
    protected $fillable = [
        'connection_id',
        'conversion_action_id',
        'conversion_id',
        'gclid',
        'gbraid',
        'wbraid',
        'conversion_time',
        'conversion_value',
        'currency_code',
        'order_id',
        'user_data',
        'custom_variables',
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
        'custom_variables' => 'array',
        'sent_at' => 'datetime',
        'api_response' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConnection::class, 'connection_id');
    }

    public function conversionAction(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConversionAction::class, 'conversion_action_id');
    }

    public function hasClickId(): bool
    {
        return $this->gclid || $this->gbraid || $this->wbraid;
    }

    public function getClickIdAttribute(): ?string
    {
        return $this->gclid ?? $this->gbraid ?? $this->wbraid;
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

    public function markAsSent(array $response = []): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'api_response' => $response,
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForConnection($query, int $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }
}

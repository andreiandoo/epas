<?php

namespace App\Models\Cashless;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessWebhookDelivery extends Model
{
    protected $fillable = [
        'cashless_webhook_endpoint_id', 'event_type', 'payload',
        'response_status', 'response_body', 'attempted_at', 'succeeded',
        'attempt_number', 'next_retry_at',
    ];

    protected $casts = [
        'payload'        => 'array',
        'response_status' => 'integer',
        'attempted_at'   => 'datetime',
        'succeeded'      => 'boolean',
        'attempt_number' => 'integer',
        'next_retry_at'  => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(CashlessWebhookEndpoint::class, 'cashless_webhook_endpoint_id');
    }

    public function scopeFailed($query) { return $query->where('succeeded', false); }
    public function scopePendingRetry($query) { return $query->where('succeeded', false)->where('next_retry_at', '<=', now()); }
}

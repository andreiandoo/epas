<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'phone',
        'message_text',
        'type',
        'provider_id',
        'status',
        'cost',
        'currency',
        'event_id',
        'order_id',
        'error_message',
        'delivered_at',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'delivered_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

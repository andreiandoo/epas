<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantGiftCard extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'code', 'initial_cents', 'balance_cents',
        'status', 'recipient_name', 'message', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

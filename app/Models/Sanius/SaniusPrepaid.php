<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sanie-cicluri platite in avans la casierie, la intrare. */
class SaniusPrepaid extends Model
{
    protected $table = 'sanius_prepaids';

    protected $fillable = [
        'event_id', 'tab_id', 'order_id', 'sleds', 'cycles_per_sled', 'sled_cycles', 'day_type',
        'ticket_type_id', 'unit_price', 'amount', 'payment_method', 'simulated', 'status', 'member_id', 'is_test',
    ];

    protected $casts = [
        'sleds' => 'integer',
        'cycles_per_sled' => 'integer',
        'sled_cycles' => 'integer',
        'unit_price' => 'float',
        'amount' => 'float',
        'simulated' => 'boolean',
        'is_test' => 'boolean',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SaniusTab::class, 'tab_id');
    }
}

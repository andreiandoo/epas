<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'price_cents',
        'currency',
        'quota_total',
        'quota_sold',
        'status',
        'sales_start_at',
        'sales_end_at',
        'meta',
    ];

    protected $casts = [
        'meta'           => 'array',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

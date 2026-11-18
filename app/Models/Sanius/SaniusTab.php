<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Contul deschis pe un tichet de parcare. */
class SaniusTab extends Model
{
    protected $table = 'sanius_tabs';

    protected $fillable = [
        'event_id', 'parking_code', 'parking_entry_at', 'parking_number', 'vehicle_plate', 'vehicle_type',
        'status', 'is_test', 'opened_by_member_id', 'settled_at', 'settled_by_member_id', 'order_id',
        'settled_simulated', 'settlement',
    ];

    protected $casts = [
        'parking_entry_at' => 'datetime',
        'settled_at' => 'datetime',
        'is_test' => 'boolean',
        'settled_simulated' => 'boolean',
        'settlement' => 'array',
    ];

    public function rentals(): HasMany
    {
        return $this->hasMany(SaniusRental::class, 'tab_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SaniusTabTicket::class, 'tab_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}

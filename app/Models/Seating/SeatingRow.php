<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingRow extends Model
{
    protected $fillable = [
        'section_id',
        'label',
        'seat_start_number',
        'alignment',
        'curve_offset',
        'y',
        'rotation',
        'seat_count',
        'metadata',
    ];

    protected $casts = [
        'seat_start_number' => 'integer',
        'curve_offset' => 'decimal:2',
        'y' => 'decimal:2',
        'rotation' => 'decimal:2',
        'seat_count' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'seat_start_number' => 1,
        'alignment' => 'left',
        'curve_offset' => 0,
        'y' => 0,
        'rotation' => 0,
        'seat_count' => 0,
    ];

    /**
     * Relationships
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatingSection::class, 'section_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SeatingSeat::class, 'row_id');
    }

    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\TicketType::class,
            'ticket_type_seating_rows',
            'seating_row_id',
            'ticket_type_id'
        )->withTimestamps();
    }
}

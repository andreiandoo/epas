<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'seat_start_number' => 'integer',
        'curve_offset' => 'decimal:2',
        'y' => 'decimal:2',
        'rotation' => 'decimal:2',
        'seat_count' => 'integer',
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
}

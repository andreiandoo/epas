<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatingSeat extends Model
{
    protected $fillable = [
        'row_id',
        'label',
        'x',
        'y',
        'angle',
        'shape',
        'seat_uid',
    ];

    protected $casts = [
        'x' => 'decimal:2',
        'y' => 'decimal:2',
        'angle' => 'decimal:2',
    ];

    protected $attributes = [
        'angle' => 0,
        'shape' => 'circle',
    ];

    /**
     * Relationships
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(SeatingRow::class, 'row_id');
    }

    /**
     * Generate a unique seat UID
     */
    public static function generateSeatUid(string $sectionName, string $rowLabel, string $seatLabel): string
    {
        return strtoupper($sectionName) . '_' . strtoupper($rowLabel) . '_' . strtoupper($seatLabel);
    }
}

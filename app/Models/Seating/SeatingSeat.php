<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatingSeat extends Model
{
    protected $fillable = [
        'row_id',
        'label',
        'display_name',
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
     * Get the section through the row
     */
    public function getSection(): ?SeatingSection
    {
        return $this->row?->section;
    }

    /**
     * Generate a unique seat UID in format: SECTION_CODE-ROW-SEAT
     */
    public static function generateSeatUid(string $sectionCode, string $rowLabel, string $seatLabel): string
    {
        $cleanSection = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $sectionCode));
        $cleanRow = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $rowLabel));
        $cleanSeat = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $seatLabel));

        return "{$cleanSection}-{$cleanRow}-{$cleanSeat}";
    }

    /**
     * Generate display name for this seat
     * Format: "Sector A, rând 2, loc 10"
     */
    public function generateDisplayName(): string
    {
        $section = $this->getSection();
        if (!$section) {
            return "Loc {$this->label}";
        }

        return $section->generateSeatDisplayName($this->row->label, $this->label);
    }

    /**
     * Boot method to auto-generate display_name and seat_uid
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($seat) {
            // Auto-generate seat_uid if not provided
            if (empty($seat->seat_uid) && $seat->row_id) {
                $row = SeatingRow::with('section')->find($seat->row_id);
                if ($row && $row->section) {
                    $sectionCode = $row->section->section_code ?: $row->section->name;
                    $seat->seat_uid = self::generateSeatUid($sectionCode, $row->label, $seat->label);
                }
            }

            // Auto-generate display_name if not provided
            if (empty($seat->display_name) && $seat->row_id) {
                $row = SeatingRow::with('section')->find($seat->row_id);
                if ($row && $row->section) {
                    $seat->display_name = $row->section->generateSeatDisplayName($row->label, $seat->label);
                }
            }
        });
    }
}

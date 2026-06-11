<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatingSeat extends Model
{
    /**
     * Seat statuses for the base layout (template)
     * - active: Normal seat available for events
     * - imposibil: Permanently unavailable (pillar, blocked view, etc.) - not selectable
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_IMPOSIBIL = 'imposibil';

    protected $fillable = [
        'row_id',
        'label',
        'display_name',
        'x',
        'y',
        'angle',
        'shape',
        'seat_uid',
        'status',
        'block_reason',
    ];

    protected $casts = [
        'x' => 'decimal:2',
        'y' => 'decimal:2',
        'angle' => 'decimal:2',
    ];

    protected $attributes = [
        'angle' => 0,
        'shape' => 'circle',
        'status' => 'active',
    ];

    /**
     * Check if the seat is active (available for events)
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the seat is permanently unavailable
     */
    public function isImposibil(): bool
    {
        return $this->status === self::STATUS_IMPOSIBIL;
    }

    /**
     * Mark seat as permanently unavailable
     */
    public function markAsImposibil(): self
    {
        $this->status = self::STATUS_IMPOSIBIL;
        $this->save();
        return $this;
    }

    /**
     * Mark seat as active
     */
    public function markAsActive(): self
    {
        $this->status = self::STATUS_ACTIVE;
        $this->save();
        return $this;
    }

    /**
     * Scope: Only active seats
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Only imposibil seats
     */
    public function scopeImposibil($query)
    {
        return $query->where('status', self::STATUS_IMPOSIBIL);
    }

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
     * Generate a unique seat UID in format: S{sectionId}-{rowLabel}-{seatNum}
     * Example: S21-A-1, S21-VIP_ROW-15
     *
     * @param int $sectionId The section's database ID
     * @param string $rowLabel The row label (spaces converted to underscores)
     * @param string $seatLabel The seat number/label
     */
    public static function generateSeatUid(int $sectionId, string $rowLabel, string $seatLabel): string
    {
        // Clean row label: replace spaces with underscores, keep alphanumeric
        $cleanRow = strtoupper(preg_replace('/\s+/', '_', $rowLabel));
        $cleanRow = preg_replace('/[^a-zA-Z0-9_]/', '', $cleanRow);

        // Clean seat label: keep only alphanumeric
        $cleanSeat = preg_replace('/[^a-zA-Z0-9]/', '', $seatLabel);

        return "S{$sectionId}-{$cleanRow}-{$cleanSeat}";
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
                    $seat->seat_uid = self::generateSeatUid($row->section->id, $row->label, $seat->label);
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

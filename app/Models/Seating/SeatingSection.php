<?php

namespace App\Models\Seating;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SeatingSection extends Model
{
    protected $fillable = [
        'layout_id',
        'tenant_id',
        'name',
        'display_name_template',
        'section_code',
        'section_type',
        'x_position',
        'y_position',
        'width',
        'height',
        'rotation',
        'display_order',
        'color_hex',
        'seat_color',
        'background_color',
        'corner_radius',
        'background_image',
        'meta',
        'metadata',
    ];

    protected $casts = [
        'meta' => 'array',
        'metadata' => 'array',
        'x_position' => 'integer',
        'y_position' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'rotation' => 'integer',
        'display_order' => 'integer',
        'corner_radius' => 'integer',
    ];

    protected $attributes = [
        'color_hex' => '#3B82F6',
        'seat_color' => '#22C55E',
        'section_type' => 'standard',
        'x_position' => 100,
        'y_position' => 100,
        'width' => 200,
        'height' => 150,
        'rotation' => 0,
        'display_order' => 0,
        'corner_radius' => 0,
    ];

    /**
     * Relationships
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(SeatingLayout::class, 'layout_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SeatingRow::class, 'section_id');
    }

    public function seats(): HasManyThrough
    {
        return $this->hasManyThrough(
            SeatingSeat::class,
            SeatingRow::class,
            'section_id',
            'row_id',
            'id',
            'id'
        );
    }

    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            TicketType::class,
            'ticket_type_seating_sections',
            'seating_section_id',
            'ticket_type_id'
        )->withTimestamps();
    }

    /**
     * Get total seat count for this section
     */
    public function getTotalSeatsAttribute(): int
    {
        return $this->seats()->count();
    }

    /**
     * Generate display name for a seat in this section
     * Format: "Sector A, rând 2, loc 10"
     */
    public function generateSeatDisplayName(string $rowLabel, string $seatLabel): string
    {
        if ($this->display_name_template) {
            return str_replace(
                ['{section}', '{row}', '{seat}'],
                [$this->name, $rowLabel, $seatLabel],
                $this->display_name_template
            );
        }

        return "{$this->name}, rând {$rowLabel}, loc {$seatLabel}";
    }

    /**
     * Generate seat UID in format: S{sectionId}-{rowLabel}-{seatNum}
     * Example: S21-A-1, S21-B-15
     * Spaces in row labels are replaced with underscores
     */
    public function generateSeatUid(string $rowLabel, string $seatLabel): string
    {
        // Use section's database ID for uniqueness
        $sectionId = $this->id;

        // Clean row label: replace spaces with underscores, keep alphanumeric
        $cleanRow = strtoupper(preg_replace('/\s+/', '_', $rowLabel));
        $cleanRow = preg_replace('/[^a-zA-Z0-9_]/', '', $cleanRow);

        // Clean seat label: keep only alphanumeric
        $cleanSeat = preg_replace('/[^a-zA-Z0-9]/', '', $seatLabel);

        return "S{$sectionId}-{$cleanRow}-{$cleanSeat}";
    }
}

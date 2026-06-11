<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalExternalTicket extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'import_batch_id',
        'source_name',
        'barcode',
        'attendee_first_name',
        'attendee_last_name',
        'attendee_email',
        'ticket_type_name',
        'original_id',
        'status',
        'checked_in_at',
        'checked_in_by',
        'checked_in_gate',
        'day_checkins',
        'meta',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'day_checkins'  => 'array',
        'meta'          => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function getAttendeeNameAttribute(): string
    {
        return trim(($this->attendee_first_name ?? '') . ' ' . ($this->attendee_last_name ?? '')) ?: '-';
    }

    public function checkInForDay(int $dayId, ?string $gate = null, ?string $checkedInBy = null): void
    {
        $checkins = $this->day_checkins ?? [];
        $checkins[$dayId] = now()->toIso8601String();

        $this->update([
            'day_checkins'   => $checkins,
            'checked_in_at'  => $this->checked_in_at ?? now(),
            'checked_in_by'  => $checkedInBy,
            'checked_in_gate' => $gate,
            'status'         => 'used',
        ]);
    }

    public function hasCheckedInForDay(int $dayId): bool
    {
        return isset($this->day_checkins[$dayId]);
    }
}

<?php

namespace App\Models;

use App\Models\MarketplaceOrganizerPromoCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted series allocations per (event × ticket_type × promo_code × RED).
 *
 * Materialised by SeriesAllocator service; consumed by the tax template
 * generation flow (cerere de avizare, declaratie impozite, PV distrugere)
 * so all three documents reference the same series prefix and allocations.
 *
 * See database/migrations/2026_05_18_170400_create_event_ticket_type_promo_series_table.php
 */
class EventTicketTypePromoSeries extends Model
{
    protected $table = 'event_ticket_type_promo_series';

    protected $fillable = [
        'marketplace_event_id',
        'ticket_type_id',
        'promo_code_id',
        'is_intrinsic_red',
        'series_prefix',
        'qty_allocated',
        'qty_sold',
    ];

    protected $casts = [
        'is_intrinsic_red' => 'boolean',
        'qty_allocated' => 'integer',
        'qty_sold' => 'integer',
    ];

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizerPromoCode::class, 'promo_code_id');
    }

    /**
     * Returns true when the row represents the parent (full-price) tier —
     * no promo and no intrinsic discount.
     */
    public function isParent(): bool
    {
        return $this->promo_code_id === null && !$this->is_intrinsic_red;
    }

    /**
     * Build the auto-derived prefix for a tier. Empty parent series_start
     * falls back to "" so callers can pick a sensible default (e.g. the
     * type name) when no series is configured on the ticket type.
     */
    public static function derivePrefix(string $parentSeriesStart, ?string $promoCode, bool $isIntrinsicRed): string
    {
        $base = '';
        if ($parentSeriesStart !== '' && preg_match('/^(.*?)(\d+)$/', $parentSeriesStart, $m)) {
            $base = trim($m[1]);
        }

        $suffix = '';
        if ($isIntrinsicRed) {
            $suffix = 'RED';
        } elseif ($promoCode !== null && $promoCode !== '') {
            $suffix = strtoupper($promoCode);
        }

        if ($suffix === '') {
            return $base;
        }
        return $base !== '' ? $base . '-' . $suffix : $suffix;
    }
}

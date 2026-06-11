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
        'discount_code',
        'discount_source',
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
     * Build the auto-derived prefix for a tier.
     *
     * The returned prefix always ends with the separator that joins to
     * the sequence number (typically "-"), so the caller can do
     * $prefix . str_pad($n, ...) and get a properly-formed identifier
     * for both parent and promo tiers:
     *   parent  series_start="AMB-4399-10543-001" → "AMB-4399-10543-"
     *   promo                                      → "AMB-4399-10543-HAILAQFEEL!-"
     *
     * Empty parent series_start falls back to "" so callers can pick a
     * sensible default (e.g. the type name) when no series is configured.
     */
    public static function derivePrefix(string $parentSeriesStart, ?string $promoCode, bool $isIntrinsicRed): string
    {
        // Split parent series_start into "base prefix" + trailing number.
        // The base keeps its trailing separator so parent rows can append
        // a padded number directly.
        $base = '';
        if ($parentSeriesStart !== '' && preg_match('/^(.*?)(\d+)$/', $parentSeriesStart, $m)) {
            $base = $m[1];
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

        // For promo / RED tiers, drop the trailing dash from the base
        // (so we don't get "AMB--HAILAQFEEL!"), append the suffix, then
        // restore a trailing dash so the caller-side number concatenation
        // stays uniform with parent rows.
        $baseNoTrailingDash = rtrim($base, '-');
        return ($baseNoTrailingDash !== '' ? $baseNoTrailingDash . '-' : '') . $suffix . '-';
    }
}

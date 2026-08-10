<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A paid boost for one short (D3).
 *
 * Covers three products on one mechanism: boosting your own event, boosting an
 * artist, and a third-party brand ad. They differ in who pays (see
 * ShortAdvertiser) and in how the placement must be disclosed — not in how the
 * slot is filled.
 */
class ShortPromotion extends Model
{
    public const MODEL_CPM = 'cpm';

    public const MODEL_CPC = 'cpc';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_PAUSED,
        self::STATUS_ENDED, self::STATUS_REJECTED,
    ];

    public const OBJECTIVE_EVENT = 'event';

    public const OBJECTIVE_ARTIST = 'artist';

    public const OBJECTIVE_BRAND = 'brand';

    public const OBJECTIVE_HOUSE = 'house';

    public const OBJECTIVES = [
        self::OBJECTIVE_EVENT, self::OBJECTIVE_ARTIST,
        self::OBJECTIVE_BRAND, self::OBJECTIVE_HOUSE,
    ];

    protected $fillable = [
        'short_id',
        'tenant_id',
        'short_advertiser_id',
        'model',
        'objective',
        'bid_cents',
        'budget_cents',
        'targeting',
        'disclosure_label',
        'frequency_cap',
        'priority',
        'start_at',
        'end_at',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'targeting' => 'array',
        'bid_cents' => 'integer',
        'budget_cents' => 'integer',
        'spent_cents' => 'integer',
        'priority' => 'integer',
        'frequency_cap' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * SQLite keeps tenant_id NOT NULL because the migration cannot relax it
     * there without rebuilding the table (see the migration's note). A brand
     * advertiser has no tenant, so write 0 rather than fail on the local engine.
     */
    protected static function booted(): void
    {
        static::saving(function (self $promotion) {
            if ($promotion->tenant_id === null && $promotion->getConnection()->getDriverName() === 'sqlite') {
                $promotion->tenant_id = 0;
            }
        });
    }

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(ShortAdvertiser::class, 'short_advertiser_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShortPromotionEvent::class);
    }

    /**
     * Promotions eligible to be served right now.
     *
     * Deliberately does NOT check targeting or the advertiser's balance: those
     * need the viewer and a row lock respectively, so they belong in
     * ShortPromotionService::pick() where both are available.
     */
    public function scopeServable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereColumn('spent_cents', '<', 'budget_cents')
            ->where(fn (Builder $q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('end_at')->orWhere('end_at', '>', now()));
    }

    public function budgetRemaining(): int
    {
        return max(0, $this->budget_cents - $this->spent_cents);
    }

    public function isHouseAd(): bool
    {
        return $this->objective === self::OBJECTIVE_HOUSE
            || $this->bid_cents === 0
            || (bool) $this->advertiser?->isHouse();
    }

    /**
     * What the viewer is told. Never null and never empty — a placement that
     * cannot be labelled must not be served.
     */
    public function disclosure(): string
    {
        if ($this->disclosure_label) {
            return $this->disclosure_label;
        }

        return match ($this->objective) {
            self::OBJECTIVE_BRAND => (string) config('shorts.ads.labels.brand', 'Reclamă'),
            self::OBJECTIVE_HOUSE => (string) config('shorts.ads.labels.house', 'Recomandat de Tixello'),
            default => (string) config('shorts.ads.labels.default', 'Sponsorizat'),
        };
    }

    /**
     * How much of the budget the flight *should* have spent by now.
     *
     * Pacing exists so a campaign does not burn a week's budget in the first
     * hour and then vanish for six days — which is worse for the advertiser than
     * a smaller, steady presence.
     */
    public function pacedBudgetCents(): int
    {
        if (! $this->start_at || ! $this->end_at) {
            return $this->budget_cents;
        }

        $total = $this->start_at->diffInSeconds($this->end_at);

        if ($total <= 0) {
            return $this->budget_cents;
        }

        $elapsed = max(0, $this->start_at->diffInSeconds(now(), absolute: false));

        return (int) round($this->budget_cents * min(1, $elapsed / $total));
    }

    /**
     * True when the flight is ahead of its pacing curve and should sit out.
     */
    public function isAheadOfPace(): bool
    {
        return $this->spent_cents >= $this->pacedBudgetCents();
    }

    /**
     * What one impression costs. CPC bills nothing here — the money moves on
     * the click instead.
     */
    public function impressionCost(): int
    {
        return $this->model === self::MODEL_CPM ? (int) round($this->bid_cents / 1000) : 0;
    }

    public function clickCost(): int
    {
        return $this->model === self::MODEL_CPC ? $this->bid_cents : 0;
    }
}

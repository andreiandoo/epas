<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A paid boost for one short (D3).
 */
class ShortPromotion extends Model
{
    public const MODEL_CPM = 'cpm';

    public const MODEL_CPC = 'cpc';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'short_id',
        'tenant_id',
        'model',
        'bid_cents',
        'budget_cents',
        'targeting',
        'start_at',
        'end_at',
        'status',
    ];

    protected $casts = [
        'targeting' => 'array',
        'bid_cents' => 'integer',
        'budget_cents' => 'integer',
        'spent_cents' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShortPromotionEvent::class);
    }

    /**
     * Promotions eligible to be served right now.
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Whoever is paying for a promoted short (D3).
 *
 * Three kinds, one balance:
 *   - tenant   : an organiser boosting their own events or artists;
 *   - house    : us, cross-promoting our own inventory — never billed;
 *   - external : a brand with no tenant account.
 *
 * Credit is prepaid. Post-paid invoicing would mean serving impressions we
 * might never collect on, and an ad server that can run a negative balance is
 * an ad server that will.
 */
class ShortAdvertiser extends Model
{
    public const TYPE_TENANT = 'tenant';

    public const TYPE_HOUSE = 'house';

    public const TYPE_EXTERNAL = 'external';

    public const TYPES = [self::TYPE_TENANT, self::TYPE_HOUSE, self::TYPE_EXTERNAL];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'name',
        'type',
        'tenant_id',
        'contact_email',
        'website',
        'status',
        'notes',
    ];

    protected $casts = [
        'credit_cents' => 'integer',
        'tenant_id' => 'integer',
    ];

    public function promotions(): HasMany
    {
        return $this->hasMany(ShortPromotion::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ShortAdvertiserTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * House ads cost nothing, so they are never blocked by a balance — that is
     * the entire point of the lane: it fills slots that would otherwise be dead
     * air, and dead air converts worse than our own cross-promotion.
     */
    public function isHouse(): bool
    {
        return $this->type === self::TYPE_HOUSE;
    }

    public function canSpend(int $cents = 1): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->isHouse() || $this->credit_cents >= $cents;
    }

    /**
     * Add prepaid credit and record it.
     *
     * TODO(owner): the Stripe top-up is not wired — see D-031, the checkout
     * controller is untouched. This is the seam: call it with the payment
     * intent id as `$reference` once a payment succeeds, and the ad server needs
     * no other change.
     */
    public function topUp(int $cents, ?string $reference = null, ?string $note = null): ShortAdvertiserTransaction
    {
        return $this->ledger('topup', abs($cents), $reference, $note);
    }

    /**
     * Debit for a served impression or click.
     *
     * Returns false when the balance will not cover it, having written nothing:
     * the caller must not serve. Charging is the only place the balance moves
     * down, and it moves under a row lock so two concurrent impressions cannot
     * both spend the last cent.
     */
    public function charge(int $cents, ?ShortPromotion $promotion = null): bool
    {
        if ($cents <= 0 || $this->isHouse()) {
            return true;
        }

        return (bool) DB::transaction(function () use ($cents, $promotion) {
            $fresh = static::query()->lockForUpdate()->find($this->getKey());

            if (! $fresh || ! $fresh->canSpend($cents)) {
                return false;
            }

            $fresh->decrement('credit_cents', $cents);
            $this->credit_cents = $fresh->credit_cents - $cents;

            ShortAdvertiserTransaction::create([
                'short_advertiser_id' => $this->getKey(),
                'short_promotion_id' => $promotion?->getKey(),
                'type' => 'charge',
                'amount_cents' => -$cents,
                'balance_after_cents' => max(0, $this->credit_cents),
                'created_at' => now(),
            ]);

            return true;
        });
    }

    protected function ledger(string $type, int $cents, ?string $reference, ?string $note): ShortAdvertiserTransaction
    {
        return DB::transaction(function () use ($type, $cents, $reference, $note) {
            $this->increment('credit_cents', $cents);
            $this->refresh();

            return ShortAdvertiserTransaction::create([
                'short_advertiser_id' => $this->getKey(),
                'type' => $type,
                'amount_cents' => $cents,
                'balance_after_cents' => $this->credit_cents,
                'reference' => $reference,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * The advertiser row a tenant advertises through, created on first use.
     *
     * Organisers should not have to be onboarded as advertisers before they can
     * boost a short — the panel creates the row behind them.
     */
    public static function forTenant(int $tenantId, ?string $name = null): self
    {
        return static::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'type' => self::TYPE_TENANT],
            ['name' => $name ?: "Tenant #{$tenantId}", 'status' => self::STATUS_ACTIVE],
        );
    }
}

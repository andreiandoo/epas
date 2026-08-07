<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortPromotion;
use App\Models\ShortPromotionEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Promoted shorts: selection, frequency capping and billing (D3).
 *
 * A promoted item is injected at a fixed slot rather than scored into the feed,
 * and it is always labelled. Money must not be able to quietly become relevance —
 * the viewer has to be able to tell the difference.
 */
class ShortPromotionService
{
    /** One promoted slot every N organic shorts. */
    private const SLOT_INTERVAL = 5;

    /** How many times one viewer may see the same promotion per day. */
    private const FREQUENCY_CAP = 3;

    /**
     * Pick a promotion to inject, or null when nothing is eligible.
     *
     * @param  array<int, int>  $excludeShortIds  Shorts already on this page.
     */
    public function pick(?MarketplaceCustomer $customer, array $excludeShortIds = []): ?ShortPromotion
    {
        $candidates = ShortPromotion::query()
            ->servable()
            ->whereNotIn('short_id', $excludeShortIds)
            ->with('short')
            ->get()
            // Pacing: a flight ahead of its curve sits out this request rather
            // than burning a week's budget in the first hour.
            ->reject(fn (ShortPromotion $promotion) => $promotion->isAheadOfPace())
            ->filter(fn (ShortPromotion $promotion) => $promotion->short?->status === Short::STATUS_PUBLISHED)
            ->reject(fn (ShortPromotion $promotion) => $this->isCapped($promotion, $customer));

        if ($candidates->isEmpty()) {
            return null;
        }

        // Highest bid wins. Not a full auction — with one slot and no reserve
        // price, second-price mechanics would add machinery without changing
        // who gets served.
        return $candidates->sortByDesc('bid_cents')->first();
    }

    /**
     * Insert promoted items into a page at fixed slots.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function inject(array $items, ?MarketplaceCustomer $customer, ShortPayload $payload): array
    {
        if ($items === []) {
            return $items;
        }

        $promotion = $this->pick($customer, array_column($items, 'id'));

        if (! $promotion || ! $promotion->short) {
            return $items;
        }

        $slot = min(self::SLOT_INTERVAL - 1, count($items));

        $promoted = $payload->one($promotion->short, feed: 'for_you');
        // The label is not optional. A viewer must always be able to tell a paid
        // placement from an organic one.
        $promoted['promoted'] = ['id' => $promotion->id, 'label' => 'Sponsorizat'];

        array_splice($items, $slot, 0, [$promoted]);

        $this->chargeImpression($promotion, $customer);

        return $items;
    }

    /**
     * CPM bills per thousand impressions, so one impression costs bid/1000.
     */
    public function chargeImpression(ShortPromotion $promotion, ?MarketplaceCustomer $customer): void
    {
        $cents = $promotion->model === ShortPromotion::MODEL_CPM
            ? (int) round($promotion->bid_cents / 1000)
            : 0;

        $this->record($promotion, $customer, 'impression', $cents);
        $this->countTowardsCap($promotion, $customer);
    }

    public function chargeClick(ShortPromotion $promotion, ?MarketplaceCustomer $customer): void
    {
        $cents = $promotion->model === ShortPromotion::MODEL_CPC ? $promotion->bid_cents : 0;

        $this->record($promotion, $customer, 'click', $cents);
    }

    protected function record(ShortPromotion $promotion, ?MarketplaceCustomer $customer, string $type, int $cents): void
    {
        DB::transaction(function () use ($promotion, $customer, $type, $cents) {
            ShortPromotionEvent::create([
                'short_promotion_id' => $promotion->id,
                'marketplace_customer_id' => $customer?->id,
                'type' => $type,
                'charged_cents' => $cents,
                'created_at' => now(),
            ]);

            if ($cents > 0) {
                ShortPromotion::query()->whereKey($promotion->id)->increment('spent_cents', $cents);
            }
        });

        // Stop serving the moment the budget is gone, rather than waiting for
        // the next scheduled sweep.
        $spent = (int) ShortPromotion::query()->whereKey($promotion->id)->value('spent_cents');

        if ($spent >= $promotion->budget_cents) {
            ShortPromotion::query()->whereKey($promotion->id)->update(['status' => 'ended']);
        }
    }

    protected function capKey(ShortPromotion $promotion, ?MarketplaceCustomer $customer): ?string
    {
        return $customer
            ? "shorts:promo:{$promotion->id}:{$customer->id}:".now()->toDateString()
            : null;
    }

    protected function isCapped(ShortPromotion $promotion, ?MarketplaceCustomer $customer): bool
    {
        $key = $this->capKey($promotion, $customer);

        return $key ? (int) Cache::get($key, 0) >= self::FREQUENCY_CAP : false;
    }

    protected function countTowardsCap(ShortPromotion $promotion, ?MarketplaceCustomer $customer): void
    {
        $key = $this->capKey($promotion, $customer);

        if (! $key) {
            return;
        }

        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfDay());
    }

    /**
     * @return Collection<int, ShortPromotion>
     */
    public function servable(): Collection
    {
        return ShortPromotion::query()->servable()->get();
    }
}

<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortPromotion;
use App\Models\ShortPromotionEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Promoted shorts: selection, targeting, frequency capping and billing (D3).
 *
 * A promoted item is injected at a fixed slot rather than scored into the feed,
 * and it is always labelled. Money must not be able to quietly become relevance —
 * the viewer has to be able to tell the difference.
 *
 * The same mechanism serves three products: an organiser boosting their own
 * event, an artist promo, and a third-party brand ad. They differ in who pays
 * and in the disclosure wording, not in how a slot is filled.
 */
class ShortPromotionService
{
    /**
     * Everything the selection needs to know about the person looking.
     *
     * Passed as an array rather than pulled off the customer inside pick(),
     * because the two biggest ad surfaces (the public tenant-client feed and a
     * logged-out mobile session) have no customer at all, and an ad server that
     * only works for logged-in users sells a fraction of the inventory.
     *
     * @param  array<string, mixed>  $context  {country, city, session_id}
     */
    public function pick(
        ?MarketplaceCustomer $customer,
        array $excludeShortIds = [],
        array $context = [],
        array $excludePromotionIds = [],
    ): ?ShortPromotion {
        $candidates = ShortPromotion::query()
            ->servable()
            ->whereNotIn('short_id', $excludeShortIds ?: [0])
            ->when($excludePromotionIds !== [], fn ($q) => $q->whereKeyNot($excludePromotionIds))
            ->with(['short', 'advertiser'])
            ->get()
            // Pacing: a flight ahead of its curve sits out this request rather
            // than burning a week's budget in the first hour.
            ->reject(fn (ShortPromotion $promotion) => $promotion->isAheadOfPace())
            ->filter(fn (ShortPromotion $promotion) => $promotion->short?->status === Short::STATUS_PUBLISHED)
            // A promotion whose advertiser cannot pay for the next impression is
            // not eligible. Checked before serving, not after: an impression we
            // cannot bill is revenue that never existed.
            ->filter(fn (ShortPromotion $promotion) => $this->isAffordable($promotion))
            ->filter(fn (ShortPromotion $promotion) => $this->matchesTargeting($promotion, $customer, $context))
            ->reject(fn (ShortPromotion $promotion) => $this->isCapped($promotion, $customer, $context));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $this->auction($candidates);
    }

    /**
     * Paid ads first, by bid; house ads only fill what is left over.
     *
     * Not a full auction — with one slot and no reserve price, second-price
     * mechanics would add machinery without changing who gets served. The lane
     * split matters more: house ads bid nothing and would otherwise lose every
     * comparison, which would make the fallback inventory unreachable.
     *
     * @param  Collection<int, ShortPromotion>  $candidates
     */
    protected function auction(Collection $candidates): ShortPromotion
    {
        [$house, $paid] = $candidates->partition(fn (ShortPromotion $p) => $p->isHouseAd());

        $lane = $paid->isNotEmpty() ? $paid : $house;

        return $lane->sortByDesc(fn (ShortPromotion $p) => [$p->priority, $p->bid_cents])->first();
    }

    /**
     * Insert promoted items into a page at fixed slots.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function inject(
        array $items,
        ?MarketplaceCustomer $customer,
        ShortPayload $payload,
        string $feed = 'for_you',
        array $context = [],
    ): array {
        if ($items === [] || ! config('shorts.ads.enabled', true)) {
            return $items;
        }

        $interval = max(2, (int) config('shorts.ads.slot_interval', 5));
        $maxPerPage = max(1, (int) config('shorts.ads.max_per_page', 2));

        $usedShortIds = array_column($items, 'id');
        $usedPromotionIds = [];
        $organicCount = count($items);

        // Slot positions are computed against the ORIGINAL page and then walked
        // back-to-front, so each splice cannot shift a slot we have not filled.
        //
        // The first slot is clamped to the end of the page rather than skipped:
        // a page shorter than the interval is common (the tail of a feed, a
        // narrow filter) and dropping the ad there would quietly zero out fill
        // on exactly those requests.
        $slots = [];
        for ($i = 0; $i < $maxPerPage; $i++) {
            $position = ($i + 1) * $interval - 1;

            if ($i > 0 && $position > $organicCount) {
                break;
            }

            $slots[] = min($position, $organicCount);
        }

        foreach (array_reverse($slots) as $slot) {
            $promotion = $this->pick($customer, $usedShortIds, $context, $usedPromotionIds);

            if (! $promotion || ! $promotion->short) {
                continue;
            }

            $promoted = $payload->one($promotion->short, feed: $feed);
            // The label is not optional. A viewer must always be able to tell a
            // paid placement from an organic one, and a brand ad from a boosted
            // event — they are different things under consumer protection law.
            $promoted['promoted'] = [
                'id' => $promotion->id,
                'label' => $promotion->disclosure(),
                'objective' => $promotion->objective,
                'advertiser' => $promotion->advertiser?->name,
            ];

            array_splice($items, $slot, 0, [$promoted]);

            $usedShortIds[] = $promotion->short_id;
            $usedPromotionIds[] = $promotion->id;

            $this->chargeImpression($promotion, $customer, $context);
        }

        return $items;
    }

    /**
     * Does this viewer match what the campaign asked for?
     *
     * targeting is {geo: ['RO'], genres: [12, 30], age: {min, max}}. The three
     * gates fail in deliberately different directions:
     *
     *   - geo fails CLOSED. Territory is a rights and tax question, and serving
     *     into the wrong country because we could not resolve one is the kind of
     *     mistake that costs money rather than relevance.
     *   - age fails CLOSED. Age-gated advertising (alcohol, gambling) is
     *     regulated; "we did not know how old they were" is not a defence.
     *   - genres fail OPEN. It is a relevance hint, not a rule, and failing it
     *     closed would make every logged-out viewer untargetable — which would
     *     silently zero out most of the sellable inventory.
     */
    protected function matchesTargeting(ShortPromotion $promotion, ?MarketplaceCustomer $customer, array $context): bool
    {
        $targeting = $promotion->targeting;

        if (! is_array($targeting) || $targeting === []) {
            return true;
        }

        if (! $this->matchesGeo($targeting, $customer, $context)) {
            return false;
        }

        if (! $this->matchesAge($targeting, $customer)) {
            return false;
        }

        return $this->matchesGenres($targeting, $promotion);
    }

    protected function matchesGeo(array $targeting, ?MarketplaceCustomer $customer, array $context): bool
    {
        $wanted = array_filter((array) ($targeting['geo'] ?? []));

        if ($wanted === []) {
            return true;
        }

        $country = $context['country'] ?? $customer?->country;

        if (! $country) {
            return false;
        }

        $wanted = array_map(fn ($code) => mb_strtoupper((string) $code), $wanted);

        return in_array(mb_strtoupper((string) $country), $wanted, true);
    }

    protected function matchesAge(array $targeting, ?MarketplaceCustomer $customer): bool
    {
        $age = $targeting['age'] ?? null;

        if (! is_array($age) || ($age['min'] ?? null) === null && ($age['max'] ?? null) === null) {
            return true;
        }

        $viewerAge = $this->ageOf($customer);

        if ($viewerAge === null) {
            return false;
        }

        if (isset($age['min']) && $viewerAge < (int) $age['min']) {
            return false;
        }

        return ! (isset($age['max']) && $viewerAge > (int) $age['max']);
    }

    /**
     * Genre targeting matches on the promoted short's own event genres, not on a
     * viewer affinity profile: the profile costs a query per candidate and is
     * empty for exactly the viewers we most want to reach. Campaigns therefore
     * express "run this against gigs of these genres".
     *
     * Genres live on the event_event_genre pivot — there is no category column
     * on `events` — so this reads the pivot directly and memoises per request.
     */
    protected function matchesGenres(array $targeting, ShortPromotion $promotion): bool
    {
        $wanted = array_map('intval', array_filter((array) ($targeting['genres'] ?? [])));

        if ($wanted === []) {
            return true;
        }

        $eventId = $promotion->short?->event_id;

        // An ad with no event behind it (a brand ad) cannot be genre-matched.
        // Failing it open keeps genre targeting a relevance hint, consistent
        // with the rest of this gate.
        if (! $eventId) {
            return true;
        }

        return array_intersect($wanted, $this->genresOf((int) $eventId)) !== [];
    }

    /**
     * @var array<int, array<int, int>>
     */
    private array $genreCache = [];

    /**
     * @return array<int, int>
     */
    protected function genresOf(int $eventId): array
    {
        return $this->genreCache[$eventId] ??= DB::table('event_event_genre')
            ->where('event_id', $eventId)
            ->pluck('event_genre_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function ageOf(?MarketplaceCustomer $customer): ?int
    {
        if (! $customer?->birth_date) {
            return null;
        }

        try {
            return (int) Carbon::parse($customer->birth_date)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isAffordable(ShortPromotion $promotion): bool
    {
        $advertiser = $promotion->advertiser;

        // Legacy promotions predate advertisers; they are billed against the
        // campaign budget alone, which scopeServable() already enforces.
        if (! $advertiser) {
            return true;
        }

        return $advertiser->canSpend(max(1, $promotion->impressionCost()));
    }

    /**
     * CPM bills per thousand impressions, so one impression costs bid/1000.
     */
    public function chargeImpression(ShortPromotion $promotion, ?MarketplaceCustomer $customer, array $context = []): void
    {
        $this->record($promotion, $customer, 'impression', $promotion->impressionCost());
        $this->countTowardsCap($promotion, $customer, $context);
    }

    public function chargeClick(ShortPromotion $promotion, ?MarketplaceCustomer $customer): void
    {
        $this->record($promotion, $customer, 'click', $promotion->clickCost());
    }

    /**
     * Bill a CTA click against the promotion the client says it came from.
     *
     * The promotion id is echoed back by the client from the feed payload rather
     * than looked up from the short, because the same short can be reached
     * organically. Charging an advertiser for a click their ad did not buy is
     * the worst failure mode here, so an unverifiable id charges nothing.
     */
    public function chargeClickFor(Short $short, ?int $promotionId, ?MarketplaceCustomer $customer): ?ShortPromotion
    {
        if (! $promotionId) {
            return null;
        }

        $promotion = ShortPromotion::query()
            ->whereKey($promotionId)
            ->where('short_id', $short->id)
            ->first();

        if (! $promotion) {
            return null;
        }

        $this->chargeClick($promotion, $customer);

        return $promotion;
    }

    protected function record(ShortPromotion $promotion, ?MarketplaceCustomer $customer, string $type, int $cents): void
    {
        // Take the money first. If the advertiser cannot cover it we still log
        // the event — it happened — but at zero, so the ledger never claims
        // revenue that was not collected.
        if ($cents > 0 && $promotion->advertiser && ! $promotion->advertiser->charge($cents, $promotion)) {
            $cents = 0;
        }

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
            ShortPromotion::query()->whereKey($promotion->id)->update(['status' => ShortPromotion::STATUS_ENDED]);
        }
    }

    /**
     * Per-viewer, per-day cap key.
     *
     * Falls back to the client's session id so logged-out viewers are capped
     * too. Without it the largest audience on the feed would see the same ad on
     * every page for as long as the budget held — which burns the budget on one
     * person and reads as broken to everyone else.
     */
    protected function capKey(ShortPromotion $promotion, ?MarketplaceCustomer $customer, array $context = []): ?string
    {
        $viewer = $customer
            ? "c{$customer->id}"
            : (($context['session_id'] ?? null) ? 's'.substr(hash('xxh128', (string) $context['session_id']), 0, 16) : null);

        return $viewer ? "shorts:promo:{$promotion->id}:{$viewer}:".now()->toDateString() : null;
    }

    protected function frequencyCap(ShortPromotion $promotion): int
    {
        return $promotion->frequency_cap ?: (int) config('shorts.ads.frequency_cap', 3);
    }

    protected function isCapped(ShortPromotion $promotion, ?MarketplaceCustomer $customer, array $context = []): bool
    {
        $key = $this->capKey($promotion, $customer, $context);

        return $key ? (int) Cache::get($key, 0) >= $this->frequencyCap($promotion) : false;
    }

    protected function countTowardsCap(ShortPromotion $promotion, ?MarketplaceCustomer $customer, array $context = []): void
    {
        $key = $this->capKey($promotion, $customer, $context);

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

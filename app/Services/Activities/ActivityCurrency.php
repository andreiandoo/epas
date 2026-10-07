<?php

namespace App\Services\Activities;

use App\Models\ExchangeRate;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The currency an operator sells in, and the euro value of prices.
 *
 * An operator has one currency (marketplace_organizers.currency); with none set, the marketplace's, then RON, which
 * is how every operator worked before the column existed. All of an operator's price variants carry that currency,
 * so "the cheapest price of a product" stays a comparison between like amounts.
 *
 * A product keeps, next to cheapest_price_cents, its currency and the value in euro cents. The euro value is what a
 * marketplace that spans several currencies filters and sorts by; it follows the daily ECB rate (exchange_rates) and
 * is null when there is no rate for the currency.
 */
class ActivityCurrency
{
    public const FALLBACK = 'RON';

    /** Currencies an operator can choose; the ones the daily ECB file gives a rate for, plus the euro. */
    public const CHOICES = [
        'EUR' => 'Euro (EUR)',
        'GBP' => 'Pound sterling (GBP)',
        'CHF' => 'Swiss franc (CHF)',
        'CZK' => 'Czech koruna (CZK)',
        'PLN' => 'Polish złoty (PLN)',
        'HUF' => 'Hungarian forint (HUF)',
        'RON' => 'Romanian leu (RON)',
        'SEK' => 'Swedish krona (SEK)',
        'NOK' => 'Norwegian krone (NOK)',
        'DKK' => 'Danish krone (DKK)',
        'ISK' => 'Icelandic króna (ISK)',
    ];

    /** The currency the operator sells in. */
    public static function forOrganizer(?MarketplaceOrganizer $organizer, ?MarketplaceClient $client = null): string
    {
        $own = self::ready() ? strtoupper((string) ($organizer?->currency ?? '')) : '';
        if ($own !== '') {
            return $own;
        }
        $client ??= $organizer?->marketplaceClient;

        return strtoupper((string) ($client?->currency ?? '')) ?: self::FALLBACK;
    }

    /** An amount in cents of $currency, in euro cents at the latest rate; null when there is no rate. */
    public static function toEurCents(?int $cents, ?string $currency): ?int
    {
        if ($cents === null) {
            return null;
        }
        $currency = strtoupper((string) $currency) ?: self::FALLBACK;
        if ($currency === 'EUR') {
            return $cents;
        }
        $rate = ExchangeRate::getLatestRate($currency, 'EUR');   // reads EUR→X and inverts it

        return $rate ? (int) round($cents * $rate) : null;
    }

    /**
     * Recalculates a product's cached cheapest price, its currency and its euro value from its active variants.
     * Called after every variant change and once a day (the rate moves).
     */
    public static function refresh(?int $activityId): void
    {
        if (! $activityId) {
            return;
        }
        $cheapest = DB::table('activity_variants')
            ->where('activity_id', $activityId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('price_cents', '>', 0)
            ->orderBy('price_cents')
            ->first(['price_cents', 'currency']);

        $update = ['cheapest_price_cents' => $cheapest?->price_cents];
        if (self::ready()) {
            $currency = $cheapest ? (strtoupper((string) $cheapest->currency) ?: self::FALLBACK) : null;
            $update['currency'] = $currency;
            $update['cheapest_price_eur_cents'] = $cheapest ? self::toEurCents((int) $cheapest->price_cents, $currency) : null;
        }

        // The product might be soft-deleted; its cached price is still kept right in case it is restored.
        DB::table('activities')->where('id', $activityId)->update($update);
    }

    /**
     * Gives every price variant of the operator the operator's currency and recalculates his products.
     * Amounts are not converted: an operator who changes currency re-enters his prices. Returns the products touched.
     */
    public static function applyToOrganizer(MarketplaceOrganizer $organizer): int
    {
        if (! self::ready()) {
            return 0;
        }
        $currency = self::forOrganizer($organizer);
        $ids = DB::table('activities')->where('marketplace_organizer_id', $organizer->id)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        DB::table('activity_variants')->whereIn('activity_id', $ids)->where('currency', '!=', $currency)->update(['currency' => $currency]);
        foreach ($ids as $id) {
            self::refresh((int) $id);
        }

        return $ids->count();
    }

    /**
     * True once the migration that adds the currency columns has run. The code is deployed before the migration on
     * the live servers, and a variant saved in between must not fail on a column that is not there yet.
     */
    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasColumn('activities', 'cheapest_price_eur_cents')
            && Schema::hasColumn('marketplace_organizers', 'currency');
    }
}

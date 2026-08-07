<?php

namespace App\Services\Shorts;

use App\Models\Order;
use App\Models\Short;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credits a short with the tickets and revenue it produced (B1).
 *
 * Last-touch within the session: an order carries source_short_id only if the
 * checkout was started from a short. Attribution is stamped on the order
 * (short_attributed_at) rather than inferred, so a repeated status transition —
 * webhook retries are normal — cannot credit the same sale twice.
 */
class ShortAttributionService
{
    /** Statuses that mean the money actually arrived. */
    public const PAID_STATUSES = ['paid', 'confirmed', 'completed'];

    public const REFUNDED_STATUSES = ['refunded', 'cancelled'];

    /**
     * Credit the short behind this order. Idempotent.
     */
    public function credit(Order $order): bool
    {
        if (! $order->source_short_id || $order->short_attributed_at !== null) {
            return false;
        }

        $short = Short::find($order->source_short_id);

        if (! $short) {
            // The short was deleted between the click and the payment; stamp the
            // order so we stop re-checking it on every status change.
            $order->forceFill(['short_attributed_at' => now()])->saveQuietly();

            return false;
        }

        $cents = $this->revenueCents($order);

        DB::transaction(function () use ($short, $order, $cents) {
            Short::query()->whereKey($short->id)->update([
                'conversions' => DB::raw('conversions + 1'),
                'revenue_cents' => DB::raw('revenue_cents + '.$cents),
                // First sale sets the currency; a short selling in two currencies
                // is a reporting problem for the owner, not something to average.
                'revenue_currency' => $short->revenue_currency ?? ($order->currency ?? null),
            ]);

            $order->forceFill(['short_attributed_at' => now()])->saveQuietly();
        });

        Log::info('Shorts: credited a conversion', [
            'short_id' => $short->id,
            'order_id' => $order->id,
            'cents' => $cents,
            'feed' => $order->source_feed,
        ]);

        return true;
    }

    /**
     * Take the credit back on refund. Only reverses what was actually credited.
     */
    public function reverse(Order $order): bool
    {
        if (! $order->source_short_id || $order->short_attributed_at === null) {
            return false;
        }

        $cents = $this->revenueCents($order);

        DB::transaction(function () use ($order, $cents) {
            // Floors at zero: an aggregate rebuild or a manual edit must not be
            // able to push these negative.
            Short::query()
                ->whereKey($order->source_short_id)
                ->update([
                    'conversions' => DB::raw('CASE WHEN conversions > 0 THEN conversions - 1 ELSE 0 END'),
                    'revenue_cents' => DB::raw("CASE WHEN revenue_cents >= {$cents} THEN revenue_cents - {$cents} ELSE 0 END"),
                ]);

            $order->forceFill(['short_attributed_at' => null])->saveQuietly();
        });

        Log::info('Shorts: reversed a conversion', [
            'short_id' => $order->source_short_id,
            'order_id' => $order->id,
        ]);

        return true;
    }

    /**
     * Order totals are decimal currency units in this schema; the short's
     * aggregate is in cents so it stays an integer under concurrent updates.
     */
    protected function revenueCents(Order $order): int
    {
        $total = $order->total ?? $order->total_cents ?? 0;

        // total_cents is already in cents; `total` is not.
        if ($order->total === null && $order->total_cents !== null) {
            return (int) $order->total_cents;
        }

        return (int) round(((float) $total) * 100);
    }

    /**
     * CTR / CVR for the admin tables. Returned as ratios, formatted at the edge.
     *
     * @return array{ctr: float, cvr: float}
     */
    public function rates(Short $short): array
    {
        return [
            'ctr' => $short->views > 0 ? $short->cta_clicks / $short->views : 0.0,
            'cvr' => $short->cta_clicks > 0 ? $short->conversions / $short->cta_clicks : 0.0,
        ];
    }
}

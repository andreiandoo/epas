<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortEvent;

/**
 * Ingests the batched telemetry the mobile feed emits.
 *
 * Deliberately cheap: validate, drop what is not credible, bulk-insert. The
 * denormalised counters on `shorts` are refreshed out-of-band by
 * AggregateShortStatsJob so the write path never blocks on aggregate updates.
 */
class ShortTelemetryService
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{accepted: int, rejected: int}
     */
    public function record(array $events, ?MarketplaceCustomer $customer = null, ?string $sessionId = null): array
    {
        $max = (int) config('shorts.telemetry.max_batch', 100);
        $events = array_slice($events, 0, $max);

        if ($events === []) {
            return ['accepted' => 0, 'rejected' => 0];
        }

        $shortIds = collect($events)
            ->pluck('short_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        // Only accept telemetry for shorts that actually exist — a bad client or
        // a replayed batch must not create orphan rows.
        $knownIds = Short::query()
            ->whereIn('id', $shortIds)
            ->pluck('id')
            ->flip();

        $now = now();
        $rows = [];
        $rejected = 0;

        foreach ($events as $event) {
            $shortId = (int) ($event['short_id'] ?? 0);
            $type = (string) ($event['type'] ?? '');

            if (! $knownIds->has($shortId) || ! in_array($type, ShortEvent::TYPES, true)) {
                $rejected++;

                continue;
            }

            $watchMs = isset($event['watch_ms']) ? (int) $event['watch_ms'] : null;
            $watchRatio = isset($event['watch_ratio']) ? (float) $event['watch_ratio'] : null;

            if (! $this->isCredible($type, $watchMs, $watchRatio)) {
                $rejected++;

                continue;
            }

            if ($type === ShortEvent::TYPE_IMPRESSION && ! $this->keepImpression()) {
                // Sampled out — not an error, just not stored.
                continue;
            }

            $feed = $event['feed'] ?? null;

            $rows[] = [
                'short_id' => $shortId,
                'marketplace_customer_id' => $customer?->id,
                'session_id' => $sessionId ? substr($sessionId, 0, 64) : null,
                'type' => $type,
                'watch_ms' => $watchMs,
                'watch_ratio' => $watchRatio !== null ? round(min(max($watchRatio, 0), 1), 3) : null,
                'feed' => is_string($feed) && in_array($feed, ShortEvent::FEEDS, true) ? $feed : null,
                'meta' => isset($event['meta']) && is_array($event['meta']) ? json_encode($event['meta']) : null,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            ShortEvent::insert($rows);
        }

        return ['accepted' => count($rows), 'rejected' => $rejected];
    }

    /**
     * A "view" only counts once the viewer actually watched something. Keeps
     * impression-farming and instant skips out of the stats (see D6).
     */
    protected function isCredible(string $type, ?int $watchMs, ?float $watchRatio): bool
    {
        if ($type !== ShortEvent::TYPE_VIEW) {
            return true;
        }

        $minMs = (int) config('shorts.telemetry.view_min_ms', 2000);
        $minRatio = (float) config('shorts.telemetry.view_min_ratio', 0.25);

        if ($watchMs === null && $watchRatio === null) {
            return false;
        }

        return ($watchMs !== null && $watchMs >= $minMs)
            || ($watchRatio !== null && $watchRatio >= $minRatio);
    }

    /**
     * Impressions are by far the highest-volume event; keep 1/N when sampling
     * is configured.
     */
    protected function keepImpression(): bool
    {
        $sampling = max(1, (int) config('shorts.telemetry.impression_sampling', 1));

        return $sampling === 1 || random_int(1, $sampling) === 1;
    }
}

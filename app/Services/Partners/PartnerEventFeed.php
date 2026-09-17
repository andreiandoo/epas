<?php

namespace App\Services\Partners;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\PartnerEventFeedItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps partner_event_feed in step with the marketplace's events.
 *
 * Each pass recomputes the partner payload for upcoming events and bumps
 * changed_at only when the payload hash differs. Rows whose event was deleted,
 * unpublished or turned into a recurring template get removed_at. Every change
 * is passed to PartnerWebhooks.
 */
class PartnerEventFeed
{
    private const CHUNK = 200;

    public function __construct(private readonly PartnerWebhooks $webhooks)
    {
    }

    /**
     * @return array{checked: int, created: int, updated: int, cancelled: int, removed: int, notified: int}
     */
    public function refresh(MarketplaceClient $client): array
    {
        $presenter = PartnerEventPresenter::forClient($client);
        $stats = ['checked' => 0, 'created' => 0, 'updated' => 0, 'cancelled' => 0, 'removed' => 0, 'notified' => 0];
        $changes = [];
        $now = now();
        $cutoff = $now->copy()->setTimezone($presenter->timezone())->subDay()->toDateString();

        // The first pass fills the feed from scratch; that is not news to notify.
        $initialFill = !PartnerEventFeedItem::where('marketplace_client_id', $client->id)->exists();

        $this->eligible($client)
            ->where(fn ($q) => $q
                ->where('event_date', '>=', $cutoff)
                ->orWhere('range_start_date', '>=', $cutoff)
                ->orWhere('range_end_date', '>=', $cutoff)
                ->orWhere('postponed_date', '>=', $cutoff)
                ->orWhere('duration_mode', 'multi_day'))
            ->with($this->relations())
            ->chunkById(self::CHUNK, function ($events) use ($client, $presenter, $now, &$stats, &$changes) {
                $rows = PartnerEventFeedItem::where('marketplace_client_id', $client->id)
                    ->whereIn('event_id', $events->pluck('id'))
                    ->get()
                    ->keyBy('event_id');

                foreach ($events as $event) {
                    $stats['checked']++;

                    // One malformed event must not stall the feed for the others.
                    try {
                        $change = $this->sync($presenter, $client, $event, $rows->get($event->id), $now, false);
                    } catch (\Throwable $e) {
                        Log::warning('Partner event feed: event skipped', [
                            'event_id' => $event->id,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }

                    if ($change !== null) {
                        $changes[$event->id] = $change;
                        $stats[Str::after($change, 'event.')]++;
                    }
                }
            });

        foreach ($this->sweepRemoved($client) as $eventId) {
            $changes[$eventId] = 'event.deleted';
            $stats['removed']++;
        }

        if (!$initialFill) {
            $stats['notified'] = $this->webhooks->eventsChanged($client, $changes);
        }

        return $stats;
    }

    /**
     * Bring one event's row up to date, e.g. when a partner asks for an event
     * the last pass did not cover. Returns null when the event is not eligible.
     */
    public function refreshOne(MarketplaceClient $client, int $eventId): ?PartnerEventFeedItem
    {
        $event = $this->eligible($client)->with($this->relations())->find($eventId);

        if (!$event) {
            return null;
        }

        $row = PartnerEventFeedItem::where('marketplace_client_id', $client->id)
            ->where('event_id', $eventId)
            ->first();

        $presenter = PartnerEventPresenter::forClient($client);
        $change = $this->sync($presenter, $client, $event, $row, now(), true);

        // A partner reading an old event must not make every partner hear about it;
        // the regular pass notifies only for events that are still current.
        if ($change !== null && $presenter->listedUntil($event)?->gte(now()->subDay())) {
            $this->webhooks->eventsChanged($client, [$eventId => $change]);
        }

        return PartnerEventFeedItem::where('marketplace_client_id', $client->id)
            ->where('event_id', $eventId)
            ->whereNull('removed_at')
            ->first();
    }

    /**
     * Events a partner may see. Recurring and multi-day templates are left out:
     * their child occurrences are listed instead, as on the marketplace site.
     */
    private function eligible(MarketplaceClient $client): Builder
    {
        return Event::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('duration_mode')->orWhere('duration_mode', '!=', 'recurring'))
            ->where(fn ($q) => $q->whereNull('is_template')->orWhere('is_template', false));
    }

    private function relations(): array
    {
        $ticketTypes = fn ($q) => $q
            ->where('status', 'active')
            ->where(fn ($w) => $w->where('is_entry_ticket', false)->orWhereNull('is_entry_ticket'));

        return [
            'venue',
            'marketplaceEventCategory',
            'eventGenres',
            'artists',
            'ticketTypes' => $ticketTypes,
            'parent.venue',
            'parent.marketplaceEventCategory',
            'parent.eventGenres',
            'parent.artists',
            'parent.ticketTypes' => $ticketTypes,
            'parent.performances' => fn ($q) => $q->where(fn ($w) => $w->where('status', 'active')->orWhereNull('status')),
        ];
    }

    /**
     * @return string|null event.created (new or back after removal), event.cancelled,
     *                     event.updated, or null when nothing changed
     */
    private function sync(
        PartnerEventPresenter $presenter,
        MarketplaceClient $client,
        Event $event,
        ?PartnerEventFeedItem $row,
        Carbon $now,
        bool $allowPast
    ): ?string {
        $payload = $presenter->present($event, $row?->payload);

        if ($payload === null) {
            return null;
        }

        $startsAt = Carbon::parse($payload['starts_at'])->utc();
        $listedUntil = $presenter->listedUntil($event)->utc();
        $hash = hash('sha256', json_encode($payload));

        $attributes = [
            'status' => $payload['status'],
            'starts_at' => $startsAt,
            'listed_until' => $listedUntil,
            'city_key' => static::cityKey($payload['venue']['city'] ?? null),
            'category_id' => $payload['category']['id'] ?? null,
            'payload' => $payload,
            'payload_hash' => $hash,
            // Stamped at write time, not at the start of the pass: a partner that
            // syncs while a long pass is running must still see this row next time.
            'changed_at' => now(),
            'removed_at' => null,
        ];

        if (!$row) {
            // Multi-day events are matched without a date filter; don't start tracking old ones.
            if (!$allowPast && $listedUntil->lt($now->copy()->subDay())) {
                return null;
            }

            try {
                PartnerEventFeedItem::create($attributes + [
                    'marketplace_client_id' => $client->id,
                    'event_id' => $event->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent pass created it; the next pass compares hashes.
                return null;
            }

            return 'event.created';
        }

        if ($row->payload_hash === $hash && $row->removed_at === null) {
            return null;
        }

        $change = match (true) {
            $row->removed_at !== null => 'event.created',
            $payload['status'] === 'cancelled' && $row->status !== 'cancelled' => 'event.cancelled',
            default => 'event.updated',
        };

        $row->fill($attributes)->save();

        return $change;
    }

    /**
     * Mark rows whose event is gone (deleted, unpublished, now a template).
     *
     * @return array<int> event ids removed in this pass
     */
    private function sweepRemoved(MarketplaceClient $client): array
    {
        $removed = [];

        PartnerEventFeedItem::where('marketplace_client_id', $client->id)
            ->whereNull('removed_at')
            ->select(['id', 'event_id'])
            ->chunkById(1000, function ($rows) use ($client, &$removed) {
                $live = $this->eligible($client)
                    ->whereIn('id', $rows->pluck('event_id'))
                    ->pluck('id')
                    ->mapWithKeys(fn ($id) => [(int) $id => true]);

                $gone = $rows->reject(fn ($row) => $live->has((int) $row->event_id));

                if ($gone->isNotEmpty()) {
                    $now = now();
                    PartnerEventFeedItem::whereIn('id', $gone->pluck('id'))->update([
                        'removed_at' => $now,
                        'changed_at' => $now,
                        'updated_at' => $now,
                    ]);
                    array_push($removed, ...$gone->pluck('event_id')->map(fn ($id) => (int) $id)->all());
                }
            });

        return $removed;
    }

    public static function cityKey(?string $city): ?string
    {
        $key = Str::ascii(mb_strtolower(trim((string) $city)));

        return $key === '' ? null : $key;
    }
}

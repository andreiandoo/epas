<?php

namespace App\Services\Shorts;

use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortLike;
use App\Models\ShortSave;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds the cursor-paginated shorts feed for the mobile app.
 *
 * Phase A keeps the ordering deterministic (featured → freshest → id) so the
 * client can scroll forever without duplicates. The personalised "For You"
 * ranking arrives in a later phase via ShortFeedRanker and replaces only the
 * candidate ordering — the cursor contract stays the same.
 */
class ShortFeedService
{
    public const FEEDS = ['for_you', 'featured', 'nearby', 'following', 'event', 'artist'];

    public function __construct(private readonly ShortPayload $payload) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, next_cursor: string|null, feed: string}
     */
    public function page(
        string $feed = 'for_you',
        ?string $cursor = null,
        int $limit = 10,
        ?MarketplaceCustomer $customer = null,
        array $filters = [],
    ): array {
        $feed = in_array($feed, self::FEEDS, true) ? $feed : 'for_you';
        $limit = max(1, min($limit, (int) config('shorts.feed.max_page_size', 30)));

        $query = $this->baseQuery($filters);
        $this->applyFeedSegment($query, $feed, $customer, $filters);

        $decoded = ShortFeedCursor::decode($cursor);
        if ($decoded) {
            $this->applyCursor($query, $decoded);
        }

        // Fetch one extra row to know whether another page exists.
        $rows = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit);

        [$likedIds, $savedIds] = $this->viewerState($items, $customer);

        return [
            'feed' => $feed,
            'items' => $this->payload->collection($items, $likedIds, $savedIds, $feed),
            'next_cursor' => $hasMore ? $this->cursorFor($items->last()) : null,
        ];
    }

    /**
     * Shorts attached to one owner model (event page, artist page).
     *
     * @return array{items: array<int, array<string, mixed>>, next_cursor: string|null, feed: string}
     */
    public function forOwner(
        Model $owner,
        ?string $cursor = null,
        int $limit = 10,
        ?MarketplaceCustomer $customer = null,
    ): array {
        $feed = str(class_basename($owner))->snake()->toString();

        $query = $this->baseQuery()->where(function (Builder $q) use ($owner) {
            $q->where(function (Builder $inner) use ($owner) {
                $inner->where('owner_type', $owner->getMorphClass())
                    ->where('owner_id', $owner->getKey());
            });

            // An event-scoped short may be owned by the artist but still carry
            // the denormalised event_id — surface it on the event page too.
            if ($owner instanceof Event) {
                $q->orWhere('event_id', $owner->getKey());
            }
        });

        $decoded = ShortFeedCursor::decode($cursor);
        if ($decoded) {
            $this->applyCursor($query, $decoded);
        }

        $rows = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit);

        [$likedIds, $savedIds] = $this->viewerState($items, $customer);

        return [
            'feed' => in_array($feed, self::FEEDS, true) ? $feed : 'event',
            'items' => $this->payload->collection($items, $likedIds, $savedIds, $feed),
            'next_cursor' => $hasMore ? $this->cursorFor($items->last()) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters = []): Builder
    {
        $query = Short::query()
            ->published()
            ->with(['owner', 'event']);

        // A marketplace only ever sees its own shorts plus the editorial ones
        // that were never bound to a marketplace.
        if (! empty($filters['marketplace_client_id'])) {
            $clientId = (int) $filters['marketplace_client_id'];
            $query->where(fn (Builder $q) => $q
                ->whereNull('marketplace_client_id')
                ->orWhere('marketplace_client_id', $clientId));
        }

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['language'])) {
            $language = (string) $filters['language'];
            $query->where(fn (Builder $q) => $q
                ->whereNull('language')
                ->orWhere('language', $language));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFeedSegment(Builder $query, string $feed, ?MarketplaceCustomer $customer, array $filters): void
    {
        match ($feed) {
            'featured' => $query->where('is_featured', true),
            'event' => isset($filters['event_id']) ? $query->where('event_id', (int) $filters['event_id']) : null,
            // "nearby" and "following" need geo + the follow graph; until those
            // land (later phases) they degrade to the default ordering rather
            // than returning an empty feed.
            default => null,
        };
    }

    protected function applyCursor(Builder $query, ShortFeedCursor $cursor): void
    {
        $query->where(function (Builder $q) use ($cursor) {
            // Featured rows come first; once the cursor left the featured block
            // we never go back to it.
            if ($cursor->featured) {
                $q->where(function (Builder $inner) use ($cursor) {
                    $inner->where('is_featured', true)
                        ->where(fn (Builder $tie) => $this->keysetAfter($tie, $cursor));
                })->orWhere('is_featured', false);

                return;
            }

            $q->where('is_featured', false)
                ->where(fn (Builder $tie) => $this->keysetAfter($tie, $cursor));
        });
    }

    /**
     * Keyset comparison on (published_at desc, id desc), NULL published_at last.
     */
    protected function keysetAfter(Builder $query, ShortFeedCursor $cursor): Builder
    {
        if ($cursor->publishedAt === null) {
            return $query->whereNull('published_at')->where('id', '<', $cursor->id);
        }

        return $query->where(function (Builder $q) use ($cursor) {
            $q->where('published_at', '<', $cursor->publishedAt)
                ->orWhere(function (Builder $tie) use ($cursor) {
                    $tie->where('published_at', $cursor->publishedAt)
                        ->where('id', '<', $cursor->id);
                })
                ->orWhereNull('published_at');
        });
    }

    protected function cursorFor(?Short $short): ?string
    {
        if (! $short) {
            return null;
        }

        return (new ShortFeedCursor(
            publishedAt: $short->published_at?->toDateTimeString(),
            id: $short->id,
            featured: (bool) $short->is_featured,
        ))->encode();
    }

    /**
     * @param  Collection<int, Short>  $shorts
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    protected function viewerState(Collection $shorts, ?MarketplaceCustomer $customer): array
    {
        if (! $customer || $shorts->isEmpty()) {
            return [[], []];
        }

        $ids = $shorts->pluck('id')->all();

        $liked = ShortLike::query()
            ->where('marketplace_customer_id', $customer->id)
            ->whereIn('short_id', $ids)
            ->pluck('short_id')
            ->all();

        $saved = ShortSave::query()
            ->where('marketplace_customer_id', $customer->id)
            ->whereIn('short_id', $ids)
            ->pluck('short_id')
            ->all();

        return [$liked, $saved];
    }
}

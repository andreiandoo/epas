<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortCollection;
use App\Models\ShortLike;
use App\Models\ShortSave;
use Illuminate\Support\Collection;

/**
 * Editorial collections (B7) and the stories tray (B8).
 */
class ShortCollectionService
{
    public function __construct(
        private readonly ShortPayload $payload,
        private readonly ShortDeepLink $deepLink,
    ) {}

    /**
     * Active collections, with a small preview of each so the discovery screen
     * can render rails in a single round trip.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(?int $clientId = null, int $previewCount = 6): array
    {
        return ShortCollection::query()
            ->active()
            ->forClient($clientId)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->with(['shorts' => fn ($q) => $q->published()->limit($previewCount)])
            ->get()
            ->map(fn (ShortCollection $collection) => [
                'id' => $collection->id,
                'slug' => $collection->slug,
                'title' => $collection->title,
                'description' => $collection->description,
                'cover_url' => $collection->cover_url,
                'count' => $collection->shorts->count(),
                // Makes a rail shareable. Without it ShortDeepLink::forCollection()
                // had no caller at all, so a curated rail was the one surface in
                // the feature nobody could send to a friend (D1/B7).
                'deep_link' => $this->deepLink->forCollection($collection->slug),
                'preview' => $this->payload->collection($collection->shorts, feed: 'collection'),
            ])
            ->values()
            ->all();
    }

    /**
     * One collection, in its curated order.
     *
     * @return array<string, mixed>|null
     */
    public function show(string $slug, ?MarketplaceCustomer $customer = null, ?int $clientId = null): ?array
    {
        $collection = ShortCollection::query()
            ->active()
            ->forClient($clientId)
            ->where('slug', $slug)
            ->with(['shorts' => fn ($q) => $q->published()->with(['owner', 'event.venue', 'captions'])])
            ->first();

        if (! $collection) {
            return null;
        }

        [$liked, $saved] = $this->viewerState($collection->shorts, $customer);

        return [
            'id' => $collection->id,
            'slug' => $collection->slug,
            'title' => $collection->title,
            'description' => $collection->description,
            'cover_url' => $collection->cover_url,
            'items' => $this->payload->collection($collection->shorts, $liked, $saved, 'collection'),
        ];
    }

    /**
     * The stories tray: live stories grouped by owner, newest first.
     *
     * Grouped rather than flat because that is how the tray renders — one avatar
     * per owner, tapping it plays that owner's stories in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stories(?int $clientId = null, ?MarketplaceCustomer $customer = null): array
    {
        $shorts = Short::query()
            ->published()
            ->stories()
            ->when($clientId, fn ($q) => $q->where(fn ($inner) => $inner
                ->whereNull('marketplace_client_id')
                ->orWhere('marketplace_client_id', $clientId)))
            ->with(['owner', 'event.venue', 'captions'])
            ->orderBy('published_at')
            ->get();

        if ($shorts->isEmpty()) {
            return [];
        }

        [$liked, $saved] = $this->viewerState($shorts, $customer);

        return $shorts
            ->groupBy(fn (Short $short) => $short->owner_type.':'.$short->owner_id)
            ->map(function (Collection $group) use ($liked, $saved) {
                $first = $group->first();
                $owner = $first->relationLoaded('owner') ? $first->owner : null;

                return [
                    'owner' => [
                        'type' => $first->owner_type ? str(class_basename($first->owner_type))->snake()->toString() : null,
                        'id' => $first->owner_id,
                        'name' => $owner?->name ?? $owner?->title,
                        'slug' => $owner?->slug,
                    ],
                    'cover_url' => $first->poster_url,
                    // The tray shows a segment per story, so the count is part
                    // of the contract, not something the client recomputes.
                    'count' => $group->count(),
                    'items' => $this->payload->collection($group, $liked, $saved, 'story'),
                ];
            })
            ->values()
            ->all();
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

        return [
            ShortLike::query()
                ->where('marketplace_customer_id', $customer->id)
                ->whereIn('short_id', $ids)
                ->pluck('short_id')
                ->all(),
            ShortSave::query()
                ->where('marketplace_customer_id', $customer->id)
                ->whereIn('short_id', $ids)
                ->pluck('short_id')
                ->all(),
        ];
    }
}

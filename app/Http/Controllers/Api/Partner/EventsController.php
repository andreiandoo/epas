<?php

namespace App\Http\Controllers\Api\Partner;

use App\Models\MarketplaceEventCategory;
use App\Models\MarketplacePartner;
use App\Models\PartnerEventFeedItem;
use App\Services\Partners\PartnerEventFeed;
use App\Services\Partners\PartnerEventPresenter;
use App\Services\Partners\PartnerFeedQueries;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Events for media partners, served from partner_event_feed.
 */
class EventsController extends PartnerController
{
    private const STATUSES = ['published', 'cancelled', 'postponed'];

    public function __construct(private readonly PartnerEventFeed $feed)
    {
    }

    /**
     * GET /events?updated_since=&time_scope=&status=&city=&category=&genre=&artist_id=&page=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        return $this->list($request, $request->query('artist_id'));
    }

    /**
     * GET /artists/{id}/events
     */
    public function forArtist(Request $request, int $id): JsonResponse
    {
        return $this->list($request, $id);
    }

    /**
     * GET /events/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $this->client($request);

        $row = PartnerEventFeedItem::where('marketplace_client_id', $client->id)
            ->where('event_id', $id)
            ->first();

        if (!$row || $row->removed_at !== null) {
            $wasRemoved = $row !== null;
            $row = $this->feed->refreshOne($client, $id);

            if (!$row) {
                return $wasRemoved
                    ? $this->fail('event_deleted', 'This event was deleted or unpublished.', 410)
                    : $this->fail('event_not_found', 'Event not found.', 404);
            }
        }

        return response()->json(['data' => $this->resource($row, $this->partner($request))]);
    }

    /**
     * GET /events/deleted?since=&after_id=
     */
    public function deleted(Request $request): JsonResponse
    {
        $since = $this->dateParam($request, 'since');

        if (!$since) {
            return $this->fail('invalid_parameter', 'since is required, as an ISO 8601 date.', 422);
        }

        $client = $this->client($request);
        $timezone = PartnerEventPresenter::timezoneFor($client);

        $query = PartnerEventFeedItem::where('marketplace_client_id', $client->id)
            ->whereNotNull('removed_at');
        $this->afterCursor($query, 'removed_at', $since, (int) $request->query('after_id', 0));

        return $this->cursorPage(
            $query->orderBy('removed_at')->orderBy('event_id'),
            $this->perPage($request, 100, 500),
            fn (PartnerEventFeedItem $row) => [
                'id' => $row->event_id,
                'deleted_at' => $row->removed_at->copy()->setTimezone($timezone)->toIso8601String(),
            ],
            fn (PartnerEventFeedItem $row) => [
                'since' => $row->removed_at->copy()->setTimezone($timezone)->toIso8601String(),
                'after_id' => $row->event_id,
            ]
        );
    }

    private function list(Request $request, mixed $artistId): JsonResponse
    {
        $client = $this->client($request);
        $since = $this->dateParam($request, 'updated_since');

        if ($since === false) {
            return $this->fail('invalid_parameter', 'updated_since must be an ISO 8601 date.', 422);
        }

        $query = PartnerEventFeedItem::query()
            ->where('marketplace_client_id', $client->id)
            ->whereNull('removed_at');

        // A sync (updated_since) also needs events that just ended.
        $timeScope = $request->query('time_scope', $since ? 'all' : 'upcoming');
        if ($timeScope === 'upcoming') {
            $query->where('listed_until', '>=', now());
        } elseif ($timeScope === 'past') {
            $query->where('listed_until', '<', now());
        } elseif ($timeScope !== 'all') {
            return $this->fail('invalid_parameter', 'time_scope must be upcoming, past or all.', 422);
        }

        if ($request->filled('status')) {
            $statuses = array_values(array_intersect(self::STATUSES, explode(',', (string) $request->query('status'))));
            if (!$statuses) {
                return $this->fail('invalid_parameter', 'status must be published, cancelled and/or postponed.', 422);
            }
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('city')) {
            $query->where('city_key', PartnerEventFeed::cityKey((string) $request->query('city')));
        }

        if ($request->filled('category')) {
            $category = MarketplaceEventCategory::where('marketplace_client_id', $client->id)
                ->where('slug', (string) $request->query('category'))
                ->first();
            $categoryIds = $category ? $category->children()->pluck('id')->push($category->id) : collect();
            $query->whereIn('category_id', $categoryIds);
        }

        if ($request->filled('genre')) {
            $slugs = array_filter(array_map('trim', explode(',', (string) $request->query('genre'))));
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('event_event_genre')
                ->join('event_genres', 'event_genres.id', '=', 'event_event_genre.event_genre_id')
                ->whereIn('event_genres.slug', $slugs)
                ->where(fn ($w) => $this->matchesEventOrParent($w, 'event_event_genre.event_id')));
        }

        if ($artistId !== null && $artistId !== '') {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('event_artist')
                ->where('event_artist.artist_id', (int) $artistId)
                ->where(fn ($w) => $this->matchesEventOrParent($w, 'event_artist.event_id')));
        }

        $partner = $this->partner($request);
        $perPage = $this->perPage($request, 50, 100);

        // Sync: keyset paging on (changed_at, event_id), so rows that change while
        // a partner is paging move ahead instead of shifting later rows out of view.
        if ($since) {
            $this->afterCursor($query, 'changed_at', $since, (int) $request->query('after_id', 0));
            $timezone = PartnerEventPresenter::timezoneFor($client);

            return $this->cursorPage(
                $query->orderBy('changed_at')->orderBy('event_id'),
                $perPage,
                fn (PartnerEventFeedItem $row) => $this->resource($row, $partner),
                fn (PartnerEventFeedItem $row) => [
                    'updated_since' => $row->changed_at->copy()->setTimezone($timezone)->toIso8601String(),
                    'after_id' => $row->event_id,
                ]
            );
        }

        $rows = $query->orderBy('starts_at', $timeScope === 'past' ? 'desc' : 'asc')
            ->orderBy('event_id')
            ->paginate($perPage);

        return $this->page($rows, $rows->getCollection()
            ->map(fn (PartnerEventFeedItem $row) => $this->resource($row, $partner))
            ->all());
    }

    /**
     * Rows after the cursor: later than $since, or at $since with a higher event id.
     */
    private function afterCursor($query, string $column, Carbon $since, int $afterId): void
    {
        $at = $since->copy()->utc();

        $query->where(fn ($q) => $q
            ->where($column, '>', $at)
            ->orWhere(fn ($w) => $w->where($column, '=', $at)->where('event_id', '>', $afterId)));
    }

    /**
     * Child occurrences may keep genres and artists only on their parent event.
     */
    private function matchesEventOrParent($query, string $column): void
    {
        PartnerFeedQueries::eventOrParent($query, $column);
    }

    private function resource(PartnerEventFeedItem $row, MarketplacePartner $partner): array
    {
        $data = $row->payload;

        if (!empty($data['url'])) {
            $data['url'] = $partner->withUtm($data['url']);
        }

        $data['updated_at'] = $row->changed_at->copy()
            ->setTimezone(PartnerEventPresenter::timezoneFor($partner->marketplaceClient))
            ->toIso8601String();

        return $data;
    }
}

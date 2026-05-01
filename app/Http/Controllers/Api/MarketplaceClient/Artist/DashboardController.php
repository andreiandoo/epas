<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\MarketplaceArtistAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard + events listing for the logged-in artist account.
 * Computes profile-completion server-side so the bar matches whatever
 * the editor uses as "completeness".
 */
class DashboardController extends BaseController
{
    /**
     * Profile-completion fields. Each entry is [field, weight]; the
     * percentage is sum(weights of filled) / sum(weights of all).
     * Identity + media weighted higher because they're public-facing.
     */
    protected const COMPLETION_FIELDS = [
        ['main_image_url', 3],
        ['logo_url', 1],
        ['portrait_url', 2],
        ['bio_html', 3],            // any locale present
        ['country', 1],
        ['city', 1],
        ['founded_year', 1],
        ['website', 1],
        ['facebook_url', 1],
        ['instagram_url', 1],
        ['email', 1],
        ['phone', 1],
        ['artist_types', 2],        // M2M — filled if non-empty
        ['artist_genres', 2],
    ];

    public function index(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        $artist = $account->artist_id ? Artist::find($account->artist_id) : null;

        $completion = $artist ? $this->computeCompletion($artist) : null;
        $followers = $artist ? $this->sumFollowers($artist) : 0;

        // Upcoming/past event counts and the 5 most recent for the dashboard.
        $upcomingCount = 0;
        $pastCount = 0;
        $recentEvents = [];

        if ($artist) {
            $today = now()->toDateString();

            $upcomingCount = $artist->events()
                ->where('event_date', '>=', $today)
                ->count();

            $pastCount = $artist->events()
                ->where('event_date', '<', $today)
                ->count();

            $recentEvents = $artist->events()
                ->with(['venue:id,name,city', 'marketplaceOrganizer:id,name'])
                ->orderByDesc('event_date')
                ->limit(5)
                ->get([
                    'events.id', 'events.title', 'events.slug', 'events.short_description',
                    'events.event_date', 'events.start_time', 'events.starts_at',
                    'events.poster_url', 'events.venue_id', 'events.venue_name', 'events.suggested_venue_name',
                    'events.tenant_id', 'events.marketplace_organizer_id',
                    'events.capacity',
                ])
                ->map(fn ($event) => $this->formatEventCard($event))
                ->toArray();
        }

        return $this->success([
            'account' => [
                'id' => $account->id,
                'first_name' => $account->first_name,
                'last_name' => $account->last_name,
                'full_name' => $account->full_name,
                'email' => $account->email,
                'is_email_verified' => $account->isEmailVerified(),
                'last_login_at' => $account->last_login_at?->toIso8601String(),
            ],
            'artist' => $artist ? [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'logo_url' => $artist->logo_full_url,
                'main_image_url' => $artist->main_image_full_url,
            ] : null,
            'is_linked' => $artist !== null,
            'profile_completion' => $completion,
            'stats' => [
                'upcoming_events' => $upcomingCount,
                'past_events' => $pastCount,
                'total_events' => $upcomingCount + $pastCount,
                'total_followers' => $followers,
            ],
            'recent_events' => $recentEvents,
        ]);
    }

    /**
     * GET /artist/events — paginated list of events the artist is on, with
     * an `upcoming` (default true) / `past` filter.
     */
    public function events(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        if (!$account->artist_id) {
            return $this->success([
                'events' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
                'is_linked' => false,
            ]);
        }

        $validated = $request->validate([
            'filter' => 'sometimes|in:upcoming,past,all',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $filter = $validated['filter'] ?? 'upcoming';
        $perPage = $validated['per_page'] ?? 20;
        $today = now()->toDateString();

        $artist = Artist::find($account->artist_id);
        $query = $artist->events()->with(['venue:id,name,city', 'marketplaceOrganizer:id,name']);

        if ($filter === 'upcoming') {
            $query->where('event_date', '>=', $today)->orderBy('event_date');
        } elseif ($filter === 'past') {
            $query->where('event_date', '<', $today)->orderByDesc('event_date');
        } else {
            $query->orderByDesc('event_date');
        }

        $paginator = $query->paginate($perPage);

        return $this->paginated(
            $paginator,
            fn ($event) => $this->formatEventCard($event),
            ['filter' => $filter]
        );
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Sum of followers across the social networks tracked on the artist.
     * Ignores the legacy `followers_*` duplicates so we don't double-count.
     */
    protected function sumFollowers(Artist $artist): int
    {
        return (int) array_sum([
            (int) $artist->facebook_followers,
            (int) $artist->instagram_followers,
            (int) $artist->tiktok_followers,
            (int) $artist->spotify_followers,
            (int) $artist->youtube_followers,
            (int) $artist->twitter_followers,
        ]);
    }

    protected function computeCompletion(Artist $artist): array
    {
        $totalWeight = 0;
        $filledWeight = 0;
        $filledFlags = [];

        foreach (self::COMPLETION_FIELDS as [$field, $weight]) {
            $totalWeight += $weight;
            $isFilled = $this->isFieldFilled($artist, $field);
            $filledFlags[$field] = $isFilled;
            if ($isFilled) {
                $filledWeight += $weight;
            }
        }

        $percentage = $totalWeight > 0 ? (int) round(($filledWeight / $totalWeight) * 100) : 0;

        return [
            'percentage' => $percentage,
            'fields' => $filledFlags,
            'total_fields' => count(self::COMPLETION_FIELDS),
            'filled_fields' => count(array_filter($filledFlags)),
        ];
    }

    protected function isFieldFilled(Artist $artist, string $field): bool
    {
        // Multi-language bio: any locale with content counts.
        if ($field === 'bio_html') {
            $bio = $artist->bio_html;
            if (!is_array($bio)) {
                return !empty($bio);
            }
            foreach ($bio as $value) {
                if (!empty(trim(strip_tags((string) $value)))) {
                    return true;
                }
            }
            return false;
        }

        // M2M — filled if at least one row.
        if ($field === 'artist_types') {
            return $artist->artistTypes()->count() > 0;
        }
        if ($field === 'artist_genres') {
            return $artist->artistGenres()->count() > 0;
        }

        return !empty($artist->{$field});
    }

    /**
     * Compact event card for dashboard / events list.
     *
     * Notes (after the first round of testing):
     *  - `Venue.name` is translatable (JSON), so we flatten via
     *    translatableToString. Events without a venue row fall back to
     *    `events.venue_name` (a freeform string set when the event was
     *    created from a non-cataloged venue).
     *  - `events.start_time` is the canonical clock (HH:MM[:SS]).
     *    `events.starts_at` is what the JS actually consumes — we build
     *    it as `event_date + start_time` so the client gets a proper
     *    local-tz datetime instead of "midnight UTC" rendered as 03:00.
     *  - Poster column is `poster_url` (not poster_image — that bug
     *    500'd the dashboard initially).
     */
    protected function formatEventCard($event): array
    {
        $organizer = $event->marketplaceOrganizer ?? null;

        // Resolve a clean venue display name regardless of whether the
        // event has a real Venue row, a freeform venue_name, or nothing.
        $venueName = null;
        if ($event->venue) {
            $venueName = $this->translatableToString($event->venue->name);
        }
        if (!$venueName) {
            $venueName = $event->venue_name ?: $event->suggested_venue_name ?: null;
        }
        $city = $event->venue?->city ?: null;

        // Build a proper datetime so the JS doesn't fall back to
        // midnight-UTC parsing. If start_time is missing, send just the
        // date and let the JS show date-only.
        $startsAt = $event->starts_at;
        if (!$startsAt && $event->event_date) {
            $time = $event->start_time ?: '00:00:00';
            try {
                $startsAt = \Carbon\Carbon::parse($event->event_date->format('Y-m-d') . ' ' . $time, config('app.timezone'));
            } catch (\Throwable $e) {
                $startsAt = null;
            }
        }

        return [
            'id' => $event->id,
            'title' => $this->translatableToString($event->title),
            'short_description' => $this->translatableToString($event->short_description),
            'slug' => $event->slug,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'starts_at' => $startsAt?->toIso8601String(),
            'has_time' => $event->start_time !== null,
            'poster_url' => $event->poster_url,
            'venue_id' => $event->venue_id,
            'venue_name' => $venueName,
            'city' => $city,
            'organizer_name' => $organizer?->name,
            'tickets_sold' => method_exists($event, 'getTotalTicketsSoldAttribute')
                ? (int) $event->total_tickets_sold
                : null,
            'tickets_total' => method_exists($event, 'getTotalCapacityAttribute')
                ? (int) $event->total_capacity
                : (int) ($event->capacity ?? 0),
            'is_upcoming' => $event->event_date && $event->event_date->isFuture(),
        ];
    }

    /**
     * Same helper as ProfileController::translatableToString — flatten an
     * `array`-cast translatable column to a plain string for API responses.
     * Falls back through current locale → ro → en → first non-empty entry.
     */
    protected function translatableToString($value, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (is_array($value)) {
            return $value[$locale]
                ?? $value['ro']
                ?? $value['en']
                ?? (array_values(array_filter($value))[0] ?? '');
        }

        return (string) ($value ?? '');
    }
}

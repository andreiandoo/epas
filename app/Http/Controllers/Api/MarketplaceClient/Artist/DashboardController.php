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
                ->orderByDesc('event_date')
                ->limit(5)
                ->get(['events.id', 'events.title', 'events.slug', 'events.event_date', 'events.starts_at', 'events.poster_image', 'events.venue_id'])
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
        $query = $artist->events();

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
     */
    protected function formatEventCard($event): array
    {
        return [
            'id' => $event->id,
            'title' => is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? '') : $event->title,
            'slug' => $event->slug,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'starts_at' => $event->starts_at?->toIso8601String(),
            'poster_image' => $event->poster_image,
            'venue_id' => $event->venue_id,
            'is_upcoming' => $event->event_date && $event->event_date->isFuture(),
        ];
    }
}

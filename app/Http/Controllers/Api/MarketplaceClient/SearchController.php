<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\Artist;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends BaseController
{
    /**
     * Global search across events, artists, and locations
     */
    public function search(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $query = trim($request->input('q', ''));
        $limit = min((int) $request->input('limit', 5), 20);
        $language = $client->language ?? 'ro';

        if (strlen($query) < 2) {
            return $this->success([
                'events' => [],
                'artists' => [],
                'locations' => [],
            ]);
        }

        // Search events
        $events = $this->searchEvents($client, $query, $limit, $language);

        // Search artists
        $artists = $this->searchArtists($client, $query, $limit, $language);

        // Search locations/venues
        $locations = $this->searchLocations($client, $query, $limit, $language);

        return $this->success([
            'events' => $events,
            'artists' => $artists,
            'locations' => $locations,
        ]);
    }

    /**
     * Search events
     */
    protected function searchEvents($client, string $query, int $limit, string $language): array
    {
        $events = Event::query()
            ->with(['venue:id,name,city', 'ticketTypes' => function ($q) {
                $q->where('status', 'active')
                    ->select(['id', 'event_id', 'price_cents', 'sale_price_cents']);
            }])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('is_public', true)->orWhereNull('is_public');
            })
            // Upcoming events only
            ->where(function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->orWhere('starts_at', '>=', now());
            })
            // Not cancelled
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            // Client filter
            ->where(function ($q) use ($client) {
                $q->where('marketplace_client_id', $client->id);
                $allowedTenants = $client->allowed_tenants;
                if (!is_null($allowedTenants) && count($allowedTenants) > 0) {
                    $q->orWhereIn('tenant_id', $allowedTenants);
                }
            })
            // Search in title (JSON column)
            ->where(function ($q) use ($query) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) LIKE ?", ["%{$query}%"])
                    ->orWhereHas('venue', function ($vq) use ($query) {
                        $vq->where('name', 'like', "%{$query}%")
                            ->orWhere('city', 'like', "%{$query}%");
                    });
            })
            ->orderByRaw('COALESCE(event_date, DATE(starts_at)) ASC')
            ->limit($limit)
            ->get();

        return $events->map(function ($event) use ($language) {
            $isMarketplaceEvent = !empty($event->marketplace_client_id);

            // Get title
            $title = $isMarketplaceEvent
                ? ($event->getTranslation('title', $language) ?? $event->name)
                : $event->name;

            // Get image
            $image = $isMarketplaceEvent
                ? ($event->poster_url ?? $event->hero_image_url ?? $event->image_url)
                : $event->image_url;

            if ($image && !str_starts_with($image, 'http')) {
                $image = rtrim(config('app.url'), '/') . '/storage/' . ltrim($image, '/');
            }

            // Get venue name
            $venueName = null;
            if ($event->venue) {
                $venueName = $event->venue->getTranslation('name', $language)
                    ?? (is_array($event->venue->name) ? ($event->venue->name[$language] ?? $event->venue->name['ro'] ?? null) : $event->venue->name);
            }

            // Calculate min price
            $minPrice = $event->ticketTypes->map(function ($tt) {
                return ($tt->sale_price_cents ?? $tt->price_cents) / 100;
            })->min();

            return [
                'id' => $event->id,
                'title' => $title,
                'slug' => $event->slug,
                'start_date' => $event->event_date?->format('Y-m-d') ?? ($event->starts_at ? $event->starts_at->format('Y-m-d') : null),
                'image' => $image,
                'poster_url' => $image,
                'venue' => $venueName ? ['name' => $venueName] : null,
                'venue_name' => $venueName,
                'min_price' => $minPrice,
            ];
        })->toArray();
    }

    /**
     * Search artists
     */
    protected function searchArtists($client, string $query, int $limit, string $language): array
    {
        $artists = Artist::query()
            ->with(['artistGenres', 'artistTypes'])
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('city', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $artists->map(function ($artist) use ($language) {
            // Get genre
            $genre = $artist->artistGenres->first();
            $genreName = $genre
                ? ($genre->getTranslation('name', $language) ?: ($genre->name['en'] ?? $genre->name ?? ''))
                : null;

            return [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'image' => $artist->main_image_full_url,
                'photo_url' => $artist->main_image_full_url,
                'genre' => $genreName,
                'type' => $artist->artistTypes->first()?->name ?? null,
            ];
        })->toArray();
    }

    /**
     * Search locations/venues
     */
    protected function searchLocations($client, string $query, int $limit, string $language): array
    {
        $venues = Venue::query()
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('city', 'LIKE', "%{$query}%")
                  ->orWhere('address', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $venues->map(function ($venue) use ($language) {
            $image = $venue->image_url;
            if ($image && !str_starts_with($image, 'http')) {
                $image = rtrim(config('app.url'), '/') . '/storage/' . ltrim($image, '/');
            }

            return [
                'id' => $venue->id,
                'name' => $venue->getTranslation('name', $language) ?? $venue->name,
                'slug' => $venue->slug,
                'image' => $image,
                'address' => $venue->address,
                'city' => $venue->city,
            ];
        })->toArray();
    }
}

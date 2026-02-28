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
     * Diacritics mapping for Romanian characters
     */
    private const DIACRITICS_MAP = [
        'ă' => 'a', 'Ă' => 'A',
        'â' => 'a', 'Â' => 'A',
        'î' => 'i', 'Î' => 'I',
        'ș' => 's', 'Ș' => 'S', 'ş' => 's', 'Ş' => 'S',
        'ț' => 't', 'Ț' => 'T', 'ţ' => 't', 'Ţ' => 'T',
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't', // UTF-8 variants
    ];

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
     * Normalize search query - lowercase and remove diacritics
     */
    protected function normalizeSearch(string $query): string
    {
        $normalized = strtr($query, self::DIACRITICS_MAP);
        return mb_strtolower($normalized, 'UTF-8');
    }

    /**
     * Build SQL expression to normalize a column for search (lowercase + remove diacritics)
     */
    protected function normalizeColumnSql(string $column): string
    {
        // Chain of REPLACE calls to remove diacritics, then LOWER for case-insensitivity
        return "LOWER(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE({$column},
            'ă', 'a'), 'Ă', 'a'), 'â', 'a'), 'Â', 'a'),
            'î', 'i'), 'Î', 'i'),
            'ș', 's'), 'Ș', 's'), 'ş', 's'), 'Ş', 's'),
            'ț', 't'), 'Ț', 't'), 'ţ', 't'), 'Ţ', 't'),
            'ă', 'a'), 'â', 'a')
        )";
    }

    /**
     * Search events
     */
    protected function searchEvents($client, string $query, int $limit, string $language): array
    {
        $normalizedQuery = $this->normalizeSearch($query);

        $events = Event::query()
            ->with(['venue:id,name,city', 'marketplaceEventCategory', 'ticketTypes' => function ($q) {
                $q->where('status', 'active')
                    ->select(['id', 'event_id', 'price_cents', 'sale_price_cents']);
            }])
            ->where('is_published', true)
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
            // Search in title (JSON column) - case and diacritic insensitive
            ->where(function ($q) use ($normalizedQuery) {
                $titleRo = $this->normalizeColumnSql("JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))");
                $titleEn = $this->normalizeColumnSql("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))");

                $q->whereRaw("{$titleRo} LIKE ?", ["%{$normalizedQuery}%"])
                    ->orWhereRaw("{$titleEn} LIKE ?", ["%{$normalizedQuery}%"])
                    ->orWhereHas('venue', function ($vq) use ($normalizedQuery) {
                        $nameNorm = $this->normalizeColumnSql('name');
                        $cityNorm = $this->normalizeColumnSql('city');
                        $vq->whereRaw("{$nameNorm} LIKE ?", ["%{$normalizedQuery}%"])
                            ->orWhereRaw("{$cityNorm} LIKE ?", ["%{$normalizedQuery}%"]);
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

            // Get images (poster for mobile, hero for desktop)
            $posterUrl = $isMarketplaceEvent
                ? ($event->poster_url ?? $event->hero_image_url ?? $event->image_url)
                : $event->image_url;
            $heroUrl = $isMarketplaceEvent
                ? ($event->hero_image_url ?? $event->poster_url ?? $event->image_url)
                : $event->image_url;

            $storagePrefix = rtrim(config('app.url'), '/') . '/storage/';
            if ($posterUrl && !str_starts_with($posterUrl, 'http')) {
                $posterUrl = $storagePrefix . ltrim($posterUrl, '/');
            }
            if ($heroUrl && !str_starts_with($heroUrl, 'http')) {
                $heroUrl = $storagePrefix . ltrim($heroUrl, '/');
            }

            // Get venue info
            $venueName = null;
            $venueCity = null;
            if ($event->venue) {
                $venueName = $event->venue->getTranslation('name', $language)
                    ?? (is_array($event->venue->name) ? ($event->venue->name[$language] ?? $event->venue->name['ro'] ?? null) : $event->venue->name);
                $venueCity = $event->venue->city;
            }

            // Get category
            $category = $isMarketplaceEvent
                ? ($event->marketplaceEventCategory?->getTranslation('name', $language) ?? $event->category)
                : $event->category;

            // Calculate min price (skip free/0-price tickets when paid tickets exist)
            $allPrices = $event->ticketTypes->map(function ($tt) {
                if ($tt->sale_price_cents !== null && $tt->sale_price_cents > 0) {
                    return $tt->sale_price_cents;
                }
                return $tt->price_cents ?? 0;
            });
            $paidPrices = $allPrices->filter(fn ($p) => $p > 0);
            $minPriceCents = $paidPrices->isNotEmpty() ? $paidPrices->min() : $allPrices->min();
            $minPrice = ($minPriceCents !== null && $minPriceCents > 0) ? $minPriceCents / 100 : null;

            return [
                'id' => $event->id,
                'name' => $title,
                'title' => $title,
                'slug' => $event->slug,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'start_date' => $event->event_date?->format('Y-m-d') ?? ($event->starts_at ? $event->starts_at->format('Y-m-d') : null),
                'start_time' => $event->start_time,
                'image' => $posterUrl,
                'image_url' => $posterUrl,
                'poster_url' => $posterUrl,
                'hero_image_url' => $heroUrl,
                'venue' => $venueName,
                'venue_name' => $venueName,
                'venue_city' => $venueCity,
                'city' => $venueCity,
                'category' => $category,
                'price_from' => $minPrice,
                'min_price' => $minPrice,
                'duration_mode' => $event->duration_mode ?? 'single_day',
                'range_start_date' => $event->range_start_date?->format('Y-m-d'),
                'range_end_date' => $event->range_end_date?->format('Y-m-d'),
                'is_sold_out' => (bool) $event->is_sold_out,
                'is_cancelled' => (bool) $event->is_cancelled,
                'is_postponed' => (bool) $event->is_postponed,
            ];
        })->toArray();
    }

    /**
     * Search artists
     */
    protected function searchArtists($client, string $query, int $limit, string $language): array
    {
        $normalizedQuery = $this->normalizeSearch($query);

        $artists = Artist::query()
            ->with(['artistGenres', 'artistTypes'])
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where(function ($q) use ($normalizedQuery) {
                $nameNorm = $this->normalizeColumnSql('name');
                $cityNorm = $this->normalizeColumnSql('city');
                $q->whereRaw("{$nameNorm} LIKE ?", ["%{$normalizedQuery}%"])
                  ->orWhereRaw("{$cityNorm} LIKE ?", ["%{$normalizedQuery}%"]);
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
        $normalizedQuery = $this->normalizeSearch($query);

        $venues = Venue::query()
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($normalizedQuery) {
                $nameNorm = $this->normalizeColumnSql('name');
                $cityNorm = $this->normalizeColumnSql('city');
                $addressNorm = $this->normalizeColumnSql('address');
                $q->whereRaw("{$nameNorm} LIKE ?", ["%{$normalizedQuery}%"])
                  ->orWhereRaw("{$cityNorm} LIKE ?", ["%{$normalizedQuery}%"])
                  ->orWhereRaw("{$addressNorm} LIKE ?", ["%{$normalizedQuery}%"]);
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

<?php

namespace App\Services\ExtendedArtist;

use App\Models\Artist;
use App\Models\ArtistEpk;
use App\Models\ArtistEpkVariant;
use App\Models\MarketplaceClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sursa unică de calcul/formatare pentru Smart EPK.
 *
 * - Live stats (cu cache 1h)
 * - Past events list pentru EPK section
 * - Public payload — agregat tot ce-i trebuie unei pagini publice
 * - Cache invalidation
 *
 * NU centralizează randarea (Blade-urile o fac singure). Doar adună date.
 */
class EpkService
{
    public const STATS_CACHE_TTL = 3600;       // 1h pentru live stats
    public const PUBLIC_CACHE_TTL = 600;       // 10min pentru HTML rendered

    /**
     * Computes EPK live stats — extends Artist::computeKpis() cu metrici
     * suplimentare specifice EPK (cities, countries, peak_audience).
     *
     * Cache: 1h per artist.
     *
     * Returnează format ready-pentru-UI (numere formatate ca string + raw):
     * [
     *   'tickets_sold' => ['raw' => 47000, 'display' => '47k'],
     *   'events_played' => ['raw' => 127, 'display' => '127'],
     *   'cities' => ['raw' => 23, 'display' => '23'],
     *   'countries' => ['raw' => 5, 'display' => '5'],
     *   'peak_audience' => ['raw' => 8400, 'display' => '8.4k'],
     * ]
     */
    public function computeLiveStats(Artist $artist): array
    {
        return Cache::remember(
            $this->statsCacheKey($artist->id),
            self::STATS_CACHE_TTL,
            fn () => $this->computeLiveStatsFresh($artist)
        );
    }

    protected function computeLiveStatsFresh(Artist $artist): array
    {
        $kpis = $artist->computeKpis(now()->subYears(20), now());

        // Cities + countries unice (din venue-urile evenimentelor artistului, all-time)
        $cities = 0;
        $countries = 0;
        $rows = DB::table('events')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->leftJoin('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $artist->id)
            ->select('venues.city', 'venues.country')
            ->distinct()
            ->get();
        $citySet = [];
        $countrySet = [];
        foreach ($rows as $row) {
            if (!empty($row->city)) {
                $citySet[mb_strtolower($row->city)] = true;
            }
            if (!empty($row->country)) {
                $countrySet[mb_strtolower($row->country)] = true;
            }
        }
        $cities = count($citySet);
        $countries = count($countrySet);

        // Peak audience — max bilete vândute la un singur eveniment al artistului
        $peakAudience = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('tickets')) {
            $peakAudience = (int) DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->join('events', 'events.id', '=', 'ticket_types.event_id')
                ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                ->where('event_artist.artist_id', $artist->id)
                ->select('events.id', DB::raw('count(tickets.id) as cnt'))
                ->groupBy('events.id')
                ->orderByDesc('cnt')
                ->limit(1)
                ->value('cnt');
        }

        // Stats sociale: schema Artist are 2 seturi de coloane (legacy + nou),
        // sync-ul scrie în `followers_*` dar valorile vechi pot fi în `*_followers`.
        // Citim ambele variante și luăm prima non-zero.
        $pickFirst = fn (...$vals) => array_reduce($vals, fn ($carry, $v) => $carry ?: (int) $v, 0);

        return [
            // Stats LIVE din platformă
            'tickets_sold' => $this->formatStat($kpis['tickets_sold']),
            'events_played' => $this->formatStat($kpis['events_count']),
            'cities' => $this->formatStat($cities),
            'countries' => $this->formatStat($countries),
            'peak_audience' => $this->formatStat($peakAudience),
            // Stats sociale din profilul Artist (sync-uri separate)
            'instagram_followers' => $this->formatStat($pickFirst($artist->followers_instagram, $artist->instagram_followers)),
            'facebook_followers' => $this->formatStat($pickFirst($artist->followers_facebook, $artist->facebook_followers)),
            'youtube_followers' => $this->formatStat($pickFirst($artist->followers_youtube, $artist->youtube_followers)),
            'spotify_followers' => $this->formatStat((int) ($artist->spotify_followers ?? 0)),
            'spotify_monthly_listeners' => $this->formatStat((int) ($artist->spotify_monthly_listeners ?? 0)),
            'tiktok_followers' => $this->formatStat($pickFirst($artist->followers_tiktok, $artist->tiktok_followers)),
            // Stats noi cerute: views YouTube + popularitate Spotify (0-100)
            'youtube_views' => $this->formatStat((int) ($artist->youtube_total_views ?? 0)),
            'spotify_popularity' => $this->formatStat((int) ($artist->spotify_popularity ?? 0)),
        ];
    }

    /**
     * Past events ordonate desc dupa event_date, format compact pentru afișarea
     * in EPK section. Filter prin hidden_event_ids din variant.data.past_events.
     */
    public function getPastEventsFor(Artist $artist, ?int $limit = 20, array $hiddenIds = []): array
    {
        $query = DB::table('events')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->leftJoin('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $artist->id)
            ->where('events.event_date', '<', now()->toDateString())
            ->orderByDesc('events.event_date')
            ->select(
                'events.id',
                'events.title',
                'events.event_date',
                'venues.name as venue_name',
                'venues.city as venue_city',
            );

        if (!empty($hiddenIds)) {
            $query->whereNotIn('events.id', $hiddenIds);
        }
        if ($limit) {
            $query->limit($limit);
        }

        $monthsRo = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        return $query->get()->map(function ($row) use ($monthsRo) {
            $title = $row->title;
            if (is_string($title) && str_starts_with(trim($title), '{')) {
                $decoded = json_decode($title, true);
                if (is_array($decoded)) {
                    $title = $decoded['ro'] ?? $decoded['en'] ?? array_values($decoded)[0] ?? '';
                }
            }
            $venueName = $row->venue_name;
            if (is_string($venueName) && str_starts_with(trim($venueName), '{')) {
                $decoded = json_decode($venueName, true);
                if (is_array($decoded)) {
                    $venueName = $decoded['ro'] ?? $decoded['en'] ?? array_values($decoded)[0] ?? '';
                }
            }

            $date = $row->event_date ? Carbon::parse($row->event_date) : null;
            return [
                'id' => $row->id,
                'day' => $date?->day,
                'month' => $date ? $monthsRo[$date->month - 1] : '',
                'year' => $date?->year,
                'title' => $title,
                'venue' => trim(($venueName ?? '') . ($row->venue_city ? ', ' . $row->venue_city : '')),
            ];
        })->toArray();
    }

    /**
     * Agregă totul pentru render-ul public al unei variante EPK.
     */
    public function buildPublicPayload(ArtistEpkVariant $variant): array
    {
        $artist = $variant->artistEpk?->artist;
        if (!$artist) {
            return [];
        }

        // Defensive: getSection() poate returna null dacă varianta nu are toate
        // sectiunile (ex. variantă cloned sau veche). PHP 8+ aruncă TypeError
        // pe null['key'], deci normalizăm la array gol înainte de access.
        $pastSection = $variant->getSection(ArtistEpkVariant::SECTION_PAST_EVENTS) ?? [];
        $pastData = is_array($pastSection['data'] ?? null) ? $pastSection['data'] : [];
        $hiddenIds = (array) ($pastData['hidden_event_ids'] ?? []);
        $pastLimit = (int) ($pastData['limit'] ?? 12);

        return [
            'artist' => [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'city' => $artist->city,
                'main_image_url' => $artist->main_image_full_url,
                'logo_url' => $artist->logo_full_url,
                'portrait_url' => $artist->portrait_full_url,
            ],
            'variant' => [
                'id' => $variant->id,
                'name' => $variant->name,
                'slug' => $variant->slug,
                'target' => $variant->target,
                'accent_color' => $variant->accent_color,
                'template' => $variant->template,
                // enriched cu fallback-uri din Artist (social/contact/hero stage_name)
                'sections' => $variant->enrichedSections($artist),
            ],
            'live_stats' => $this->computeLiveStats($artist),
            'past_events' => $this->getPastEventsFor($artist, $pastLimit, $hiddenIds),
        ];
    }

    /**
     * Invalidează cache-ul pentru live stats al unui artist + cache-ul HTML
     * al variantelor lui EPK (toate). Apelat din Order observer și save variant.
     */
    public function flushCacheFor(Artist $artist): void
    {
        Cache::forget($this->statsCacheKey($artist->id));

        $epk = $artist->epk;
        if ($epk) {
            foreach ($epk->variants as $variant) {
                Cache::forget($this->publicCacheKey($variant->id));
            }
        }
    }

    public function flushVariantCache(ArtistEpkVariant $variant): void
    {
        Cache::forget($this->publicCacheKey($variant->id));
    }

    public function statsCacheKey(int $artistId): string
    {
        return "artist:{$artistId}:epk_stats";
    }

    public function publicCacheKey(int $variantId): string
    {
        return "epk:public:{$variantId}";
    }

    /**
     * Format numere mari ca "47k" / "1.2M"; restul rămân cum sunt.
     */
    protected function formatStat(int $value): array
    {
        $display = match (true) {
            $value >= 1_000_000 => round($value / 1_000_000, 1) . 'M',
            $value >= 10_000 => round($value / 1_000) . 'k',
            $value >= 1_000 => round($value / 1_000, 1) . 'k',
            default => (string) $value,
        };

        return ['raw' => $value, 'display' => $display];
    }
}

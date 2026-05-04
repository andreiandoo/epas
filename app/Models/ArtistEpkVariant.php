<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ArtistEpkVariant extends Model
{
    public const SECTION_HERO = 'hero';
    public const SECTION_STATS = 'stats';
    public const SECTION_BIO = 'bio';
    public const SECTION_GALLERY = 'gallery';
    public const SECTION_SPOTIFY = 'spotify';
    public const SECTION_YOUTUBE = 'youtube';
    public const SECTION_ACHIEVEMENTS = 'achievements';
    public const SECTION_PRESS_QUOTES = 'press_quotes';
    public const SECTION_PAST_EVENTS = 'past_events';
    public const SECTION_RIDER = 'rider';
    public const SECTION_SOCIAL = 'social';
    public const SECTION_CONTACT = 'contact';

    public const ALL_SECTIONS = [
        self::SECTION_HERO,
        self::SECTION_STATS,
        self::SECTION_BIO,
        self::SECTION_GALLERY,
        self::SECTION_SPOTIFY,
        self::SECTION_YOUTUBE,
        self::SECTION_ACHIEVEMENTS,
        self::SECTION_PRESS_QUOTES,
        self::SECTION_PAST_EVENTS,
        self::SECTION_RIDER,
        self::SECTION_SOCIAL,
        self::SECTION_CONTACT,
    ];

    public const MAX_VARIANTS_PER_EPK = 3;
    public const MAX_GALLERY_IMAGES = 12;
    public const MAX_YOUTUBE_VIDEOS = 3;

    protected $fillable = [
        'artist_epk_id',
        'name',
        'target',
        'slug',
        'accent_color',
        'template',
        'sections',
        'views_count',
        'conversion_pct',
    ];

    protected $casts = [
        'sections' => 'array',
        'views_count' => 'integer',
        'conversion_pct' => 'decimal:2',
    ];

    public function artistEpk(): BelongsTo
    {
        return $this->belongsTo(ArtistEpk::class);
    }

    public function riderLeads(): HasMany
    {
        return $this->hasMany(ArtistEpkRiderLead::class);
    }

    public function getSection(string $id): ?array
    {
        $sections = $this->sections ?? [];
        foreach ($sections as $section) {
            if (($section['id'] ?? null) === $id) {
                return $section;
            }
        }
        return null;
    }

    public function setSection(string $id, array $data, bool $enabled = true): void
    {
        $sections = $this->sections ?? [];
        $found = false;
        foreach ($sections as &$section) {
            if (($section['id'] ?? null) === $id) {
                $section['data'] = $data;
                $section['enabled'] = $enabled;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $sections[] = ['id' => $id, 'enabled' => $enabled, 'data' => $data];
        }
        $this->sections = $sections;
    }

    /**
     * Returnează cele 12 secțiuni cu valori default (goale sau pre-completate
     * din câmpurile existente ale Artist-ului acolo unde e logic).
     */
    public static function defaultSections(?Artist $artist = null): array
    {
        $bio = $artist ? ($artist->bio_html['ro'] ?? $artist->bio_html['en'] ?? '') : '';
        $bioPlain = is_string($bio) ? trim(strip_tags($bio)) : '';

        return [
            ['id' => self::SECTION_HERO, 'enabled' => true, 'data' => [
                'stage_name' => $artist?->name ?? '',
                'tagline' => '',
                // Folosim *_full_url accessor (URL absolut). main_image e landscape pe artist profile.
                'cover_image' => $artist?->main_image_full_url ?? null,
            ]],
            ['id' => self::SECTION_STATS, 'enabled' => true, 'data' => [
                'show' => [
                    'tickets_sold' => true,
                    'events_played' => true,
                    'cities' => true,
                    'countries' => true,
                    'peak_audience' => true,
                ],
            ]],
            ['id' => self::SECTION_BIO, 'enabled' => true, 'data' => [
                'bio_short' => mb_substr($bioPlain, 0, 280),
                'bio_long' => $bioPlain,
            ]],
            ['id' => self::SECTION_GALLERY, 'enabled' => true, 'data' => [
                // Full URLs (Storage::disk('public')->url()) — sunt absolute,
                // se încarcă corect din ambilet.ro. Filtrează valorile null/empty.
                'images' => array_values(array_filter([
                    $artist?->main_image_full_url,
                    $artist?->portrait_full_url,
                ])),
            ]],
            ['id' => self::SECTION_SPOTIFY, 'enabled' => !empty($artist?->spotify_url), 'data' => [
                'spotify_url' => $artist?->spotify_url ?? '',
            ]],
            ['id' => self::SECTION_YOUTUBE, 'enabled' => false, 'data' => [
                // Normalize la [{url: '...'}] indiferent de format-ul stocat pe Artist
                // (uneori e [{url}], uneori string, uneori [{url, title}]).
                'videos' => collect($artist?->youtube_videos ?? [])
                    ->map(fn ($v) => ['url' => is_string($v) ? $v : ($v['url'] ?? '')])
                    ->filter(fn ($v) => !empty($v['url']))
                    ->values()
                    ->toArray(),
            ]],
            ['id' => self::SECTION_ACHIEVEMENTS, 'enabled' => false, 'data' => [
                'items' => $artist?->achievements ?? [],
            ]],
            ['id' => self::SECTION_PRESS_QUOTES, 'enabled' => false, 'data' => [
                'quotes' => [],
            ]],
            ['id' => self::SECTION_PAST_EVENTS, 'enabled' => true, 'data' => [
                'hidden_event_ids' => [],
                'limit' => 12,
            ]],
            ['id' => self::SECTION_RIDER, 'enabled' => false, 'data' => [
                'rider_pdf_url' => null,
                'gated' => false,
            ]],
            ['id' => self::SECTION_SOCIAL, 'enabled' => true, 'data' => [
                'website' => $artist?->website ?? '',
                'facebook' => $artist?->facebook_url ?? '',
                'instagram' => $artist?->instagram_url ?? '',
                'tiktok' => $artist?->tiktok_url ?? '',
                'youtube' => $artist?->youtube_url ?? '',
            ]],
            ['id' => self::SECTION_CONTACT, 'enabled' => true, 'data' => [
                'email' => $artist?->email ?? '',
                'phone' => $artist?->phone ?? '',
                'show_booking_cta' => true,
            ]],
        ];
    }

    /**
     * URL public absolut pentru aceasta varianta.
     * Daca e variant activa, omite slug-ul ca sa pastreze URL-uri scurte.
     */
    public function publicUrl(MarketplaceClient $marketplace): string
    {
        $artist = $this->artistEpk?->artist;
        if (!$artist) {
            return '';
        }

        $domain = rtrim($marketplace->domain ?? '', '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $isActive = $this->artistEpk?->active_variant_id === $this->id;

        $path = '/epk/' . $artist->slug;
        if (!$isActive) {
            $path .= '/' . $this->slug;
        }

        return $domain . $path;
    }

    /**
     * Generate slug unique per artist_epk_id daca nu e setat.
     */
    public static function uniqueSlugForEpk(int $artistEpkId, string $proposed, ?int $ignoreId = null): string
    {
        $base = Str::slug($proposed);
        if ($base === '') {
            $base = 'variant';
        }

        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('artist_epk_id', $artistEpkId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function isActive(): bool
    {
        return $this->artistEpk?->active_variant_id === $this->id;
    }

    /**
     * Returnează sections cu fallback-uri din Artist profile pentru câmpurile
     * care sunt goale. Utilizat la afișare (editor + public render) ca să nu
     * vadă utilizatorul "blank" când are de fapt URL-urile setate pe profil.
     *
     * NU mută datele în DB — doar le îmbogățește la output.
     */
    public function enrichedSections(?Artist $artist = null): array
    {
        $artist = $artist ?? $this->artistEpk?->artist;
        $rawSections = is_array($this->sections) ? $this->sections : [];

        if (!$artist) {
            // Sanitize doar — fără fallback-uri din artist
            return array_values(array_filter(array_map(
                fn ($s) => is_array($s) ? $s + ['data' => is_array($s['data'] ?? null) ? $s['data'] : []] : null,
                $rawSections
            )));
        }

        $socialMap = [
            'website' => $artist->website,
            'facebook' => $artist->facebook_url,
            'instagram' => $artist->instagram_url,
            'tiktok' => $artist->tiktok_url,
            'youtube' => $artist->youtube_url,
        ];

        $contactMap = [
            'email' => $artist->email,
            'phone' => $artist->phone,
        ];

        // Normalize artist YouTube videos defensively
        $artistYoutubeVideos = collect($artist->youtube_videos ?? [])
            ->map(fn ($v) => ['url' => is_string($v) ? $v : ($v['url'] ?? '')])
            ->filter(fn ($v) => !empty($v['url']))
            ->values()
            ->all();

        $enriched = [];
        foreach ($rawSections as $section) {
            // Defensive: skip non-array sections (data corruption guard)
            if (!is_array($section)) {
                continue;
            }
            $section['data'] = is_array($section['data'] ?? null) ? $section['data'] : [];
            $sectionId = $section['id'] ?? null;

            if ($sectionId === self::SECTION_SOCIAL) {
                foreach ($socialMap as $key => $artistVal) {
                    if (empty($section['data'][$key]) && !empty($artistVal)) {
                        $section['data'][$key] = $artistVal;
                    }
                }
            }

            if ($sectionId === self::SECTION_CONTACT) {
                foreach ($contactMap as $key => $artistVal) {
                    if (empty($section['data'][$key]) && !empty($artistVal)) {
                        $section['data'][$key] = $artistVal;
                    }
                }
            }

            // Hero: stage_name + cover_image fallback la artist
            if ($sectionId === self::SECTION_HERO) {
                if (empty($section['data']['stage_name'])) {
                    $section['data']['stage_name'] = $artist->name;
                }
                if (empty($section['data']['cover_image']) && !empty($artist->main_image_full_url)) {
                    $section['data']['cover_image'] = $artist->main_image_full_url;
                }
            }

            // YouTube: fallback la videoclipurile din profil + auto-enable când avem date
            if ($sectionId === self::SECTION_YOUTUBE) {
                $currentVideos = collect($section['data']['videos'] ?? [])
                    ->map(fn ($v) => ['url' => is_string($v) ? $v : ($v['url'] ?? '')])
                    ->filter(fn ($v) => !empty($v['url']))
                    ->values()
                    ->all();
                if (empty($currentVideos) && !empty($artistYoutubeVideos)) {
                    $section['data']['videos'] = $artistYoutubeVideos;
                    if (empty($section['enabled'])) {
                        $section['enabled'] = true;
                    }
                } else {
                    $section['data']['videos'] = $currentVideos;
                }
            }

            // Spotify: fallback la URL profil
            if ($sectionId === self::SECTION_SPOTIFY) {
                if (empty($section['data']['spotify_url']) && !empty($artist->spotify_url)) {
                    $section['data']['spotify_url'] = $artist->spotify_url;
                    if (empty($section['enabled'])) {
                        $section['enabled'] = true;
                    }
                }
            }

            // Achievements: fallback la cele din profil
            if ($sectionId === self::SECTION_ACHIEVEMENTS) {
                $items = is_array($section['data']['items'] ?? null) ? $section['data']['items'] : [];
                if (empty($items) && !empty($artist->achievements)) {
                    $section['data']['items'] = $artist->achievements;
                    if (empty($section['enabled'])) {
                        $section['enabled'] = true;
                    }
                }
            }

            // Bio: fallback la bio_html din profil pentru bio_long
            if ($sectionId === self::SECTION_BIO) {
                if (empty($section['data']['bio_long'])) {
                    $bioHtml = $artist->bio_html;
                    if (is_array($bioHtml)) {
                        $section['data']['bio_long'] = $bioHtml['ro'] ?? $bioHtml['en'] ?? (array_values($bioHtml)[0] ?? '');
                    }
                }
            }

            // Gallery: normalize la URL-uri absolute, filtrează valorile goale
            // + fallback la imaginile din profil dacă tot gol
            if ($sectionId === self::SECTION_GALLERY) {
                $images = (array) ($section['data']['images'] ?? []);
                $images = array_values(array_filter(array_map(function ($img) {
                    if (!is_string($img) || $img === '') {
                        return null;
                    }
                    if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                        return $img;
                    }
                    try {
                        return \Illuminate\Support\Facades\Storage::disk('public')->url(ltrim($img, '/'));
                    } catch (\Throwable $e) {
                        return null;
                    }
                }, $images)));
                if (empty($images)) {
                    $images = array_values(array_filter([
                        $artist->main_image_full_url,
                        $artist->portrait_full_url,
                    ]));
                }
                $section['data']['images'] = $images;
            }

            $enriched[] = $section;
        }

        return $enriched;
    }
}

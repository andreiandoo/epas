<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\ArtistGenre;
use App\Models\ArtistType;
use App\Models\MarketplaceArtistAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Artist self-service controller for the linked Artist content profile.
 * All endpoints are gated to authenticated, status=active accounts that
 * have a linked artist_id (canEditArtistProfile()). Field allow-lists are
 * intentionally narrow — admin-only fields (is_partner, is_featured,
 * partner_notes, social stats, slug, etc.) are stripped.
 */
class ProfileController extends BaseController
{
    /**
     * Whitelist of fields the artist may edit on their own profile.
     * Anything else (slug, status, marketplace ownership, social-stats
     * snapshots, partner flags) is admin-only.
     */
    protected const EDITABLE_FIELDS = [
        'name',
        'bio_html',          // Translatable JSON: ['ro' => '...', 'en' => '...']
        'main_image_url',    // updated by uploadImage(), not via PUT body
        'logo_url',
        'portrait_url',
        'country', 'state', 'city',
        'founded_year', 'members_count', 'record_label',
        'achievements',      // JSON array of {title, subtitle}
        'discography',       // JSON array of {image, name, type, year}
        'website',
        'facebook_url', 'instagram_url', 'tiktok_url', 'youtube_url',
        'spotify_url', 'spotify_id', 'youtube_id',
        'youtube_videos',    // JSON array of {url}
        'email',
        'phone',
        'manager_first_name', 'manager_last_name', 'manager_email',
        'manager_phone', 'manager_website',
        'agent_first_name', 'agent_last_name', 'agent_email',
        'agent_phone', 'agent_website',
        'booking_agency',    // JSON {name, email, phone, website, services[]}
        // Fees are editable per user request (Etapa 1 conversation)
        'min_fee_concert', 'max_fee_concert',
        'min_fee_festival', 'max_fee_festival',
    ];

    /**
     * GET /artist/profile — return the linked Artist record + the M2M
     * relationship IDs the editor needs to repopulate selects.
     */
    public function show(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        if (!$account->canEditArtistProfile()) {
            return $this->error('Profilul tău nu este încă asociat cu un cont activ.', 403, [
                'code' => $account->artist_id ? 'inactive' : 'unlinked',
            ]);
        }

        $artist = Artist::with(['artistTypes:id,name,slug', 'artistGenres:id,name,slug'])
            ->find($account->artist_id);

        if (!$artist) {
            return $this->error('Profilul de artist asociat nu a fost găsit.', 404);
        }

        return $this->success([
            'artist' => $this->formatArtist($artist),
        ]);
    }

    /**
     * PUT /artist/profile — update the linked Artist with a strict allowed
     * subset of fields. Translatable bio_html is merged so other locales
     * are preserved when the user edits only one.
     */
    public function update(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        if (!$account->canEditArtistProfile()) {
            return $this->error('Profilul tău nu este încă asociat cu un cont activ.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio_html' => 'sometimes|array',
            'bio_html.ro' => 'nullable|string|max:50000',
            'bio_html.en' => 'nullable|string|max:50000',
            'logo_url' => 'sometimes|nullable|string|max:500',
            'portrait_url' => 'sometimes|nullable|string|max:500',
            'main_image_url' => 'sometimes|nullable|string|max:500',
            'country' => 'sometimes|nullable|string|max:64',
            'state' => 'sometimes|nullable|string|max:120',
            'city' => 'sometimes|nullable|string|max:120',
            'founded_year' => 'sometimes|nullable|integer|min:1800|max:' . (date('Y') + 1),
            'members_count' => 'sometimes|nullable|integer|min:1|max:500',
            'record_label' => 'sometimes|nullable|string|max:255',
            'achievements' => 'sometimes|nullable|array|max:20',
            'achievements.*.title' => 'required_with:achievements|string|max:14',
            'achievements.*.subtitle' => 'required_with:achievements|string|max:24',
            'discography' => 'sometimes|nullable|array|max:50',
            'discography.*.name' => 'required_with:discography|string|max:255',
            'discography.*.type' => 'nullable|in:album,ep,single,live,live_dvd,compilation,soundtrack,remix',
            'discography.*.year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'discography.*.image' => 'nullable|string|max:500',
            'website' => 'sometimes|nullable|url|max:255',
            'facebook_url' => 'sometimes|nullable|url|max:255',
            'instagram_url' => 'sometimes|nullable|url|max:255',
            'tiktok_url' => 'sometimes|nullable|url|max:255',
            'youtube_url' => 'sometimes|nullable|url|max:255',
            'spotify_url' => 'sometimes|nullable|url|max:255',
            'spotify_id' => 'sometimes|nullable|string|max:64',
            'youtube_id' => 'sometimes|nullable|string|max:64',
            'youtube_videos' => 'sometimes|nullable|array|max:5',
            'youtube_videos.*.url' => 'required_with:youtube_videos|url|max:500',
            'email' => 'sometimes|nullable|email|max:190',
            'phone' => 'sometimes|nullable|string|max:64',
            'manager_first_name' => 'sometimes|nullable|string|max:100',
            'manager_last_name' => 'sometimes|nullable|string|max:100',
            'manager_email' => 'sometimes|nullable|email|max:190',
            'manager_phone' => 'sometimes|nullable|string|max:64',
            'manager_website' => 'sometimes|nullable|url|max:255',
            'agent_first_name' => 'sometimes|nullable|string|max:100',
            'agent_last_name' => 'sometimes|nullable|string|max:100',
            'agent_email' => 'sometimes|nullable|email|max:190',
            'agent_phone' => 'sometimes|nullable|string|max:64',
            'agent_website' => 'sometimes|nullable|url|max:255',
            'booking_agency' => 'sometimes|nullable|array',
            'booking_agency.name' => 'nullable|string|max:255',
            'booking_agency.email' => 'nullable|email|max:190',
            'booking_agency.phone' => 'nullable|string|max:64',
            'booking_agency.website' => 'nullable|url|max:255',
            'booking_agency.services' => 'nullable|array',
            'booking_agency.services.*' => 'in:booking,management,pr',
            'min_fee_concert' => 'sometimes|nullable|numeric|min:0|max:9999999.99',
            'max_fee_concert' => 'sometimes|nullable|numeric|min:0|max:9999999.99',
            'min_fee_festival' => 'sometimes|nullable|numeric|min:0|max:9999999.99',
            'max_fee_festival' => 'sometimes|nullable|numeric|min:0|max:9999999.99',
            'artist_type_ids' => 'sometimes|array',
            'artist_type_ids.*' => 'integer|exists:artist_types,id',
            'artist_genre_ids' => 'sometimes|array',
            'artist_genre_ids.*' => 'integer|exists:artist_genres,id',
        ]);

        $artist = Artist::find($account->artist_id);
        if (!$artist) {
            return $this->error('Profilul de artist asociat nu a fost găsit.', 404);
        }

        // Bio merge: if user only edited Romanian, keep English untouched.
        if (isset($validated['bio_html'])) {
            $existing = is_array($artist->bio_html) ? $artist->bio_html : [];
            $artist->bio_html = array_replace($existing, array_filter(
                $validated['bio_html'],
                fn ($v) => $v !== null
            ));
            unset($validated['bio_html']);
        }

        // Sanitize HTML in bio if helper exists (defense-in-depth).
        if (isset($artist->bio_html) && is_array($artist->bio_html) && class_exists(\App\Helpers\HtmlSanitizer::class)) {
            $sanitized = [];
            foreach ($artist->bio_html as $locale => $html) {
                $sanitized[$locale] = $html ? \App\Helpers\HtmlSanitizer::sanitize($html) : $html;
            }
            $artist->bio_html = $sanitized;
        }

        // Apply scalar/array fields from the whitelist.
        $artist->fill(array_intersect_key($validated, array_flip(self::EDITABLE_FIELDS)));
        $artist->save();

        // Sync M2M relations if supplied.
        if (array_key_exists('artist_type_ids', $validated)) {
            $artist->artistTypes()->sync($validated['artist_type_ids'] ?? []);
        }
        if (array_key_exists('artist_genre_ids', $validated)) {
            $artist->artistGenres()->sync($validated['artist_genre_ids'] ?? []);
        }

        $artist->load(['artistTypes:id,name,slug', 'artistGenres:id,name,slug']);

        return $this->success([
            'artist' => $this->formatArtist($artist),
        ], 'Profil actualizat.');
    }

    /**
     * GET /artist/profile/taxonomies — flat list of artist types + genres
     * for the editor's multi-select inputs. Cached for 1 hour because
     * these change very rarely.
     *
     * Names on ArtistType / ArtistGenre are translatable (cast as array
     * with locale keys), so we resolve to a plain string here. Otherwise
     * the picker UI would render "[object Object]" — the JS doesn't need
     * to know about the translatable shape.
     */
    public function taxonomies(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        $locale = $account->locale ?: app()->getLocale();

        $data = Cache::remember('artist_account.taxonomies.v2.' . $locale, now()->addHour(), function () use ($locale) {
            $resolveName = function ($model) use ($locale) {
                $name = $model->name;
                if (is_array($name)) {
                    return $name[$locale] ?? $name['ro'] ?? $name['en'] ?? array_values(array_filter($name))[0] ?? '';
                }
                return (string) $name;
            };

            return [
                'artist_types' => ArtistType::query()
                    ->get(['id', 'name', 'slug'])
                    ->map(fn ($t) => [
                        'id' => $t->id,
                        'name' => $resolveName($t),
                        'slug' => $t->slug,
                    ])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->toArray(),
                'artist_genres' => ArtistGenre::query()
                    ->get(['id', 'name', 'slug'])
                    ->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $resolveName($g),
                        'slug' => $g->slug,
                    ])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->toArray(),
            ];
        });

        return $this->success($data);
    }

    /**
     * POST /artist/profile/image — upload a single image (main/logo/
     * portrait/discography). Returns the storage path that should be
     * sent back via PUT /profile to actually persist it on the artist
     * record (so previews can be cancelled before save).
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        if (!$account->canEditArtistProfile()) {
            return $this->error('Profilul tău nu este încă asociat cu un cont activ.', 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'type' => 'required|in:main,logo,portrait,discography',
        ]);

        $directory = match ($validated['type']) {
            'main' => 'artists',
            'logo' => 'artists/logos',
            'portrait' => 'artists/portraits',
            'discography' => 'artists/discography',
        };

        $path = $request->file('image')->store($directory, 'public');

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'type' => $validated['type'],
        ], 'Imagine încărcată.');
    }

    /**
     * Formats the Artist for the editor UI. Includes both raw paths (for
     * the form to round-trip) and `*_full_url` accessors for previews.
     */
    protected function formatArtist(Artist $artist): array
    {
        return [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'bio_html' => is_array($artist->bio_html) ? $artist->bio_html : [],
            'main_image_url' => $artist->main_image_url,
            'main_image_full_url' => $artist->main_image_full_url,
            'logo_url' => $artist->logo_url,
            'logo_full_url' => $artist->logo_full_url,
            'portrait_url' => $artist->portrait_url,
            'portrait_full_url' => $artist->portrait_full_url,
            'country' => $artist->country,
            'state' => $artist->state,
            'city' => $artist->city,
            'founded_year' => $artist->founded_year,
            'members_count' => $artist->members_count,
            'record_label' => $artist->record_label,
            'achievements' => $artist->achievements ?? [],
            'discography' => $artist->discography ?? [],
            'website' => $artist->website,
            'facebook_url' => $artist->facebook_url,
            'instagram_url' => $artist->instagram_url,
            'tiktok_url' => $artist->tiktok_url,
            'youtube_url' => $artist->youtube_url,
            'spotify_url' => $artist->spotify_url,
            'spotify_id' => $artist->spotify_id,
            'youtube_id' => $artist->youtube_id,
            'youtube_videos' => $artist->youtube_videos ?? [],
            'email' => $artist->email,
            'phone' => $artist->phone,
            'manager_first_name' => $artist->manager_first_name,
            'manager_last_name' => $artist->manager_last_name,
            'manager_email' => $artist->manager_email,
            'manager_phone' => $artist->manager_phone,
            'manager_website' => $artist->manager_website,
            'agent_first_name' => $artist->agent_first_name,
            'agent_last_name' => $artist->agent_last_name,
            'agent_email' => $artist->agent_email,
            'agent_phone' => $artist->agent_phone,
            'agent_website' => $artist->agent_website,
            'booking_agency' => $artist->booking_agency ?? [],
            'min_fee_concert' => $artist->min_fee_concert,
            'max_fee_concert' => $artist->max_fee_concert,
            'min_fee_festival' => $artist->min_fee_festival,
            'max_fee_festival' => $artist->max_fee_festival,
            // Translatable names → flatten to a string for the JS picker.
            // Same fallback chain as taxonomies(): current account locale,
            // then ro, then en, then any non-empty value.
            'artist_types' => $artist->artistTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $this->translatableToString($t->name),
                'slug' => $t->slug,
            ])->values()->toArray(),
            'artist_genres' => $artist->artistGenres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $this->translatableToString($g->name),
                'slug' => $g->slug,
            ])->values()->toArray(),
        ];
    }

    /**
     * Flatten a translatable value to a string for API responses.
     * Falls back through current locale → ro → en → any non-empty entry.
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

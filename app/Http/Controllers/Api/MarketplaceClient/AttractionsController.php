<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Attraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * F4 — Public marketplace API for Attractions (points of interest).
 *
 *   GET /attractions                — list (filter by city / type)
 *   GET /attractions/{slug}         — single attraction detail + activities
 *
 * Scoped by marketplace client (resolved from the API key by marketplace.auth).
 * Read access is open even when `discovery-module` is off (the table is simply
 * empty for marketplaces that never seeded attractions — same pattern as
 * activities/events).
 */
class AttractionsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $query = Attraction::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->with(['type:id,slug,name,icon_emoji', 'city:id,slug,name']);

        if ($citySlug = $request->query('city')) {
            $query->whereHas('city', fn ($q) => $q->where('slug', $citySlug));
        }
        if ($typeSlug = $request->query('type')) {
            $query->whereHas('type', fn ($q) => $q->where('slug', $typeSlug));
        }
        if ($featured = $request->query('featured')) {
            if (in_array($featured, ['1', 'true', 'yes'], true)) {
                $query->where('is_featured', true);
            }
        }

        $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id');

        $perPage = max(1, min(50, (int) $request->query('per_page', 24)));
        $paginator = $query->paginate($perPage);

        return $this->success([
            'items' => $paginator->getCollection()->map(fn ($a) => $this->cardPayload($a, $locale))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $attraction = Attraction::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('is_visible', true)
            ->with([
                'type:id,slug,name,icon_emoji',
                'city:id,slug,name',
                'activities' => fn ($q) => $q->where('is_published', true)->orderBy('activity_attraction.sort_order'),
                'activities.city:id,slug,name',
                'activities.category:id,slug,name,parent_id',
            ])
            ->first();

        if (! $attraction) {
            return response()->json(['success' => false, 'message' => 'Attraction not found'], 404);
        }

        return $this->success([
            'attraction' => array_merge($this->cardPayload($attraction, $locale), [
                'subtitle'    => $this->translate($attraction->subtitle, $locale),
                'description' => $this->translate($attraction->description, $locale),
                'gallery'     => collect((array) $attraction->gallery)->map(fn ($g) => $this->img($g))->filter()->values()->all(),
                'address'     => $attraction->address,
                'latitude'    => $attraction->latitude,
                'longitude'   => $attraction->longitude,
                'faqs'        => (array) ($attraction->faqs ?? []),
                'seo'         => [
                    'title'       => is_array($attraction->seo) ? ($attraction->seo['title_' . $locale] ?? null) : null,
                    'description' => is_array($attraction->seo) ? ($attraction->seo['description_' . $locale] ?? null) : null,
                ],
                'activities'  => $attraction->activities->map(fn ($act) => [
                    'slug'            => $act->slug,
                    'title'          => $this->translate($act->title, $locale),
                    'cover_image_url' => $this->img($act->cover_image_url),
                    'cheapest_price_cents' => $act->cheapest_price_cents,
                    'duration_minutes' => (int) $act->duration_minutes,
                    'city'           => $act->city ? ['slug' => $act->city->slug, 'name' => $this->translate($act->city->name, $locale)] : null,
                    'category'       => $act->category ? ['slug' => $act->category->slug, 'name' => $this->translate($act->category->name, $locale)] : null,
                ])->values()->all(),
            ]),
        ]);
    }

    private function cardPayload(Attraction $a, string $locale): array
    {
        return [
            'id'              => $a->id,
            'slug'            => $a->slug,
            'name'            => $this->translate($a->name, $locale),
            'cover_image_url' => $this->img($a->cover_image_url),
            'latitude'        => $a->latitude,
            'longitude'       => $a->longitude,
            'is_featured'     => (bool) $a->is_featured,
            'activities_count' => $a->activities_count ?? null,
            'type' => $a->type ? [
                'slug' => $a->type->slug,
                'name' => $this->translate($a->type->name, $locale),
                'icon' => $a->type->icon_emoji,
            ] : null,
            'city' => $a->city ? [
                'slug' => $a->city->slug,
                'name' => $this->translate($a->city->name, $locale),
            ] : null,
        ];
    }

    private function translate($value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? (reset($value) ?: null);
        }

        return $value !== '' && $value !== null ? (string) $value : null;
    }

    private function img(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}

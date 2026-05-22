<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceCityIntent;
use App\Services\IntentFilterResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public marketplace endpoint that resolves a (city, intent) pair into
 * a filtered events list + rendered SEO meta + cross-link suggestions
 * for sidebar / footer internal linking.
 */
class IntentEventsController extends BaseController
{
    public function __construct(protected IntentFilterResolver $resolver) {}

    /**
     * GET /api/marketplace-client/intents/{intent_slug}/cities/{city_slug?}/events
     *
     * Query params:
     *   page         — pagination page (default 1)
     *   per_page     — page size (default 24, max 60)
     *   locale       — `ro` (default) | `en`
     */
    public function index(Request $request, string $intentSlug, ?string $citySlug = null): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->input('locale', $client->language ?? 'ro');

        $intent = MarketplaceCityIntent::forMarketplace($client->id)
            ->active()
            ->where('slug', $intentSlug)
            ->first();

        if (!$intent) {
            return response()->json(['success' => false, 'error' => 'intent_not_found'], 404);
        }

        $city = null;
        if ($citySlug) {
            $city = MarketplaceCity::where('marketplace_client_id', $client->id)
                ->where('slug', $citySlug)
                ->first();
            if (!$city) {
                return response()->json(['success' => false, 'error' => 'city_not_found'], 404);
            }
        }

        // Base query — published + not past. Cancelled stays in (with badge handled at render).
        $query = Event::where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereDate('event_date', '>=', now()->toDateString())
                    ->orWhereNull('event_date');
            })
            ->with([
                'marketplaceOrganizer:id,name,slug,logo',
                'marketplaceEventCategory:id,name,slug',
                'marketplaceCity:id,name,slug',
                'venue:id,name,city,address',
            ]);

        // Apply intent filter DSL
        $bindings = [
            'marketplace_client_id' => $client->id,
            'city' => $citySlug,
            'city_id' => $city?->id,
            'locale' => $locale,
        ];
        $this->resolver->apply($query, $intent->filter_rule_json ?? [], $bindings);

        // Pagination
        $perPage = min((int) $request->input('per_page', 24), 60);
        $events = $query->orderBy('next_session_at')
            ->orderByDesc('is_promoted')
            ->paginate($perPage);

        // SEO meta with placeholder substitution
        $cityName = $city
            ? ($city->getTranslation('name', $locale) ?? (is_array($city->name) ? reset($city->name) : $city->name))
            : null;
        $context = [
            'city_name' => $cityName ?? '',
            'result_count' => $events->total(),
        ];

        $meta = [
            'title' => $intent->renderTemplate('title_template', $locale, $context),
            'h1' => $intent->renderTemplate('h1_template', $locale, $context)
                ?: $intent->renderTemplate('title_template', $locale, $context),
            'description' => $intent->renderTemplate('meta_description_template', $locale, $context),
            'intro_copy' => $intent->renderTemplate('intro_copy', $locale, $context),
            'seo_copy' => $intent->renderTemplate('seo_copy', $locale, $context),
            'accent_color' => $intent->accent_color,
            'icon' => $intent->icon,
            'cover_image_url' => $intent->cover_image_url,
            'noindex' => $events->total() < ($intent->min_results_for_index ?? 3),
            'canonical_path' => $citySlug
                ? "/{$citySlug}/{$intent->slug}"
                : "/{$intent->slug}",
        ];

        // Cross-link suggestions for SEO sidebar/footer
        $crossLinks = $this->buildCrossLinks($client->id, $intent, $city, $locale);

        return response()->json([
            'success' => true,
            'data' => [
                'intent' => [
                    'slug' => $intent->slug,
                    'name' => $intent->getTranslation('name', $locale) ?? '',
                ],
                'city' => $city ? [
                    'slug' => $city->slug,
                    'name' => $cityName,
                ] : null,
                'meta' => $meta,
                'events' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
                'cross_links' => $crossLinks,
            ],
        ]);
    }

    /**
     * Builds two link bundles for internal SEO linking:
     *   other_intents_for_city — if a city is set, lists every other active intent
     *                            scoped to the same city (max 12)
     *   same_intent_for_cities — top featured cities + a few extras, with the
     *                            same intent applied (max 12)
     */
    protected function buildCrossLinks(int $clientId, MarketplaceCityIntent $intent, ?MarketplaceCity $city, string $locale): array
    {
        $otherIntents = MarketplaceCityIntent::forMarketplace($clientId)
            ->active()
            ->where('id', '!=', $intent->id)
            ->orderBy('sort_order')
            ->limit(12)
            ->get()
            ->map(fn ($i) => [
                'slug' => $i->slug,
                'name' => $i->getTranslation('name', $locale) ?? '',
                'icon' => $i->icon,
                'accent_color' => $i->accent_color,
                'path' => $city ? "/{$city->slug}/{$i->slug}" : "/{$i->slug}",
            ])
            ->all();

        $cities = MarketplaceCity::where('marketplace_client_id', $clientId)
            ->where('is_visible', true)
            ->when($city, fn ($q) => $q->where('id', '!=', $city->id))
            ->orderByDesc('is_featured')
            ->orderByDesc('event_count')
            ->limit(12)
            ->get()
            ->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->getTranslation('name', $locale) ?? (is_array($c->name) ? reset($c->name) : $c->name),
                'path' => "/{$c->slug}/{$intent->slug}",
            ])
            ->all();

        return [
            'other_intents_for_city' => $otherIntents,
            'same_intent_for_cities' => $cities,
        ];
    }
}

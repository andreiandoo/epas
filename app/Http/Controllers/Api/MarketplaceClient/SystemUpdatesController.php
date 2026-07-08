<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\SystemUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API for the marketplace-scoped "Noutăți" (system updates)
 * module. The marketplace is resolved from the X-API-Key header by the
 * MarketplaceClientAuth middleware — updates from other marketplaces
 * are invisible even if the caller guesses the slug.
 */
class SystemUpdatesController extends BaseController
{
    /**
     * Paginated list of published updates for the caller's marketplace.
     *
     * Query params:
     *   - page       (int, default 1)
     *   - per_page   (int, default 12, capped 50)
     *   - category   (interfata|organizator|client, optional)
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $perPage = min(50, max(1, (int) $request->query('per_page', 12)));
        $category = $request->query('category');

        $query = SystemUpdate::query()
            ->forMarketplace($client->id)
            ->published();

        if (in_array($category, SystemUpdate::CATEGORIES, true)) {
            $query->where('category', $category);
        }

        $paginator = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginated($paginator, fn ($u) => $this->transform($u, false));
    }

    /**
     * Show a single published update by slug. Also returns 3 "related"
     * (latest other published entries in the same marketplace, excluding
     * the current one) so the public detail page doesn't need a second
     * round-trip.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $update = SystemUpdate::query()
            ->forMarketplace($client->id)
            ->published()
            ->where('slug', $slug)
            ->first();

        if (!$update) {
            return $this->error('Noutatea nu a fost găsită.', 404);
        }

        $related = SystemUpdate::query()
            ->forMarketplace($client->id)
            ->published()
            ->where('id', '!=', $update->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        return $this->success([
            'update'  => $this->transform($update, true),
            'related' => $related->map(fn ($u) => $this->transform($u, false))->all(),
        ]);
    }

    /**
     * Return only the fields the public site needs. `withBody` controls
     * whether the (potentially large) HTML body is included — list pages
     * don't need it, but the detail endpoint does.
     */
    private function transform(SystemUpdate $u, bool $withBody): array
    {
        $data = [
            'id'                => $u->id,
            'title'             => $u->title,
            'slug'              => $u->slug,
            'category'          => $u->category,
            'category_label'    => match ($u->category) {
                'interfata'   => 'Interfață',
                'organizator' => 'Organizator',
                'client'      => 'Client',
                default       => ucfirst((string) $u->category),
            },
            'excerpt'           => $u->excerpt,
            'featured_image'    => $u->featured_image_url,
            'published_at'      => $u->published_at?->toIso8601String(),
            'published_at_human'=> $u->published_at
                ? $u->published_at->locale('ro')->translatedFormat('j F Y')
                : null,
            'meta_title'        => $u->meta_title,
            'meta_description'  => $u->meta_description,
            'url'               => '/noutati/' . $u->slug,
        ];

        if ($withBody) {
            $data['body'] = $u->body;
        }

        return $data;
    }
}

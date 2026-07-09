<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\SystemUpdate;
use App\Models\SystemUpdateReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Prev / next in the same marketplace so the detail page can
        // offer chronological navigation without a second round-trip.
        $prev = SystemUpdate::query()
            ->forMarketplace($client->id)
            ->published()
            ->where('published_at', '<', $update->published_at)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
        $next = SystemUpdate::query()
            ->forMarketplace($client->id)
            ->published()
            ->where('published_at', '>', $update->published_at)
            ->orderBy('published_at')
            ->orderBy('id')
            ->first();

        // Session-hash comes from a signed cookie the frontend sets on
        // first reaction. Used ONLY to tell the caller which reactions
        // they already gave; anonymous browsers never send it.
        $sessionHash = $request->cookie('noutati_session') ?? $request->header('X-Noutati-Session');

        return $this->success([
            'update'          => $this->transform($update, true),
            'related'         => $related->map(fn ($u) => $this->transform($u, false))->all(),
            'prev'            => $prev ? $this->transform($prev, false) : null,
            'next'            => $next ? $this->transform($next, false) : null,
            'reaction_counts' => $update->getReactionCounts(),
            'my_reactions'    => $update->getReactionsForSession($sessionHash),
        ]);
    }

    /**
     * Toggle a reaction (thumbs_up / heart / rocket / party) on an
     * update for the caller's session. Body params:
     *   - type          (string, one of SystemUpdateReaction::TYPES)
     *   - session_hash  (string, 64 chars — the anonymous browser id)
     *
     * Returns updated counts + the caller's active reactions so the UI
     * can re-render without a follow-up GET.
     */
    public function react(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $data = $request->validate([
            'type'         => 'required|string|in:' . implode(',', SystemUpdateReaction::TYPES),
            'session_hash' => 'required|string|size:64',
        ]);

        $update = SystemUpdate::query()
            ->forMarketplace($client->id)
            ->published()
            ->where('slug', $slug)
            ->first();

        if (!$update) {
            return $this->error('Noutatea nu a fost găsită.', 404);
        }

        // Toggle semantics: if the row exists, delete it (un-vote);
        // otherwise insert. Wrapped in a small transaction so a race
        // between two clicks can't leave a half-state.
        DB::transaction(function () use ($update, $data) {
            $existing = SystemUpdateReaction::query()
                ->where('system_update_id', $update->id)
                ->where('session_hash', $data['session_hash'])
                ->where('reaction_type', $data['type'])
                ->first();

            if ($existing) {
                $existing->delete();
            } else {
                SystemUpdateReaction::create([
                    'system_update_id' => $update->id,
                    'session_hash'     => $data['session_hash'],
                    'reaction_type'    => $data['type'],
                ]);
            }
        });

        return $this->success([
            'reaction_counts' => $update->getReactionCounts(),
            'my_reactions'    => $update->getReactionsForSession($data['session_hash']),
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
                'aplicatie-mobila' => 'Aplicație mobilă',
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

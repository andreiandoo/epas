<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\ArtistTourScenario;
use App\Models\MarketplaceArtistAccount;
use App\Services\ExtendedArtist\TourOptimizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tour Optimizer (Modulul 3 din Extended Artist).
 *
 * Endpoints under /api/marketplace-client/artist/tour/* — toate gated cu
 * auth:sanctum + extended.artist middleware. Authorization la nivel de
 * resursă: $account->artist_id determină toate query-urile.
 */
class TourOptimizerController extends BaseController
{
    public function __construct(private readonly TourOptimizerService $tour)
    {
    }

    public function opportunities(Request $request): JsonResponse
    {
        return $this->success($this->tour->opportunityMap($this->requireArtist($request)));
    }

    public function predictions(Request $request): JsonResponse
    {
        return $this->success($this->tour->predictions($this->requireArtist($request)));
    }

    public function optimize(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);

        $validated = $request->validate([
            'cities' => 'required|array|min:2|max:15',
            'cities.*.name' => 'required|string|max:80',
            'cities.*.fixed' => 'nullable|boolean',
            'constraints' => 'nullable|array',
            'constraints.max_distance_km' => 'nullable|integer|min:50|max:3000',
            'constraints.min_days_between' => 'nullable|integer|min:1|max:14',
            'constraints.budget_ron' => 'nullable|integer|min:0',
            'constraints.include_border' => 'nullable|boolean',
            'start_date' => 'nullable|date',
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : null;

        $result = $this->tour->optimizeRoute(
            $artist,
            $validated['cities'],
            $validated['constraints'] ?? [],
            $startDate
        );

        return $this->success($result);
    }

    public function listScenarios(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);

        $scenarios = $this->tour->scenarios($artist)->map(function (ArtistTourScenario $s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
                'date_range' => $s->start_date && $s->end_date
                    ? $s->start_date->translatedFormat('j M') . ' - ' . $s->end_date->translatedFormat('j M Y')
                    : '—',
                'cities_count' => count($s->cities ?? []),
                'summary' => $s->summary ?? [],
                'status' => $s->status,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'scenarios' => $scenarios->values()->all(),
            'total' => $scenarios->count(),
            'limit' => ArtistTourScenario::MAX_PER_ARTIST,
        ]);
    }

    public function saveScenario(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);

        if (ArtistTourScenario::where('artist_id', $artist->id)->count() >= ArtistTourScenario::MAX_PER_ARTIST) {
            return $this->error('Limita de ' . ArtistTourScenario::MAX_PER_ARTIST . ' scenarii a fost atinsă.', 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cities' => 'required|array|min:1',
            'constraints' => 'nullable|array',
            'optimized_route' => 'nullable|array',
            'summary' => 'nullable|array',
            'status' => 'nullable|in:active,draft',
        ]);

        $scenario = ArtistTourScenario::create([
            'artist_id' => $artist->id,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'cities' => $validated['cities'],
            'constraints' => $validated['constraints'] ?? null,
            'optimized_route' => $validated['optimized_route'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        return $this->success(['id' => $scenario->id], 'Scenariu salvat.', 201);
    }

    public function updateScenario(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $scenario = ArtistTourScenario::where('artist_id', $artist->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'cities' => 'sometimes|array',
            'constraints' => 'sometimes|nullable|array',
            'optimized_route' => 'sometimes|nullable|array',
            'summary' => 'sometimes|nullable|array',
            'status' => 'sometimes|in:active,draft',
        ]);

        $scenario->update($validated);

        return $this->success(['id' => $scenario->id], 'Scenariu actualizat.');
    }

    public function deleteScenario(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $scenario = ArtistTourScenario::where('artist_id', $artist->id)->findOrFail($id);
        $scenario->delete();

        return $this->success(null, 'Scenariu șters.');
    }

    public function compareScenarios(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $aId = (int) $request->query('a');
        $bId = (int) $request->query('b');

        if ($aId <= 0 || $bId <= 0) {
            return $this->error('Trimite două ID-uri valide pentru comparare.', 422);
        }

        return $this->success($this->tour->compareScenarios($artist, $aId, $bId));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function requireArtist(Request $request): Artist
    {
        $account = $request->user();
        if (!$account instanceof MarketplaceArtistAccount) {
            abort(401, 'Artist account required');
        }
        if (!$account->artist_id) {
            abort(403, 'Profilul tău nu este asociat cu un artist.');
        }
        $artist = Artist::find($account->artist_id);
        if (!$artist) {
            abort(404, 'Artist record not found');
        }
        return $artist;
    }
}

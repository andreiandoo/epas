<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\ArtistFanSegment;
use App\Models\MarketplaceArtistAccount;
use App\Services\ExtendedArtist\FanCrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fan CRM (Modulul 1 din Extended Artist).
 *
 * Endpoints under /api/marketplace-client/artist/fan-crm/* — toate gated cu
 * auth:sanctum + extended.artist middleware. Authorization la nivel de
 * resursă: $account->artist_id determină toate query-urile.
 */
class FanCrmController extends BaseController
{
    public function __construct(private readonly FanCrmService $fanCrm)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->success($this->fanCrm->overview($this->requireArtist($request)));
    }

    public function mapData(Request $request): JsonResponse
    {
        return $this->success($this->fanCrm->mapData($this->requireArtist($request)));
    }

    public function segments(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);

        return $this->success([
            'predefined' => collect(FanCrmService::predefinedSegments())
                ->map(fn ($cfg, $id) => [
                    'id' => $id,
                    'name' => $cfg['name'],
                    'description' => $cfg['description'],
                    'color' => $cfg['color'],
                    'is_predefined' => true,
                ])
                ->values()
                ->all(),
            'counts' => $this->fanCrm->predefinedSegmentsCounts($artist),
            'custom' => $this->fanCrm->customSegments($artist)->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'criteria' => $s->criteria,
                'color' => $s->color,
                'is_predefined' => false,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function createSegment(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);

        if (ArtistFanSegment::where('artist_id', $artist->id)->count() >= ArtistFanSegment::MAX_PER_ARTIST) {
            return $this->error('Limita de ' . ArtistFanSegment::MAX_PER_ARTIST . ' segmente custom a fost atinsă.', 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:500',
            'criteria' => 'nullable|array',
            'color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $segment = ArtistFanSegment::create([
            'artist_id' => $artist->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'criteria' => ArtistFanSegment::sanitizeCriteria($validated['criteria'] ?? []),
            'color' => $validated['color'] ?? '#A51C30',
        ]);

        $this->fanCrm->flushCache($artist);

        return $this->success([
            'id' => $segment->id,
            'name' => $segment->name,
            'description' => $segment->description,
            'criteria' => $segment->criteria,
            'color' => $segment->color,
        ], 'Segment creat.', 201);
    }

    public function updateSegment(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $segment = ArtistFanSegment::where('artist_id', $artist->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:80',
            'description' => 'sometimes|nullable|string|max:500',
            'criteria' => 'sometimes|nullable|array',
            'color' => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        if (isset($validated['criteria'])) {
            $validated['criteria'] = ArtistFanSegment::sanitizeCriteria($validated['criteria']);
        }

        $segment->update($validated);
        $this->fanCrm->flushCache($artist);

        return $this->success([
            'id' => $segment->id,
            'name' => $segment->name,
            'description' => $segment->description,
            'criteria' => $segment->criteria,
            'color' => $segment->color,
        ], 'Segment actualizat.');
    }

    public function deleteSegment(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $segment = ArtistFanSegment::where('artist_id', $artist->id)->findOrFail($id);
        $segment->delete();

        $this->fanCrm->flushCache($artist);

        return $this->success(null, 'Segment șters.');
    }

    public function previewSegment(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $segment = ArtistFanSegment::where('artist_id', $artist->id)->findOrFail($id);

        $result = $this->fanCrm->fansList($artist, ['custom_segment_id' => $segment->id], 1, 10);

        return $this->success([
            'segment' => [
                'id' => $segment->id,
                'name' => $segment->name,
                'description' => $segment->description,
                'criteria' => $segment->criteria,
            ],
            'preview' => $result['fans'],
            'total' => $result['total'],
        ]);
    }

    public function fans(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $filters = [
            'search' => $request->query('search'),
            'segment' => $request->query('segment'),
            'custom_segment_id' => $request->query('custom_segment_id'),
        ];

        return $this->success($this->fanCrm->fansList($artist, $filters, $page, $perPage));
    }

    public function exportFans(Request $request)
    {
        $artist = $this->requireArtist($request);
        $filters = [
            'search' => $request->query('search'),
            'segment' => $request->query('segment'),
            'custom_segment_id' => $request->query('custom_segment_id'),
        ];

        return $this->fanCrm->exportFansCsv($artist, $filters);
    }

    public function cohort(Request $request): JsonResponse
    {
        return $this->success($this->fanCrm->cohortMatrix($this->requireArtist($request)));
    }

    public function demographics(Request $request): JsonResponse
    {
        return $this->success($this->fanCrm->demographics($this->requireArtist($request)));
    }

    public function compare(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', 'period');
        $aId = $request->query('a_id');
        $bId = $request->query('b_id');

        return $this->success($this->fanCrm->comparison($this->requireArtist($request), $type, $aId, $bId));
    }

    public function vip(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 10)));
        return $this->success($this->fanCrm->topVips($this->requireArtist($request), $limit));
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

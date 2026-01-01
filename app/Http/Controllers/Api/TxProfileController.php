<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureStore\FsPersonActivityPattern;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use App\Models\FeatureStore\FsPersonChannelAffinity;
use App\Models\FeatureStore\FsPersonEmailMetrics;
use App\Models\FeatureStore\FsPersonPurchaseWindow;
use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\PersonTagAssignment;
use App\Services\Tracking\PersonProfileService;
use App\Services\Tracking\TxAudienceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TxProfileController extends Controller
{
    /**
     * Get complete profile for a person.
     *
     * GET /api/tx/profile/{personId}
     */
    public function show(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $cached = $request->boolean('cached', true);

        $profile = PersonProfileService::for((int) $tenantId, $personId)
            ->getFullProfile($cached);

        if (isset($profile['error'])) {
            return response()->json($profile, 404);
        }

        return response()->json($profile);
    }

    /**
     * Get summary profile for a person.
     *
     * GET /api/tx/profile/{personId}/summary
     */
    public function summary(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $profile = PersonProfileService::for((int) $tenantId, $personId)
            ->getSummaryProfile();

        if (isset($profile['error'])) {
            return response()->json($profile, 404);
        }

        return response()->json($profile);
    }

    /**
     * Get affinities for a person.
     *
     * GET /api/tx/profile/{personId}/affinities
     */
    public function affinities(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $limit = $request->integer('limit', 20);

        $artists = FsPersonAffinityArtist::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('affinity_score')
            ->limit($limit)
            ->get()
            ->map(fn($a) => [
                'artist_id' => $a->artist_id,
                'score' => round($a->affinity_score, 2),
                'views' => $a->views_count,
                'purchases' => $a->purchases_count,
                'attended' => $a->attendance_count,
                'last_interaction' => $a->last_interaction_at?->toIso8601String(),
            ]);

        $genres = FsPersonAffinityGenre::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('affinity_score')
            ->limit($limit)
            ->get()
            ->map(fn($g) => [
                'genre' => $g->genre,
                'score' => round($g->affinity_score, 2),
                'views' => $g->views_count,
                'purchases' => $g->purchases_count,
            ]);

        return response()->json([
            'person_id' => $personId,
            'artists' => $artists,
            'genres' => $genres,
        ]);
    }

    /**
     * Get tags for a person.
     *
     * GET /api/tx/profile/{personId}/tags
     */
    public function tags(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $tags = PersonTagAssignment::getTagsForPerson((int) $tenantId, $personId);

        return response()->json([
            'person_id' => $personId,
            'tags' => $tags,
            'count' => count($tags),
        ]);
    }

    /**
     * Get temporal patterns for a person.
     *
     * GET /api/tx/profile/{personId}/temporal
     */
    public function temporal(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $activityPattern = FsPersonActivityPattern::getProfile((int) $tenantId, $personId);
        $purchaseWindows = FsPersonPurchaseWindow::getProfile((int) $tenantId, $personId);

        return response()->json([
            'person_id' => $personId,
            'activity_pattern' => $activityPattern,
            'purchase_windows' => $purchaseWindows,
        ]);
    }

    /**
     * Get channel preferences for a person.
     *
     * GET /api/tx/profile/{personId}/channels
     */
    public function channels(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $channels = FsPersonChannelAffinity::getProfile((int) $tenantId, $personId);

        return response()->json(array_merge(
            ['person_id' => $personId],
            $channels
        ));
    }

    /**
     * Get email metrics for a person.
     *
     * GET /api/tx/profile/{personId}/email
     */
    public function email(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $metrics = FsPersonEmailMetrics::getProfile((int) $tenantId, $personId);

        // Also get core customer email data
        $person = CoreCustomer::find($personId);

        $data = [
            'person_id' => $personId,
            'subscribed' => $person?->email_subscribed,
        ];

        if ($metrics) {
            $data = array_merge($data, $metrics);
        } else {
            $data['total_sent'] = $person?->emails_sent;
            $data['total_opened'] = $person?->emails_opened;
            $data['open_rate'] = $person?->email_open_rate;
        }

        return response()->json($data);
    }

    /**
     * Bulk get profiles for multiple persons.
     *
     * POST /api/tx/profile/bulk
     */
    public function bulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer',
            'person_ids' => 'required|array|max:100',
            'person_ids.*' => 'integer',
            'type' => 'in:full,summary',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenantId = $request->integer('tenant_id');
        $personIds = $request->input('person_ids');
        $type = $request->input('type', 'summary');

        $profiles = [];

        foreach ($personIds as $personId) {
            $service = PersonProfileService::for($tenantId, $personId);
            $profiles[$personId] = $type === 'full'
                ? $service->getFullProfile()
                : $service->getSummaryProfile();
        }

        return response()->json([
            'profiles' => $profiles,
            'count' => count($profiles),
        ]);
    }

    /**
     * Find similar persons based on profile.
     *
     * GET /api/tx/profile/{personId}/similar
     */
    public function similar(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $limit = $request->integer('limit', 10);

        // Get person's top affinities
        $topArtists = FsPersonAffinityArtist::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('affinity_score')
            ->limit(3)
            ->pluck('artist_id')
            ->toArray();

        $topGenres = FsPersonAffinityGenre::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('affinity_score')
            ->limit(3)
            ->pluck('genre')
            ->toArray();

        // Find others with similar affinities
        $builder = TxAudienceBuilder::forTenant((int) $tenantId);

        if (!empty($topArtists)) {
            $builder->withArtistAffinity($topArtists, 3.0);
        }

        if (!empty($topGenres)) {
            $builder->withGenreAffinity($topGenres, 3.0);
        }

        $similarPersonIds = $builder->limit($limit + 1)->getPersonIds();

        // Remove the original person
        $similarPersonIds = $similarPersonIds->filter(fn($id) => $id !== $personId)->take($limit);

        $profiles = [];
        foreach ($similarPersonIds as $similarId) {
            $profiles[] = PersonProfileService::for((int) $tenantId, $similarId)->getSummaryProfile();
        }

        return response()->json([
            'person_id' => $personId,
            'similar_persons' => $profiles,
            'count' => count($profiles),
        ]);
    }

    /**
     * Invalidate profile cache.
     *
     * DELETE /api/tx/profile/{personId}/cache
     */
    public function invalidateCache(Request $request, int $personId): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        PersonProfileService::for((int) $tenantId, $personId)->invalidateCache();

        return response()->json(['success' => true, 'message' => 'Cache invalidated']);
    }
}

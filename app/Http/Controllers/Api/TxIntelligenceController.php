<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tracking\RecommendationService;
use App\Services\Tracking\NextBestActionService;
use App\Services\Tracking\WinBackCampaignService;
use App\Services\Tracking\AlertTriggerService;
use App\Services\Tracking\LookalikeAudienceService;
use App\Services\Tracking\DemandForecastingService;
use App\Services\Tracking\CustomerJourneyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TxIntelligenceController extends Controller
{
    /**
     * Get personalized recommendations for a person
     */
    public function recommendations(Request $request, int $tenantId, int $personId): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'days_ahead' => 'nullable|integer|min:1|max:365',
            'cached' => 'nullable|boolean',
        ]);

        $recommendations = RecommendationService::for($tenantId, $personId)
            ->getEventRecommendations(
                $validated['limit'] ?? 10,
                [
                    'days_ahead' => $validated['days_ahead'] ?? 90,
                    'cached' => $validated['cached'] ?? true,
                ]
            );

        return response()->json($recommendations);
    }

    /**
     * Get artist recommendations
     */
    public function artistRecommendations(int $tenantId, int $personId): JsonResponse
    {
        $recommendations = RecommendationService::for($tenantId, $personId)
            ->getArtistRecommendations(10);

        return response()->json($recommendations);
    }

    /**
     * Get cross-sell recommendations for an event
     */
    public function crossSellRecommendations(int $tenantId, int $personId, int $eventId): JsonResponse
    {
        $recommendations = RecommendationService::for($tenantId, $personId)
            ->getCrossSellRecommendations($eventId);

        return response()->json($recommendations);
    }

    /**
     * Get next best action for a person
     */
    public function nextBestAction(int $tenantId, int $personId): JsonResponse
    {
        $nba = NextBestActionService::for($tenantId, $personId)
            ->getNextBestAction();

        return response()->json($nba);
    }

    /**
     * Get action queue for a person
     */
    public function actionQueue(Request $request, int $tenantId, int $personId): JsonResponse
    {
        $limit = $request->input('limit', 5);

        $queue = NextBestActionService::for($tenantId, $personId)
            ->getActionQueue($limit);

        return response()->json($queue);
    }

    /**
     * Get win-back candidates
     */
    public function winBackCandidates(int $tenantId): JsonResponse
    {
        $candidates = WinBackCampaignService::forTenant($tenantId)
            ->identifyWinBackCandidates();

        return response()->json($candidates);
    }

    /**
     * Get win-back summary stats
     */
    public function winBackStats(int $tenantId): JsonResponse
    {
        $stats = WinBackCampaignService::forTenant($tenantId)
            ->getSummaryStats();

        return response()->json($stats);
    }

    /**
     * Mark customers as contacted for win-back
     */
    public function markWinBackContacted(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'person_ids' => 'required|array',
            'person_ids.*' => 'integer',
            'tier' => 'required|string',
            'campaign_id' => 'required|string',
        ]);

        $count = WinBackCampaignService::forTenant($tenantId)
            ->markAsContacted(
                $validated['person_ids'],
                $validated['tier'],
                $validated['campaign_id']
            );

        return response()->json(['marked' => $count]);
    }

    /**
     * Get pending alerts
     */
    public function pendingAlerts(Request $request, int $tenantId): JsonResponse
    {
        $limit = $request->input('limit', 50);

        $alerts = AlertTriggerService::forTenant($tenantId)
            ->getPendingAlerts($limit);

        return response()->json(['alerts' => $alerts]);
    }

    /**
     * Get alert statistics
     */
    public function alertStats(Request $request, int $tenantId): JsonResponse
    {
        $days = $request->input('days', 7);

        $stats = AlertTriggerService::forTenant($tenantId)
            ->getAlertStats($days);

        return response()->json($stats);
    }

    /**
     * Mark alert as handled
     */
    public function handleAlert(Request $request, int $tenantId, string $alertId): JsonResponse
    {
        $action = $request->input('action');

        $success = AlertTriggerService::forTenant($tenantId)
            ->markAsHandled($alertId, $action);

        return response()->json(['success' => $success]);
    }

    /**
     * Find lookalike audience
     */
    public function findLookalikes(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'seed_person_ids' => 'required|array|min:1',
            'seed_person_ids.*' => 'integer',
            'limit' => 'nullable|integer|min:1|max:5000',
            'min_similarity' => 'nullable|numeric|min:0|max:1',
        ]);

        $lookalikes = LookalikeAudienceService::forTenant($tenantId)
            ->findLookalikes(
                $validated['seed_person_ids'],
                $validated['limit'] ?? 1000,
                $validated['min_similarity'] ?? 0.5
            );

        return response()->json($lookalikes);
    }

    /**
     * Find lookalikes for high-value customers
     */
    public function highValueLookalikes(Request $request, int $tenantId): JsonResponse
    {
        $minLtv = $request->input('min_ltv', 500);
        $limit = $request->input('limit', 1000);

        $lookalikes = LookalikeAudienceService::forTenant($tenantId)
            ->createFromHighValueCustomers($minLtv, $limit);

        return response()->json($lookalikes);
    }

    /**
     * Find lookalikes from event purchasers
     */
    public function eventPurchaserLookalikes(Request $request, int $tenantId, int $eventId): JsonResponse
    {
        $limit = $request->input('limit', 1000);

        $lookalikes = LookalikeAudienceService::forTenant($tenantId)
            ->createFromEventPurchasers($eventId, $limit);

        return response()->json($lookalikes);
    }

    /**
     * Find similar persons
     */
    public function similarPersons(int $tenantId, int $personId): JsonResponse
    {
        $similar = LookalikeAudienceService::forTenant($tenantId)
            ->findSimilarPersons($personId, 20);

        return response()->json($similar);
    }

    /**
     * Export lookalike for ad platform
     */
    public function exportLookalikes(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'seed_person_ids' => 'required|array',
            'platform' => 'required|in:facebook,google,tiktok',
            'limit' => 'nullable|integer',
        ]);

        $lookalikes = LookalikeAudienceService::forTenant($tenantId)
            ->findLookalikes($validated['seed_person_ids'], $validated['limit'] ?? 1000);

        $export = LookalikeAudienceService::forTenant($tenantId)
            ->exportForAdPlatform($lookalikes, $validated['platform']);

        return response()->json($export);
    }

    /**
     * Forecast event demand
     */
    public function forecastEvent(int $tenantId, int $eventId): JsonResponse
    {
        $forecast = DemandForecastingService::forTenant($tenantId)
            ->forecastEvent($eventId);

        return response()->json($forecast);
    }

    /**
     * Forecast all upcoming events
     */
    public function forecastAllEvents(Request $request, int $tenantId): JsonResponse
    {
        $daysAhead = $request->input('days_ahead', 90);

        $forecasts = DemandForecastingService::forTenant($tenantId)
            ->forecastAllUpcoming($daysAhead);

        return response()->json($forecasts);
    }

    /**
     * Get pricing recommendations for event
     */
    public function pricingRecommendations(int $tenantId, int $eventId): JsonResponse
    {
        $recommendations = DemandForecastingService::forTenant($tenantId)
            ->getPricingRecommendations($eventId);

        return response()->json($recommendations);
    }

    /**
     * Get customer journey stage
     */
    public function journeyStage(int $tenantId, int $personId): JsonResponse
    {
        $stage = CustomerJourneyService::forTenant($tenantId)
            ->getCurrentStage($personId);

        return response()->json($stage);
    }

    /**
     * Get full customer journey
     */
    public function fullJourney(int $tenantId, int $personId): JsonResponse
    {
        $journey = CustomerJourneyService::forTenant($tenantId)
            ->getFullJourney($personId);

        return response()->json($journey);
    }

    /**
     * Get journey analytics
     */
    public function journeyAnalytics(int $tenantId): JsonResponse
    {
        $analytics = CustomerJourneyService::forTenant($tenantId)
            ->getJourneyAnalytics();

        return response()->json($analytics);
    }

    /**
     * Get customers stuck at a stage
     */
    public function stuckCustomers(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'stage' => 'required|string',
            'min_days' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $customers = CustomerJourneyService::forTenant($tenantId)
            ->getStuckCustomers(
                $validated['stage'],
                $validated['min_days'] ?? 30,
                $validated['limit'] ?? 100
            );

        return response()->json(['customers' => $customers]);
    }

    /**
     * Record journey transition
     */
    public function recordTransition(Request $request, int $tenantId, int $personId): JsonResponse
    {
        $validated = $request->validate([
            'from_stage' => 'required|string',
            'to_stage' => 'required|string',
            'trigger' => 'nullable|string',
        ]);

        CustomerJourneyService::forTenant($tenantId)
            ->recordTransition(
                $personId,
                $validated['from_stage'],
                $validated['to_stage'],
                $validated['trigger'] ?? null
            );

        return response()->json(['success' => true]);
    }

    /**
     * Invalidate caches
     */
    public function invalidateCaches(int $tenantId, int $personId): JsonResponse
    {
        RecommendationService::for($tenantId, $personId)->invalidateCache();
        NextBestActionService::for($tenantId, $personId)->invalidateCache();
        CustomerJourneyService::forTenant($tenantId)->invalidateCache($personId);

        return response()->json(['success' => true]);
    }
}

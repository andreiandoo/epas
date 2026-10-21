<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Services\Whitelabel\WhitelabelEventFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Event sync for an organizer's own site, read only.
 *
 * Works only with the organizer's whitelabel key (wlk_...), and only returns
 * that organizer's events. The site is told by a signed POST when an event
 * changes (NotifyWhitelabelSiteJob) and comes here to read it.
 */
class WhitelabelSyncController extends BaseController
{
    /** GET whitelabel/sync/events: all upcoming events of the organizer. */
    public function index(Request $request): JsonResponse
    {
        $organizerId = $this->organizerId($request);
        if (!$organizerId) {
            return $this->error('This endpoint needs an organizer whitelabel key', 403);
        }

        $events = (new WhitelabelEventFeed($this->requireClient($request)))->upcoming($organizerId);

        return $this->success([
            'events' => $events,
            'complete' => count($events) < WhitelabelEventFeed::MAX_EVENTS,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /** GET whitelabel/sync/events/{id}: one event, or its status if it's gone. */
    public function show(Request $request, int $id): JsonResponse
    {
        $organizerId = $this->organizerId($request);
        if (!$organizerId) {
            return $this->error('This endpoint needs an organizer whitelabel key', 403);
        }

        return $this->success([
            'event' => (new WhitelabelEventFeed($this->requireClient($request)))->single($organizerId, $id),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    protected function organizerId(Request $request): ?int
    {
        $id = $request->attributes->get('whitelabel_organizer_id');

        return $id ? (int) $id : null;
    }
}

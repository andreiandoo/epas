<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public share-link data endpoint. No auth — the URL fragment (code)
 * IS the access token. Password gate enforced for protected links.
 *
 * GET  /v1/marketplace-client/share/{code}/data
 * POST /v1/marketplace-client/share/{code}/data  body: {password: '...'}
 */
class PublicShareLinkController extends BaseController
{
    public function data(Request $request, string $code): JsonResponse
    {
        if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $code)) {
            return $this->error('Invalid code', 400);
        }

        // Per-IP rate limit: 30 requests / 60s.
        $ip = $request->ip() ?: 'unknown';
        $bucket = 'share_link_data:' . md5($ip);
        $count = (int) Cache::increment($bucket);
        if ($count === 1) {
            Cache::put($bucket, 1, 60);
        }
        if ($count > 30) {
            return $this->error('Too many requests. Please try again later.', 429);
        }

        $link = MarketplaceShareLink::where('code', $code)->first();
        if (!$link) return $this->error('Link not found', 404);

        if (!$link->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'inactive',
                'message' => 'This link is no longer active',
            ], 410);
        }

        // Password gate
        if ($link->has_password) {
            // Brute-force protection — 5 failed attempts per 5 min lock for 10 min
            $bfKey = 'share_link_bf:' . $code;
            $attempts = (int) Cache::get($bfKey, 0);
            if ($attempts >= 5) {
                return response()->json([
                    'success' => false,
                    'error' => 'too_many_attempts',
                    'message' => 'Prea multe incercari. Incearca din nou mai tarziu.',
                ], 429);
            }

            $provided = $request->input('password', '');
            if (!$provided) {
                return response()->json([
                    'success' => false,
                    'error' => 'password_required',
                    'message' => 'Acest link necesita o parola',
                ], 401);
            }

            if (!password_verify($provided, $link->password_hash)) {
                Cache::put($bfKey, $attempts + 1, 600);
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_password',
                    'message' => 'Parola introdusa este incorecta',
                ], 403);
            }

            Cache::forget($bfKey);
        }

        // Bump access counters (skip on JS auto-refresh — same header
        // the proxy.php used historically, kept for client compat).
        $isAutoRefresh = $request->header('X-Auto-Refresh') === '1';
        if (!$isAutoRefresh) {
            $link->increment('access_count');
            $link->last_accessed_at = now();
            $link->saveQuietly();
        }

        // Build event payload by merging fresh ticket_data with current
        // event metadata (title, venue, date). The snapshot on the link
        // is refreshed each call (min 5s interval to absorb bursts) so
        // the public view never serves stale sold/total/revenue numbers.
        $eventIds = $link->event_ids ?? [];
        $cachedTicketData = $link->ticket_data ?? [];

        $staleAfterSec = 5;
        $updatedAt = $link->ticket_data_updated_at;
        $isStale = !$updatedAt || $updatedAt->lt(now()->subSeconds($staleAfterSec));
        if ($isStale || empty($cachedTicketData)) {
            $cachedTicketData = MarketplaceShareLink::computeFreshTicketStats(
                $eventIds,
                $link->marketplace_organizer_id,
            );
            $link->ticket_data = $cachedTicketData;
            $link->ticket_data_updated_at = now();
            $link->saveQuietly();
        }

        $events = Event::whereIn('id', $eventIds)
            ->with(['venue'])
            ->get()
            ->map(function ($event) use ($cachedTicketData) {
                $cached = $cachedTicketData[(string) $event->id] ?? [];
                $title = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: 'Eveniment')
                    : ($event->title ?? 'Eveniment');
                return [
                    'id' => $event->id,
                    'title' => $title,
                    'slug' => $event->slug,
                    'start_date' => $event->event_date?->toIso8601String() ?? $event->range_start_date?->toIso8601String(),
                    'start_time' => $event->start_time,
                    'venue_name' => $event->venue?->getTranslation('name', 'ro')
                        ?? $event->venue?->getTranslation('name', 'en'),
                    'city' => $event->venue?->city,
                    'tickets_sold' => $cached['sold'] ?? 0,
                    'tickets_total' => $cached['total'] ?? 0,
                    'revenue_net' => $cached['revenue_net'] ?? 0,
                    'currency' => $cached['currency'] ?? 'RON',
                    'ticket_types' => $cached['ticket_types'] ?? [],
                ];
            })
            ->values()
            ->all();

        $showRevenue = (bool) $link->show_revenue;
        if (!$showRevenue) {
            $events = array_map(function ($ev) {
                unset($ev['revenue_net']);
                return $ev;
            }, $events);
        }

        $payload = [
            'events' => $events,
            'show_participants' => (bool) $link->show_participants,
            'show_revenue' => $showRevenue,
            'updated_at' => $link->ticket_data_updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];

        if ($link->show_participants && !empty($link->participants_data)) {
            $payload['participants'] = $link->participants_data;
        }

        return $this->success($payload);
    }
}

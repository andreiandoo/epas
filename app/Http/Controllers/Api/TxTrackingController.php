<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTxEvents;
use App\Models\Tracking\TxEvent;
use App\Models\Tracking\TxIdentityLink;
use App\Models\Platform\CoreSession;
use App\Models\Tenant;
use App\Services\Tracking\SchemaValidator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TxTrackingController extends Controller
{
    protected SchemaValidator $schemaValidator;

    public function __construct(SchemaValidator $schemaValidator)
    {
        $this->schemaValidator = $schemaValidator;
    }

    /**
     * Receive a single tracking event.
     * POST /api/tx/events
     */
    public function trackEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|uuid',
            'event_name' => 'required|string|max:100',
            'event_version' => 'integer|min:1',
            'occurred_at' => 'required|date',
            'tenant_id' => 'required',
            'source_system' => 'required|in:web,mobile,scanner,backend,payments,shop,wallet',
            'visitor_id' => 'required_if:source_system,web,mobile|string|max:64',
            'session_id' => 'required_if:source_system,web,mobile|string|max:64',
            'consent_snapshot' => 'required|array',
            'context' => 'array',
            'entities' => 'array',
            'payload' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            $eventData = $request->all();

            // Validate against schema
            $validation = $this->schemaValidator->validate($eventData);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'errors' => $validation['errors'],
                ], 422);
            }

            // Resolve tenant
            $tenant = $this->resolveTenant($eventData['tenant_id']);
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unknown tenant',
                ], 400);
            }
            $eventData['tenant_id'] = $tenant->id;

            // Check idempotency
            if (!empty($eventData['idempotency_key'])) {
                $existing = TxEvent::findByIdempotencyKey($eventData['idempotency_key']);
                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'event_id' => $existing->event_id,
                        'duplicate' => true,
                    ]);
                }
            }

            // Add server-side metadata
            $eventData['received_at'] = now()->toIso8601String();

            // Try to resolve person_id if visitor has been stitched
            if (!empty($eventData['visitor_id']) && empty($eventData['person_id'])) {
                $personId = TxIdentityLink::findPersonId($tenant->id, $eventData['visitor_id']);
                if ($personId) {
                    $eventData['person_id'] = $personId;
                }
            }

            // Queue for async processing or insert directly
            if ($this->shouldQueueEvent($eventData)) {
                $this->queueEvent($eventData);
            } else {
                TxEvent::createFromEnvelope($eventData);
            }

            // Update session stats
            $this->updateSessionStats($eventData);

            return response()->json([
                'success' => true,
                'event_id' => $eventData['event_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('TxTracking error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'event_name' => $request->input('event_name'),
                'tenant_id' => $request->input('tenant_id'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process event',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Receive batch of tracking events.
     * POST /api/tx/events/batch
     */
    public function trackEventsBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'events' => 'required|array|min:1|max:100',
            'events.*.event_id' => 'required|uuid',
            'events.*.event_name' => 'required|string|max:100',
            'events.*.occurred_at' => 'required|date',
            'events.*.tenant_id' => 'required',
            'events.*.source_system' => 'required|in:web,mobile,scanner,backend,payments,shop,wallet',
            'events.*.consent_snapshot' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $results = [
            'processed' => 0,
            'duplicates' => 0,
            'errors' => [],
        ];

        $events = $request->input('events', []);
        $tenantCache = [];
        $personCache = [];

        foreach ($events as $index => $eventData) {
            try {
                // Validate against schema
                $validation = $this->schemaValidator->validate($eventData);
                if (!$validation['valid']) {
                    $results['errors'][] = [
                        'index' => $index,
                        'event_id' => $eventData['event_id'] ?? null,
                        'errors' => $validation['errors'],
                    ];
                    continue;
                }

                // Resolve tenant (cached)
                $tenantKey = $eventData['tenant_id'];
                if (!isset($tenantCache[$tenantKey])) {
                    $tenantCache[$tenantKey] = $this->resolveTenant($tenantKey);
                }
                $tenant = $tenantCache[$tenantKey];

                if (!$tenant) {
                    $results['errors'][] = [
                        'index' => $index,
                        'event_id' => $eventData['event_id'] ?? null,
                        'errors' => ['Unknown tenant'],
                    ];
                    continue;
                }
                $eventData['tenant_id'] = $tenant->id;

                // Check idempotency
                if (!empty($eventData['idempotency_key'])) {
                    $existing = TxEvent::findByIdempotencyKey($eventData['idempotency_key']);
                    if ($existing) {
                        $results['duplicates']++;
                        continue;
                    }
                }

                // Add server-side metadata
                $eventData['received_at'] = now()->toIso8601String();

                // Resolve person_id (cached per visitor)
                if (!empty($eventData['visitor_id']) && empty($eventData['person_id'])) {
                    $visitorKey = $tenant->id . ':' . $eventData['visitor_id'];
                    if (!isset($personCache[$visitorKey])) {
                        $personCache[$visitorKey] = TxIdentityLink::findPersonId(
                            $tenant->id,
                            $eventData['visitor_id']
                        );
                    }
                    if ($personCache[$visitorKey]) {
                        $eventData['person_id'] = $personCache[$visitorKey];
                    }
                }

                // Queue for async processing
                $this->queueEvent($eventData);
                $results['processed']++;

            } catch (\Exception $e) {
                Log::error('TxTracking batch item error', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'event_name' => $eventData['event_name'] ?? null,
                ]);

                $results['errors'][] = [
                    'index' => $index,
                    'event_id' => $eventData['event_id'] ?? null,
                    'errors' => ['Processing failed: ' . $e->getMessage()],
                ];
            }
        }

        // Dispatch job to process queued events
        if ($results['processed'] > 0) {
            ProcessTxEvents::dispatch()->onQueue('tracking');
        }

        return response()->json([
            'success' => empty($results['errors']),
            'data' => $results,
        ]);
    }

    /**
     * Get session information.
     * GET /api/tx/session/{sessionId}
     */
    public function getSession(Request $request, string $sessionId): JsonResponse
    {
        $tenantId = $request->query('tenant_id');

        try {
            $session = CoreSession::where('session_id', $sessionId)
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $session->session_id,
                    'visitor_id' => $session->visitor_id,
                    'started_at' => $session->started_at?->toIso8601String(),
                    'pageviews' => $session->pageviews,
                    'events_count' => $session->tx_events_count ?? $session->events,
                    'engagement_active_ms' => $session->engagement_active_ms,
                    'is_active' => $session->isActive(),
                    'first_touch' => $session->first_touch,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve session',
            ], 500);
        }
    }

    /**
     * Get events for a visitor.
     * GET /api/tx/visitor/{visitorId}/events
     */
    public function getVisitorEvents(Request $request, string $visitorId): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $limit = min((int) $request->query('limit', 50), 100);
        $eventTypes = $request->query('event_types') ? explode(',', $request->query('event_types')) : null;

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_id is required',
            ], 400);
        }

        try {
            $query = TxEvent::forTenant((int) $tenantId)
                ->forVisitor($visitorId)
                ->when($eventTypes, fn($q) => $q->ofTypes($eventTypes))
                ->orderByDesc('occurred_at')
                ->limit($limit);

            $events = $query->get()->map(fn($e) => [
                'event_id' => $e->event_id,
                'event_name' => $e->event_name,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'session_id' => $e->session_id,
                'entities' => $e->entities,
                'payload' => $e->payload,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'visitor_id' => $visitorId,
                    'events' => $events,
                    'count' => $events->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve events',
            ], 500);
        }
    }

    /**
     * Health check endpoint.
     * GET /api/tx/health
     */
    public function health(): JsonResponse
    {
        try {
            // Check database
            DB::select('SELECT 1');

            // Check Redis if available
            $redisOk = true;
            try {
                Redis::ping();
            } catch (\Exception $e) {
                $redisOk = false;
            }

            return response()->json([
                'success' => true,
                'status' => 'healthy',
                'checks' => [
                    'database' => true,
                    'redis' => $redisOk,
                    'schema_loaded' => count($this->schemaValidator->getEventNames()) > 0,
                ],
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Resolve tenant by ID or slug.
     */
    protected function resolveTenant($tenantId): ?Tenant
    {
        if (is_numeric($tenantId)) {
            return Tenant::find((int) $tenantId);
        }

        return Tenant::where('slug', $tenantId)->first();
    }

    /**
     * Determine if event should be queued for async processing.
     */
    protected function shouldQueueEvent(array $eventData): bool
    {
        // Always queue high-volume events
        $highVolumeEvents = ['page_view', 'page_engagement', 'event_list_impression'];

        return in_array($eventData['event_name'], $highVolumeEvents);
    }

    /**
     * Queue event for async processing.
     */
    protected function queueEvent(array $eventData): void
    {
        try {
            Redis::rpush('tx_events_queue', json_encode($eventData));
        } catch (\Exception $e) {
            // Fallback to direct insert if Redis fails
            Log::warning('Redis queue failed, inserting directly', ['error' => $e->getMessage()]);
            TxEvent::createFromEnvelope($eventData);
        }
    }

    /**
     * Update session statistics.
     */
    protected function updateSessionStats(array $eventData): void
    {
        if (empty($eventData['session_id']) || empty($eventData['tenant_id'])) {
            return;
        }

        try {
            $session = CoreSession::where('tenant_id', $eventData['tenant_id'])
                ->where('session_id', $eventData['session_id'])
                ->first();

            if ($session) {
                $updates = ['tx_events_count' => DB::raw('COALESCE(tx_events_count, 0) + 1')];

                // Update engagement metrics if available
                if (isset($eventData['payload']['active_ms'])) {
                    $updates['engagement_active_ms'] = DB::raw(
                        'COALESCE(engagement_active_ms, 0) + ' . (int) $eventData['payload']['active_ms']
                    );
                }
                if (isset($eventData['payload']['total_ms'])) {
                    $updates['engagement_total_ms'] = DB::raw(
                        'COALESCE(engagement_total_ms, 0) + ' . (int) $eventData['payload']['total_ms']
                    );
                }
                if (isset($eventData['payload']['scroll_max_pct'])) {
                    $updates['max_scroll_pct'] = DB::raw(
                        'GREATEST(COALESCE(max_scroll_pct, 0), ' . (int) $eventData['payload']['scroll_max_pct'] . ')'
                    );
                }

                // Store first touch if not set
                if (!$session->first_touch && isset($eventData['context'])) {
                    $updates['first_touch'] = json_encode([
                        'utm' => $eventData['context']['utm'] ?? null,
                        'click_ids' => $eventData['context']['click_ids'] ?? null,
                        'referrer' => $eventData['context']['referrer'] ?? null,
                        'landing_page' => $eventData['context']['page']['path'] ?? null,
                    ]);
                }

                // Store initial consent if not set
                if (!$session->consent_snapshot_initial && isset($eventData['consent_snapshot'])) {
                    $updates['consent_snapshot_initial'] = json_encode($eventData['consent_snapshot']);
                }

                // Always update final consent
                if (isset($eventData['consent_snapshot'])) {
                    $updates['consent_snapshot_final'] = json_encode($eventData['consent_snapshot']);
                }

                $session->update($updates);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update session stats', [
                'session_id' => $eventData['session_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}

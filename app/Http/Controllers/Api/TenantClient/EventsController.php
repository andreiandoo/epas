<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    /**
     * List public events for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // In a real implementation, this would query the Event model
        // For now, return a structured response
        $events = []; // Event::where('tenant_id', $tenant->id)->published()->get()

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events,
                'meta' => [
                    'total' => count($events),
                    'page' => $request->input('page', 1),
                    'per_page' => $request->input('per_page', 12),
                ],
            ],
        ]);
    }

    /**
     * Get single event details
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Event::where('tenant_id', $tenant->id)->where('slug', $slug)->firstOrFail()
        $event = null;

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * Get event ticket types
     */
    public function tickets(Request $request, string $slug): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get ticket types for the event
        $tickets = [];

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Get event seating layout (if applicable)
     */
    public function seating(Request $request, string $slug): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get seating layout
        $seating = null;

        return response()->json([
            'success' => true,
            'data' => $seating,
        ]);
    }
}

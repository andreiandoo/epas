<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get admin dashboard stats
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_events' => 0,
                    'active_events' => 0,
                    'total_orders' => 0,
                    'total_revenue' => 0,
                    'tickets_sold' => 0,
                    'customers' => 0,
                ],
                'recent_orders' => [],
                'upcoming_events' => [],
            ],
        ]);
    }

    /**
     * List events (admin view)
     */
    public function events(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'events' => [],
                'meta' => [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => 20,
                ],
            ],
        ]);
    }

    /**
     * Create event
     */
    public function createEvent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:draft,published,cancelled',
        ]);

        // Create event

        return response()->json([
            'success' => true,
            'message' => 'Event created',
            'data' => [
                'id' => 1,
                'slug' => 'new-event',
            ],
        ]);
    }

    /**
     * Update event
     */
    public function updateEvent(Request $request, int $eventId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'sometimes|in:draft,published,cancelled',
        ]);

        // Update event

        return response()->json([
            'success' => true,
            'message' => 'Event updated',
        ]);
    }

    /**
     * List orders
     */
    public function orders(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [],
                'meta' => [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => 20,
                ],
            ],
        ]);
    }

    /**
     * Get order details
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * List customers
     */
    public function customers(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => [],
                'meta' => [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => 20,
                ],
            ],
        ]);
    }

    /**
     * Get settings
     */
    public function settings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'general' => [
                    'name' => $tenant->name,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                ],
                'branding' => $tenant->settings['branding'] ?? [],
                'theme' => $tenant->settings['theme'] ?? [],
                'payments' => $this->getPaymentSettings($tenant),
                'notifications' => $tenant->settings['notifications'] ?? [],
            ],
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'general' => 'sometimes|array',
            'branding' => 'sometimes|array',
            'theme' => 'sometimes|array',
            'notifications' => 'sometimes|array',
        ]);

        // Update tenant settings

        return response()->json([
            'success' => true,
            'message' => 'Settings updated',
        ]);
    }

    protected function getPaymentSettings($tenant): array
    {
        $settings = $tenant->settings['payments'] ?? [];

        // Remove sensitive data
        return [
            'stripe' => [
                'enabled' => !empty($settings['stripe']['enabled']),
            ],
            'netopia' => [
                'enabled' => !empty($settings['netopia']['enabled']),
            ],
            'euplatesc' => [
                'enabled' => !empty($settings['euplatesc']['enabled']),
            ],
            'payu' => [
                'enabled' => !empty($settings['payu']['enabled']),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationsController extends BaseController
{
    /**
     * Get customer's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $query = DB::table('marketplace_customer_notifications')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('created_at');

        // Filter by read status
        if ($request->has('unread')) {
            $query->whereNull('read_at');
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $perPage = min((int) $request->get('per_page', 20), 50);
        $notifications = $query->paginate($perPage);

        $formatted = collect($notifications->items())->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'icon' => $notification->icon,
                'action_url' => $notification->action_url,
                'action_text' => $notification->action_text,
                'data' => json_decode($notification->data, true),
                'read' => $notification->read_at !== null,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        });

        // Get unread count
        $unreadCount = DB::table('marketplace_customer_notifications')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get unread count only
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $count = DB::table('marketplace_customer_notifications')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->whereNull('read_at')
            ->count();

        return $this->success([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark notifications as read
     */
    public function markRead(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'notification_ids' => 'sometimes|array',
            'notification_ids.*' => 'integer',
            'all' => 'sometimes|boolean',
        ]);

        $query = DB::table('marketplace_customer_notifications')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->whereNull('read_at');

        if (!empty($validated['all'])) {
            // Mark all as read
            $updated = $query->update(['read_at' => now()]);
        } elseif (!empty($validated['notification_ids'])) {
            // Mark specific notifications as read
            $updated = $query->whereIn('id', $validated['notification_ids'])
                ->update(['read_at' => now()]);
        } else {
            return $this->error('Please specify notification_ids or set all=true', 400);
        }

        return $this->success([
            'marked_count' => $updated,
        ], 'Notifications marked as read');
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, int $notificationId): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $deleted = DB::table('marketplace_customer_notifications')
            ->where('id', $notificationId)
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->delete();

        if (!$deleted) {
            return $this->error('Notification not found', 404);
        }

        return $this->success(null, 'Notification deleted');
    }

    /**
     * Get notification settings
     */
    public function settings(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $settings = $customer->settings['notification_preferences'] ?? [
            'reminders' => true,
            'newsletter' => true,
            'favorites' => true,
            'history' => true,
            'marketing' => $customer->accepts_marketing,
        ];

        return $this->success([
            'settings' => $settings,
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'reminders' => 'sometimes|boolean',
            'newsletter' => 'sometimes|boolean',
            'favorites' => 'sometimes|boolean',
            'history' => 'sometimes|boolean',
            'marketing' => 'sometimes|boolean',
        ]);

        $currentSettings = $customer->settings ?? [];
        $currentNotifications = $currentSettings['notification_preferences'] ?? [];

        $newNotifications = array_merge($currentNotifications, $validated);
        $currentSettings['notification_preferences'] = $newNotifications;

        $customer->settings = $currentSettings;

        // Sync marketing preference
        if (isset($validated['marketing'])) {
            $customer->accepts_marketing = $validated['marketing'];
            $customer->marketing_consent_at = $validated['marketing'] ? now() : null;
        }

        $customer->save();

        return $this->success([
            'settings' => $newNotifications,
        ], 'Notification settings updated');
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get notifications for a tenant
     */
    public function index(Request $request, string $tenantId): JsonResponse
    {
        $query = DB::table('tenant_notifications')
            ->where('tenant_id', $tenantId);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $notifications = $query
            ->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 50))
            ->get();

        $unreadCount = DB::table('tenant_notifications')
            ->where('tenant_id', $tenantId)
            ->where('status', 'unread')
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $tenantId, int $notificationId): JsonResponse
    {
        $updated = DB::table('tenant_notifications')
            ->where('id', $notificationId)
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => (bool) $updated,
            'message' => $updated ? 'Notification marked as read' : 'Notification not found',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(string $tenantId): JsonResponse
    {
        $updated = DB::table('tenant_notifications')
            ->where('tenant_id', $tenantId)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notifications marked as read",
            'count' => $updated,
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(string $tenantId, int $notificationId): JsonResponse
    {
        $deleted = DB::table('tenant_notifications')
            ->where('id', $notificationId)
            ->where('tenant_id', $tenantId)
            ->delete();

        return response()->json([
            'success' => (bool) $deleted,
            'message' => $deleted ? 'Notification deleted' : 'Notification not found',
        ]);
    }
}

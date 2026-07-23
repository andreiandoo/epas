<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceNotification;
use App\Models\MarketplaceOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * Emergency report from a mobile-app operator — creates a notification
     * on the current organizer so ALL admin/owner staff on that organizer
     * can see it in their notifications panel. Reporter's name is embedded
     * in the payload so the recipients know who raised the alert.
     *
     * Body: { type: string, title: string, message: string, severity?: string }
     */
    public function reportEmergency(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'type' => 'required|string|max:64',
            'title' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
            'severity' => 'nullable|string|in:high,medium,info',
        ]);

        // Reporter identity — team member if the token belongs to one,
        // otherwise fall back to the organizer's own account label.
        $reporterName = null;
        $reporterRole = null;
        $tokenable = $request->user();
        if ($tokenable && method_exists($tokenable, 'currentAccessToken')) {
            $token = $tokenable->currentAccessToken();
            if ($token && $token->name) {
                $reporterName = $token->name;
            }
        }
        if ($tokenable instanceof \App\Models\MarketplaceOrganizerTeamMember) {
            $reporterName = $tokenable->name;
            $reporterRole = $tokenable->role;
        } elseif ($tokenable instanceof MarketplaceOrganizer) {
            $reporterName = $tokenable->name ?? $tokenable->email;
            $reporterRole = 'owner';
        }

        $severity = $validated['severity'] ?? 'high';
        $iconMap = [
            'high' => 'alert-triangle',
            'medium' => 'alert-circle',
            'info' => 'info',
        ];
        $colorMap = [
            'high' => 'red',
            'medium' => 'amber',
            'info' => 'blue',
        ];

        $note = MarketplaceNotification::create([
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'type' => 'emergency_report',
            'title' => $validated['title'],
            'message' => $validated['message'] ?? null,
            'icon' => $iconMap[$severity] ?? 'alert-triangle',
            'color' => $colorMap[$severity] ?? 'red',
            'data' => [
                'emergency_type' => $validated['type'],
                'severity' => $severity,
                'reporter' => $reporterName,
                'reporter_role' => $reporterRole,
                'reported_at' => now()->toIso8601String(),
            ],
        ]);

        return $this->success([
            'notification' => $this->formatNotification($note),
        ], 'Raport trimis către administratori');
    }

    /**
     * Get paginated notifications for organizer
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = MarketplaceNotification::forOrganizer($organizer->id)
            ->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->has('read')) {
            if ($request->read === '1' || $request->read === 'true') {
                $query->read();
            } else {
                $query->unread();
            }
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->ofType($request->type);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $notifications = $query->paginate($perPage);

        return $this->paginated($notifications, function ($notification) {
            return $this->formatNotification($notification);
        });
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $count = MarketplaceNotification::forOrganizer($organizer->id)
            ->unread()
            ->count();

        return $this->success(['unread_count' => $count]);
    }

    /**
     * Get recent notifications (for dropdown)
     */
    public function recent(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $limit = min((int) $request->input('limit', 10), 20);

        $notifications = MarketplaceNotification::forOrganizer($organizer->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => $this->formatNotification($n));

        $unreadCount = MarketplaceNotification::forOrganizer($organizer->id)
            ->unread()
            ->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $notification = MarketplaceNotification::forOrganizer($organizer->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->error('Notificare negasita', 404);
        }

        $notification->markAsRead();

        return $this->success(null, 'Notificare marcata ca citita');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        MarketplaceNotification::forOrganizer($organizer->id)
            ->unread()
            ->update(['read_at' => now()]);

        return $this->success(null, 'Toate notificarile au fost marcate ca citite');
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $notification = MarketplaceNotification::forOrganizer($organizer->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->error('Notificare negasita', 404);
        }

        $notification->delete();

        return $this->success(null, 'Notificare stearsa');
    }

    /**
     * Delete all read notifications
     */
    public function clearRead(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $deleted = MarketplaceNotification::forOrganizer($organizer->id)
            ->read()
            ->delete();

        return $this->success(['deleted' => $deleted], 'Notificarile citite au fost sterse');
    }

    /**
     * Get notification types available
     */
    public function types(Request $request): JsonResponse
    {
        // Organizer-relevant types
        $types = [
            MarketplaceNotification::TYPE_TICKET_SALE => 'Vanzari bilete',
            MarketplaceNotification::TYPE_REFUND_REQUEST => 'Cereri rambursare',
            MarketplaceNotification::TYPE_DOCUMENT_GENERATED => 'Documente generate',
            MarketplaceNotification::TYPE_SERVICE_ORDER => 'Comenzi servicii',
            MarketplaceNotification::TYPE_SERVICE_ORDER_COMPLETED => 'Servicii finalizate',
            MarketplaceNotification::TYPE_SERVICE_ORDER_INVOICE => 'Facturi servicii',
            MarketplaceNotification::TYPE_SERVICE_ORDER_RESULTS => 'Rezultate servicii',
            MarketplaceNotification::TYPE_SERVICE_ORDER_STARTED => 'Servicii pornite',
            MarketplaceNotification::TYPE_PAYOUT_REQUEST => 'Cereri plată',
            MarketplaceNotification::TYPE_PAYOUT_APPROVED => 'Plăți aprobate',
            MarketplaceNotification::TYPE_PAYOUT_PROCESSING => 'Plăți în procesare',
            MarketplaceNotification::TYPE_PAYOUT_COMPLETED => 'Plăți finalizate',
            MarketplaceNotification::TYPE_PAYOUT_REJECTED => 'Plăți respinse',
        ];

        return $this->success(['types' => $types]);
    }

    /**
     * Format notification for response
     */
    protected function formatNotification(MarketplaceNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'type_label' => $notification->type_label,
            'title' => $notification->title,
            'message' => $notification->message,
            'icon' => $notification->icon ?? $notification->default_icon,
            'color' => $notification->color ?? $notification->default_color,
            'action_url' => $notification->action_url,
            'data' => $notification->data,
            'is_read' => $notification->isRead(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'time_ago' => $notification->time_ago,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}

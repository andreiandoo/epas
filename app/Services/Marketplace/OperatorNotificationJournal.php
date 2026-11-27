<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Jurnalul zilnic al operatorului din panoul /marketplace.
 *
 * - arată doar ultimele 3 zile;
 * - fiecare rol vede doar tipurile lui;
 * - doar tipurile care cer acțiune au stare „necitit”, iar starea e per operator
 *   (nu mai atinge read_at, care e al organizatorului).
 */
class OperatorNotificationJournal
{
    public const RETENTION_DAYS = 3;

    // Tipuri care cer ca operatorul să le vadă/acționeze.
    public const ACTION_TYPES = [
        MarketplaceNotification::TYPE_REFUND_REQUEST,
        MarketplaceNotification::TYPE_PAYOUT_REQUEST,
        MarketplaceNotification::TYPE_DOCUMENT_ACTION,
        MarketplaceNotification::TYPE_COMPANY_INVOICE_REQUEST,
    ];

    private const MODERATOR_TYPES = [
        MarketplaceNotification::TYPE_EVENT_CREATED,
        MarketplaceNotification::TYPE_EVENT_UPDATED,
        MarketplaceNotification::TYPE_TICKET_SALE,
        MarketplaceNotification::TYPE_ARTIST_CREATED,
        MarketplaceNotification::TYPE_VENUE_CREATED,
        MarketplaceNotification::TYPE_SEATING_LAYOUT_CREATED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_COMPLETED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_RESULTS,
        MarketplaceNotification::TYPE_SERVICE_ORDER_STARTED,
    ];

    private const ADMIN_TYPES = [
        MarketplaceNotification::TYPE_EVENT_CREATED,
        MarketplaceNotification::TYPE_EVENT_UPDATED,
        MarketplaceNotification::TYPE_TICKET_SALE,
        MarketplaceNotification::TYPE_REFUND_REQUEST,
        MarketplaceNotification::TYPE_ORGANIZER_REGISTRATION,
        MarketplaceNotification::TYPE_PAYOUT_REQUEST,
        MarketplaceNotification::TYPE_PAYOUT_APPROVED,
        MarketplaceNotification::TYPE_PAYOUT_PROCESSING,
        MarketplaceNotification::TYPE_PAYOUT_COMPLETED,
        MarketplaceNotification::TYPE_PAYOUT_REJECTED,
        MarketplaceNotification::TYPE_DOCUMENT_GENERATED,
        MarketplaceNotification::TYPE_DOCUMENT_ACTION,
        MarketplaceNotification::TYPE_CUSTOMER_REGISTRATION,
        MarketplaceNotification::TYPE_SERVICE_ORDER,
        MarketplaceNotification::TYPE_ARTIST_CREATED,
        MarketplaceNotification::TYPE_VENUE_CREATED,
        MarketplaceNotification::TYPE_SEATING_LAYOUT_CREATED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_COMPLETED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_INVOICE,
        MarketplaceNotification::TYPE_SERVICE_ORDER_RESULTS,
        MarketplaceNotification::TYPE_SERVICE_ORDER_STARTED,
        MarketplaceNotification::TYPE_COMPANY_INVOICE_REQUEST,
    ];

    public function admin(): ?MarketplaceAdmin
    {
        // Garda direct întâi: jurnalul e cerut și din endpoint-ul barei, în afara unei pagini Filament.
        $user = auth('marketplace_admin')->user() ?? filament()->auth()->user();

        return $user instanceof MarketplaceAdmin ? $user : null;
    }

    public function clientId(): ?int
    {
        $admin = $this->admin();
        if ($admin && $admin->marketplace_client_id) {
            return (int) $admin->marketplace_client_id;
        }

        return session('super_admin_marketplace_client_id') ? (int) session('super_admin_marketplace_client_id') : null;
    }

    public function isSuperAdmin(?MarketplaceAdmin $admin): bool
    {
        return session('marketplace_is_super_admin') || ($admin && $admin->isSuperAdmin());
    }

    public function visibleTypes(?MarketplaceAdmin $admin): array
    {
        if ($this->isSuperAdmin($admin)) {
            return array_values(array_unique(array_merge(self::ADMIN_TYPES, [MarketplaceNotification::TYPE_ADMIN_USER_ADDED])));
        }

        return match ($admin?->role) {
            'admin' => self::ADMIN_TYPES,
            'moderator' => self::MODERATOR_TYPES,
            default => [],
        };
    }

    public static function isActionType(?string $type): bool
    {
        return in_array($type, self::ACTION_TYPES, true);
    }

    /**
     * Notificările din jurnal pentru operatorul curent, cu atributul read_by_me.
     */
    public function query(): Builder
    {
        $admin = $this->admin();
        $adminId = $admin?->id ?? 0;

        return MarketplaceNotification::query()
            ->where('marketplace_client_id', $this->clientId() ?? 0)
            ->whereIn('type', $this->visibleTypes($admin) ?: ['__none__'])
            ->where('created_at', '>=', now()->subDays(self::RETENTION_DAYS))
            ->withExists(['operatorReads as read_by_me' => fn ($q) => $q->where('marketplace_admin_id', $adminId)]);
    }

    /**
     * Ultimele intrări pentru sertarul barei de atenție.
     */
    public function forBar(int $limit = 40): array
    {
        $labels = MarketplaceNotification::getTypeLabels();

        return $this->query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (MarketplaceNotification $n) use ($labels) {
                $url = (string) ($n->action_url ?? '');

                return [
                    'id' => (int) $n->id,
                    'type' => $n->type,
                    'label' => $labels[$n->type] ?? $n->type,
                    'color' => $n->color ?: (MarketplaceNotification::getTypeColors()[$n->type] ?? 'primary'),
                    'title' => (string) $n->title,
                    'message' => $n->message ? mb_strimwidth((string) $n->message, 0, 160, '…') : null,
                    'time' => $n->created_at?->isToday() ? $n->created_at->format('H:i') : $n->created_at?->format('d.m H:i'),
                    'day' => $n->created_at?->isToday() ? 'Azi' : ($n->created_at?->isYesterday() ? 'Ieri' : $n->created_at?->format('d.m')),
                    // Linkurile notificărilor de organizator duc în contul lui, nu în panou.
                    'url' => str_starts_with($url, '/marketplace') ? $url : null,
                    'attention' => $n->needsOperatorAttention(),
                ];
            })
            ->all();
    }

    public function unreadQuery(): Builder
    {
        $adminId = $this->admin()?->id ?? 0;

        return $this->query()
            ->whereIn('type', self::ACTION_TYPES)
            ->whereDoesntHave('operatorReads', fn ($q) => $q->where('marketplace_admin_id', $adminId));
    }

    public function unreadCount(): int
    {
        return $this->unreadQuery()->count();
    }

    public function markRead(int $notificationId): void
    {
        $adminId = $this->admin()?->id;
        if (! $adminId) {
            return;
        }

        $exists = $this->query()->whereKey($notificationId)->exists();
        if (! $exists) {
            return;
        }

        DB::table('marketplace_notification_reads')->insertOrIgnore([
            'marketplace_notification_id' => $notificationId,
            'marketplace_admin_id' => $adminId,
            'read_at' => now(),
        ]);
    }

    public function markUnread(int $notificationId): void
    {
        $adminId = $this->admin()?->id;
        if (! $adminId) {
            return;
        }

        DB::table('marketplace_notification_reads')
            ->where('marketplace_notification_id', $notificationId)
            ->where('marketplace_admin_id', $adminId)
            ->delete();
    }

    public function markAllRead(): int
    {
        $adminId = $this->admin()?->id;
        if (! $adminId) {
            return 0;
        }

        $now = now();
        $rows = $this->unreadQuery()->pluck('id')->map(fn ($id) => [
            'marketplace_notification_id' => $id,
            'marketplace_admin_id' => $adminId,
            'read_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('marketplace_notification_reads')->insertOrIgnore($chunk);
        }

        return count($rows);
    }
}

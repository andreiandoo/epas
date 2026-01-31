<?php

namespace App\Services;

use App\Models\MarketplaceNotification;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\OrganizerDocument;
use App\Models\MarketplaceRefundRequest;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Model;

class OrganizerNotificationService
{
    /**
     * Create a notification for an organizer
     */
    public static function notify(
        MarketplaceOrganizer $organizer,
        string $type,
        string $title,
        ?string $message = null,
        ?string $actionUrl = null,
        ?Model $actionable = null,
        ?array $data = null
    ): MarketplaceNotification {
        $defaults = self::getTypeDefaults($type);

        return MarketplaceNotification::create([
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $defaults['icon'],
            'color' => $defaults['color'],
            'action_url' => $actionUrl,
            'actionable_type' => $actionable ? get_class($actionable) : null,
            'actionable_id' => $actionable?->id,
            'data' => $data,
        ]);
    }

    /**
     * Notify about a new ticket sale
     */
    public static function notifySale(Order $order): ?MarketplaceNotification
    {
        $organizer = $order->marketplaceOrganizer;
        if (!$organizer) {
            return null;
        }

        $ticketCount = $order->orderItems->sum('quantity') ?? 1;
        $ticketWord = $ticketCount === 1 ? 'bilet' : 'bilete';
        $eventName = $order->marketplaceEvent?->name ?? 'eveniment';

        return self::notify(
            $organizer,
            MarketplaceNotification::TYPE_TICKET_SALE,
            "Vanzare: {$ticketCount} {$ticketWord}",
            "{$order->total} {$order->currency} - {$eventName}",
            "/organizator/participanti",
            $order,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'currency' => $order->currency,
                'ticket_count' => $ticketCount,
                'event_id' => $order->marketplace_event_id,
                'event_name' => $eventName,
            ]
        );
    }

    /**
     * Notify about a document generation
     */
    public static function notifyDocumentGenerated(OrganizerDocument $document): ?MarketplaceNotification
    {
        $organizer = $document->marketplaceOrganizer;
        if (!$organizer) {
            return null;
        }

        $typeLabels = [
            'cerere_avizare' => 'Cerere Avizare',
            'autorizatie_spectacol' => 'Autorizatie Spectacol',
            'pv_bilant' => 'PV Bilant',
            'decont_drepturi' => 'Decont Drepturi',
        ];

        $label = $typeLabels[$document->document_type] ?? $document->document_type;
        $eventName = $document->event?->name ?? 'eveniment';

        return self::notify(
            $organizer,
            MarketplaceNotification::TYPE_DOCUMENT_GENERATED,
            "Document generat: {$label}",
            "Pentru evenimentul: {$eventName}",
            "/organizator/documente",
            $document,
            [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'event_id' => $document->event_id,
                'event_name' => $eventName,
            ]
        );
    }

    /**
     * Notify about a refund request
     */
    public static function notifyRefundRequest(MarketplaceRefundRequest $refund): ?MarketplaceNotification
    {
        $order = $refund->order;
        $organizer = $order?->marketplaceOrganizer;
        if (!$organizer) {
            return null;
        }

        $eventName = $order->marketplaceEvent?->name ?? 'eveniment';

        return self::notify(
            $organizer,
            MarketplaceNotification::TYPE_REFUND_REQUEST,
            "Cerere de rambursare",
            "{$refund->amount} {$refund->currency} - {$eventName}",
            "/organizator/participanti",
            $refund,
            [
                'refund_id' => $refund->id,
                'order_id' => $order->id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'reason' => $refund->reason,
                'event_name' => $eventName,
            ]
        );
    }

    /**
     * Notify about a service order status change
     */
    public static function notifyServiceOrderStatus(ServiceOrder $serviceOrder, string $status): ?MarketplaceNotification
    {
        $organizer = $serviceOrder->marketplaceOrganizer;
        if (!$organizer) {
            return null;
        }

        $typeLabel = $serviceOrder->service_type_label ?? $serviceOrder->service_type;
        $eventName = $serviceOrder->event?->name ?? 'eveniment';

        $typeMap = [
            'started' => [
                'notification_type' => MarketplaceNotification::TYPE_SERVICE_ORDER_STARTED,
                'title' => "Serviciu pornit: {$typeLabel}",
                'message' => "Serviciul pentru {$eventName} a inceput",
            ],
            'completed' => [
                'notification_type' => MarketplaceNotification::TYPE_SERVICE_ORDER_COMPLETED,
                'title' => "Serviciu finalizat: {$typeLabel}",
                'message' => "Serviciul pentru {$eventName} s-a incheiat",
            ],
            'results' => [
                'notification_type' => MarketplaceNotification::TYPE_SERVICE_ORDER_RESULTS,
                'title' => "Rezultate disponibile: {$typeLabel}",
                'message' => "Rezultatele pentru {$eventName} sunt gata",
            ],
            'invoice' => [
                'notification_type' => MarketplaceNotification::TYPE_SERVICE_ORDER_INVOICE,
                'title' => "Factura generata: {$typeLabel}",
                'message' => "{$serviceOrder->total} {$serviceOrder->currency}",
            ],
        ];

        $config = $typeMap[$status] ?? null;
        if (!$config) {
            return null;
        }

        return self::notify(
            $organizer,
            $config['notification_type'],
            $config['title'],
            $config['message'],
            "/organizator/servicii/comenzi",
            $serviceOrder,
            [
                'service_order_id' => $serviceOrder->id,
                'service_order_uuid' => $serviceOrder->uuid,
                'service_type' => $serviceOrder->service_type,
                'status' => $status,
                'event_id' => $serviceOrder->marketplace_event_id,
                'event_name' => $eventName,
            ]
        );
    }

    /**
     * Get default icon and color for notification type
     */
    protected static function getTypeDefaults(string $type): array
    {
        $icons = MarketplaceNotification::getTypeIcons();
        $colors = MarketplaceNotification::getTypeColors();

        return [
            'icon' => $icons[$type] ?? 'heroicon-o-bell',
            'color' => $colors[$type] ?? 'primary',
        ];
    }
}

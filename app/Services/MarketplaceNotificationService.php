<?php

namespace App\Services;

use App\Models\MarketplaceNotification;
use App\Models\MarketplaceClient;
use Illuminate\Database\Eloquent\Model;

class MarketplaceNotificationService
{
    /**
     * Create a notification
     */
    public function create(
        int $marketplaceClientId,
        string $type,
        string $title,
        ?string $message = null,
        ?Model $actionable = null,
        ?string $actionUrl = null,
        ?array $data = null,
        ?string $icon = null,
        ?string $color = null
    ): MarketplaceNotification {
        return MarketplaceNotification::create([
            'marketplace_client_id' => $marketplaceClientId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon ?? MarketplaceNotification::getTypeIcons()[$type] ?? 'heroicon-o-bell',
            'color' => $color ?? MarketplaceNotification::getTypeColors()[$type] ?? 'primary',
            'data' => $data,
            'actionable_type' => $actionable ? get_class($actionable) : null,
            'actionable_id' => $actionable?->id,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Notify ticket sale
     */
    public function notifyTicketSale(
        int $marketplaceClientId,
        string $eventName,
        string $ticketType,
        float $amount,
        ?Model $order = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_TICKET_SALE,
            'Bilet vândut',
            "Bilet {$ticketType} pentru {$eventName} - " . number_format($amount, 2) . ' RON',
            $order,
            $actionUrl,
            [
                'event_name' => $eventName,
                'ticket_type' => $ticketType,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify refund request
     */
    public function notifyRefundRequest(
        int $marketplaceClientId,
        string $orderReference,
        string $customerName,
        float $amount,
        ?Model $refundRequest = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_REFUND_REQUEST,
            'Cerere de rambursare',
            "{$customerName} solicită rambursare pentru comanda {$orderReference} - " . number_format($amount, 2) . ' RON',
            $refundRequest,
            $actionUrl,
            [
                'order_reference' => $orderReference,
                'customer_name' => $customerName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify organizer registration
     */
    public function notifyOrganizerRegistration(
        int $marketplaceClientId,
        string $organizerName,
        ?string $companyName = null,
        ?Model $organizer = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        $message = $companyName
            ? "{$organizerName} ({$companyName}) s-a înregistrat ca organizator"
            : "{$organizerName} s-a înregistrat ca organizator";

        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_ORGANIZER_REGISTRATION,
            'Organizator nou',
            $message,
            $organizer,
            $actionUrl,
            [
                'organizer_name' => $organizerName,
                'company_name' => $companyName,
            ]
        );
    }

    /**
     * Notify event created
     */
    public function notifyEventCreated(
        int $marketplaceClientId,
        string $eventName,
        string $organizerName,
        ?Model $event = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_EVENT_CREATED,
            'Eveniment nou',
            "{$organizerName} a adăugat evenimentul \"{$eventName}\"",
            $event,
            $actionUrl,
            [
                'event_name' => $eventName,
                'organizer_name' => $organizerName,
            ]
        );
    }

    /**
     * Notify event updated
     */
    public function notifyEventUpdated(
        int $marketplaceClientId,
        string $eventName,
        string $organizerName,
        ?Model $event = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_EVENT_UPDATED,
            'Eveniment modificat',
            "{$organizerName} a modificat evenimentul \"{$eventName}\"",
            $event,
            $actionUrl,
            [
                'event_name' => $eventName,
                'organizer_name' => $organizerName,
            ]
        );
    }

    /**
     * Notify payout request
     */
    public function notifyPayoutRequest(
        int $marketplaceClientId,
        string $organizerName,
        float $amount,
        ?Model $payout = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'Cerere payout',
            "{$organizerName} solicită payout de " . number_format($amount, 2) . ' RON',
            $payout,
            $actionUrl,
            [
                'organizer_name' => $organizerName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify document generated
     */
    public function notifyDocumentGenerated(
        int $marketplaceClientId,
        string $documentType,
        string $entityName,
        ?Model $document = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        $typeLabels = [
            'organizer_contract' => 'Contract organizator',
            'cerere_avizare' => 'Cerere avizare',
            'declaratie_impozite' => 'Declarație impozite',
        ];

        $label = $typeLabels[$documentType] ?? $documentType;

        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_DOCUMENT_GENERATED,
            'Document generat',
            "{$label} generat pentru {$entityName}",
            $document,
            $actionUrl,
            [
                'document_type' => $documentType,
                'entity_name' => $entityName,
            ]
        );
    }

    /**
     * Notify admin user added
     */
    public function notifyAdminUserAdded(
        int $marketplaceClientId,
        string $userName,
        string $userEmail,
        ?Model $user = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_ADMIN_USER_ADDED,
            'Admin nou',
            "Utilizator admin adăugat: {$userName} ({$userEmail})",
            $user,
            $actionUrl,
            [
                'user_name' => $userName,
                'user_email' => $userEmail,
            ]
        );
    }

    /**
     * Notify customer registration
     */
    public function notifyCustomerRegistration(
        int $marketplaceClientId,
        string $customerName,
        string $customerEmail,
        ?Model $customer = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_CUSTOMER_REGISTRATION,
            'Client nou',
            "{$customerName} ({$customerEmail}) s-a înregistrat",
            $customer,
            $actionUrl,
            [
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
            ]
        );
    }

    /**
     * Notify service order
     */
    public function notifyServiceOrder(
        int $marketplaceClientId,
        string $serviceName,
        string $customerName,
        float $amount,
        ?Model $order = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_SERVICE_ORDER,
            'Comandă servicii',
            "{$customerName} a comandat {$serviceName} - " . number_format($amount, 2) . ' RON',
            $order,
            $actionUrl,
            [
                'service_name' => $serviceName,
                'customer_name' => $customerName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify artist created
     */
    public function notifyArtistCreated(
        int $marketplaceClientId,
        string $artistName,
        ?Model $artist = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_ARTIST_CREATED,
            'Artist nou',
            "Artist nou adăugat: {$artistName}",
            $artist,
            $actionUrl,
            [
                'artist_name' => $artistName,
            ]
        );
    }

    /**
     * Notify venue created
     */
    public function notifyVenueCreated(
        int $marketplaceClientId,
        string $venueName,
        ?string $city = null,
        ?Model $venue = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        $message = $city
            ? "Locație nouă adăugată: {$venueName}, {$city}"
            : "Locație nouă adăugată: {$venueName}";

        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_VENUE_CREATED,
            'Locație nouă',
            $message,
            $venue,
            $actionUrl,
            [
                'venue_name' => $venueName,
                'city' => $city,
            ]
        );
    }

    /**
     * Notify seating layout created
     */
    public function notifySeatingLayoutCreated(
        int $marketplaceClientId,
        string $layoutName,
        ?string $venueName = null,
        ?Model $layout = null,
        ?string $actionUrl = null
    ): MarketplaceNotification {
        $message = $venueName
            ? "Hartă locuri nouă: {$layoutName} pentru {$venueName}"
            : "Hartă locuri nouă: {$layoutName}";

        return $this->create(
            $marketplaceClientId,
            MarketplaceNotification::TYPE_SEATING_LAYOUT_CREATED,
            'Hartă locuri nouă',
            $message,
            $layout,
            $actionUrl,
            [
                'layout_name' => $layoutName,
                'venue_name' => $venueName,
            ]
        );
    }

    /**
     * Get unread count for a marketplace
     */
    public function getUnreadCount(int $marketplaceClientId): int
    {
        return MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->unread()
            ->count();
    }

    /**
     * Get latest notifications for dropdown
     */
    public function getLatest(int $marketplaceClientId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark all as read for a marketplace
     */
    public function markAllAsRead(int $marketplaceClientId): int
    {
        return MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->unread()
            ->update(['read_at' => now()]);
    }
}

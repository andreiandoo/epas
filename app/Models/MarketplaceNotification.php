<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MarketplaceNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'type',
        'title',
        'message',
        'icon',
        'color',
        'data',
        'actionable_type',
        'actionable_id',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Notification types constants
     */
    public const TYPE_TICKET_SALE = 'ticket_sale';
    public const TYPE_REFUND_REQUEST = 'refund_request';
    public const TYPE_ORGANIZER_REGISTRATION = 'organizer_registration';
    public const TYPE_EVENT_CREATED = 'event_created';
    public const TYPE_EVENT_UPDATED = 'event_updated';
    public const TYPE_PAYOUT_REQUEST = 'payout_request';
    public const TYPE_DOCUMENT_GENERATED = 'document_generated';
    public const TYPE_ADMIN_USER_ADDED = 'admin_user_added';
    public const TYPE_CUSTOMER_REGISTRATION = 'customer_registration';
    public const TYPE_SERVICE_ORDER = 'service_order';
    public const TYPE_ARTIST_CREATED = 'artist_created';
    public const TYPE_VENUE_CREATED = 'venue_created';
    public const TYPE_SEATING_LAYOUT_CREATED = 'seating_layout_created';

    // Service order notification types
    public const TYPE_SERVICE_ORDER_COMPLETED = 'service_order_completed';
    public const TYPE_SERVICE_ORDER_INVOICE = 'service_order_invoice';
    public const TYPE_SERVICE_ORDER_RESULTS = 'service_order_results';
    public const TYPE_SERVICE_ORDER_STARTED = 'service_order_started';

    /**
     * Type labels for display
     */
    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_TICKET_SALE => 'Vânzare Bilet',
            self::TYPE_REFUND_REQUEST => 'Cerere Rambursare',
            self::TYPE_ORGANIZER_REGISTRATION => 'Înregistrare Organizator',
            self::TYPE_EVENT_CREATED => 'Eveniment Nou',
            self::TYPE_EVENT_UPDATED => 'Eveniment Modificat',
            self::TYPE_PAYOUT_REQUEST => 'Cerere Payout',
            self::TYPE_DOCUMENT_GENERATED => 'Document Generat',
            self::TYPE_ADMIN_USER_ADDED => 'Admin Nou',
            self::TYPE_CUSTOMER_REGISTRATION => 'Client Nou',
            self::TYPE_SERVICE_ORDER => 'Comandă Servicii',
            self::TYPE_ARTIST_CREATED => 'Artist Nou',
            self::TYPE_VENUE_CREATED => 'Locație Nouă',
            self::TYPE_SEATING_LAYOUT_CREATED => 'Hartă Locuri Nouă',
            self::TYPE_SERVICE_ORDER_COMPLETED => 'Serviciu Finalizat',
            self::TYPE_SERVICE_ORDER_INVOICE => 'Factură Servicii',
            self::TYPE_SERVICE_ORDER_RESULTS => 'Rezultate Serviciu',
            self::TYPE_SERVICE_ORDER_STARTED => 'Serviciu Pornit',
        ];
    }

    /**
     * Type icons mapping
     */
    public static function getTypeIcons(): array
    {
        return [
            self::TYPE_TICKET_SALE => 'heroicon-o-ticket',
            self::TYPE_REFUND_REQUEST => 'heroicon-o-arrow-uturn-left',
            self::TYPE_ORGANIZER_REGISTRATION => 'heroicon-o-building-office',
            self::TYPE_EVENT_CREATED => 'heroicon-o-calendar-days',
            self::TYPE_EVENT_UPDATED => 'heroicon-o-pencil-square',
            self::TYPE_PAYOUT_REQUEST => 'heroicon-o-banknotes',
            self::TYPE_DOCUMENT_GENERATED => 'heroicon-o-document-text',
            self::TYPE_ADMIN_USER_ADDED => 'heroicon-o-user-plus',
            self::TYPE_CUSTOMER_REGISTRATION => 'heroicon-o-user',
            self::TYPE_SERVICE_ORDER => 'heroicon-o-shopping-cart',
            self::TYPE_ARTIST_CREATED => 'heroicon-o-musical-note',
            self::TYPE_VENUE_CREATED => 'heroicon-o-map-pin',
            self::TYPE_SEATING_LAYOUT_CREATED => 'heroicon-o-squares-2x2',
            self::TYPE_SERVICE_ORDER_COMPLETED => 'heroicon-o-check-circle',
            self::TYPE_SERVICE_ORDER_INVOICE => 'heroicon-o-document-currency-euro',
            self::TYPE_SERVICE_ORDER_RESULTS => 'heroicon-o-chart-bar',
            self::TYPE_SERVICE_ORDER_STARTED => 'heroicon-o-play',
        ];
    }

    /**
     * Type colors mapping
     */
    public static function getTypeColors(): array
    {
        return [
            self::TYPE_TICKET_SALE => 'success',
            self::TYPE_REFUND_REQUEST => 'warning',
            self::TYPE_ORGANIZER_REGISTRATION => 'info',
            self::TYPE_EVENT_CREATED => 'primary',
            self::TYPE_EVENT_UPDATED => 'primary',
            self::TYPE_PAYOUT_REQUEST => 'warning',
            self::TYPE_DOCUMENT_GENERATED => 'info',
            self::TYPE_ADMIN_USER_ADDED => 'success',
            self::TYPE_CUSTOMER_REGISTRATION => 'info',
            self::TYPE_SERVICE_ORDER => 'success',
            self::TYPE_ARTIST_CREATED => 'primary',
            self::TYPE_VENUE_CREATED => 'primary',
            self::TYPE_SEATING_LAYOUT_CREATED => 'primary',
            self::TYPE_SERVICE_ORDER_COMPLETED => 'success',
            self::TYPE_SERVICE_ORDER_INVOICE => 'info',
            self::TYPE_SERVICE_ORDER_RESULTS => 'success',
            self::TYPE_SERVICE_ORDER_STARTED => 'info',
        ];
    }

    /**
     * Marketplace client relationship
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Marketplace organizer relationship
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    /**
     * Polymorphic relationship to the related model
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for organizer notifications
     */
    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('marketplace_organizer_id', $organizerId);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for filtering by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypeLabels()[$this->type] ?? $this->type;
    }

    /**
     * Get default icon for type
     */
    public function getDefaultIconAttribute(): string
    {
        return self::getTypeIcons()[$this->type] ?? 'heroicon-o-bell';
    }

    /**
     * Get default color for type
     */
    public function getDefaultColorAttribute(): string
    {
        return self::getTypeColors()[$this->type] ?? 'primary';
    }

    /**
     * Get time ago formatted
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}

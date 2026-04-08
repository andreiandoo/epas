<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEmailTemplate extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'slug',
        'name',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'category',
        'is_active',
        'is_default',
        'notify_organizer',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'notify_organizer' => 'boolean',
    ];

    /**
     * Default template slugs
     */
    public const TEMPLATE_SLUGS = [
        'ticket_purchase' => 'Ticket Purchase Confirmation',
        'order_confirmation' => 'Order Confirmation',
        'welcome' => 'Welcome Email',
        'points_earned' => 'Points Earned',
        'points_redeemed' => 'Points Redeemed',
        'refund_requested' => 'Refund Request Received',
        'refund_approved' => 'Refund Approved',
        'refund_rejected' => 'Refund Rejected',
        'refund_completed' => 'Refund Completed',
        'refund_processed' => 'Refund Processed (Admin-initiated)',
        'ticket_cancelled' => 'Ticket Cancelled',
        'event_reminder' => 'Event Reminder',
        'event_updated' => 'Event Updated',
        'event_cancelled' => 'Event Cancelled',
        'ticket_delivery' => 'Ticket Delivery to Beneficiary',
        'invitation' => 'Event Invitation',
        'password_reset' => 'Password Reset',
        'organizer_payout' => 'Organizer Payout Notification',
        'organizer_event_approved' => 'Event Approved',
        'organizer_event_rejected' => 'Event Rejected',
        'organizer_daily_report' => 'Organizer Daily Report',
        'organizer_weekly_report' => 'Organizer Weekly Report',
        'organizer_report' => 'Organizer Report',
        // Admin Notifications
        'admin_event_cancelled' => 'Admin: Event Cancelled by Organizer',
        'admin_event_postponed' => 'Admin: Event Postponed by Organizer',
        'admin_new_order' => 'Admin: Comandă nouă bilete',
        'admin_refund_request' => 'Admin: Cerere de retur',
        'admin_new_service_order' => 'Admin: Comandă servicii extra',
        // Stock Alerts
        'stock_low_alert' => 'Low Stock Alert',
        // Gift Card Templates
        'gift_card_delivery' => 'Gift Card Delivery',
        'gift_card_purchase_confirmation' => 'Gift Card Purchase Confirmation',
        'gift_card_expiry_reminder' => 'Gift Card Expiry Reminder',
        'gift_card_claimed' => 'Gift Card Claimed Notification',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Render template with variables
     */
    public function render(array $data = []): array
    {
        $subject = $this->replaceVariables($this->subject, $data);
        $bodyHtml = $this->replaceVariables($this->body_html, $data);
        $bodyText = $this->body_text ? $this->replaceVariables($this->body_text, $data) : strip_tags($bodyHtml);

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Replace template variables
     */
    protected function replaceVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
                $content = str_replace('{{ ' . $key . ' }}', $value, $content);
            }
        }

        return $content;
    }

    /**
     * Get available variables for this template
     */
    public function getAvailableVariables(): array
    {
        $common = [
            'customer_name' => 'Customer full name',
            'customer_email' => 'Customer email',
            'marketplace_name' => 'Marketplace name',
        ];

        $templateVars = match ($this->slug) {
            'ticket_purchase', 'order_confirmation' => [
                'order_number' => 'Order reference number',
                'event_name' => 'Event name',
                'event_date' => 'Event date and time',
                'venue_name' => 'Venue name',
                'venue_city' => 'Venue city',
                'venue_location' => 'Venue name + city combined',
                'ticket_count' => 'Number of tickets',
                'total_amount' => 'Total order amount',
                'tickets_list' => 'List of tickets with QR codes (HTML)',
                'download_url' => 'URL to download/view tickets',
            ],
            'points_earned', 'points_redeemed' => [
                'points_amount' => 'Points amount',
                'points_balance' => 'Current points balance',
                'action' => 'What earned/used the points',
            ],
            'refund_requested', 'refund_approved', 'refund_rejected', 'refund_completed' => [
                'refund_reference' => 'Refund request reference',
                'order_number' => 'Original order number',
                'refund_amount' => 'Refund amount',
                'refund_reason' => 'Reason for refund',
                'rejection_reason' => 'Reason for rejection (if rejected)',
            ],
            'refund_processed' => [
                'refund_reference' => 'Referință rambursare',
                'order_number' => 'Număr comandă',
                'refund_amount' => 'Sumă rambursată',
                'refund_reason' => 'Motiv rambursare',
                'refunded_tickets' => 'Lista biletelor rambursate',
                'marketplace_email' => 'Email contact marketplace',
            ],
            'welcome' => [
                'login_url' => 'Login URL',
            ],
            'password_reset' => [
                'reset_url' => 'Password reset URL',
            ],
            'ticket_delivery' => [
                'beneficiary_name' => 'Beneficiary full name',
                'event_name' => 'Event name',
                'event_date' => 'Event date and time',
                'venue_name' => 'Venue name',
                'ticket_type' => 'Ticket type name',
            ],
            'event_reminder', 'event_updated', 'event_cancelled' => [
                'event_name' => 'Event name',
                'event_date' => 'Event date and time',
                'venue_name' => 'Venue name',
                'venue_address' => 'Venue address',
            ],
            'organizer_payout' => [
                'organizer_name' => 'Organizer name',
                'payout_amount' => 'Payout amount',
                'payout_reference' => 'Payout reference',
                'payout_status' => 'Payout status',
                'period' => 'Payout period',
            ],
            'organizer_event_approved', 'organizer_event_rejected' => [
                'organizer_name' => 'Organizer name',
                'event_name' => 'Event name',
                'event_date' => 'Event date',
                'rejection_reason' => 'Rejection reason (if rejected)',
            ],
            'organizer_report', 'organizer_daily_report', 'organizer_weekly_report' => [
                'organizer_name' => 'Organizer name',
                'period' => 'Report period',
                'total_sales' => 'Total sales amount',
                'commission' => 'Commission amount',
                'net_amount' => 'Net amount',
                'orders_count' => 'Number of orders',
                'tickets_count' => 'Number of tickets sold',
            ],
            'admin_event_cancelled', 'admin_event_postponed' => [
                'organizer_name' => 'Organizer name',
                'event_name' => 'Event name',
                'event_date' => 'Event date',
                'venue_name' => 'Venue name',
                'admin_url' => 'Admin edit URL',
            ],
            'admin_new_order' => [
                'order_number' => 'Order number',
                'customer_name' => 'Customer name',
                'customer_email' => 'Customer email',
                'total_amount' => 'Order total',
                'currency' => 'Currency',
                'tickets_count' => 'Number of tickets',
                'event_name' => 'Event name',
                'view_url' => 'Admin order URL',
            ],
            'admin_refund_request' => [
                'refund_reference' => 'Refund reference',
                'order_number' => 'Order number',
                'customer_name' => 'Customer name',
                'customer_email' => 'Customer email',
                'refund_amount' => 'Refund amount',
                'refund_reason' => 'Refund reason',
                'view_url' => 'Admin refund URL',
            ],
            'admin_new_service_order' => [
                'service_order_number' => 'Service order number',
                'service_type' => 'Service type',
                'organizer_name' => 'Organizer name',
                'event_name' => 'Event name',
                'total_amount' => 'Order total',
                'view_url' => 'Admin service order URL',
            ],
            'stock_low_alert' => [
                'organizer_name' => 'Organizer name',
                'event_name' => 'Event name',
                'ticket_type' => 'Ticket type name',
                'remaining_stock' => 'Remaining stock count',
                'admin_url' => 'Admin edit URL',
            ],
            'gift_card_delivery' => [
                'recipient_name' => 'Gift card recipient name',
                'purchaser_name' => 'Name of person who sent the gift card',
                'gift_card_code' => 'Gift card code',
                'gift_card_pin' => 'Gift card PIN',
                'gift_card_amount' => 'Gift card amount',
                'personal_message' => 'Personal message from sender',
                'occasion' => 'Occasion (birthday, thank you, etc.)',
                'expires_at' => 'Expiration date',
                'claim_url' => 'URL to claim the gift card',
            ],
            'gift_card_purchase_confirmation' => [
                'purchaser_name' => 'Purchaser name',
                'recipient_name' => 'Recipient name',
                'recipient_email' => 'Recipient email',
                'gift_card_amount' => 'Gift card amount',
                'gift_card_code' => 'Gift card code',
                'delivery_method' => 'Delivery method',
                'scheduled_delivery' => 'Scheduled delivery date/time',
            ],
            'gift_card_expiry_reminder' => [
                'recipient_name' => 'Gift card holder name',
                'gift_card_code' => 'Gift card code (masked)',
                'remaining_balance' => 'Remaining balance',
                'expires_at' => 'Expiration date',
                'days_remaining' => 'Days until expiry',
            ],
            'gift_card_claimed' => [
                'purchaser_name' => 'Purchaser name',
                'recipient_name' => 'Person who claimed the card',
                'gift_card_amount' => 'Gift card amount',
            ],
            default => [],
        };

        return array_merge($common, $templateVars);
    }

    /**
     * Scope: Active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}

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
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Default template slugs
     */
    public const TEMPLATES = [
        'ticket_purchase' => 'Ticket Purchase Confirmation',
        'order_confirmation' => 'Order Confirmation',
        'welcome' => 'Welcome Email',
        'points_earned' => 'Points Earned',
        'points_redeemed' => 'Points Redeemed',
        'refund_requested' => 'Refund Request Received',
        'refund_approved' => 'Refund Approved',
        'refund_rejected' => 'Refund Rejected',
        'refund_completed' => 'Refund Completed',
        'ticket_cancelled' => 'Ticket Cancelled',
        'event_reminder' => 'Event Reminder',
        'event_updated' => 'Event Updated',
        'event_cancelled' => 'Event Cancelled',
        'invitation' => 'Event Invitation',
        'password_reset' => 'Password Reset',
        'organizer_payout' => 'Organizer Payout Notification',
        'organizer_event_approved' => 'Event Approved',
        'organizer_event_rejected' => 'Event Rejected',
        'organizer_daily_report' => 'Organizer Daily Report',
        'organizer_weekly_report' => 'Organizer Weekly Report',
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
                'ticket_count' => 'Number of tickets',
                'total_amount' => 'Total order amount',
                'tickets_list' => 'List of tickets (HTML)',
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
            'event_reminder', 'event_updated', 'event_cancelled' => [
                'event_name' => 'Event name',
                'event_date' => 'Event date and time',
                'venue_name' => 'Venue name',
                'venue_address' => 'Venue address',
            ],
            'organizer_payout' => [
                'payout_amount' => 'Payout amount',
                'payout_reference' => 'Payout reference',
                'payout_status' => 'Payout status',
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

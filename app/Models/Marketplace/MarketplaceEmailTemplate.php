<?php

namespace App\Models\Marketplace;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MarketplaceEmailTemplate Model
 *
 * Email templates specific to marketplace tenants.
 * Allows marketplaces to customize email content for organizers and customers.
 */
class MarketplaceEmailTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'subject',
        'body',
        'event_trigger',
        'description',
        'available_variables',
        'is_active',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Available event triggers for marketplace emails
     */
    public const EVENT_TRIGGERS = [
        // Organizer notifications
        'organizer_registration_submitted' => 'Organizer Registration Submitted',
        'organizer_approved' => 'Organizer Approved',
        'organizer_rejected' => 'Organizer Rejected',
        'organizer_suspended' => 'Organizer Suspended',

        // Order notifications
        'order_confirmation' => 'Order Confirmation (Customer)',
        'organizer_new_order' => 'New Order (Organizer)',
        'order_paid' => 'Order Paid',
        'ticket_delivery' => 'Ticket Delivery',

        // Payout notifications
        'payout_ready' => 'Payout Ready',
        'payout_processed' => 'Payout Processed',
        'payout_failed' => 'Payout Failed',

        // Event notifications
        'event_reminder' => 'Event Reminder (24h before)',
        'event_cancelled' => 'Event Cancelled',
    ];

    /**
     * Variables available for each event trigger
     */
    public const TRIGGER_VARIABLES = [
        'organizer_registration_submitted' => [
            'organizer_name', 'organizer_email', 'company_name', 'marketplace_name',
        ],
        'organizer_approved' => [
            'organizer_name', 'organizer_email', 'company_name', 'marketplace_name', 'login_url',
        ],
        'organizer_rejected' => [
            'organizer_name', 'organizer_email', 'company_name', 'marketplace_name', 'rejection_reason',
        ],
        'organizer_suspended' => [
            'organizer_name', 'organizer_email', 'company_name', 'marketplace_name', 'suspension_reason',
        ],
        'order_confirmation' => [
            'customer_name', 'customer_email', 'order_number', 'order_total', 'event_name',
            'event_date', 'ticket_count', 'marketplace_name',
        ],
        'organizer_new_order' => [
            'organizer_name', 'order_number', 'order_total', 'customer_name', 'event_name',
            'ticket_count', 'organizer_revenue', 'marketplace_name',
        ],
        'order_paid' => [
            'customer_name', 'customer_email', 'order_number', 'order_total', 'event_name',
            'event_date', 'marketplace_name',
        ],
        'ticket_delivery' => [
            'customer_name', 'customer_email', 'order_number', 'event_name', 'event_date',
            'event_venue', 'ticket_count', 'ticket_download_url', 'marketplace_name',
        ],
        'payout_ready' => [
            'organizer_name', 'payout_amount', 'payout_reference', 'period_start', 'period_end',
            'orders_count', 'marketplace_name',
        ],
        'payout_processed' => [
            'organizer_name', 'payout_amount', 'payout_reference', 'bank_reference',
            'processed_date', 'marketplace_name',
        ],
        'payout_failed' => [
            'organizer_name', 'payout_amount', 'payout_reference', 'failure_reason', 'marketplace_name',
        ],
        'event_reminder' => [
            'customer_name', 'event_name', 'event_date', 'event_time', 'event_venue',
            'ticket_count', 'marketplace_name',
        ],
        'event_cancelled' => [
            'customer_name', 'event_name', 'event_date', 'order_number', 'refund_info', 'marketplace_name',
        ],
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the marketplace tenant this template belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Alias for tenant.
     */
    public function marketplace(): BelongsTo
    {
        return $this->tenant();
    }

    /**
     * Get email logs using this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MarketplaceEmailLog::class, 'template_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to active templates only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to a specific event trigger.
     */
    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('event_trigger', $trigger);
    }

    /**
     * Scope to a specific marketplace.
     */
    public function scopeForMarketplace($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Process template variables and return final content.
     */
    public function processTemplate(array $variables = []): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, $value ?? '', $subject);
            $body = str_replace($placeholder, $value ?? '', $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get available variables for this template's event trigger.
     */
    public function getAvailableVariablesForTrigger(): array
    {
        return self::TRIGGER_VARIABLES[$this->event_trigger] ?? [];
    }

    /**
     * Find template for a specific trigger.
     */
    public static function findForTrigger(int $tenantId, string $trigger): ?self
    {
        return self::forMarketplace($tenantId)
            ->forTrigger($trigger)
            ->active()
            ->first();
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            // Auto-generate slug if not provided
            if (empty($template->slug)) {
                $template->slug = \Illuminate\Support\Str::slug($template->name);
            }

            // Auto-set available variables based on event trigger
            if (empty($template->available_variables) && $template->event_trigger) {
                $template->available_variables = self::TRIGGER_VARIABLES[$template->event_trigger] ?? [];
            }
        });
    }
}

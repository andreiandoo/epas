<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Per-event flexible-payment configuration set in the event admin panel.
 */
class EventFlexiblePaymentConfig extends Model
{
    protected $fillable = [
        'event_id',
        'marketplace_event_id',
        'enable_installments',
        'enable_bnpl',
        'enable_delegated_pay',
        'eligible_ticket_type_ids',
        'down_payment_type',
        'down_payment_value',
        'bnpl_max_horizon_days',
        'delegated_hold_hours',
        'delegated_max_locked_tickets',
        'notes',
    ];

    protected $casts = [
        'enable_installments' => 'boolean',
        'enable_bnpl' => 'boolean',
        'enable_delegated_pay' => 'boolean',
        'eligible_ticket_type_ids' => 'array',
        'down_payment_value' => 'integer',
        'bnpl_max_horizon_days' => 'integer',
        'delegated_hold_hours' => 'integer',
        'delegated_max_locked_tickets' => 'integer',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            InstallmentPlan::class,
            'event_installment_plan',
            'event_flexible_payment_config_id',
            'installment_plan_id'
        )->withPivot(['sort_order', 'is_active'])->withTimestamps();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function anyMethodEnabled(): bool
    {
        return $this->enable_installments || $this->enable_bnpl || $this->enable_delegated_pay;
    }

    /**
     * Resolve the config for an event, matching each id in its OWN id-space.
     *
     * `event_id` (tenant Event table) and `marketplace_event_id` (marketplace
     * event table) are distinct id-spaces, so a blanket
     * `where(event_id)->orWhere(marketplace_event_id)` can cross-match a config
     * belonging to a different event whose id happens to collide. We instead
     * match marketplace_event_id when given, then event_id — never mixing them.
     */
    public static function resolveFor(?int $eventId, ?int $marketplaceEventId): ?self
    {
        if ($marketplaceEventId) {
            $byMarketplace = static::where('marketplace_event_id', $marketplaceEventId)->first();
            if ($byMarketplace) {
                return $byMarketplace;
            }
        }
        if ($eventId) {
            return static::where('event_id', $eventId)->first();
        }
        return null;
    }

    /**
     * Is a given ticket type eligible for flexible payment on this event?
     * Empty/null list = all ticket types eligible.
     */
    public function ticketTypeEligible($ticketTypeId): bool
    {
        $ids = $this->eligible_ticket_type_ids;
        if (empty($ids) || ! is_array($ids)) {
            return true;
        }
        return in_array((int) $ticketTypeId, array_map('intval', $ids), true);
    }

    /**
     * Are ALL of the given ticket type ids eligible? (checkout gate — the whole
     * order must consist of eligible ticket types).
     */
    public function allTicketTypesEligible(array $ticketTypeIds): bool
    {
        foreach ($ticketTypeIds as $id) {
            if (! $this->ticketTypeEligible($id)) {
                return false;
            }
        }
        return true;
    }
}

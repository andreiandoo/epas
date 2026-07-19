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
}

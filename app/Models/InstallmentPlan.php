<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable flexible-payment plan template (installments or single BNPL).
 */
class InstallmentPlan extends Model
{
    use Translatable;
    use SoftDeletes;

    public const TYPE_INSTALLMENTS = 'installments';
    public const TYPE_BNPL = 'bnpl_single';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'name',
        'slug',
        'description',
        'plan_type',
        'is_active',
        'sort_order',
        'currency',
        'down_payment_default_type',
        'down_payment_default_value',
        'number_of_installments',
        'schedule_type',
        'interval_unit',
        'interval_count',
        'fixed_dates',
        'distribution',
        'installments_percentages',
        'surcharge_percent',
        'surcharge_fixed_cents',
        'min_order_cents',
        'max_order_cents',
        'days_before_event_fully_paid',
        'compress_schedule',
        'max_duration_days',
        'eligibility',
        'ticket_issuance_policy',
        'default_policy',
        'refund_policy',
        'terms_url',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'compress_schedule' => 'boolean',
        'fixed_dates' => 'array',
        'installments_percentages' => 'array',
        'eligibility' => 'array',
        'default_policy' => 'array',
        'refund_policy' => 'array',
        'surcharge_percent' => 'integer',
        'surcharge_fixed_cents' => 'integer',
        'number_of_installments' => 'integer',
        'interval_count' => 'integer',
        'days_before_event_fully_paid' => 'integer',
        'max_duration_days' => 'integer',
        'min_order_cents' => 'integer',
        'max_order_cents' => 'integer',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isBnpl(): bool
    {
        return $this->plan_type === self::TYPE_BNPL;
    }

    /**
     * Grace days: default policy value, clamped to a minimum of 1 so the last
     * payment is never on the event day.
     */
    public function daysBeforeEvent(): int
    {
        return max(1, (int) $this->days_before_event_fully_paid);
    }

    public function defaultPolicy(): array
    {
        return array_merge([
            'grace_days' => 3,
            'max_retries' => 3,
            'retry_backoff_days' => [1, 3, 5],
            'forfeit_down_payment' => true,
        ], $this->default_policy ?? []);
    }

    public function refundPolicy(): array
    {
        return array_merge([
            'surcharge_refundable' => false,
            'platform_fee_refundable' => false,
        ], $this->refund_policy ?? []);
    }
}

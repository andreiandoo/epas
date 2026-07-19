<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A concrete flexible-payment agreement bound to an order.
 */
class InstallmentAgreement extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DEFAULTED = 'defaulted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'order_id',
        'installment_plan_id',
        'marketplace_customer_id',
        'customer_email',
        'customer_name',
        'customer_phone',
        'event_id',
        'marketplace_event_id',
        'event_start_date',
        'plan_type',
        'currency',
        'base_total_cents',
        'surcharge_cents',
        'customer_total_cents',
        'platform_fee_cents',
        'platform_fee_percent',
        'down_payment_cents',
        'financed_cents',
        'number_of_installments',
        'paid_installments_count',
        'next_due_at',
        'status',
        'ticket_issuance_policy',
        'provider',
        'payment_method_id',
        'mandate_reference',
        'auto_debit_enabled',
        'portal_token',
        'plan_snapshot',
        'metadata',
    ];

    protected $hidden = ['mandate_reference', 'portal_token'];

    protected $casts = [
        'event_start_date' => 'datetime',
        'next_due_at' => 'datetime',
        'base_total_cents' => 'integer',
        'surcharge_cents' => 'integer',
        'customer_total_cents' => 'integer',
        'platform_fee_cents' => 'integer',
        'platform_fee_percent' => 'decimal:2',
        'down_payment_cents' => 'integer',
        'financed_cents' => 'integer',
        'number_of_installments' => 'integer',
        'paid_installments_count' => 'integer',
        'auto_debit_enabled' => 'boolean',
        'plan_snapshot' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            if (empty($a->portal_token)) {
                $a->portal_token = Str::random(48);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class)->orderBy('sequence');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InstallmentEvent::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomerPaymentMethod::class, 'payment_method_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    // ---------------------------------------------------------------------

    public function paidCents(): int
    {
        return (int) $this->payments()->where('status', 'paid')->sum('paid_amount_cents');
    }

    public function outstandingCents(): int
    {
        return max(0, (int) $this->customer_total_cents - $this->paidCents());
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function log(string $type, ?string $message = null, array $payload = [], ?int $paymentId = null): void
    {
        $this->events()->create([
            'installment_payment_id' => $paymentId,
            'type' => $type,
            'message' => $message,
            'payload' => $payload ?: null,
        ]);
    }

    public function recomputeNextDue(): void
    {
        $next = $this->payments()
            ->whereIn('status', ['scheduled', 'due', 'retrying', 'action_required'])
            ->orderBy('due_date')
            ->first();

        $this->next_due_at = $next?->due_date;
        $this->save();
    }
}

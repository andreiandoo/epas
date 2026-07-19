<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single scheduled charge in an agreement. sequence 0 = down payment.
 */
class InstallmentPayment extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DUE = 'due';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_ACTION_REQUIRED = 'action_required';
    public const STATUS_WAIVED = 'waived';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'installment_agreement_id',
        'sequence',
        'due_date',
        'amount_cents',
        'principal_cents',
        'fee_cents',
        'status',
        'paid_at',
        'paid_amount_cents',
        'payment_reference',
        'attempts',
        'last_attempt_at',
        'last_error',
        'reminder_sent_at',
        'dunning_stage',
        'pay_link_token',
        'metadata',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'sequence' => 'integer',
        'amount_cents' => 'integer',
        'principal_cents' => 'integer',
        'fee_cents' => 'integer',
        'paid_amount_cents' => 'integer',
        'attempts' => 'integer',
        'dunning_stage' => 'integer',
        'metadata' => 'array',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(InstallmentAgreement::class, 'installment_agreement_id');
    }

    public function isDownPayment(): bool
    {
        return $this->sequence === 0;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [
            self::STATUS_SCHEDULED,
            self::STATUS_DUE,
            self::STATUS_RETRYING,
            self::STATUS_ACTION_REQUIRED,
            self::STATUS_FAILED,
        ], true);
    }

    public function getAmount(): float
    {
        return $this->amount_cents / 100;
    }
}

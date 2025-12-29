<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceRefundRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_customer_id',
        'marketplace_event_id',
        'order_id',
        'reference',
        'type',
        'reason',
        'reason_category',
        'ticket_ids',
        'customer_notes',
        'requested_amount',
        'approved_amount',
        'currency',
        'status',
        'admin_notes',
        'rejection_reason',
        'refund_method',
        'payment_processor',
        'payment_refund_id',
        'payment_response',
        'is_automatic',
        'organizer_deduction',
        'commission_refund',
        'fees_refund',
        'requested_at',
        'reviewed_at',
        'processed_at',
        'completed_at',
        'reviewed_by',
        'processed_by',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'organizer_deduction' => 'decimal:2',
        'commission_refund' => 'decimal:2',
        'fees_refund' => 'decimal:2',
        'ticket_ids' => 'array',
        'payment_response' => 'array',
        'is_automatic' => 'boolean',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_FAILED = 'failed';

    /**
     * Type constants
     */
    public const TYPE_FULL_REFUND = 'full_refund';
    public const TYPE_PARTIAL_REFUND = 'partial_refund';
    public const TYPE_CANCELLATION = 'cancellation';

    /**
     * Reason constants
     */
    public const REASONS = [
        'event_cancelled' => 'Event was cancelled',
        'event_postponed' => 'Event was postponed',
        'cannot_attend' => 'Cannot attend the event',
        'wrong_tickets' => 'Purchased wrong tickets',
        'duplicate_purchase' => 'Duplicate purchase',
        'technical_issue' => 'Technical issue during purchase',
        'other' => 'Other reason',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->reference)) {
                $model->reference = static::generateReference($model->marketplace_client_id);
            }
        });
    }

    /**
     * Generate unique reference
     */
    public static function generateReference(int $marketplaceClientId): string
    {
        $prefix = 'REF';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Alias for backwards compatibility
     */
    public function getRequestNumberAttribute(): string
    {
        return $this->reference;
    }

    /**
     * Relationships
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'processed_by');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'refund_request_id');
    }

    /**
     * Status checks
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isRefunded(): bool
    {
        return in_array($this->status, [self::STATUS_REFUNDED, self::STATUS_PARTIALLY_REFUNDED]);
    }

    public function canBeProcessed(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED]);
    }

    /**
     * Actions
     */
    public function markUnderReview(): void
    {
        $this->update(['status' => self::STATUS_UNDER_REVIEW]);
    }

    public function approve(float $amount = null, string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_amount' => $amount ?? $this->requested_amount,
            'admin_notes' => $notes,
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'admin_notes' => $reason,
            'processed_at' => now(),
        ]);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markRefunded(string $paymentRefundId = null, int $processedBy = null): void
    {
        $isPartial = $this->approved_amount < $this->requested_amount;

        $this->update([
            'status' => $isPartial ? self::STATUS_PARTIALLY_REFUNDED : self::STATUS_REFUNDED,
            'payment_refund_id' => $paymentRefundId,
            'processed_by' => $processedBy,
            'processed_at' => now(),
            'completed_at' => now(),
        ]);

        // Update order
        $this->order->update([
            'refund_status' => $isPartial ? 'partial' : 'full',
            'refunded_amount' => $this->approved_amount,
            'refunded_at' => now(),
        ]);

        // Cancel associated tickets
        foreach ($this->tickets as $ticket) {
            $ticket->update([
                'is_cancelled' => true,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Refund processed: ' . $this->reference,
            ]);
        }
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'is_automatic' => true,
            'admin_notes' => $this->admin_notes ? $this->admin_notes . "\n\nAuto-refund error: " . $error : "Auto-refund error: " . $error,
        ]);
    }

    /**
     * Attempt automatic refund via payment processor
     */
    public function attemptAutoRefund(): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        $this->markProcessing();

        try {
            // Get payment method from order
            $order = $this->order;
            $paymentMethod = $order->payment_method ?? 'stripe';

            // TODO: Implement actual payment processor refund logic
            // This would integrate with Stripe, PayPal, etc.

            // For now, mark as needing manual processing
            $this->update([
                'auto_refund_attempted' => true,
                'auto_refund_error' => 'Automatic refund not implemented for ' . $paymentMethod,
            ]);

            return false;
        } catch (\Exception $e) {
            $this->markFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Scopes
     */
    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('marketplace_organizer_id', $organizerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeNeedsAction($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
        ]);
    }

    /**
     * Get reason label
     */
    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_UNDER_REVIEW => 'info',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_REFUNDED => 'success',
            self::STATUS_PARTIALLY_REFUNDED => 'warning',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }
}

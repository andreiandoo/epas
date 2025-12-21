<?php

namespace App\Models\Marketplace;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MarketplacePayout Model
 *
 * Represents a payout from a marketplace to an organizer.
 * Contains aggregated data from multiple orders.
 */
class MarketplacePayout extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'organizer_id',
        'reference',
        'external_reference',
        'amount',
        'currency',
        'status',
        'method',
        'method_details',
        'period_start',
        'period_end',
        'orders_count',
        'tickets_count',
        'gross_revenue',
        'tixello_fees',
        'marketplace_fees',
        'refunds_total',
        'notes',
        'failure_reason',
        'processed_by',
        'processed_at',
        'completed_at',
        'failed_at',
        'bank_reference',
        'bank_confirmed_at',
    ];

    protected $casts = [
        'method_details' => 'array',
        'amount' => 'decimal:2',
        'gross_revenue' => 'decimal:2',
        'tixello_fees' => 'decimal:2',
        'marketplace_fees' => 'decimal:2',
        'refunds_total' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'bank_confirmed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the marketplace (tenant) this payout belongs to.
     */
    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Alias for marketplace.
     */
    public function tenant(): BelongsTo
    {
        return $this->marketplace();
    }

    /**
     * Get the organizer this payout is for.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'organizer_id');
    }

    /**
     * Get the user who processed this payout.
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the payout items (orders included in this payout).
     */
    public function items(): HasMany
    {
        return $this->hasMany(MarketplacePayoutItem::class, 'payout_id');
    }

    /**
     * Get the orders included in this payout.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'payout_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope for pending payouts.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for processing payouts.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for completed payouts.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed payouts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for a specific organizer.
     */
    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }

    /**
     * Scope for a specific marketplace.
     */
    public function scopeForMarketplace($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    /**
     * Check if payout is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payout is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if payout is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payout has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if payout can be processed.
     */
    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payout can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_FAILED]);
    }

    // =========================================================================
    // STATUS TRANSITIONS
    // =========================================================================

    /**
     * Mark payout as processing.
     */
    public function markAsProcessing(?int $processedBy = null): bool
    {
        if (!$this->canBeProcessed()) {
            return false;
        }

        $this->status = self::STATUS_PROCESSING;
        $this->processed_by = $processedBy;
        $this->processed_at = now();
        return $this->save();
    }

    /**
     * Mark payout as completed.
     */
    public function markAsCompleted(?string $bankReference = null): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->bank_reference = $bankReference;
        $this->bank_confirmed_at = $bankReference ? now() : null;

        if ($this->save()) {
            // Update organizer statistics
            $this->organizer->refreshStatistics();
            return true;
        }

        return false;
    }

    /**
     * Mark payout as failed.
     */
    public function markAsFailed(string $reason): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        $this->failed_at = now();

        if ($this->save()) {
            // Unlink orders so they can be included in a future payout
            $this->orders()->update(['payout_id' => null]);
            return true;
        }

        return false;
    }

    /**
     * Cancel the payout.
     */
    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;

        if ($this->save()) {
            // Unlink orders so they can be included in a future payout
            $this->orders()->update(['payout_id' => null]);
            return true;
        }

        return false;
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    /**
     * Get status color for display.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get period description.
     */
    public function getPeriodDescription(): string
    {
        return $this->period_start->format('M d') . ' - ' . $this->period_end->format('M d, Y');
    }

    // =========================================================================
    // BOOT & OBSERVERS
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (MarketplacePayout $payout) {
            // Auto-generate reference if not provided
            if (empty($payout->reference)) {
                $payout->reference = 'PAY-' . strtoupper(Str::random(10));
            }
        });
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'method', 'bank_reference'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Payout {$eventName}")
            ->useLogName('marketplace');
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
        $activity->properties = $activity->properties->put('organizer_id', $this->organizer_id);
    }
}

<?php

namespace App\Models;

use App\Notifications\AffiliateWithdrawalStatusNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateWithdrawal extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'affiliate_id',
        'reference',
        'amount',
        'currency',
        'status',
        'rejection_reason',
        'payment_method',
        'payment_details',
        'transaction_id',
        'processed_at',
        'processed_by',
        'admin_notes',
        'requested_ip',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_details' => 'array',
        'processed_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForAffiliate($query, int $affiliateId)
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Generate unique withdrawal reference
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'WD-' . strtoupper(Str::random(8));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Check if withdrawal can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if withdrawal can be processed
     */
    public function canBeProcessed(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Cancel the withdrawal request
     */
    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        // Restore balance to affiliate
        $this->affiliate->increment('available_balance', $this->amount);

        $this->update(['status' => self::STATUS_CANCELLED]);

        return true;
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(?int $processedBy = null): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $previousStatus = $this->status;

        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_by' => $processedBy,
        ]);

        $this->sendStatusNotification($previousStatus);

        return true;
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(?string $transactionId = null, ?string $adminNotes = null): bool
    {
        if (!$this->canBeProcessed()) {
            return false;
        }

        $previousStatus = $this->status;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'transaction_id' => $transactionId,
            'admin_notes' => $adminNotes,
            'processed_at' => now(),
        ]);

        // Update affiliate stats
        $this->affiliate->increment('total_withdrawn', $this->amount);
        $this->affiliate->update(['last_withdrawal_at' => now()]);

        $this->sendStatusNotification($previousStatus);

        return true;
    }

    /**
     * Reject the withdrawal
     */
    public function reject(string $reason, ?int $processedBy = null): bool
    {
        if (!$this->canBeProcessed()) {
            return false;
        }

        $previousStatus = $this->status;

        // Restore balance to affiliate
        $this->affiliate->increment('available_balance', $this->amount);

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_by' => $processedBy,
            'processed_at' => now(),
        ]);

        $this->sendStatusNotification($previousStatus);

        return true;
    }

    /**
     * Send status notification to affiliate
     */
    protected function sendStatusNotification(string $previousStatus): void
    {
        try {
            $this->affiliate->notify(new AffiliateWithdrawalStatusNotification($this, $previousStatus));
        } catch (\Exception $e) {
            Log::warning('Failed to send withdrawal status notification', [
                'withdrawal_id' => $this->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $locale = app()->getLocale();

        return match ($this->status) {
            self::STATUS_PENDING => $locale === 'ro' ? 'In asteptare' : 'Pending',
            self::STATUS_PROCESSING => $locale === 'ro' ? 'In procesare' : 'Processing',
            self::STATUS_COMPLETED => $locale === 'ro' ? 'Finalizat' : 'Completed',
            self::STATUS_REJECTED => $locale === 'ro' ? 'Respins' : 'Rejected',
            self::STATUS_CANCELLED => $locale === 'ro' ? 'Anulat' : 'Cancelled',
            default => $this->status,
        };
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabel(): string
    {
        $labels = [
            'bank_transfer' => 'Transfer bancar',
            'paypal' => 'PayPal',
            'revolut' => 'Revolut',
            'wise' => 'Wise',
        ];

        return $labels[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    /**
     * Format payment details for display
     */
    public function getFormattedPaymentDetails(): string
    {
        $details = $this->payment_details ?? [];

        return match ($this->payment_method) {
            'bank_transfer' => sprintf(
                '%s - %s (%s)',
                $details['bank_name'] ?? 'N/A',
                $details['iban'] ?? 'N/A',
                $details['account_holder'] ?? 'N/A'
            ),
            'paypal' => $details['paypal_email'] ?? 'N/A',
            'revolut' => $details['revolut_tag'] ?? ($details['phone'] ?? 'N/A'),
            'wise' => $details['wise_email'] ?? 'N/A',
            default => json_encode($details),
        };
    }
}

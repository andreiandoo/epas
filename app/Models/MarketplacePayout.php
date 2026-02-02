<?php

namespace App\Models;

use App\Notifications\MarketplaceAdminPayoutRequestNotification;
use App\Notifications\MarketplacePayoutNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplacePayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'reference',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'gross_amount',
        'commission_amount',
        'fees_amount',
        'adjustments_amount',
        'adjustments_note',
        'status',
        'payout_method',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'completed_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'payment_reference',
        'payment_method',
        'payment_notes',
        'admin_notes',
        'organizer_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'fees_amount' => 'decimal:2',
        'adjustments_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'payout_method' => 'array',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payout) {
            if (empty($payout->reference)) {
                $payout->reference = 'PAY-' . strtoupper(Str::random(8));
            }
        });

        static::created(function ($payout) {
            // Append payout ID to reference (e.g., PAY-QGTQJTNF-1)
            if (!str_ends_with($payout->reference, '-' . $payout->id)) {
                $payout->updateQuietly(['reference' => $payout->reference . '-' . $payout->id]);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'approved_by');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'processed_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'rejected_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MarketplaceTransaction::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeProcessed(): bool
    {
        return $this->isApproved();
    }

    public function canBeCompleted(): bool
    {
        // Can complete from either approved or processing status
        return $this->isApproved() || $this->isProcessing();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    // =========================================
    // Actions
    // =========================================

    /**
     * Approve the payout request
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->notifyOrganizer('approved');
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(int $userId): void
    {
        $this->update([
            'status' => 'processing',
            'processed_by' => $userId,
            'processed_at' => now(),
        ]);

        $this->notifyOrganizer('processing');
    }

    /**
     * Complete the payout
     */
    public function complete(string $paymentReference, ?string $paymentNotes = null): void
    {
        $this->update([
            'status' => 'completed',
            'payment_reference' => $paymentReference,
            'payment_notes' => $paymentNotes,
            'completed_at' => now(),
        ]);

        // Update organizer balances
        $this->organizer->recordPayoutCompleted($this->amount);

        // Build description with payment reference
        $description = "Plată {$this->reference} finalizată";
        if ($paymentReference) {
            $description .= " (Ref: {$paymentReference})";
        }

        // Record transaction
        MarketplaceTransaction::create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'marketplace_organizer_id' => $this->marketplace_organizer_id,
            'type' => 'payout',
            'amount' => -$this->amount,
            'currency' => $this->currency,
            'balance_after' => $this->organizer->available_balance,
            'marketplace_payout_id' => $this->id,
            'description' => $description,
            'metadata' => [
                'payment_reference' => $paymentReference,
                'payment_notes' => $paymentNotes,
            ],
        ]);

        $this->notifyOrganizer('completed');
    }

    /**
     * Reject the payout request
     */
    public function reject(int $userId, string $reason): void
    {
        $wasApproved = $this->isApproved();

        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by' => $userId,
            'rejected_at' => now(),
        ]);

        // Return balance to available
        $this->organizer->returnPendingBalance($this->amount);

        $this->notifyOrganizer('rejected');
    }

    /**
     * Cancel the payout request (by organizer)
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'processing' => 'primary',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Send notification to organizer
     */
    public function notifyOrganizer(string $action): void
    {
        if (!$this->organizer) {
            return;
        }

        // Send email notification
        $this->organizer->notify(new MarketplacePayoutNotification($this, $action));

        // Create database notification in MarketplaceNotification table
        $this->createOrganizerNotification($action);
    }

    /**
     * Create notification record in MarketplaceNotification table
     */
    protected function createOrganizerNotification(string $action): void
    {
        $amount = number_format($this->amount, 2) . ' ' . $this->currency;

        $typeMap = [
            'submitted' => MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'approved' => MarketplaceNotification::TYPE_PAYOUT_APPROVED,
            'processing' => MarketplaceNotification::TYPE_PAYOUT_PROCESSING,
            'completed' => MarketplaceNotification::TYPE_PAYOUT_COMPLETED,
            'rejected' => MarketplaceNotification::TYPE_PAYOUT_REJECTED,
        ];

        $titleMap = [
            'submitted' => 'Cerere de plată înregistrată',
            'approved' => 'Cerere de plată aprobată',
            'processing' => 'Plată în procesare',
            'completed' => 'Plată finalizată',
            'rejected' => 'Cerere de plată respinsă',
        ];

        $messageMap = [
            'submitted' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost înregistrată.",
            'approved' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost aprobată.",
            'processing' => "Plata {$this->reference} în valoare de {$amount} este în curs de procesare.",
            'completed' => "Plata {$this->reference} în valoare de {$amount} a fost finalizată.",
            'rejected' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost respinsă.",
        ];

        $data = [
            'payout_id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'action' => $action,
        ];

        if ($action === 'rejected' && $this->rejection_reason) {
            $data['rejection_reason'] = $this->rejection_reason;
        }

        MarketplaceNotification::create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'marketplace_organizer_id' => $this->marketplace_organizer_id,
            'type' => $typeMap[$action] ?? MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'title' => $titleMap[$action] ?? 'Actualizare plată',
            'message' => $messageMap[$action] ?? "Actualizare pentru plata {$this->reference}.",
            'data' => $data,
            'actionable_type' => self::class,
            'actionable_id' => $this->id,
            'action_url' => "/organizator/sold",
        ]);
    }

    /**
     * Send notification to marketplace admins about new payout request
     */
    public function notifyAdmins(): void
    {
        // Get all active admins for this marketplace client
        $admins = MarketplaceAdmin::where('marketplace_client_id', $this->marketplace_client_id)
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new MarketplaceAdminPayoutRequestNotification($this));
        }
    }
}

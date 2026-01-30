<?php

namespace App\Models;

use App\Services\OrganizerNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServiceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_event_id',
        'service_type',
        'config',
        'subtotal',
        'tax',
        'total',
        'currency',
        'payment_method',
        'payment_status',
        'paid_at',
        'payment_reference',
        'status',
        'scheduled_at',
        'executed_at',
        'sent_count',
        'brevo_campaign_id',
        'service_start_date',
        'service_end_date',
        'admin_notes',
        'assigned_to',
    ];

    protected $casts = [
        'config' => 'array',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    // Payment status constants
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';

    // Service type constants
    public const TYPE_FEATURING = 'featuring';
    public const TYPE_EMAIL = 'email';
    public const TYPE_TRACKING = 'tracking';
    public const TYPE_CAMPAIGN = 'campaign';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->marketplace_client_id);
            }
        });
    }

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(int $marketplaceClientId): string
    {
        $year = date('Y');
        $prefix = 'SVC';

        $lastOrder = self::where('marketplace_client_id', $marketplaceClientId)
            ->where('order_number', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $newNumber);
    }

    /**
     * Get service type label
     */
    public function getServiceTypeLabelAttribute(): string
    {
        return match ($this->service_type) {
            self::TYPE_FEATURING => 'Promovare Eveniment',
            self::TYPE_EMAIL => 'Email Marketing',
            self::TYPE_TRACKING => 'Ad Tracking',
            self::TYPE_CAMPAIGN => 'Creare Campanie',
            default => $this->service_type,
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_PAYMENT => 'Asteapta Plata',
            self::STATUS_PROCESSING => 'In Procesare',
            self::STATUS_ACTIVE => 'Activ',
            self::STATUS_COMPLETED => 'Finalizat',
            self::STATUS_CANCELLED => 'Anulat',
            self::STATUS_REFUNDED => 'Rambursat',
            default => $this->status,
        };
    }

    /**
     * Get payment status label
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_PENDING => 'In Asteptare',
            self::PAYMENT_PAID => 'Platit',
            self::PAYMENT_FAILED => 'Esuat',
            self::PAYMENT_REFUNDED => 'Rambursat',
            default => $this->payment_status,
        };
    }

    /**
     * Get status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING_PAYMENT => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_COMPLETED => 'primary',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_REFUNDED => 'danger',
            default => 'gray',
        };
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid(string $paymentReference = null): self
    {
        $this->update([
            'payment_status' => self::PAYMENT_PAID,
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
            'status' => self::STATUS_PROCESSING,
        ]);

        return $this;
    }

    /**
     * Activate the service
     */
    public function activate(): self
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
        ]);

        // Notify organizer that service has started
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'started');
        } catch (\Exception $e) {
            Log::warning('Failed to send service started notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Complete the service
     */
    public function complete(): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
        ]);

        // Notify organizer that service is completed
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'completed');
        } catch (\Exception $e) {
            Log::warning('Failed to send service completed notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Mark that results are available
     */
    public function markResultsAvailable(): self
    {
        // Notify organizer that results are ready
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'results');
        } catch (\Exception $e) {
            Log::warning('Failed to send service results notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Notify about invoice generation
     */
    public function notifyInvoiceGenerated(): self
    {
        // Notify organizer about invoice
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'invoice');
        } catch (\Exception $e) {
            Log::warning('Failed to send service invoice notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Cancel the order
     */
    public function cancel(): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        return $this;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_PAYMENT,
        ]);
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID
            && in_array($this->status, [
                self::STATUS_PROCESSING,
                self::STATUS_ACTIVE,
            ]);
    }

    // Relationships

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class, 'marketplace_event_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes

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
        return $query->whereIn('status', [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PROCESSING,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('service_type', $type);
    }
}

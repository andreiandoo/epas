<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplaceOrganizerPromoCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_event_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'applies_to',
        'ticket_type_id',
        'min_purchase_amount',
        'max_discount_amount',
        'min_tickets',
        'usage_limit',
        'usage_limit_per_customer',
        'usage_count',
        'starts_at',
        'expires_at',
        'status',
        'is_public',
        'metadata',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($promoCode) {
            if (empty($promoCode->code)) {
                $promoCode->code = strtoupper(Str::random(8));
            } else {
                $promoCode->code = strtoupper($promoCode->code);
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class, 'marketplace_event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTicketType::class);
    }

    public function usage(): HasMany
    {
        return $this->hasMany(MarketplacePromoCodeUsage::class, 'promo_code_id');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return $this->status === 'expired';
    }

    public function isExhausted(): bool
    {
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return true;
        }

        return $this->status === 'exhausted';
    }

    public function hasStarted(): bool
    {
        if (!$this->starts_at) {
            return true;
        }

        return $this->starts_at->isPast();
    }

    // =========================================
    // Validation
    // =========================================

    /**
     * Check if the promo code is valid for use
     */
    public function isValid(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (!$this->hasStarted()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if ($this->isExhausted()) {
            return false;
        }

        return true;
    }

    /**
     * Validate promo code for a specific cart
     */
    public function validateForCart(array $cart, ?string $customerEmail = null, ?int $customerId = null): array
    {
        // Check basic validity
        if (!$this->isValid()) {
            return ['valid' => false, 'reason' => 'Promo code is not active'];
        }

        // Check minimum purchase amount
        $cartTotal = $cart['total'] ?? 0;
        if ($this->min_purchase_amount && $cartTotal < (float) $this->min_purchase_amount) {
            return [
                'valid' => false,
                'reason' => "Minimum purchase amount is {$this->min_purchase_amount}",
            ];
        }

        // Check minimum tickets
        $ticketCount = $cart['ticket_count'] ?? count($cart['items'] ?? []);
        if ($this->min_tickets && $ticketCount < $this->min_tickets) {
            return [
                'valid' => false,
                'reason' => "Minimum {$this->min_tickets} tickets required",
            ];
        }

        // Check per-customer usage limit
        if ($this->usage_limit_per_customer && $customerEmail) {
            $customerUsageCount = $this->usage()
                ->where('customer_email', $customerEmail)
                ->count();

            if ($customerUsageCount >= $this->usage_limit_per_customer) {
                return ['valid' => false, 'reason' => 'You have already used this promo code'];
            }
        }

        // Check event applicability
        if ($this->applies_to === 'specific_event' && $this->marketplace_event_id) {
            $eventId = $cart['event_id'] ?? null;
            if ($eventId && $eventId != $this->marketplace_event_id) {
                return ['valid' => false, 'reason' => 'Promo code is not valid for this event'];
            }
        }

        // Check ticket type applicability
        if ($this->applies_to === 'ticket_type' && $this->ticket_type_id) {
            $hasApplicableTicket = false;
            foreach ($cart['items'] ?? [] as $item) {
                if (($item['ticket_type_id'] ?? null) == $this->ticket_type_id) {
                    $hasApplicableTicket = true;
                    break;
                }
            }

            if (!$hasApplicableTicket) {
                return ['valid' => false, 'reason' => 'Promo code is not valid for selected ticket types'];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    // =========================================
    // Discount Calculation
    // =========================================

    /**
     * Calculate discount amount for a cart
     */
    public function calculateDiscount(array $cart): array
    {
        $subtotal = (float) ($cart['total'] ?? 0);
        $applicableAmount = $this->getApplicableAmount($cart);

        if ($this->type === 'percentage') {
            $discount = $applicableAmount * ((float) $this->value / 100);
        } else {
            $discount = min((float) $this->value, $applicableAmount);
        }

        // Apply max discount cap
        if ($this->max_discount_amount && $discount > (float) $this->max_discount_amount) {
            $discount = (float) $this->max_discount_amount;
        }

        $discount = round($discount, 2);

        return [
            'discount_amount' => $discount,
            'original_amount' => $subtotal,
            'final_amount' => max(0, $subtotal - $discount),
            'applied_to' => $this->applies_to,
        ];
    }

    /**
     * Get the amount the discount should be applied to
     */
    protected function getApplicableAmount(array $cart): float
    {
        if ($this->applies_to === 'all_events') {
            return (float) ($cart['total'] ?? 0);
        }

        if ($this->applies_to === 'specific_event' && $this->marketplace_event_id) {
            $total = 0;
            foreach ($cart['items'] ?? [] as $item) {
                if (($item['event_id'] ?? null) == $this->marketplace_event_id) {
                    $total += (float) ($item['total'] ?? 0);
                }
            }
            return $total;
        }

        if ($this->applies_to === 'ticket_type' && $this->ticket_type_id) {
            $total = 0;
            foreach ($cart['items'] ?? [] as $item) {
                if (($item['ticket_type_id'] ?? null) == $this->ticket_type_id) {
                    $total += (float) ($item['total'] ?? 0);
                }
            }
            return $total;
        }

        return (float) ($cart['total'] ?? 0);
    }

    // =========================================
    // Usage Tracking
    // =========================================

    /**
     * Record usage of this promo code
     */
    public function recordUsage(Order $order, float $discountApplied, ?MarketplaceCustomer $customer = null, ?string $ipAddress = null): MarketplacePromoCodeUsage
    {
        $usage = $this->usage()->create([
            'order_id' => $order->id,
            'marketplace_customer_id' => $customer?->id,
            'customer_email' => $order->customer_email,
            'discount_applied' => $discountApplied,
            'order_total' => $order->total,
            'ip_address' => $ipAddress,
        ]);

        // Increment usage count
        $this->increment('usage_count');

        // Check if exhausted
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            $this->update(['status' => 'exhausted']);
        }

        return $usage;
    }

    // =========================================
    // Actions
    // =========================================

    /**
     * Activate the promo code
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the promo code
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * Get formatted discount description
     */
    public function getFormattedDiscount(): string
    {
        if ($this->type === 'percentage') {
            return "{$this->value}%";
        }

        return number_format($this->value, 2) . ' RON';
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('marketplace_organizer_id', $organizerId);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where(function ($q) use ($eventId) {
            $q->where('applies_to', 'all_events')
              ->orWhere(function ($q2) use ($eventId) {
                  $q2->where('applies_to', 'specific_event')
                     ->where('marketplace_event_id', $eventId);
              });
        });
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereRaw('usage_count < usage_limit');
            });
    }
}

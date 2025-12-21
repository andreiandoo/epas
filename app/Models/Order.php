<?php

namespace App\Models;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplacePayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'organizer_id',
        'customer_email',
        'total_cents',
        'status',
        'meta',
        'customer_id',
        'promo_code_id',
        'promo_code',
        'promo_discount',
        // Commission tracking for marketplace orders
        'tixello_commission',
        'marketplace_commission',
        'organizer_revenue',
        'payout_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'tixello_commission' => 'decimal:2',
        'marketplace_commission' => 'decimal:2',
        'organizer_revenue' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the organizer for this order (marketplace orders only).
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'organizer_id');
    }

    /**
     * Get the payout this order is included in.
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayout::class, 'payout_id');
    }

    /**
     * Check if this is a marketplace order.
     */
    public function isMarketplaceOrder(): bool
    {
        return $this->organizer_id !== null;
    }

    /**
     * Check if this order has been paid out.
     */
    public function isPaidOut(): bool
    {
        return $this->payout_id !== null && $this->payout?->isCompleted();
    }

    /**
     * Get the total amount as decimal (from cents).
     */
    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }

    /**
     * Calculate and store commission breakdown.
     * Called when order is paid for marketplace orders.
     */
    public function calculateCommission(): array
    {
        if (!$this->isMarketplaceOrder()) {
            return [];
        }

        $breakdown = $this->tenant->calculateMarketplaceCommission(
            $this->total,
            $this->organizer
        );

        $this->tixello_commission = $breakdown['tixello_commission'];
        $this->marketplace_commission = $breakdown['marketplace_commission'];
        $this->organizer_revenue = $breakdown['organizer_revenue'];
        $this->save();

        return $breakdown;
    }

    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            // sincronizare email <-> customer (cum ți-am dat anterior)
            if ($order->customer_id && empty($order->customer_email)) {
                $email = $order->customer?->email;
                if ($email) {
                    $order->customer_email = $email;
                }
            }
            if (!$order->customer_id && $order->tenant_id && $order->customer_email) {
                $customer = Customer::where('email', $order->customer_email)->first();
                if (!$customer) {
                    $customer = Customer::create([
                        'tenant_id' => $order->tenant_id, // legacy
                        'email'     => $order->customer_email,
                        'primary_tenant_id' => $order->tenant_id,
                    ]);
                }
                $order->customer_id = $customer->id;
            }
        });

        static::saved(function (Order $order) {
            // asigură membership în pivot pt. tenantul comenzii
            if ($order->customer_id && $order->tenant_id) {
                $customer = $order->customer;
                if ($customer && !$customer->tenants()->where('tenants.id', $order->tenant_id)->exists()) {
                    $customer->tenants()->attach($order->tenant_id);
                }
                // setează primary dacă lipsește
                if ($customer && !$customer->primary_tenant_id) {
                    $customer->primary_tenant_id = $order->tenant_id;
                    $customer->save();
                }
            }

            // Update ticket statuses based on order status
            if ($order->wasChanged('status')) {
                $newStatus = $order->status;

                // When order is paid/confirmed, tickets become valid
                if (in_array($newStatus, ['paid', 'confirmed', 'completed'])) {
                    $order->tickets()->update(['status' => 'valid']);
                }
                // When order is cancelled/refunded, tickets become cancelled
                elseif (in_array($newStatus, ['cancelled', 'refunded'])) {
                    $order->tickets()->update(['status' => 'cancelled']);
                }
                // When order is pending, tickets stay pending
                elseif ($newStatus === 'pending') {
                    $order->tickets()->update(['status' => 'pending']);
                }
            }
        });
    }

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_email', 'total_cents', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Order {$eventName}")
            ->useLogName('tenant');
    }

    /**
     * Add tenant_id to activity properties for scoping
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }
}

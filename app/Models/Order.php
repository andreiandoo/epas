<?php

namespace App\Models;

use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatHold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'customer_email',
        'total_cents',
        'status',
        'meta',
        'customer_id',
        'promo_code_id',
        'promo_code',
        'promo_discount',
        // Marketplace fields
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_customer_id',
        'marketplace_event_id',
        'event_id',
        'order_number',
        'subtotal',
        'discount_amount',
        'commission_rate',
        'commission_amount',
        'total',
        'currency',
        'source',
        'customer_name',
        'customer_phone',
        'payment_status',
        'payment_reference',
        'payment_processor',
        'payment_error',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'metadata',
    ];

    protected $casts = [
        'meta' => 'array',
        'metadata' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
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

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    public function marketplaceCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class);
    }

    public function marketplaceEvent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(OrderReminder::class);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(MarketplaceRefundRequest::class);
    }

    public function activeRefundRequest()
    {
        return $this->hasOne(MarketplaceRefundRequest::class)
            ->whereIn('status', ['pending', 'under_review', 'approved', 'processing'])
            ->latest();
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
                // When order is cancelled/refunded, tickets become cancelled + release seats + restore stock
                elseif (in_array($newStatus, ['cancelled', 'refunded', 'expired'])) {
                    $order->tickets()->update(['status' => 'cancelled']);
                    $order->releaseSeatsAndRestoreStock();
                }
                // When order is pending, tickets stay pending
                elseif ($newStatus === 'pending') {
                    $order->tickets()->update(['status' => 'pending']);
                }
            }
        });

        static::deleting(function (Order $order) {
            // Release seats and restore stock before deletion
            $order->releaseSeatsAndRestoreStock();
        });
    }

    /**
     * Release held/sold seats and restore ticket stock for this order
     */
    public function releaseSeatsAndRestoreStock(): void
    {
        try {
            // 1. Release seats from order meta or ticket meta
            $seatInfo = $this->extractSeatInfo();
            foreach ($seatInfo as $item) {
                $eventSeatingId = $item['event_seating_id'];
                $seatUids = $item['seat_uids'];

                $released = EventSeat::where('event_seating_id', $eventSeatingId)
                    ->whereIn('seat_uid', $seatUids)
                    ->whereIn('status', ['held', 'sold'])
                    ->update([
                        'status' => 'available',
                        'version' => DB::raw('version + 1'),
                        'last_change_at' => now(),
                    ]);

                SeatHold::where('event_seating_id', $eventSeatingId)
                    ->whereIn('seat_uid', $seatUids)
                    ->delete();

                if ($released > 0) {
                    Log::channel('marketplace')->info('Order: Released seats', [
                        'order_id' => $this->id,
                        'order_number' => $this->order_number,
                        'event_seating_id' => $eventSeatingId,
                        'released_count' => $released,
                    ]);
                }
            }

            // 2. Restore stock for ticket types
            $this->load('items');
            foreach ($this->items as $orderItem) {
                if ($orderItem->quantity > 0) {
                    // Try MarketplaceTicketType first
                    $mtt = MarketplaceTicketType::find($orderItem->ticket_type_id);
                    if ($mtt) {
                        $mtt->decrement('quantity_sold', min($orderItem->quantity, $mtt->quantity_sold ?? 0));
                        if ($mtt->status === 'sold_out' && $mtt->quantity_sold < ($mtt->quantity ?? PHP_INT_MAX)) {
                            $mtt->update(['status' => 'active']);
                        }
                    } else {
                        // Fallback to TicketType
                        TicketType::where('id', $orderItem->ticket_type_id)
                            ->where('quota_sold', '>=', $orderItem->quantity)
                            ->decrement('quota_sold', $orderItem->quantity);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Order: Failed to release seats/stock', [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract seat info from order meta or ticket meta
     */
    protected function extractSeatInfo(): array
    {
        // Try order meta first
        $seatedItems = $this->meta['seated_items'] ?? [];
        if (!empty($seatedItems)) {
            return $seatedItems;
        }

        // Fallback: extract from individual tickets
        $tickets = $this->tickets()->whereNotNull('meta')->get();
        $seatsByLayout = [];

        foreach ($tickets as $ticket) {
            $meta = $ticket->meta;
            $seatUid = $meta['seat_uid'] ?? null;
            $eventSeatingId = $meta['event_seating_id'] ?? null;

            if ($seatUid && $eventSeatingId) {
                $key = (string) $eventSeatingId;
                if (!isset($seatsByLayout[$key])) {
                    $seatsByLayout[$key] = [
                        'event_seating_id' => (int) $eventSeatingId,
                        'seat_uids' => [],
                    ];
                }
                $seatsByLayout[$key]['seat_uids'][] = $seatUid;
            }
        }

        return array_values($seatsByLayout);
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

<?php

namespace App\Models;

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
        'customer_email',
        'total_cents',
        'status',
        'meta',
        'customer_id',
    ];

    protected $casts = [
        'meta' => 'array',
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

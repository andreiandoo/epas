<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Jobs\TrackOrderConversionJob;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        if (in_array($order->status, ['paid', 'confirmed', 'completed'])) {
            $this->dispatchConversionTracking($order);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            $newStatus = $order->status;
            $oldStatus = $order->getOriginal('status');

            if (in_array($newStatus, ['paid', 'confirmed', 'completed']) &&
                !in_array($oldStatus, ['paid', 'confirmed', 'completed'])) {
                $this->dispatchConversionTracking($order);
            }
        }
    }

    /**
     * Dispatch async conversion tracking and notifications.
     *
     * CoreCustomer creation remains synchronous (needed immediately for checkout flow).
     * Tracking, analytics, and notifications are dispatched to the queue.
     */
    protected function dispatchConversionTracking(Order $order): void
    {
        // Synchronous: ensure CoreCustomer exists (needed for immediate checkout response)
        $this->ensureCoreCustomerExists($order);

        // Async: tracking, analytics, milestone attribution, organizer notification
        TrackOrderConversionJob::dispatch(
            orderId: $order->id,
            notifyOrganizer: (bool) $order->marketplace_organizer_id,
        );
    }

    /**
     * Ensure CoreCustomer exists for the order (synchronous, lightweight).
     */
    protected function ensureCoreCustomerExists(Order $order): void
    {
        if (!$order->customer_email) {
            return;
        }

        $existing = CoreCustomer::findByEmail($order->customer_email);
        if ($existing) {
            return;
        }

        try {
            $customer = $order->customer;

            CoreCustomer::create([
                'tenant_id' => $order->tenant_id,
                'email' => $order->customer_email,
                'email_hash' => hash('sha256', strtolower(trim($order->customer_email))),
                'first_name' => $customer?->first_name ?? $order->meta['first_name'] ?? null,
                'last_name' => $customer?->last_name ?? $order->meta['last_name'] ?? null,
                'phone' => $customer?->phone ?? $order->meta['phone'] ?? null,
                'phone_hash' => isset($order->meta['phone'])
                    ? hash('sha256', preg_replace('/[^0-9]/', '', $order->meta['phone']))
                    : null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'total_orders' => 1,
                'total_spent' => $order->total_cents / 100,
                'first_order_at' => now(),
                'last_order_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create CoreCustomer from order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }
}

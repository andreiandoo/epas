<?php

namespace App\Services\DoorSales;

use App\Models\DoorSale;
use App\Models\DoorSaleItem;
use App\Models\DoorSalePlatformFee;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DoorSalesService
{
    /**
     * Get available events for door sales
     */
    public function getAvailableEvents(string $tenantId): array
    {
        return Event::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('end_date', '>=', now())
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'venue'])
            ->toArray();
    }

    /**
     * Get ticket types with availability for an event
     */
    public function getTicketTypes(int $eventId): array
    {
        return TicketType::where('event_id', $eventId)
            ->where('status', 'active')
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'price' => $type->price,
                    'available' => $type->quantity_available ?? 999,
                    'max_per_order' => $type->max_per_order ?? 10,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate totals before payment
     */
    public function calculate(array $data): array
    {
        $items = $data['items'];
        $subtotal = 0;

        $itemDetails = [];
        foreach ($items as $item) {
            $ticketType = TicketType::findOrFail($item['ticket_type_id']);
            $itemTotal = $ticketType->price * $item['quantity'];
            $subtotal += $itemTotal;

            $itemDetails[] = [
                'ticket_type_id' => $ticketType->id,
                'name' => $ticketType->name,
                'quantity' => $item['quantity'],
                'unit_price' => $ticketType->price,
                'total' => $itemTotal,
            ];
        }

        $platformFee = $this->calculatePlatformFee($subtotal);
        $processingFee = $this->calculateProcessingFee($subtotal + $platformFee);
        $total = $subtotal + $platformFee + $processingFee;

        return [
            'items' => $itemDetails,
            'subtotal' => round($subtotal, 2),
            'platform_fee' => round($platformFee, 2),
            'processing_fee' => round($processingFee, 2),
            'total' => round($total, 2),
            'currency' => $data['currency'] ?? 'EUR',
        ];
    }

    /**
     * Process door sale payment
     */
    public function process(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Calculate totals
            $calculation = $this->calculate($data);

            // Create door sale record
            $doorSale = DoorSale::create([
                'tenant_id' => $data['tenant_id'],
                'event_id' => $data['event_id'],
                'user_id' => $data['user_id'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'platform_fee' => $calculation['platform_fee'],
                'payment_processing_fee' => $calculation['processing_fee'],
                'total' => $calculation['total'],
                'currency' => $calculation['currency'],
                'payment_method' => $data['payment_method'],
                'status' => DoorSale::STATUS_PROCESSING,
                'device_id' => $data['device_id'] ?? null,
            ]);

            // Create line items
            foreach ($calculation['items'] as $item) {
                DoorSaleItem::create([
                    'door_sale_id' => $doorSale->id,
                    'ticket_type_id' => $item['ticket_type_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                ]);
            }

            // Process payment via Stripe
            $paymentResult = $this->processPayment($doorSale, $data);

            if (!$paymentResult['success']) {
                $doorSale->update([
                    'status' => DoorSale::STATUS_FAILED,
                    'failure_reason' => $paymentResult['error'],
                ]);

                return [
                    'success' => false,
                    'error' => $paymentResult['error'],
                    'door_sale_id' => $doorSale->id,
                ];
            }

            // Update with payment details
            $doorSale->update([
                'status' => DoorSale::STATUS_COMPLETED,
                'gateway_transaction_id' => $paymentResult['transaction_id'],
                'gateway_payment_intent_id' => $paymentResult['payment_intent_id'],
            ]);

            // Create platform fee record
            DoorSalePlatformFee::create([
                'tenant_id' => $data['tenant_id'],
                'door_sale_id' => $doorSale->id,
                'transaction_amount' => $calculation['subtotal'],
                'fee_percentage' => config('door-sales.platform_fee_percentage', 2.5),
                'fee_amount' => $calculation['platform_fee'],
            ]);

            // Issue tickets
            $order = $this->issueTickets($doorSale);
            $doorSale->update(['order_id' => $order->id]);

            // Send tickets via email if provided
            if ($doorSale->customer_email) {
                $this->sendTickets($doorSale);
            }

            return [
                'success' => true,
                'door_sale' => $doorSale->fresh(['items', 'order']),
                'tickets_issued' => $order->tickets->count(),
            ];
        });
    }

    /**
     * Process payment via Stripe Tap to Pay
     */
    protected function processPayment(DoorSale $doorSale, array $data): array
    {
        // In real implementation, use Stripe Terminal SDK
        // This is a placeholder for the payment processing logic

        try {
            // $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            //
            // For Tap to Pay, you would:
            // 1. Create PaymentIntent on server
            // 2. Collect payment on device using Terminal SDK
            // 3. Confirm payment

            // Simulated success response
            return [
                'success' => true,
                'transaction_id' => 'txn_' . Str::random(24),
                'payment_intent_id' => 'pi_' . Str::random(24),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Issue tickets and create order
     */
    protected function issueTickets(DoorSale $doorSale): Order
    {
        // Find or create customer
        $customer = null;
        if ($doorSale->customer_email) {
            $customer = Customer::firstOrCreate(
                [
                    'tenant_id' => $doorSale->tenant_id,
                    'email' => $doorSale->customer_email,
                ],
                [
                    'name' => $doorSale->customer_name ?? 'Door Sale Customer',
                ]
            );
        }

        // Create order
        $order = Order::create([
            'tenant_id' => $doorSale->tenant_id,
            'event_id' => $doorSale->event_id,
            'customer_id' => $customer?->id,
            'order_number' => 'DS-' . strtoupper(Str::random(8)),
            'subtotal' => $doorSale->subtotal,
            'fees' => $doorSale->platform_fee + $doorSale->payment_processing_fee,
            'total' => $doorSale->total,
            'currency' => $doorSale->currency,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'door_sale',
            'source' => 'door_sale',
            'meta' => ['door_sale_id' => $doorSale->id],
        ]);

        // Create tickets
        foreach ($doorSale->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                Ticket::create([
                    'tenant_id' => $doorSale->tenant_id,
                    'event_id' => $doorSale->event_id,
                    'order_id' => $order->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'customer_id' => $customer?->id,
                    'barcode' => strtoupper(Str::random(12)),
                    'status' => 'valid',
                    'price' => $item->unit_price,
                ]);
            }
        }

        return $order;
    }

    /**
     * Send tickets via email
     */
    protected function sendTickets(DoorSale $doorSale): void
    {
        // Dispatch job to send tickets
        // SendDoorSaleTicketsJob::dispatch($doorSale);
    }

    /**
     * Process refund
     */
    public function refund(DoorSale $doorSale, ?float $amount = null): array
    {
        if (!$doorSale->canRefund()) {
            return ['success' => false, 'error' => 'This sale cannot be refunded'];
        }

        $refundAmount = $amount ?? ($doorSale->total - $doorSale->refunded_amount);

        // Process refund via Stripe
        // $stripe->refunds->create([...]);

        $doorSale->update([
            'refunded_amount' => $doorSale->refunded_amount + $refundAmount,
            'status' => $doorSale->refunded_amount + $refundAmount >= $doorSale->total
                ? DoorSale::STATUS_REFUNDED
                : 'partially_refunded',
        ]);

        // Void tickets if full refund
        if ($doorSale->status === DoorSale::STATUS_REFUNDED && $doorSale->order) {
            $doorSale->order->tickets()->update(['status' => 'voided']);
        }

        return [
            'success' => true,
            'refunded_amount' => $refundAmount,
            'door_sale' => $doorSale->fresh(),
        ];
    }

    /**
     * Get sales history
     */
    public function getHistory(string $tenantId, array $filters = []): array
    {
        $query = DoorSale::forTenant($tenantId)
            ->with(['event', 'user', 'items.ticketType'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Get daily summary
     */
    public function getDailySummary(string $tenantId, ?int $eventId = null): array
    {
        $query = DoorSale::forTenant($tenantId)
            ->completed()
            ->today();

        if ($eventId) {
            $query->forEvent($eventId);
        }

        $sales = $query->get();

        return [
            'total_sales' => $sales->count(),
            'total_tickets' => $sales->sum(fn($s) => $s->getTotalTickets()),
            'total_revenue' => $sales->sum('subtotal'),
            'total_fees' => $sales->sum('platform_fee'),
            'by_payment_method' => $sales->groupBy('payment_method')->map->count(),
        ];
    }

    /**
     * Calculate platform fee
     */
    protected function calculatePlatformFee(float $amount): float
    {
        $percentage = config('door-sales.platform_fee_percentage', 2.5);
        $minFee = config('door-sales.min_fee', 0.10);

        $fee = $amount * ($percentage / 100);

        return max($fee, $minFee);
    }

    /**
     * Calculate payment processing fee (Stripe)
     */
    protected function calculateProcessingFee(float $amount): float
    {
        $percentage = config('door-sales.processing_fee_percentage', 1.4);
        $fixed = config('door-sales.processing_fee_fixed', 0.25);

        return ($amount * ($percentage / 100)) + $fixed;
    }
}

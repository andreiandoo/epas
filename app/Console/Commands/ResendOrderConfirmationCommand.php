<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Http\Controllers\Api\MarketplaceClient\PaymentController;
use Illuminate\Console\Command;

class ResendOrderConfirmationCommand extends Command
{
    protected $signature = 'orders:resend-confirmation
        {--order-ids= : Comma-separated order IDs}
        {--event-id= : Send to all completed orders for this event}
        {--ticket-type-id= : Filter by ticket type ID}
        {--dry-run : Show what would be sent without sending}';

    protected $description = 'Resend order confirmation emails (with ticket PDF) for specified orders';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($this->option('order-ids')) {
            $ids = array_map('intval', explode(',', $this->option('order-ids')));
            $orders = Order::whereIn('id', $ids)->whereIn('status', ['completed', 'confirmed', 'paid'])->get();
        } elseif ($this->option('event-id')) {
            $query = Order::where('event_id', $this->option('event-id'))
                ->whereIn('status', ['completed', 'confirmed', 'paid']);

            if ($this->option('ticket-type-id')) {
                $ttId = (int) $this->option('ticket-type-id');
                $query->whereHas('tickets', fn ($q) => $q->where('ticket_type_id', $ttId));
            }

            $orders = $query->get();
        } else {
            $this->error('Provide --order-ids or --event-id');
            return 1;
        }

        $this->info("Orders to send: {$orders->count()}");

        if ($orders->isEmpty()) {
            $this->info('No orders found.');
            return 0;
        }

        foreach ($orders as $order) {
            $email = $order->customer_email;
            $this->line("  Order #{$order->id} ({$order->order_number}) → {$email}");
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No emails sent.');
            return 0;
        }

        $sent = 0;
        $failed = 0;
        $controller = app(PaymentController::class);

        foreach ($orders as $order) {
            try {
                $order->load(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType', 'tickets.ticketType', 'tickets.event', 'marketplaceEvent', 'marketplaceClient']);
                $controller->sendOrderConfirmationEmail($order);
                $sent++;
                $this->line("  ✓ Sent to {$order->customer_email} (Order {$order->order_number})");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ Failed {$order->order_number}: {$e->getMessage()}");
            }
        }

        $this->info("Done! Sent: {$sent} | Failed: {$failed}");
        return 0;
    }
}

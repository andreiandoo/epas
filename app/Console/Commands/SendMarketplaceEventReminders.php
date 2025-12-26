<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\MarketplaceEventReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendMarketplaceEventReminders extends Command
{
    protected $signature = 'marketplace:send-event-reminders {--type=all : Type of reminder (24h, 1h, or all)}';

    protected $description = 'Send event reminder emails to marketplace customers';

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type === 'all' || $type === '24h') {
            $this->send24HourReminders();
        }

        if ($type === 'all' || $type === '1h') {
            $this->send1HourReminders();
        }

        return self::SUCCESS;
    }

    protected function send24HourReminders(): void
    {
        $this->info('Sending 24-hour reminders...');

        // Find orders for events happening in ~24 hours (23-25 hour window)
        $orders = Order::whereNotNull('marketplace_client_id')
            ->where('status', 'completed')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [
                    now()->addHours(23),
                    now()->addHours(25),
                ]);
            })
            ->whereDoesntHave('reminders', function ($q) {
                $q->where('type', '24h');
            })
            ->with(['event', 'marketplaceClient'])
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            if (!$order->customer_email) {
                continue;
            }

            try {
                Notification::route('mail', $order->customer_email)
                    ->notify(new MarketplaceEventReminderNotification($order, '24h'));

                // Record that we sent this reminder
                $order->reminders()->create([
                    'type' => '24h',
                    'sent_at' => now(),
                ]);

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to send 24h reminder', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$count} 24-hour reminders.");
    }

    protected function send1HourReminders(): void
    {
        $this->info('Sending 1-hour reminders...');

        // Find orders for events happening in ~1 hour (45-75 minute window)
        $orders = Order::whereNotNull('marketplace_client_id')
            ->where('status', 'completed')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [
                    now()->addMinutes(45),
                    now()->addMinutes(75),
                ]);
            })
            ->whereDoesntHave('reminders', function ($q) {
                $q->where('type', '1h');
            })
            ->with(['event', 'marketplaceClient'])
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            if (!$order->customer_email) {
                continue;
            }

            try {
                Notification::route('mail', $order->customer_email)
                    ->notify(new MarketplaceEventReminderNotification($order, '1h'));

                // Record that we sent this reminder
                $order->reminders()->create([
                    'type' => '1h',
                    'sent_at' => now(),
                ]);

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to send 1h reminder', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$count} 1-hour reminders.");
    }
}

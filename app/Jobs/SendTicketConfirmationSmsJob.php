<?php

namespace App\Jobs;

use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Models\SmsCredit;
use App\Services\Sms\SendSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTicketConfirmationSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(protected int $orderId) {}

    public function handle(SendSmsService $smsService): void
    {
        $order = Order::with(['event', 'marketplaceClient', 'tickets'])->find($this->orderId);

        if (!$order || !$order->customer_phone) {
            return;
        }

        $client = $order->marketplaceClient;
        if (!$client) {
            return;
        }

        // Check if marketplace has SMS microservice active with transactional enabled
        if (!$client->hasMicroservice('sms-notifications')) {
            return;
        }

        $config = $client->getMicroserviceConfig('sms-notifications');
        if (!($config['transactional_enabled'] ?? false)) {
            return;
        }

        // Check available credits
        $available = SmsCredit::getAvailableCredits($client, 'transactional');
        if ($available <= 0) {
            Log::channel('marketplace')->warning('SMS transactional credit exhausted', [
                'marketplace_client_id' => $client->id,
                'order_id' => $order->id,
            ]);
            return;
        }

        // Consume credit
        if (!SmsCredit::consumeCredit($client, 'transactional')) {
            return;
        }

        // Send the SMS
        $smsService->sendTicketConfirmation($order);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('marketplace')->error('SendTicketConfirmationSmsJob failed', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}

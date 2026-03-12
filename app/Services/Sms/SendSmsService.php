<?php

namespace App\Services\Sms;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Microservice;
use App\Models\Order;
use App\Models\SmsCredit;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSmsService
{
    protected string $username;
    protected string $apiKey;
    protected string $from;
    protected string $endpoint;

    public function __construct()
    {
        $config = config('microservices.sms.sendsms');
        $this->username = $config['username'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->from = $config['from'] ?? 'Tixello';
        $this->endpoint = $config['endpoint'] ?? 'https://api.sendsms.ro/json';
    }

    public function sendSms(string $to, string $text, array $context = []): array
    {
        $phone = $this->formatPhoneNumber($to);
        $type = $context['type'] ?? 'transactional';

        // Create log entry
        $log = SmsLog::create([
            'tenant_id' => $context['tenant_id'] ?? null,
            'marketplace_client_id' => $context['marketplace_client_id'] ?? null,
            'phone' => $phone,
            'message_text' => $text,
            'type' => $type,
            'status' => 'queued',
            'cost' => $this->getSmsCost($type, $context['marketplace_client_id'] ?? null),
            'currency' => 'EUR',
            'event_id' => $context['event_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
        ]);

        if (!config('microservices.sms.enabled')) {
            $log->update(['status' => 'failed', 'error_message' => 'SMS service disabled']);
            return ['status' => -1, 'message' => 'SMS service disabled', 'log_id' => $log->id];
        }

        // Build report URL for delivery callbacks
        $reportUrl = url("/api/sms/delivery-report/{$log->id}?status=%d");

        try {
            $response = Http::timeout(10)->get($this->endpoint, [
                'action' => 'message_send',
                'username' => $this->username,
                'password' => $this->apiKey,
                'to' => $phone,
                'from' => $this->from,
                'text' => $text,
                'report_mask' => 19, // delivered + undelivered + failed
                'report_url' => $reportUrl,
            ]);

            $result = $response->json();

            if (isset($result['status']) && $result['status'] >= 1) {
                $log->update([
                    'status' => 'sent',
                    'provider_id' => $result['details'] ?? null,
                ]);

                return [
                    'status' => 1,
                    'message' => 'Sent',
                    'provider_id' => $result['details'] ?? null,
                    'log_id' => $log->id,
                ];
            }

            $errorMsg = $result['message'] ?? 'Unknown error (status: ' . ($result['status'] ?? 'null') . ')';
            $log->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);

            Log::channel('marketplace')->error('SMS send failed', [
                'phone' => $phone,
                'error' => $errorMsg,
                'response' => $result,
            ]);

            return ['status' => $result['status'] ?? -1, 'message' => $errorMsg, 'log_id' => $log->id];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('marketplace')->error('SMS send exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return ['status' => -1, 'message' => $e->getMessage(), 'log_id' => $log->id];
        }
    }

    public function formatPhoneNumber(string $phone): string
    {
        // Remove spaces, dashes, dots
        $phone = preg_replace('/[\s\-\.\(\)]/', '', $phone);

        // Remove leading +
        $phone = ltrim($phone, '+');

        // Romanian numbers: 07xx → 407xx
        if (preg_match('/^0[2-9]\d{8}$/', $phone)) {
            $phone = '40' . substr($phone, 1);
        }

        return $phone;
    }

    public function sendTicketConfirmation(Order $order): void
    {
        $phone = $order->customer_phone;
        if (!$phone) {
            return;
        }

        $event = $order->event;
        $eventName = $event?->getTranslation('title', 'ro') ?: $event?->getTranslation('title', 'en') ?: 'eveniment';
        $ticketsUrl = $this->getTicketsUrl($order);

        $ticketCount = $order->tickets()->count();
        $ticketWord = $ticketCount === 1 ? 'biletul' : 'biletele';

        $text = "Felicitari! {$ticketWord} tau/tale pentru {$eventName} au fost confirmate. Acceseaza-le aici: {$ticketsUrl}";

        // Truncate to 160 chars for single SMS
        if (mb_strlen($text) > 160) {
            $text = "Biletele tale pentru {$eventName} sunt confirmate! Link: {$ticketsUrl}";
        }

        $this->sendSms($phone, $text, [
            'type' => 'transactional',
            'marketplace_client_id' => $order->marketplace_client_id,
            'event_id' => $order->event_id ?? $order->marketplace_event_id,
            'order_id' => $order->id,
        ]);
    }

    protected function getTicketsUrl(Order $order): string
    {
        $client = $order->marketplaceClient;
        if ($client && $client->domain) {
            $domain = preg_replace('#^(https?:?/?/?|//)#i', '', $client->domain);
            $domain = ltrim($domain, '/');
            $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
            return $protocol . '://' . $domain . '/orders/' . $order->order_number;
        }
        return config('app.url') . '/orders/' . $order->order_number;
    }

    public function getSmsCostPublic(string $type, ?int $marketplaceClientId = null): float
    {
        return $this->getSmsCost($type, $marketplaceClientId);
    }

    protected function getSmsCost(string $type, ?int $marketplaceClientId = null): float
    {
        // Try to get price from microservice metadata first
        $microservice = Microservice::where('slug', 'sms-notifications')->first();
        if ($microservice) {
            $pricing = $microservice->metadata['sms_pricing'] ?? [];
            if (isset($pricing[$type]['price'])) {
                return (float) $pricing[$type]['price'];
            }
        }

        // Fallback to config
        return config("microservices.sms.pricing.{$type}", $type === 'promotional' ? 0.50 : 0.40);
    }
}

<?php

namespace App\Services\SmsPayment;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\SmsPaymentProcessor;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Jobs\SendSmsPaymentLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS Payment Microservice
 *
 * Provides SMS-based payment collection functionality for tenants.
 * Features include:
 * - Send payment links via SMS
 * - Scheduled payment reminders
 * - Batch SMS payment requests
 * - Payment confirmation notifications
 * - SMS delivery tracking
 */
class SmsPaymentService
{
    protected Tenant $tenant;
    protected ?SmsPaymentProcessor $processor = null;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->initializeProcessor();
    }

    /**
     * Initialize the SMS payment processor
     */
    protected function initializeProcessor(): void
    {
        $config = TenantPaymentConfig::where('tenant_id', $this->tenant->id)
            ->where('processor', 'sms')
            ->where('is_active', true)
            ->first();

        if ($config) {
            $this->processor = new SmsPaymentProcessor($config);
        }
    }

    /**
     * Check if SMS payments are available for this tenant
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->processor?->isConfigured() ?? false;
    }

    /**
     * Send a payment request via SMS
     *
     * @param array $data Payment request data:
     *   - phone_number: Customer phone number (required)
     *   - amount: Payment amount (required)
     *   - currency: Currency code (default: tenant currency)
     *   - description: Payment description
     *   - order_id: Associated order ID
     *   - customer_name: Customer name
     *   - customer_email: Customer email
     *   - success_url: Redirect URL after payment
     *   - cancel_url: Redirect URL on cancel
     *   - send_async: Whether to send async via queue (default: true)
     *   - scheduled_at: Schedule SMS for later (Carbon instance)
     * @return array Result with payment_id and sms status
     */
    public function sendPaymentRequest(array $data): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('SMS Payment is not available for this tenant');
        }

        // Validate required fields
        if (empty($data['phone_number'])) {
            throw new \Exception('Phone number is required');
        }

        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Valid payment amount is required');
        }

        // Set defaults
        $data['currency'] = $data['currency'] ?? $this->tenant->currency ?? 'EUR';
        $data['description'] = $data['description'] ?? 'Payment Request';
        $data['order_id'] = $data['order_id'] ?? $this->generateOrderReference();
        $data['success_url'] = $data['success_url'] ?? $this->getDefaultSuccessUrl();
        $data['cancel_url'] = $data['cancel_url'] ?? $this->getDefaultCancelUrl();

        // Determine if async
        $sendAsync = $data['send_async'] ?? true;
        $scheduledAt = $data['scheduled_at'] ?? null;

        if ($sendAsync || $scheduledAt) {
            // Dispatch job
            $job = new SendSmsPaymentLink($this->tenant->id, $data);

            if ($scheduledAt) {
                $job->delay($scheduledAt);
            }

            dispatch($job);

            return [
                'status' => 'queued',
                'message' => $scheduledAt
                    ? 'SMS payment request scheduled for ' . $scheduledAt->toDateTimeString()
                    : 'SMS payment request queued for delivery',
                'order_id' => $data['order_id'],
            ];
        }

        // Send synchronously
        return $this->processor->createPayment($data);
    }

    /**
     * Send payment reminders for pending orders
     *
     * @param array $orderIds Order IDs to send reminders for
     * @return array Results per order
     */
    public function sendPaymentReminders(array $orderIds): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('SMS Payment is not available for this tenant');
        }

        $results = [];

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::where('tenant_id', $this->tenant->id)
                    ->where('id', $orderId)
                    ->where('payment_status', 'pending')
                    ->first();

                if (!$order) {
                    $results[$orderId] = ['status' => 'skipped', 'reason' => 'Order not found or not pending'];
                    continue;
                }

                // Get customer phone from order or related customer
                $phoneNumber = $order->phone ?? $order->customer?->phone ?? null;

                if (!$phoneNumber) {
                    $results[$orderId] = ['status' => 'skipped', 'reason' => 'No phone number available'];
                    continue;
                }

                // Generate payment URL (you'd typically have this stored or generate new)
                $paymentUrl = $this->getPaymentUrl($order);

                $sent = $this->processor->sendPaymentReminder(
                    $phoneNumber,
                    $paymentUrl,
                    $order->total,
                    $order->currency ?? $this->tenant->currency,
                    $order->order_number ?? $order->id
                );

                $results[$orderId] = [
                    'status' => $sent ? 'sent' : 'failed',
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                ];

                // Log reminder sent
                if ($sent) {
                    Log::info('SMS payment reminder sent', [
                        'tenant_id' => $this->tenant->id,
                        'order_id' => $orderId,
                    ]);
                }
            } catch (\Exception $e) {
                $results[$orderId] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Send batch payment requests
     *
     * @param array $requests Array of payment request data
     * @return array Results for each request
     */
    public function sendBatchPaymentRequests(array $requests): array
    {
        $results = [];

        foreach ($requests as $index => $request) {
            try {
                $request['send_async'] = true; // Always async for batch
                $results[$index] = $this->sendPaymentRequest($request);
            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Send payment confirmation SMS
     *
     * @param Order $order The completed order
     * @return bool Success status
     */
    public function sendPaymentConfirmation(Order $order): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $phoneNumber = $order->phone ?? $order->customer?->phone ?? null;

        if (!$phoneNumber) {
            return false;
        }

        return $this->processor->sendPaymentConfirmation(
            $phoneNumber,
            $order->total,
            $order->currency ?? $this->tenant->currency,
            $order->order_number ?? $order->id
        );
    }

    /**
     * Get SMS statistics for tenant
     *
     * @param string $period 'today', 'week', 'month'
     * @return array Statistics
     */
    public function getStatistics(string $period = 'month'): array
    {
        // This would typically query a logs table
        // For now, return structure
        return [
            'period' => $period,
            'sms_sent' => 0,
            'sms_delivered' => 0,
            'sms_failed' => 0,
            'payments_initiated' => 0,
            'payments_completed' => 0,
            'conversion_rate' => 0,
            'total_collected' => 0,
        ];
    }

    /**
     * Get configuration for SMS payments
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        if (!$this->processor) {
            return [
                'configured' => false,
                'fallback_processor' => null,
            ];
        }

        return [
            'configured' => $this->isAvailable(),
            'fallback_processor' => $this->processor->getFallbackProcessor()?->getName(),
            'sender_number' => $this->getMaskedSenderNumber(),
        ];
    }

    /**
     * Generate a unique order reference
     *
     * @return string
     */
    protected function generateOrderReference(): string
    {
        return 'SMS-' . strtoupper(Str::random(8));
    }

    /**
     * Get default success URL
     *
     * @return string
     */
    protected function getDefaultSuccessUrl(): string
    {
        $domain = $this->tenant->domain ?? config('app.url');
        return rtrim($domain, '/') . '/payment/success';
    }

    /**
     * Get default cancel URL
     *
     * @return string
     */
    protected function getDefaultCancelUrl(): string
    {
        $domain = $this->tenant->domain ?? config('app.url');
        return rtrim($domain, '/') . '/payment/cancelled';
    }

    /**
     * Get payment URL for an order
     *
     * @param Order $order
     * @return string
     */
    protected function getPaymentUrl(Order $order): string
    {
        $domain = $this->tenant->domain ?? config('app.url');
        return rtrim($domain, '/') . '/checkout/' . $order->uuid;
    }

    /**
     * Mask phone number for display
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function maskPhoneNumber(string $phoneNumber): string
    {
        $length = strlen($phoneNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phoneNumber, 0, 3) . str_repeat('*', $length - 6) . substr($phoneNumber, -3);
    }

    /**
     * Get masked sender phone number
     *
     * @return string|null
     */
    protected function getMaskedSenderNumber(): ?string
    {
        $config = TenantPaymentConfig::where('tenant_id', $this->tenant->id)
            ->where('processor', 'sms')
            ->first();

        if (!$config || !$config->sms_twilio_phone_number) {
            return null;
        }

        return $this->maskPhoneNumber($config->sms_twilio_phone_number);
    }
}

<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS Payment Processor
 *
 * A hybrid payment processor that sends payment links via SMS.
 * Uses Twilio for SMS delivery and a fallback payment processor for actual payment processing.
 *
 * This allows customers to receive payment requests via SMS and complete payment through
 * traditional payment methods (Stripe, PayPal, etc.)
 */
class SmsPaymentProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;
    protected ?PaymentProcessorInterface $fallbackProcessor = null;

    /**
     * Twilio API endpoints
     */
    private const TWILIO_API_URL = 'https://api.twilio.com/2010-04-01';

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();

        // Initialize fallback processor if configured
        $this->initializeFallbackProcessor();
    }

    /**
     * Initialize the fallback payment processor
     */
    protected function initializeFallbackProcessor(): void
    {
        $fallbackType = $this->keys['fallback_processor'] ?? 'stripe';

        // Find the fallback processor config for this tenant
        $fallbackConfig = TenantPaymentConfig::where('tenant_id', $this->config->tenant_id)
            ->where('processor', $fallbackType)
            ->where('is_active', true)
            ->first();

        if ($fallbackConfig) {
            $this->fallbackProcessor = PaymentProcessorFactory::makeFromConfig($fallbackConfig);
        }
    }

    /**
     * Create a payment and send SMS with payment link
     *
     * @param array $data Payment data including:
     *   - phone_number: Customer's phone number (required for SMS)
     *   - amount, currency, description, order_id, success_url, cancel_url
     *   - sms_message: Custom SMS message (optional)
     * @return array Payment details
     */
    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('SMS Payment is not properly configured');
        }

        if (empty($data['phone_number'])) {
            throw new \Exception('Phone number is required for SMS payment');
        }

        // Create payment via fallback processor
        if (!$this->fallbackProcessor) {
            throw new \Exception('No fallback payment processor configured for SMS payments');
        }

        // Generate a unique token for this SMS payment request
        $smsToken = Str::random(32);

        // Add metadata for tracking
        $data['metadata'] = array_merge($data['metadata'] ?? [], [
            'sms_payment' => true,
            'sms_token' => $smsToken,
            'phone_number' => $this->maskPhoneNumber($data['phone_number']),
        ]);

        // Create payment with fallback processor
        $paymentResult = $this->fallbackProcessor->createPayment($data);

        // Send SMS with payment link
        $smsSent = $this->sendPaymentSms(
            $data['phone_number'],
            $paymentResult['redirect_url'],
            $data['amount'],
            $data['currency'] ?? 'EUR',
            $data['sms_message'] ?? null,
            $data['description'] ?? 'Payment'
        );

        return [
            'payment_id' => $paymentResult['payment_id'],
            'redirect_url' => $paymentResult['redirect_url'],
            'additional_data' => array_merge($paymentResult['additional_data'] ?? [], [
                'sms_sent' => $smsSent,
                'sms_token' => $smsToken,
                'phone_number' => $this->maskPhoneNumber($data['phone_number']),
                'fallback_processor' => $this->fallbackProcessor->getName(),
            ]),
        ];
    }

    /**
     * Send payment link via SMS
     *
     * @param string $phoneNumber Customer phone number
     * @param string $paymentUrl Payment URL
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string|null $customMessage Custom message template
     * @param string $description Payment description
     * @return bool Whether SMS was sent successfully
     */
    protected function sendPaymentSms(
        string $phoneNumber,
        string $paymentUrl,
        float $amount,
        string $currency,
        ?string $customMessage,
        string $description
    ): bool {
        $formattedAmount = number_format($amount, 2) . ' ' . $currency;

        // Build SMS message
        if ($customMessage) {
            $message = str_replace(
                ['{amount}', '{currency}', '{description}', '{link}'],
                [$formattedAmount, $currency, $description, $paymentUrl],
                $customMessage
            );
        } else {
            $message = "Payment request: {$formattedAmount} for {$description}. Pay securely here: {$paymentUrl}";
        }

        // Ensure message fits SMS limits (consider URL shortening for production)
        if (strlen($message) > 1600) {
            $message = "Payment: {$formattedAmount}. Pay here: {$paymentUrl}";
        }

        return $this->sendTwilioSms($phoneNumber, $message);
    }

    /**
     * Send SMS via Twilio API
     *
     * @param string $to Recipient phone number
     * @param string $message SMS message
     * @return bool Success status
     */
    protected function sendTwilioSms(string $to, string $message): bool
    {
        if (empty($this->keys['twilio_sid']) || empty($this->keys['twilio_auth_token'])) {
            Log::warning('Twilio credentials not configured for SMS payment');
            return false;
        }

        try {
            $response = Http::withBasicAuth(
                $this->keys['twilio_sid'],
                $this->keys['twilio_auth_token']
            )->asForm()->post(
                self::TWILIO_API_URL . '/Accounts/' . $this->keys['twilio_sid'] . '/Messages.json',
                [
                    'To' => $this->formatPhoneNumber($to),
                    'From' => $this->keys['twilio_phone_number'],
                    'Body' => $message,
                ]
            );

            if (!$response->successful()) {
                Log::error('Twilio SMS failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $result = $response->json();

            Log::info('SMS payment link sent', [
                'sid' => $result['sid'] ?? null,
                'to' => $this->maskPhoneNumber($to),
                'status' => $result['status'] ?? 'unknown',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('SMS send error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Process callback - delegates to fallback processor
     *
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @return array Normalized payment result
     */
    public function processCallback(array $payload, array $headers = []): array
    {
        if (!$this->fallbackProcessor) {
            throw new \Exception('No fallback payment processor configured');
        }

        return $this->fallbackProcessor->processCallback($payload, $headers);
    }

    /**
     * Verify signature - delegates to fallback processor
     *
     * @param array $payload Raw payload
     * @param array $headers Request headers
     * @return bool
     */
    public function verifySignature(array $payload, array $headers): bool
    {
        if (!$this->fallbackProcessor) {
            return true;
        }

        return $this->fallbackProcessor->verifySignature($payload, $headers);
    }

    /**
     * Get payment status - delegates to fallback processor
     *
     * @param string $paymentId Payment ID
     * @return array Payment status details
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->fallbackProcessor) {
            throw new \Exception('No fallback payment processor configured');
        }

        return $this->fallbackProcessor->getPaymentStatus($paymentId);
    }

    /**
     * Refund payment - delegates to fallback processor
     *
     * @param string $paymentId Payment ID
     * @param float|null $amount Amount to refund
     * @param string|null $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->fallbackProcessor) {
            throw new \Exception('No fallback payment processor configured');
        }

        return $this->fallbackProcessor->refundPayment($paymentId, $amount, $reason);
    }

    /**
     * Check if processor is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->keys['twilio_sid'])
            && !empty($this->keys['twilio_auth_token'])
            && !empty($this->keys['twilio_phone_number'])
            && $this->fallbackProcessor?->isConfigured();
    }

    /**
     * Get processor name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'SMS Payment';
    }

    /**
     * Format phone number for Twilio (E.164 format)
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // Add + if not present and number doesn't start with 00
        if (!str_starts_with($cleaned, '+')) {
            if (str_starts_with($cleaned, '00')) {
                $cleaned = '+' . substr($cleaned, 2);
            } elseif (str_starts_with($cleaned, '0')) {
                // Assume local number, would need country context
                // Default to assuming it's already international
            } else {
                $cleaned = '+' . $cleaned;
            }
        }

        return $cleaned;
    }

    /**
     * Mask phone number for logging/display
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
     * Send payment reminder SMS
     *
     * @param string $phoneNumber Customer phone number
     * @param string $paymentUrl Payment URL
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string $orderId Order reference
     * @return bool
     */
    public function sendPaymentReminder(
        string $phoneNumber,
        string $paymentUrl,
        float $amount,
        string $currency,
        string $orderId
    ): bool {
        $formattedAmount = number_format($amount, 2) . ' ' . $currency;
        $message = "Reminder: Your payment of {$formattedAmount} (Order: {$orderId}) is pending. Complete payment: {$paymentUrl}";

        return $this->sendTwilioSms($phoneNumber, $message);
    }

    /**
     * Send payment confirmation SMS
     *
     * @param string $phoneNumber Customer phone number
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string $orderId Order reference
     * @return bool
     */
    public function sendPaymentConfirmation(
        string $phoneNumber,
        float $amount,
        string $currency,
        string $orderId
    ): bool {
        $formattedAmount = number_format($amount, 2) . ' ' . $currency;
        $message = "Payment confirmed! {$formattedAmount} received for Order {$orderId}. Thank you!";

        return $this->sendTwilioSms($phoneNumber, $message);
    }

    /**
     * Get SMS delivery status
     *
     * @param string $messageSid Twilio message SID
     * @return array Status details
     */
    public function getSmsStatus(string $messageSid): array
    {
        try {
            $response = Http::withBasicAuth(
                $this->keys['twilio_sid'],
                $this->keys['twilio_auth_token']
            )->get(
                self::TWILIO_API_URL . '/Accounts/' . $this->keys['twilio_sid'] . '/Messages/' . $messageSid . '.json'
            );

            if (!$response->successful()) {
                throw new \Exception('Failed to get SMS status');
            }

            $message = $response->json();

            return [
                'status' => $message['status'],
                'error_code' => $message['error_code'] ?? null,
                'error_message' => $message['error_message'] ?? null,
                'date_sent' => $message['date_sent'] ?? null,
                'date_updated' => $message['date_updated'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('SMS status check failed', ['error' => $e->getMessage()]);
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the fallback processor instance
     *
     * @return PaymentProcessorInterface|null
     */
    public function getFallbackProcessor(): ?PaymentProcessorInterface
    {
        return $this->fallbackProcessor;
    }
}

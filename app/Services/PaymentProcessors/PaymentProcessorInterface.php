<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;

interface PaymentProcessorInterface
{
    /**
     * Initialize the processor with configuration
     */
    public function __construct(TenantPaymentConfig $config);

    /**
     * Create a payment session/intent
     *
     * @param array $data Payment data including:
     *   - amount: float (in main currency unit)
     *   - currency: string (ISO code)
     *   - description: string
     *   - customer_email: string
     *   - customer_name: string
     *   - order_id: string (internal order reference)
     *   - success_url: string
     *   - cancel_url: string
     *   - metadata: array (optional additional data)
     * @return array Returns:
     *   - payment_id: string (processor's payment/session ID)
     *   - redirect_url: string (URL to redirect customer to)
     *   - additional_data: array (processor-specific data)
     */
    public function createPayment(array $data): array;

    /**
     * Verify and process a payment callback/webhook
     *
     * @param array $payload Raw webhook/callback data
     * @param array $headers Request headers (for signature verification)
     * @return array Returns:
     *   - status: string (success, failed, pending, cancelled)
     *   - payment_id: string
     *   - order_id: string (your internal order reference)
     *   - amount: float
     *   - currency: string
     *   - transaction_id: string (processor's transaction ID)
     *   - paid_at: string (ISO 8601 datetime)
     *   - metadata: array
     */
    public function processCallback(array $payload, array $headers = []): array;

    /**
     * Verify webhook/callback signature for security
     *
     * @param array $payload Raw payload data
     * @param array $headers Request headers
     * @return bool
     */
    public function verifySignature(array $payload, array $headers): bool;

    /**
     * Get payment status
     *
     * @param string $paymentId Processor's payment ID
     * @return array Returns:
     *   - status: string (success, failed, pending, cancelled)
     *   - amount: float
     *   - currency: string
     *   - paid_at: string|null
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Refund a payment
     *
     * @param string $paymentId Processor's payment ID
     * @param float|null $amount Amount to refund (null for full refund)
     * @param string|null $reason Refund reason
     * @return array Returns:
     *   - refund_id: string
     *   - status: string (success, failed, pending)
     *   - amount: float
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array;

    /**
     * Check if the processor is properly configured
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get processor name
     *
     * @return string
     */
    public function getName(): string;
}

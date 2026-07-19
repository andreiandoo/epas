<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;

/**
 * A fake processor for END-TO-END TESTING of flexible payments WITHOUT hitting a
 * real gateway. Enable it with FLEX_FAKE_PROCESSOR=true (config installments.fake_processor);
 * ProcessorResolver then returns this instead of the marketplace's real processor.
 *
 * Deterministic outcome control (so testers can exercise every branch):
 *   - amount ending in .13  → hard decline
 *   - amount ending in .15  → action_required (3DS)
 *   - anything else         → success
 * or pass metadata['force'] = 'success'|'failed'|'action_required'.
 *
 * NEVER selected in production unless the flag is explicitly on.
 */
class MockTokenizableProcessor implements PaymentProcessorInterface, SupportsTokenizedPayments
{
    public function __construct(?TenantPaymentConfig $config = null, ?array $arrayConfig = null)
    {
    }

    public function createPayment(array $data): array
    {
        $id = 'mock_pay_' . substr(md5(json_encode($data) . microtime()), 0, 12);
        return [
            'payment_id' => $id,
            // A local "hosted page" stand-in that immediately hits the confirm URL.
            'redirect_url' => $data['success_url'] ?? url('/'),
            'additional_data' => ['mock' => true],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        return [
            'status' => 'success',
            'payment_id' => $payload['payment_id'] ?? 'mock_tx',
            'order_id' => $payload['order_id'] ?? null,
            'amount' => (float) ($payload['amount'] ?? 0),
            'currency' => $payload['currency'] ?? 'RON',
            'transaction_id' => 'mock_tx_' . substr(md5(microtime()), 0, 10),
            'paid_at' => now()->toIso8601String(),
            'mandate_reference' => 'mock_mandate_' . substr(md5(microtime()), 0, 10),
            'metadata' => [],
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        return true;
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return ['status' => 'success', 'amount' => 0, 'currency' => 'RON', 'paid_at' => now()->toIso8601String()];
    }

    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        return ['refund_id' => 'mock_refund_' . substr(md5($paymentId), 0, 10), 'status' => 'success', 'amount' => $amount ?? 0];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Mock (test)';
    }

    // --- SupportsTokenizedPayments -------------------------------------------

    public function supportsTokenization(): bool
    {
        return true;
    }

    public function supportsZeroAmountMandate(): bool
    {
        return true;
    }

    public function createPaymentWithMandate(array $data): array
    {
        return [
            'payment_id' => 'mock_down_' . substr(md5(microtime()), 0, 10),
            'redirect_url' => $data['success_url'] ?? url('/'),
            'mandate_reference' => 'mock_mandate_' . substr(md5(($data['order_id'] ?? '') . microtime()), 0, 12),
            'additional_data' => ['mock' => true],
        ];
    }

    public function chargeWithToken(string $mandateReference, array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        $forced = $data['metadata']['force'] ?? $this->fromAmount($amount);

        return match ($forced) {
            'failed' => [
                'status' => 'failed', 'payment_id' => null, 'amount' => $amount,
                'currency' => strtoupper($data['currency'] ?? 'RON'), 'action_url' => null,
                'decline_code' => 'insufficient_funds', 'hard_decline' => false, 'error' => 'Mock decline',
            ],
            'action_required' => [
                'status' => 'action_required', 'payment_id' => null, 'amount' => $amount,
                'currency' => strtoupper($data['currency'] ?? 'RON'),
                'action_url' => url('/'), 'decline_code' => null, 'hard_decline' => false, 'error' => null,
            ],
            default => [
                'status' => 'success',
                'payment_id' => 'mock_tx_' . substr(md5($mandateReference . ($data['idempotency_key'] ?? microtime())), 0, 12),
                'amount' => $amount, 'currency' => strtoupper($data['currency'] ?? 'RON'),
                'action_url' => null, 'decline_code' => null, 'hard_decline' => false, 'error' => null,
            ],
        };
    }

    protected function fromAmount(float $amount): string
    {
        $cents = (int) round($amount * 100) % 100;
        return match ($cents) {
            13 => 'failed',
            15 => 'action_required',
            default => 'success',
        };
    }
}

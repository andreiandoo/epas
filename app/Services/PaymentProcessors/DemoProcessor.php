<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Procesator de plată DEMO — se comportă ca un gateway real (redirect către o
 * pagină de plată), dar fără chei, fără cont, fără bani reali. Auto-aprobă sau
 * refuză în funcție de butonul apăsat pe pagina demo. Reutilizabil pe orice
 * site demo prin payment_processor = 'demo'.
 */
class DemoProcessor implements PaymentProcessorInterface
{
    public function __construct(?TenantPaymentConfig $config = null, ?array $arrayConfig = null)
    {
        // Nu are nevoie de configurare.
    }

    public function createPayment(array $data): array
    {
        $paymentId = 'demo_' . Str::random(24);

        // Redirect către pagina demo de plată (semnat, ne-falsificabil).
        $redirect = URL::signedRoute('demo.pay', [
            'order'   => $data['order_id'],
            'pid'     => $paymentId,
            'success' => $data['success_url'] ?? '',
            'cancel'  => $data['cancel_url'] ?? '',
        ]);

        return [
            'payment_id'      => $paymentId,
            'redirect_url'    => $redirect,
            'additional_data' => ['demo' => true],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        $ok = ($payload['result'] ?? 'success') === 'success';

        return [
            'status'         => $ok ? 'success' : 'failed',
            'payment_id'     => $payload['pid'] ?? '',
            'order_id'       => $payload['order'] ?? '',
            'amount'         => (float) ($payload['amount'] ?? 0),
            'currency'       => $payload['currency'] ?? 'RON',
            'transaction_id' => 'demo_txn_' . ($payload['order'] ?? Str::random(8)),
            'paid_at'        => now()->toIso8601String(),
            'metadata'       => ['demo' => true],
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        return true;
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return [
            'status'   => 'success',
            'amount'   => 0.0,
            'currency' => 'RON',
            'paid_at'  => now()->toIso8601String(),
        ];
    }

    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        return [
            'refund_id' => 'demo_refund_' . Str::random(12),
            'status'    => 'success',
            'amount'    => $amount ?? 0.0,
        ];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'demo';
    }
}

<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;

class EuplatescProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;
    protected string $baseUrl;

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();

        // Set base URL based on mode
        $this->baseUrl = $config->mode === 'live'
            ? 'https://secure.euplatesc.ro'
            : 'https://sandboxsecure.euplatesc.ro';
    }

    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Euplatesc is not properly configured');
        }

        // Generate unique payment ID
        $paymentId = $data['order_id'] ?? ('euplatesc_' . uniqid() . '_' . time());

        // Prepare payment parameters
        $params = [
            'amount' => number_format($data['amount'], 2, '.', ''),
            'curr' => strtoupper($data['currency'] ?? 'RON'),
            'invoice_id' => $paymentId,
            'order_desc' => $data['description'] ?? 'Payment',
            'merch_id' => $this->keys['merchant_id'],
            'timestamp' => time(),
            'nonce' => md5(uniqid(rand(), true)),
            'billing_email' => $data['customer_email'] ?? '',
            'billing_name' => $data['customer_name'] ?? '',
            'ExtraData[successurl]' => $data['success_url'],
            'ExtraData[backtosite]' => $data['cancel_url'],
        ];

        // Add metadata as extra data
        if (!empty($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                $params["ExtraData[{$key}]"] = $value;
            }
        }

        // Generate HMAC signature
        $params['fp_hash'] = $this->generateSignature($params);

        // Build redirect URL
        $redirectUrl = $this->baseUrl . '/tdsprocess/tranzactd.php?' . http_build_query($params);

        return [
            'payment_id' => $paymentId,
            'redirect_url' => $redirectUrl,
            'additional_data' => [
                'merchant_id' => $this->keys['merchant_id'],
                'timestamp' => $params['timestamp'],
            ],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Euplatesc is not properly configured');
        }

        // Verify signature
        if (!$this->verifySignature($payload, $headers)) {
            throw new \Exception('Invalid callback signature');
        }

        // Parse the response
        $amount = (float) ($payload['amount'] ?? 0);
        $currency = strtoupper($payload['curr'] ?? 'RON');
        $invoiceId = $payload['invoice_id'] ?? null;
        $epId = $payload['ep_id'] ?? null; // Euplatesc transaction ID
        $action = $payload['action'] ?? '';

        // Map Euplatesc action to our standard status
        $status = match ($action) {
            '0' => 'success', // Payment approved
            '1' => 'failed',  // Payment declined
            '2' => 'failed',  // Payment error
            '3' => 'cancelled', // Payment cancelled
            '4' => 'pending', // Payment pending
            '5' => 'pending', // Payment on hold
            '6' => 'success', // Payment authorized (captured later)
            default => 'pending',
        };

        return [
            'status' => $status,
            'payment_id' => $invoiceId,
            'order_id' => $invoiceId,
            'amount' => $amount,
            'currency' => $currency,
            'transaction_id' => $epId,
            'paid_at' => $status === 'success' ? date('c') : null,
            'metadata' => [
                'action' => $action,
                'message' => $payload['message'] ?? '',
                'approval' => $payload['approval'] ?? '',
            ],
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // SECURITY FIX: If no secret key, REJECT the webhook
        if (empty($this->keys['secret_key'])) {
            \Log::critical('Euplatesc webhook rejected: secret_key not configured', [
                'ip' => request()->ip(),
            ]);
            return false;
        }

        $receivedHash = $payload['fp_hash'] ?? '';

        if (empty($receivedHash)) {
            return false;
        }

        // Remove fp_hash from payload for verification
        $dataToVerify = $payload;
        unset($dataToVerify['fp_hash']);

        // Generate expected signature
        $expectedHash = $this->generateSignature($dataToVerify);

        return hash_equals($expectedHash, $receivedHash);
    }

    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Euplatesc is not properly configured');
        }

        try {
            // Prepare status check request
            $params = [
                'merch_id' => $this->keys['merchant_id'],
                'invoice_id' => $paymentId,
                'timestamp' => time(),
                'nonce' => md5(uniqid(rand(), true)),
            ];

            $params['fp_hash'] = $this->generateSignature($params);

            // Make API request
            $url = $this->baseUrl . '/query/status.php?' . http_build_query($params);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->mode === 'live');
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if (!$data || !isset($data['status'])) {
                throw new \Exception('Invalid response from Euplatesc');
            }

            $statusMap = [
                '0' => 'success',
                '1' => 'failed',
                '2' => 'failed',
                '3' => 'cancelled',
                '4' => 'pending',
                '5' => 'pending',
                '6' => 'success',
            ];

            $status = $statusMap[$data['status']] ?? 'pending';

            return [
                'status' => $status,
                'amount' => (float) ($data['amount'] ?? 0),
                'currency' => strtoupper($data['curr'] ?? 'RON'),
                'paid_at' => $status === 'success' ? ($data['timestamp'] ?? date('c')) : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Euplatesc is not properly configured');
        }

        try {
            // Prepare refund request
            $params = [
                'merch_id' => $this->keys['merchant_id'],
                'invoice_id' => $paymentId,
                'amount' => $amount ? number_format($amount, 2, '.', '') : '',
                'timestamp' => time(),
                'nonce' => md5(uniqid(rand(), true)),
            ];

            if ($reason) {
                $params['reason'] = $reason;
            }

            $params['fp_hash'] = $this->generateSignature($params);

            // Make API request
            $url = $this->baseUrl . '/query/refund.php';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->mode === 'live');
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if (!$data || !isset($data['status'])) {
                throw new \Exception('Invalid response from Euplatesc');
            }

            $statusMap = [
                '0' => 'success',
                '1' => 'failed',
                '2' => 'pending',
            ];

            return [
                'refund_id' => $data['refund_id'] ?? uniqid('refund_'),
                'status' => $statusMap[$data['status']] ?? 'pending',
                'amount' => (float) ($params['amount'] ?: $data['amount'] ?? 0),
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to process refund: {$e->getMessage()}");
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->keys['merchant_id']) && !empty($this->keys['secret_key']);
    }

    public function getName(): string
    {
        return 'Euplatesc';
    }

    /**
     * Generate HMAC signature for Euplatesc
     */
    protected function generateSignature(array $params): string
    {
        // Build string to sign (sorted by key)
        ksort($params);

        $signatureString = '';
        foreach ($params as $key => $value) {
            if ($key !== 'fp_hash') {
                $signatureString .= strlen($value) . $value;
            }
        }

        // Add secret key
        $signatureString .= $this->keys['secret_key'];

        // Generate HMAC-MD5 hash
        return strtoupper(hash_hmac('md5', $signatureString, $this->keys['secret_key']));
    }
}

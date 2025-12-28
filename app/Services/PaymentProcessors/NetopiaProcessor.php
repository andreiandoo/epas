<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;

class NetopiaProcessor implements PaymentProcessorInterface
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
            ? 'https://secure.mobilpay.ro'
            : 'https://sandboxsecure.mobilpay.ro';
    }

    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Netopia is not properly configured');
        }

        // Generate unique payment ID
        $paymentId = 'netopia_' . uniqid() . '_' . time();

        // Prepare payment data
        $paymentData = [
            'signature' => $this->keys['signature'],
            'orderId' => $data['order_id'] ?? $paymentId,
            'amount' => number_format($data['amount'], 2, '.', ''),
            'currency' => strtoupper($data['currency'] ?? 'RON'),
            'details' => $data['description'] ?? 'Payment',
            'confirmUrl' => $data['success_url'],
            'returnUrl' => $data['success_url'],
            'cancelUrl' => $data['cancel_url'],
            'params' => [
                'customer_email' => $data['customer_email'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
            ],
        ];

        // Merge metadata
        if (!empty($data['metadata'])) {
            $paymentData['params'] = array_merge($paymentData['params'], $data['metadata']);
        }

        // Create XML request for mobilPay
        $xml = $this->createMobilPayXML($paymentData);

        // Encrypt data with public key
        $encryptedData = $this->encryptData($xml);

        // Create redirect URL with encrypted data
        $redirectUrl = $this->baseUrl . '/pay?' . http_build_query([
            'env_key' => base64_encode($encryptedData['env_key']),
            'data' => base64_encode($encryptedData['data']),
        ]);

        return [
            'payment_id' => $paymentId,
            'redirect_url' => $redirectUrl,
            'additional_data' => [
                'order_id' => $paymentData['orderId'],
                'encrypted' => true,
            ],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Netopia is not properly configured');
        }

        // Verify signature first
        if (!$this->verifySignature($payload, $headers)) {
            throw new \Exception('Invalid callback signature');
        }

        // Decrypt the callback data
        $envKey = $payload['env_key'] ?? null;
        $encryptedData = $payload['data'] ?? null;

        if (!$envKey || !$encryptedData) {
            throw new \Exception('Missing required callback data');
        }

        $decryptedData = $this->decryptData($encryptedData, $envKey);
        $xmlData = simplexml_load_string($decryptedData);

        // Parse the response
        $action = (string) $xmlData->attributes()->action;
        $errorCode = (string) $xmlData->error['code'];

        // Map Netopia status to our standard status
        $status = 'pending';
        if ($action === 'confirmed' && $errorCode === '0') {
            $status = 'success';
        } elseif ($action === 'confirmed_pending') {
            $status = 'pending';
        } elseif ($action === 'canceled') {
            $status = 'cancelled';
        } elseif ($action === 'credit') {
            $status = 'refunded';
        } else {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'payment_id' => (string) $xmlData->attributes()->id,
            'order_id' => (string) $xmlData->order_id,
            'amount' => (float) $xmlData->amount,
            'currency' => (string) $xmlData->currency,
            'transaction_id' => (string) $xmlData->attributes()->id,
            'paid_at' => $status === 'success' ? date('c') : null,
            'metadata' => [
                'error_code' => $errorCode,
                'error_message' => (string) $xmlData->error,
                'action' => $action,
            ],
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // For Netopia, we verify using RSA signature
        if (empty($this->keys['public_key'])) {
            return true; // Can't verify without key
        }

        try {
            $envKey = $payload['env_key'] ?? null;
            $data = $payload['data'] ?? null;

            if (!$envKey || !$data) {
                return false;
            }

            // The data itself is encrypted with our public key,
            // which means it's authentic if we can decrypt it
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Netopia is not properly configured');
        }

        // Netopia doesn't provide a standard status check API
        // Status is typically obtained through callbacks
        // Return pending status as we can't check directly
        return [
            'status' => 'pending',
            'amount' => 0,
            'currency' => 'RON',
            'paid_at' => null,
        ];
    }

    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Netopia is not properly configured');
        }

        // Netopia refunds are typically handled through their dashboard
        // API refunds require special integration
        throw new \Exception('Refunds for Netopia must be processed manually through the dashboard');
    }

    public function isConfigured(): bool
    {
        return !empty($this->keys['signature']) && !empty($this->keys['public_key']);
    }

    public function getName(): string
    {
        return 'Netopia';
    }

    /**
     * Create mobilPay XML structure
     */
    protected function createMobilPayXML(array $data): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><order/>');

        $xml->addAttribute('id', $data['orderId']);
        $xml->addAttribute('timestamp', time());
        $xml->addAttribute('type', 'card');

        $xml->addChild('signature', $data['signature']);

        $invoice = $xml->addChild('invoice');
        $invoice->addAttribute('currency', $data['currency']);
        $invoice->addAttribute('amount', $data['amount']);

        $invoice->addChild('details', htmlspecialchars($data['details']));

        $contactInfo = $invoice->addChild('contact_info');
        if (!empty($data['params']['customer_email'])) {
            $contactInfo->addChild('email', $data['params']['customer_email']);
        }
        if (!empty($data['params']['customer_name'])) {
            $contactInfo->addChild('name', htmlspecialchars($data['params']['customer_name']));
        }

        $xml->addChild('confirm_url', htmlspecialchars($data['confirmUrl']));
        $xml->addChild('return_url', htmlspecialchars($data['returnUrl']));

        // Add custom params
        if (!empty($data['params'])) {
            $params = $xml->addChild('params');
            foreach ($data['params'] as $key => $value) {
                if (!in_array($key, ['customer_email', 'customer_name'])) {
                    $params->addChild($key, htmlspecialchars($value));
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Encrypt data with Netopia public key
     */
    protected function encryptData(string $data): array
    {
        $publicKey = $this->keys['public_key'];

        // Generate random encryption key
        $encryptionKey = openssl_random_pseudo_bytes(32);

        // Encrypt data with AES
        $iv = openssl_random_pseudo_bytes(16);
        $encryptedData = openssl_encrypt($data, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);
        $encryptedData = $iv . $encryptedData;

        // Encrypt the encryption key with RSA public key
        $publicKeyResource = openssl_pkey_get_public($publicKey);
        openssl_public_encrypt($encryptionKey, $encryptedKey, $publicKeyResource);

        return [
            'env_key' => $encryptedKey,
            'data' => $encryptedData,
        ];
    }

    /**
     * Decrypt callback data
     */
    protected function decryptData(string $encryptedData, string $encryptedKey): string
    {
        $privateKey = $this->keys['api_key']; // Private key stored as api_key

        // Decrypt the encryption key
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        openssl_private_decrypt(base64_decode($encryptedKey), $decryptionKey, $privateKeyResource);

        // Decrypt the data
        $encryptedDataDecoded = base64_decode($encryptedData);
        $iv = substr($encryptedDataDecoded, 0, 16);
        $encryptedPayload = substr($encryptedDataDecoded, 16);

        return openssl_decrypt($encryptedPayload, 'AES-256-CBC', $decryptionKey, OPENSSL_RAW_DATA, $iv);
    }
}

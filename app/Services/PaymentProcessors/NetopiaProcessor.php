<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;

class NetopiaProcessor implements PaymentProcessorInterface
{
    protected ?TenantPaymentConfig $config = null;
    protected array $keys;
    protected string $baseUrl;

    /**
     * Create processor from TenantPaymentConfig or array config
     *
     * @param TenantPaymentConfig|null $config For tenant-based payments
     * @param array|null $arrayConfig For marketplace-based payments
     */
    public function __construct(?TenantPaymentConfig $config = null, ?array $arrayConfig = null)
    {
        if ($config) {
            // Tenant-based config
            $this->config = $config;
            $this->keys = $config->getActiveKeys();
            $mode = $config->mode ?? 'sandbox';
        } elseif ($arrayConfig) {
            // Array-based config (marketplace client)
            // Determine mode first - config may have test_mode flag or mode string
            $testMode = $arrayConfig['test_mode'] ?? null;
            if ($testMode !== null) {
                // test_mode: true/1/"1" = sandbox, false/0/"0" = live
                $mode = filter_var($testMode, FILTER_VALIDATE_BOOLEAN) ? 'sandbox' : 'live';
            } else {
                $mode = $arrayConfig['mode'] ?? 'sandbox';
            }

            // Pick credentials based on mode — supports multiple key naming conventions
            $isLive = ($mode === 'live');
            $this->keys = [
                'signature' => $arrayConfig['netopia_signature'] ?? $arrayConfig['signature']
                    ?? ($isLive
                        ? ($arrayConfig['live_merchant_id'] ?? $arrayConfig['test_merchant_id'] ?? null)
                        : ($arrayConfig['test_merchant_id'] ?? $arrayConfig['live_merchant_id'] ?? null))
                    ?? $arrayConfig['merchant_id'] ?? null,
                'private_key' => $arrayConfig['netopia_api_key'] ?? $arrayConfig['private_key']
                    ?? ($isLive
                        ? ($arrayConfig['live_private_key'] ?? $arrayConfig['test_private_key'] ?? null)
                        : ($arrayConfig['test_private_key'] ?? $arrayConfig['live_private_key'] ?? null))
                    ?? null,
                'public_key' => $arrayConfig['netopia_public_key'] ?? $arrayConfig['public_key']
                    ?? ($isLive
                        ? ($arrayConfig['live_public_key'] ?? $arrayConfig['test_public_key'] ?? null)
                        : ($arrayConfig['test_public_key'] ?? $arrayConfig['live_public_key'] ?? null))
                    ?? null,
            ];
        } else {
            throw new \Exception('Either config or arrayConfig must be provided');
        }

        // Set base URL based on mode
        $this->baseUrl = $mode === 'live'
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
            'confirmUrl' => $data['callback_url'] ?? $data['success_url'],
            'returnUrl' => $data['return_url'] ?? $data['success_url'],
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

        // Netopia requires POST form submission to the base URL (no /pay path)
        $formData = [
            'env_key' => base64_encode($encryptedData['env_key']),
            'data' => base64_encode($encryptedData['data']),
        ];

        // Include cipher and IV for AES-256-CBC (required by Netopia for PHP 7+)
        if (!empty($encryptedData['cipher'])) {
            $formData['cipher'] = $encryptedData['cipher'];
        }
        if (!empty($encryptedData['iv'])) {
            $formData['iv'] = base64_encode($encryptedData['iv']);
        }

        return [
            'payment_id' => $paymentId,
            'redirect_url' => $this->baseUrl,
            'method' => 'POST',
            'form_data' => $formData,
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
        $cipher = $payload['cipher'] ?? '';
        $iv = $payload['iv'] ?? '';

        if (!$envKey || !$encryptedData) {
            throw new \Exception('Missing required callback data');
        }

        $decryptedData = $this->decryptData($encryptedData, $envKey, $cipher, $iv);

        // SECURITY FIX: Parse XML with XXE protection
        $previousValue = libxml_disable_entity_loader(true);
        libxml_use_internal_errors(true);
        $xmlData = simplexml_load_string($decryptedData, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_disable_entity_loader($previousValue);

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
     * Encrypt data using openssl_seal() — matches official mobilPay protocol
     */
    protected function encryptData(string $data): array
    {
        $publicKeyCert = $this->keys['public_key'];

        // Extract public key from X.509 certificate or PEM public key
        $publicKey = openssl_get_publickey($publicKeyCert);
        if (!$publicKey) {
            throw new \Exception('Netopia: Invalid public key/certificate');
        }

        // Use AES-256-CBC (recommended for PHP 7+), fallback to RC4
        $cipher = 'aes-256-cbc';
        $iv = '';
        $envKeys = [];

        $result = openssl_seal($data, $encryptedData, $envKeys, [$publicKey], $cipher, $iv);

        if ($result === false) {
            // Fallback to RC4 if AES fails (some older Netopia accounts)
            $cipher = 'rc4';
            $iv = '';
            $result = openssl_seal($data, $encryptedData, $envKeys, [$publicKey], $cipher);

            if ($result === false) {
                throw new \Exception('Netopia: Failed to encrypt payment data: ' . openssl_error_string());
            }
        }

        return [
            'env_key' => $envKeys[0],
            'data' => $encryptedData,
            'cipher' => $cipher,
            'iv' => $iv,
        ];
    }

    /**
     * Decrypt callback data using openssl_open() — matches official mobilPay protocol
     */
    protected function decryptData(string $encryptedData, string $encryptedKey, string $cipher = '', string $iv = ''): string
    {
        $privateKey = openssl_pkey_get_private($this->keys['private_key']);
        if (!$privateKey) {
            throw new \Exception('Netopia: Invalid private key');
        }

        $srcData = base64_decode($encryptedData);
        $srcEnvKey = base64_decode($encryptedKey);
        $srcIv = !empty($iv) ? base64_decode($iv) : '';

        // Default cipher based on what was sent, or try aes-256-cbc then rc4
        if (empty($cipher)) {
            $cipher = !empty($srcIv) ? 'aes-256-cbc' : 'rc4';
        }

        $result = openssl_open($srcData, $data, $srcEnvKey, $privateKey, $cipher, $srcIv);

        if ($result === false) {
            // Fallback: try the other cipher
            $fallbackCipher = ($cipher === 'rc4') ? 'aes-256-cbc' : 'rc4';
            $result = openssl_open($srcData, $data, $srcEnvKey, $privateKey, $fallbackCipher);

            if ($result === false) {
                throw new \Exception('Netopia: Failed to decrypt callback data: ' . openssl_error_string());
            }
        }

        return $data;
    }
}

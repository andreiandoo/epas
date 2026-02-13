<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\Log;

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

        // Use order_number as unique orderId for Netopia (more descriptive than numeric ID)
        $orderId = $data['order_number'] ?? ('ORD-' . $data['order_id'] . '-' . time());

        // Prepare payment data
        $paymentData = [
            'signature' => $this->keys['signature'],
            'orderId' => $orderId,
            'amount' => number_format($data['amount'], 2, '.', ''),
            'currency' => strtoupper($data['currency'] ?? 'RON'),
            'details' => $data['description'] ?? 'Payment',
            'confirmUrl' => $data['callback_url'] ?? $data['success_url'],
            'returnUrl' => $data['return_url'] ?? $data['success_url'],
            'cancelUrl' => $data['cancel_url'],
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
        ];

        // Create XML request for mobilPay
        $xml = $this->createMobilPayXML($paymentData);

        Log::channel('marketplace')->debug('Netopia: XML created', [
            'order_id' => $orderId,
            'xml_length' => strlen($xml),
        ]);

        // Encrypt data with public certificate
        $encryptedData = $this->encryptData($xml);

        Log::channel('marketplace')->info('Netopia: Payment encrypted', [
            'order_id' => $orderId,
            'cipher' => $encryptedData['cipher'],
            'env_key_length' => strlen($encryptedData['env_key']),
            'data_length' => strlen($encryptedData['data']),
        ]);

        // Build form data for POST submission
        $formData = [
            'env_key' => base64_encode($encryptedData['env_key']),
            'data' => base64_encode($encryptedData['data']),
        ];

        // Include cipher and IV when using AES-256-CBC
        if ($encryptedData['cipher'] !== 'rc4') {
            $formData['cipher'] = $encryptedData['cipher'];
            if (!empty($encryptedData['iv'])) {
                $formData['iv'] = base64_encode($encryptedData['iv']);
            }
        }

        return [
            'payment_id' => $orderId,
            'redirect_url' => $this->baseUrl,
            'method' => 'POST',
            'form_data' => $formData,
            'additional_data' => [
                'order_id' => $orderId,
                'encrypted' => true,
            ],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Netopia is not properly configured. Keys present: ' . implode(', ', array_keys(array_filter($this->keys))));
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
            throw new \Exception('Missing required callback data: env_key=' . ($envKey ? 'present' : 'missing') . ', data=' . ($encryptedData ? 'present' : 'missing'));
        }

        Log::channel('marketplace')->debug('Netopia: Decrypting callback', [
            'cipher' => $cipher ?: 'auto-detect',
        ]);

        $decryptedData = $this->decryptData($encryptedData, $envKey, $cipher, $iv);

        Log::channel('marketplace')->info('Netopia: Callback decrypted OK', [
            'decrypted_length' => strlen($decryptedData),
        ]);

        // Parse XML with XXE protection
        libxml_use_internal_errors(true);
        $xmlData = simplexml_load_string($decryptedData, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

        if ($xmlData === false) {
            throw new \Exception('Netopia: Failed to parse callback XML');
        }

        // Parse the response
        $action = (string) ($xmlData->mobilpay->action ?? $xmlData->attributes()->action ?? '');
        $errorCode = (string) ($xmlData->mobilpay->error['code'] ?? $xmlData->error['code'] ?? '');
        $errorMessage = (string) ($xmlData->mobilpay->error ?? $xmlData->error ?? '');

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
            'order_id' => (string) ($xmlData->order_id ?? $xmlData->attributes()->id),
            'amount' => (float) ($xmlData->invoice['amount'] ?? $xmlData->amount ?? 0),
            'currency' => (string) ($xmlData->invoice['currency'] ?? $xmlData->currency ?? 'RON'),
            'transaction_id' => (string) $xmlData->attributes()->id,
            'paid_at' => $status === 'success' ? date('c') : null,
            'metadata' => [
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'action' => $action,
            ],
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // For Netopia, authenticity is verified by successful decryption
        // (data is encrypted with merchant's public key, only our private key can decrypt)
        return true;
    }

    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Netopia is not properly configured');
        }

        // Netopia doesn't provide a standard status check API
        // Status is typically obtained through callbacks
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
     * Create mobilPay XML structure (matches official mobilPay spec)
     */
    protected function createMobilPayXML(array $data): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><order/>');

        $xml->addAttribute('id', $data['orderId']);
        $xml->addAttribute('timestamp', (string) time());
        $xml->addAttribute('type', 'card');

        $xml->addChild('signature', $data['signature']);

        $invoice = $xml->addChild('invoice');
        $invoice->addAttribute('currency', $data['currency']);
        $invoice->addAttribute('amount', $data['amount']);

        $invoice->addChild('details', htmlspecialchars($data['details']));

        // Contact info with billing element (required by mobilPay)
        $contactInfo = $invoice->addChild('contact_info');
        $billing = $contactInfo->addChild('billing');
        $billing->addAttribute('type', 'person');

        $customerName = $data['customer_name'] ?? '';
        $nameParts = explode(' ', trim($customerName), 2);
        $billing->addChild('first_name', htmlspecialchars($nameParts[0] ?? 'N/A'));
        $billing->addChild('last_name', htmlspecialchars($nameParts[1] ?? 'N/A'));

        if (!empty($data['customer_email'])) {
            $billing->addChild('email', $data['customer_email']);
        }

        $billing->addChild('address', 'N/A');
        $billing->addChild('mobile_phone', 'N/A');

        $xml->addChild('url');
        $xml->url->addChild('confirm', htmlspecialchars($data['confirmUrl']));
        $xml->url->addChild('return', htmlspecialchars($data['returnUrl']));

        return $xml->asXML();
    }

    /**
     * Encrypt data using openssl_seal() — matches official mobilPay protocol
     */
    protected function encryptData(string $data): array
    {
        $publicKeyCert = $this->keys['public_key'];

        // Read X.509 certificate and extract public key
        $certResource = openssl_x509_read($publicKeyCert);
        if (!$certResource) {
            // Try as direct public key
            $publicKey = openssl_pkey_get_public($publicKeyCert);
        } else {
            $publicKey = openssl_pkey_get_public($certResource);
        }

        if (!$publicKey) {
            throw new \Exception('Netopia: Invalid public key/certificate: ' . openssl_error_string());
        }

        $envKeys = [];
        $encryptedData = '';

        // Try RC4 first (widest Netopia compatibility, especially sandbox)
        $cipher = 'rc4';
        $iv = '';
        $result = @openssl_seal($data, $encryptedData, $envKeys, [$publicKey], $cipher);

        if ($result === false) {
            // Fallback to AES-256-CBC (RC4 may be disabled in OpenSSL 3.0+)
            $cipher = 'aes-256-cbc';
            $iv = '';
            $result = openssl_seal($data, $encryptedData, $envKeys, [$publicKey], $cipher, $iv);

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
     * Decrypt callback data — handles OpenSSL 3.0+ where RC4 is removed
     */
    protected function decryptData(string $encryptedData, string $encryptedKey, string $cipher = '', string $iv = ''): string
    {
        $rawKey = $this->keys['private_key'] ?? '';
        if (empty($rawKey)) {
            throw new \Exception('Netopia: Private key is empty');
        }

        // Normalize line endings (DB may store \r\n, openssl needs \n)
        $rawKey = str_replace("\r\n", "\n", $rawKey);

        $privateKey = openssl_pkey_get_private($rawKey);
        if (!$privateKey) {
            throw new \Exception('Netopia: Invalid private key: ' . openssl_error_string());
        }

        $srcData = base64_decode($encryptedData);
        $srcEnvKey = base64_decode($encryptedKey);
        $srcIv = !empty($iv) ? base64_decode($iv) : '';

        // Default cipher based on what was sent
        if (empty($cipher)) {
            $cipher = !empty($srcIv) ? 'aes-256-cbc' : 'rc4';
        }

        // Note: public_key is Netopia's certificate (for encrypting TO Netopia),
        // private_key is merchant's key (for decrypting FROM Netopia) — different key pairs by design.

        // Attempt 1: Try to enable legacy provider and use openssl_open
        $data = '';
        $result = false;

        // Try loading OpenSSL legacy provider via temp config (for RC4 support)
        if ($cipher === 'rc4') {
            $legacyConf = $this->tryEnableLegacyProvider();
            if ($legacyConf) {
                $result = @openssl_open($srcData, $data, $srcEnvKey, $privateKey, 'rc4');
                if ($result !== false) {
                    Log::channel('marketplace')->info('Netopia: Decrypted via openssl_open with legacy provider');
                    @unlink($legacyConf);
                    return $data;
                }
                @unlink($legacyConf);
            }

            // Try without legacy provider (in case it's already available)
            $result = @openssl_open($srcData, $data, $srcEnvKey, $privateKey, 'rc4');
        } elseif (!empty($srcIv)) {
            $result = @openssl_open($srcData, $data, $srcEnvKey, $privateKey, $cipher, $srcIv);
        }

        if ($result !== false) {
            Log::channel('marketplace')->info('Netopia: Decrypted via openssl_open');
            return $data;
        }

        // Attempt 2: Use openssl CLI tool (can load legacy provider independently)
        $cliResult = $this->decryptViaCli($srcData, $srcEnvKey, $rawKey, $cipher, $srcIv);
        if ($cliResult !== false) {
            Log::channel('marketplace')->info('Netopia: Decrypted via openssl CLI');
            return $cliResult;
        }

        // Attempt 3: Manual RSA + pure PHP RC4
        if (!openssl_private_decrypt($srcEnvKey, $symmetricKey, $privateKey, OPENSSL_PKCS1_PADDING)) {
            throw new \Exception('Netopia: Failed to decrypt envelope key: ' . openssl_error_string());
        }

        if ($cipher === 'rc4') {
            $data = $this->rc4($symmetricKey, $srcData);
        } else {
            $data = @openssl_decrypt($srcData, $cipher, $symmetricKey, OPENSSL_RAW_DATA, $srcIv);
            if ($data === false) {
                throw new \Exception('Netopia: Failed to decrypt with ' . $cipher . ': ' . openssl_error_string());
            }
        }

        // Check if output looks like XML
        $looksLikeXml = str_starts_with(trim($data), '<?xml') || str_starts_with(trim($data), '<order');
        if ($looksLikeXml) {
            Log::channel('marketplace')->info('Netopia: Decrypted via manual RSA + PHP RC4');
            return $data;
        }

        throw new \Exception('Netopia: Decryption produced invalid data (not XML). '
            . 'This usually means the private key does not match the certificate Netopia used to encrypt. '
            . 'Key len=' . strlen($symmetricKey) . ' bytes');
    }

    /**
     * Try to enable OpenSSL legacy provider via temporary config file
     */
    protected function tryEnableLegacyProvider(): ?string
    {
        try {
            $conf = tempnam(sys_get_temp_dir(), 'openssl_legacy_');
            if (!$conf) {
                return null;
            }

            file_put_contents($conf, implode("\n", [
                'openssl_conf = openssl_init',
                '',
                '[openssl_init]',
                'providers = provider_sect',
                '',
                '[provider_sect]',
                'default = default_sect',
                'legacy = legacy_sect',
                '',
                '[default_sect]',
                'activate = 1',
                '',
                '[legacy_sect]',
                'activate = 1',
            ]));

            // Set before any OpenSSL calls
            putenv("OPENSSL_CONF={$conf}");

            return $conf;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Decrypt using openssl CLI tool (can access legacy provider independently of PHP extension)
     */
    protected function decryptViaCli(string $srcData, string $srcEnvKey, string $privateKeyPem, string $cipher, string $srcIv): string|false
    {
        // Check if openssl CLI is available
        $opensslPath = trim(@shell_exec('which openssl 2>/dev/null') ?? '');
        if (empty($opensslPath)) {
            return false;
        }

        try {
            // Write temp files
            $tmpDir = sys_get_temp_dir();
            $envKeyFile = tempnam($tmpDir, 'netopia_ek_');
            $dataFile = tempnam($tmpDir, 'netopia_dat_');
            $keyFile = tempnam($tmpDir, 'netopia_key_');
            $decKeyFile = tempnam($tmpDir, 'netopia_dk_');

            file_put_contents($envKeyFile, $srcEnvKey);
            file_put_contents($dataFile, $srcData);
            file_put_contents($keyFile, $privateKeyPem);

            // Step 1: RSA decrypt the envelope key
            $cmd1 = sprintf(
                '%s pkeyutl -decrypt -inkey %s -in %s -out %s -pkeyopt rsa_padding_mode:pkcs1 2>&1',
                escapeshellarg($opensslPath),
                escapeshellarg($keyFile),
                escapeshellarg($envKeyFile),
                escapeshellarg($decKeyFile)
            );
            exec($cmd1, $output1, $code1);

            if ($code1 !== 0) {
                Log::channel('marketplace')->warning('Netopia CLI: RSA decrypt failed', [
                    'exit_code' => $code1,
                    'output' => implode("\n", $output1),
                ]);
                $this->cleanupTempFiles($envKeyFile, $dataFile, $keyFile, $decKeyFile);
                return false;
            }

            $symmetricKey = file_get_contents($decKeyFile);
            $symKeyHex = bin2hex($symmetricKey);

            // Step 2: Decrypt data with the symmetric key
            if ($cipher === 'rc4') {
                // Try openssl CLI with legacy provider for RC4
                $cmd2 = sprintf(
                    '%s enc -rc4 -d -K %s -nosalt -in %s -provider legacy -provider default 2>&1',
                    escapeshellarg($opensslPath),
                    $symKeyHex,
                    escapeshellarg($dataFile)
                );
                $data = @shell_exec($cmd2);

                if ($data === null || $data === '') {
                    // Try without provider flags (older OpenSSL)
                    $cmd2b = sprintf(
                        '%s enc -rc4 -d -K %s -nosalt -in %s 2>&1',
                        escapeshellarg($opensslPath),
                        $symKeyHex,
                        escapeshellarg($dataFile)
                    );
                    $data = @shell_exec($cmd2b);
                }

                if ($data === null || $data === '') {
                    // Last fallback: use pure PHP RC4 with CLI-decrypted key
                    $data = $this->rc4($symmetricKey, $srcData);
                }
            } else {
                $ivHex = bin2hex($srcIv);
                $cmd2 = sprintf(
                    '%s enc -%s -d -K %s -iv %s -nosalt -in %s 2>&1',
                    escapeshellarg($opensslPath),
                    escapeshellarg($cipher),
                    $symKeyHex,
                    $ivHex,
                    escapeshellarg($dataFile)
                );
                $data = @shell_exec($cmd2);
            }

            $this->cleanupTempFiles($envKeyFile, $dataFile, $keyFile, $decKeyFile);

            if ($data && (str_starts_with(trim($data), '<?xml') || str_starts_with(trim($data), '<order'))) {
                return $data;
            }

            Log::channel('marketplace')->info('Netopia CLI: decrypt completed but output not XML', [
                'data_len' => strlen($data ?? ''),
                'first_bytes' => bin2hex(substr($data ?? '', 0, 16)),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Netopia CLI: exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clean up temporary files used for CLI decryption
     */
    protected function cleanupTempFiles(string ...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Pure PHP RC4 implementation — works regardless of OpenSSL version
     * RC4 is a symmetric stream cipher: same function encrypts and decrypts
     */
    protected function rc4(string $key, string $data): string
    {
        // Key-Scheduling Algorithm (KSA)
        $s = range(0, 255);
        $j = 0;
        $keyLen = strlen($key);

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLen])) & 255;
            $tmp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $tmp;
        }

        // Pseudo-Random Generation Algorithm (PRGA) + XOR
        $i = 0;
        $j = 0;
        $result = '';
        $dataLen = strlen($data);

        for ($k = 0; $k < $dataLen; $k++) {
            $i = ($i + 1) & 255;
            $j = ($j + $s[$i]) & 255;
            $tmp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $tmp;
            $result .= chr(ord($data[$k]) ^ $s[($s[$i] + $s[$j]) & 255]);
        }

        return $result;
    }
}

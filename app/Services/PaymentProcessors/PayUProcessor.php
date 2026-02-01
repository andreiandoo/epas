<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;

class PayUProcessor implements PaymentProcessorInterface
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
            ? 'https://secure.payu.ro/order'
            : 'https://sandbox.payu.ro/order';
    }

    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayU is not properly configured');
        }

        // Generate unique payment reference
        $paymentRef = $data['order_id'] ?? ('PAYU_' . strtoupper(uniqid()));

        // Prepare order data
        $orderData = [
            'MERCHANT' => $this->keys['merchant_id'],
            'ORDER_REF' => $paymentRef,
            'ORDER_DATE' => date('Y-m-d H:i:s'),
            'ORDER_PNAME[]' => $data['description'] ?? 'Payment',
            'ORDER_PCODE[]' => 'PRODUCT_001',
            'ORDER_PRICE[]' => number_format($data['amount'], 2, '.', ''),
            'ORDER_QTY[]' => '1',
            'ORDER_VAT[]' => '0',
            'PRICES_CURRENCY' => strtoupper($data['currency'] ?? 'RON'),
            'PAY_METHOD' => 'CCVISAMC',
            'BACK_REF' => $data['success_url'],
            'ORDER_TIMEOUT' => '1800',
        ];

        // Add customer information
        if (!empty($data['customer_email'])) {
            $orderData['BILL_EMAIL'] = $data['customer_email'];
        }

        if (!empty($data['customer_name'])) {
            $nameParts = explode(' ', $data['customer_name'], 2);
            $orderData['BILL_FNAME'] = $nameParts[0] ?? '';
            $orderData['BILL_LNAME'] = $nameParts[1] ?? '';
        }

        // Add metadata as custom fields
        if (!empty($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                $orderData["ORDER_MDATA_{$key}"] = $value;
            }
        }

        // Generate HMAC signature
        $orderData['ORDER_HASH'] = $this->generateSignature($orderData);

        // Create HTML form for redirect
        $formHtml = $this->buildRedirectForm($orderData);

        // For API usage, we return the form data
        // The client will need to auto-submit this form
        return [
            'payment_id' => $paymentRef,
            'redirect_url' => $this->baseUrl . '/alu/v3',
            'additional_data' => [
                'form_data' => $orderData,
                'form_html' => $formHtml,
                'auto_submit' => true,
            ],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayU is not properly configured');
        }

        // Verify signature
        if (!$this->verifySignature($payload, $headers)) {
            throw new \Exception('Invalid callback signature');
        }

        // Extract IPN (Instant Payment Notification) data
        $orderRef = $payload['REFNOEXT'] ?? $payload['ORDER_REF'] ?? null;
        $transactionId = $payload['REFNO'] ?? null;
        $amount = (float) ($payload['IPN_TOTALGENERAL'] ?? $payload['AMOUNT'] ?? 0);
        $currency = strtoupper($payload['CURRENCY'] ?? 'RON');
        $paymentStatus = $payload['ORDERSTATUS'] ?? '';

        // Map PayU status to our standard status
        $status = match ($paymentStatus) {
            'COMPLETE', 'PAYMENT_AUTHORIZED' => 'success',
            'PAYMENT_RECEIVED' => 'success',
            'TEST' => 'success',
            'IN_PROGRESS', 'PENDING' => 'pending',
            'REVERSED', 'REFUND' => 'refunded',
            'CANCELED', 'TIMEOUT' => 'cancelled',
            default => 'failed',
        };

        // Prepare metadata
        $metadata = [
            'order_status' => $paymentStatus,
            'ipn_date' => $payload['IPN_DATE'] ?? date('Y-m-d H:i:s'),
        ];

        if (isset($payload['PAYMETHOD'])) {
            $metadata['payment_method'] = $payload['PAYMETHOD'];
        }

        return [
            'status' => $status,
            'payment_id' => $orderRef,
            'order_id' => $orderRef,
            'amount' => $amount,
            'currency' => $currency,
            'transaction_id' => $transactionId,
            'paid_at' => $status === 'success' ? date('c') : null,
            'metadata' => $metadata,
        ];
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // SECURITY FIX: If no secret key, REJECT the webhook
        if (empty($this->keys['secret_key'])) {
            \Log::critical('PayU webhook rejected: secret_key not configured', [
                'ip' => request()->ip(),
            ]);
            return false;
        }

        $receivedHash = $payload['HASH'] ?? $payload['ORDER_HASH'] ?? '';

        if (empty($receivedHash)) {
            return false;
        }

        // Build signature string based on IPN or response
        if (isset($payload['IPN_PID'])) {
            // This is an IPN
            $signatureData = array_filter($payload, function ($key) {
                return $key !== 'HASH';
            }, ARRAY_FILTER_USE_KEY);
        } else {
            // This is a redirect response
            $signatureData = $payload;
        }

        $expectedHash = $this->generateSignature($signatureData);

        return hash_equals($expectedHash, $receivedHash);
    }

    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayU is not properly configured');
        }

        try {
            // Prepare IOS (Instant Order Status) request
            $requestData = [
                'MERCHANT' => $this->keys['merchant_id'],
                'ORDER_REF' => $paymentId,
                'ORDER_AMOUNT' => '',
                'ORDER_CURRENCY' => 'RON',
                'IDN_DATE' => date('Y-m-d H:i:s'),
            ];

            $requestData['ORDER_HASH'] = $this->generateSignature($requestData);

            // Make API request
            $url = $this->baseUrl . '/ios.php';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->mode === 'live');
            $response = curl_exec($ch);
            curl_close($ch);

            // Parse XML response
            $xml = simplexml_load_string($response);

            if (!$xml || !isset($xml->ORDER)) {
                throw new \Exception('Invalid response from PayU');
            }

            $orderStatus = (string) $xml->ORDER->STATUS;

            $statusMap = [
                'COMPLETE' => 'success',
                'PAYMENT_AUTHORIZED' => 'success',
                'PAYMENT_RECEIVED' => 'success',
                'TEST' => 'success',
                'IN_PROGRESS' => 'pending',
                'PENDING' => 'pending',
                'REVERSED' => 'refunded',
                'REFUND' => 'refunded',
                'CANCELED' => 'cancelled',
                'TIMEOUT' => 'cancelled',
            ];

            $status = $statusMap[$orderStatus] ?? 'failed';

            return [
                'status' => $status,
                'amount' => (float) $xml->ORDER->AMOUNT,
                'currency' => (string) $xml->ORDER->CURRENCY,
                'paid_at' => $status === 'success' ? (string) $xml->ORDER->DATE : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayU is not properly configured');
        }

        try {
            // Get transaction details first
            $statusData = $this->getPaymentStatus($paymentId);

            // Prepare refund request (IRN - Instant Refund Notification)
            $requestData = [
                'MERCHANT' => $this->keys['merchant_id'],
                'ORDER_REF' => $paymentId,
                'ORDER_AMOUNT' => $amount ? number_format($amount, 2, '.', '') : number_format($statusData['amount'], 2, '.', ''),
                'ORDER_CURRENCY' => $statusData['currency'],
                'IRN_DATE' => date('Y-m-d H:i:s'),
            ];

            if ($reason) {
                $requestData['ORDER_MDATA_reason'] = $reason;
            }

            $requestData['ORDER_HASH'] = $this->generateSignature($requestData);

            // Make API request
            $url = $this->baseUrl . '/irn.php';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->mode === 'live');
            $response = curl_exec($ch);
            curl_close($ch);

            // Parse XML response
            $xml = simplexml_load_string($response);

            if (!$xml || !isset($xml->REFUND)) {
                throw new \Exception('Invalid response from PayU');
            }

            $refundStatus = (string) $xml->REFUND->STATUS;

            $statusMap = [
                'CONFIRMED' => 'success',
                'PENDING' => 'pending',
                'FAILED' => 'failed',
            ];

            return [
                'refund_id' => (string) $xml->REFUND->REFNO ?? uniqid('refund_'),
                'status' => $statusMap[$refundStatus] ?? 'pending',
                'amount' => (float) $requestData['ORDER_AMOUNT'],
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
        return 'PayU';
    }

    /**
     * Generate HMAC-MD5 signature for PayU
     */
    protected function generateSignature(array $data): string
    {
        // Build signature string
        $signatureString = '';

        foreach ($data as $key => $value) {
            if ($key !== 'ORDER_HASH' && $key !== 'HASH') {
                $length = strlen($value);
                $signatureString .= $length . $value;
            }
        }

        // Generate HMAC-MD5 hash
        return hash_hmac('md5', $signatureString, $this->keys['secret_key']);
    }

    /**
     * Build HTML form for auto-redirect to PayU
     */
    protected function buildRedirectForm(array $orderData): string
    {
        $formFields = '';
        foreach ($orderData as $key => $value) {
            $formFields .= sprintf(
                '<input type="hidden" name="%s" value="%s" />' . "\n",
                htmlspecialchars($key),
                htmlspecialchars($value)
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting to PayU...</title>
</head>
<body>
    <p>Redirecting to payment gateway...</p>
    <form id="payu_form" action="{$this->baseUrl}/alu/v3" method="POST">
        {$formFields}
    </form>
    <script>
        document.getElementById('payu_form').submit();
    </script>
</body>
</html>
HTML;
    }
}

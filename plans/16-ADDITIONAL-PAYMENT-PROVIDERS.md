# Additional Payment Providers Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently limited payment options (Stripe only) create friction:
1. **Regional limitations**: Stripe not available in all countries
2. **User preference**: Customers prefer their trusted payment methods
3. **Mobile payments**: Apple Pay, Google Pay for faster checkout
4. **Buy Now Pay Later**: Klarna, Afterpay for high-value tickets
5. **Local methods**: PayPal, Revolut, local bank transfers

### What This Feature Does
- PayPal integration (PayPal Checkout)
- Apple Pay / Google Pay via Stripe
- Klarna / Afterpay for BNPL
- Bank transfer option for large orders
- Multi-provider checkout selection
- Unified payment processing abstraction

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000080_add_payment_providers.php
Schema::create('payment_providers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('provider'); // stripe, paypal, klarna, bank_transfer
    $table->string('display_name');
    $table->boolean('is_active')->default(false);
    $table->boolean('is_default')->default(false);
    $table->json('config')->nullable(); // Encrypted credentials
    $table->json('supported_currencies')->nullable();
    $table->decimal('min_amount', 10, 2)->nullable();
    $table->decimal('max_amount', 10, 2)->nullable();
    $table->integer('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['tenant_id', 'provider']);
});

// Add provider to payments table
Schema::table('payments', function (Blueprint $table) {
    $table->string('provider')->default('stripe')->after('payment_method');
    $table->string('provider_payment_id')->nullable()->after('provider');
    $table->json('provider_metadata')->nullable();
});

Schema::create('payment_provider_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('payment_id')->nullable()->constrained();
    $table->string('provider');
    $table->string('action');
    $table->json('request')->nullable();
    $table->json('response')->nullable();
    $table->boolean('success')->default(true);
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index(['tenant_id', 'provider', 'created_at']);
});
```

### 2. Payment Gateway Interface

```php
// app/Contracts/PaymentGatewayInterface.php
<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getDisplayName(): string;

    public function createPaymentIntent(Order $order, array $options = []): array;
    public function capturePayment(Payment $payment): array;
    public function refundPayment(Payment $payment, ?float $amount = null): array;
    public function getPaymentStatus(string $providerPaymentId): string;

    public function supportsRecurring(): bool;
    public function supportsCurrency(string $currency): bool;
    public function getMinAmount(): ?float;
    public function getMaxAmount(): ?float;

    public function handleWebhook(array $payload): void;
}
```

### 3. PayPal Gateway

```php
// app/Services/Payments/PayPalGateway.php
<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;

class PayPalGateway implements PaymentGatewayInterface
{
    protected PayPalHttpClient $client;

    public function __construct(array $config)
    {
        $environment = config('app.env') === 'production'
            ? new ProductionEnvironment($config['client_id'], $config['client_secret'])
            : new SandboxEnvironment($config['client_id'], $config['client_secret']);

        $this->client = new PayPalHttpClient($environment);
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal';
    }

    public function createPaymentIntent(Order $order, array $options = []): array
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_number,
                'amount' => [
                    'currency_code' => strtoupper($order->currency),
                    'value' => number_format($order->total, 2, '.', ''),
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => strtoupper($order->currency),
                            'value' => number_format($order->subtotal, 2, '.', ''),
                        ],
                        'tax_total' => [
                            'currency_code' => strtoupper($order->currency),
                            'value' => number_format($order->tax_amount ?? 0, 2, '.', ''),
                        ],
                    ],
                ],
                'items' => $order->items->map(fn($item) => [
                    'name' => $item->name,
                    'quantity' => (string) $item->quantity,
                    'unit_amount' => [
                        'currency_code' => strtoupper($order->currency),
                        'value' => number_format($item->unit_price, 2, '.', ''),
                    ],
                ])->toArray(),
            ]],
            'application_context' => [
                'brand_name' => config('app.name'),
                'return_url' => url("/checkout/{$order->id}/paypal-return"),
                'cancel_url' => url("/checkout/{$order->id}/paypal-cancel"),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->client->execute($request);

        $approvalLink = collect($response->result->links)
            ->firstWhere('rel', 'approve')
            ->href ?? null;

        return [
            'provider_payment_id' => $response->result->id,
            'status' => $response->result->status,
            'approval_url' => $approvalLink,
        ];
    }

    public function capturePayment(Payment $payment): array
    {
        $request = new OrdersCaptureRequest($payment->provider_payment_id);
        $request->prefer('return=representation');

        $response = $this->client->execute($request);

        return [
            'status' => $response->result->status,
            'capture_id' => $response->result->purchase_units[0]->payments->captures[0]->id ?? null,
        ];
    }

    public function refundPayment(Payment $payment, ?float $amount = null): array
    {
        $captureId = $payment->provider_metadata['capture_id'] ?? null;

        if (!$captureId) {
            throw new \Exception('No capture ID found for refund');
        }

        $request = new CapturesRefundRequest($captureId);

        if ($amount !== null) {
            $request->body = [
                'amount' => [
                    'currency_code' => strtoupper($payment->currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ];
        }

        $response = $this->client->execute($request);

        return [
            'refund_id' => $response->result->id,
            'status' => $response->result->status,
        ];
    }

    public function getPaymentStatus(string $providerPaymentId): string
    {
        $request = new \PayPalCheckoutSdk\Orders\OrdersGetRequest($providerPaymentId);
        $response = $this->client->execute($request);

        return match ($response->result->status) {
            'COMPLETED' => 'completed',
            'APPROVED' => 'approved',
            'VOIDED' => 'cancelled',
            default => 'pending',
        };
    }

    public function supportsRecurring(): bool
    {
        return true; // With PayPal Subscriptions
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK',
        ]);
    }

    public function getMinAmount(): ?float
    {
        return 1.00;
    }

    public function getMaxAmount(): ?float
    {
        return null;
    }

    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['event_type'] ?? null;

        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($payload),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handleRefund($payload),
            default => null,
        };
    }

    protected function handleCaptureCompleted(array $payload): void
    {
        $orderId = $payload['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
        if ($orderId) {
            $payment = Payment::where('provider_payment_id', $orderId)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'provider_metadata' => array_merge(
                        $payment->provider_metadata ?? [],
                        ['capture_id' => $payload['resource']['id']]
                    ),
                ]);
            }
        }
    }

    protected function handleRefund(array $payload): void
    {
        // Handle refund confirmation
    }
}
```

### 4. Klarna Gateway (BNPL)

```php
// app/Services/Payments/KlarnaGateway.php
<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class KlarnaGateway implements PaymentGatewayInterface
{
    protected string $apiUrl;
    protected string $username;
    protected string $password;

    public function __construct(array $config)
    {
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->apiUrl = config('app.env') === 'production'
            ? 'https://api.klarna.com'
            : 'https://api.playground.klarna.com';
    }

    public function getName(): string
    {
        return 'klarna';
    }

    public function getDisplayName(): string
    {
        return 'Klarna - Pay Later';
    }

    public function createPaymentIntent(Order $order, array $options = []): array
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->post("{$this->apiUrl}/payments/v1/sessions", [
                'purchase_country' => $order->customer->country ?? 'US',
                'purchase_currency' => strtoupper($order->currency),
                'locale' => 'en-US',
                'order_amount' => (int) ($order->total * 100),
                'order_tax_amount' => (int) (($order->tax_amount ?? 0) * 100),
                'order_lines' => $order->items->map(fn($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (int) ($item->unit_price * 100),
                    'total_amount' => (int) ($item->total * 100),
                    'tax_rate' => 0,
                    'total_tax_amount' => 0,
                ])->toArray(),
                'merchant_urls' => [
                    'confirmation' => url("/checkout/{$order->id}/klarna-confirm"),
                    'notification' => url('/webhooks/klarna'),
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Klarna session creation failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'provider_payment_id' => $data['session_id'],
            'client_token' => $data['client_token'],
            'payment_methods' => $data['payment_method_categories'] ?? [],
        ];
    }

    public function capturePayment(Payment $payment): array
    {
        // Klarna requires authorization token from frontend
        $authToken = $payment->provider_metadata['authorization_token'] ?? null;

        if (!$authToken) {
            throw new \Exception('No Klarna authorization token');
        }

        $order = $payment->order;

        $response = Http::withBasicAuth($this->username, $this->password)
            ->post("{$this->apiUrl}/payments/v1/authorizations/{$authToken}/order", [
                'purchase_country' => $order->customer->country ?? 'US',
                'purchase_currency' => strtoupper($order->currency),
                'order_amount' => (int) ($order->total * 100),
                'order_tax_amount' => (int) (($order->tax_amount ?? 0) * 100),
                'order_lines' => $order->items->map(fn($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (int) ($item->unit_price * 100),
                    'total_amount' => (int) ($item->total * 100),
                    'tax_rate' => 0,
                    'total_tax_amount' => 0,
                ])->toArray(),
            ]);

        if (!$response->successful()) {
            throw new \Exception('Klarna order creation failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'order_id' => $data['order_id'],
            'status' => 'captured',
        ];
    }

    public function refundPayment(Payment $payment, ?float $amount = null): array
    {
        $orderId = $payment->provider_metadata['klarna_order_id'] ?? null;

        if (!$orderId) {
            throw new \Exception('No Klarna order ID for refund');
        }

        $refundAmount = $amount !== null
            ? (int) ($amount * 100)
            : (int) ($payment->amount * 100);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->post("{$this->apiUrl}/ordermanagement/v1/orders/{$orderId}/refunds", [
                'refunded_amount' => $refundAmount,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Klarna refund failed: ' . $response->body());
        }

        return [
            'refund_id' => $response->json('refund_id'),
            'status' => 'refunded',
        ];
    }

    public function getPaymentStatus(string $providerPaymentId): string
    {
        return 'pending'; // Klarna uses webhooks
    }

    public function supportsRecurring(): bool
    {
        return false;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['USD', 'EUR', 'GBP', 'SEK', 'NOK', 'DKK', 'AUD']);
    }

    public function getMinAmount(): ?float
    {
        return 35.00; // Klarna minimum
    }

    public function getMaxAmount(): ?float
    {
        return 10000.00; // Typical BNPL limit
    }

    public function handleWebhook(array $payload): void
    {
        // Handle Klarna webhooks
    }
}
```

### 5. Payment Gateway Factory

```php
// app/Services/Payments/PaymentGatewayFactory.php
<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\PaymentProvider;

class PaymentGatewayFactory
{
    public function make(string $provider, ?int $tenantId = null): PaymentGatewayInterface
    {
        $config = $this->getConfig($provider, $tenantId);

        return match ($provider) {
            'stripe' => new StripeGateway($config),
            'paypal' => new PayPalGateway($config),
            'klarna' => new KlarnaGateway($config),
            'apple_pay', 'google_pay' => new StripeGateway($config), // Via Stripe
            default => throw new \InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }

    public function getAvailableProviders(int $tenantId, float $amount, string $currency): array
    {
        return PaymentProvider::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(function ($provider) use ($amount, $currency) {
                $gateway = $this->make($provider->provider, $provider->tenant_id);

                if (!$gateway->supportsCurrency($currency)) {
                    return false;
                }

                if ($gateway->getMinAmount() !== null && $amount < $gateway->getMinAmount()) {
                    return false;
                }

                if ($gateway->getMaxAmount() !== null && $amount > $gateway->getMaxAmount()) {
                    return false;
                }

                return true;
            })
            ->map(fn($p) => [
                'provider' => $p->provider,
                'display_name' => $p->display_name,
                'is_default' => $p->is_default,
            ])
            ->values()
            ->toArray();
    }

    protected function getConfig(string $provider, ?int $tenantId): array
    {
        if ($tenantId) {
            $providerConfig = PaymentProvider::where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->first();

            if ($providerConfig && $providerConfig->config) {
                return decrypt($providerConfig->config);
            }
        }

        // Fall back to environment config
        return match ($provider) {
            'stripe' => [
                'secret_key' => config('services.stripe.secret'),
                'publishable_key' => config('services.stripe.key'),
            ],
            'paypal' => [
                'client_id' => config('services.paypal.client_id'),
                'client_secret' => config('services.paypal.client_secret'),
            ],
            'klarna' => [
                'username' => config('services.klarna.username'),
                'password' => config('services.klarna.password'),
            ],
            default => [],
        };
    }
}
```

### 6. Unified Payment Service

```php
// app/Services/Payments/UnifiedPaymentService.php
<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProviderLog;
use Illuminate\Support\Facades\DB;

class UnifiedPaymentService
{
    public function __construct(
        protected PaymentGatewayFactory $factory
    ) {}

    /**
     * Create payment for order
     */
    public function createPayment(Order $order, string $provider, array $options = []): Payment
    {
        $gateway = $this->factory->make($provider, $order->tenant_id);

        return DB::transaction(function () use ($order, $provider, $gateway, $options) {
            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'amount' => $order->total,
                'currency' => $order->currency,
                'provider' => $provider,
                'status' => 'pending',
            ]);

            // Log request
            $this->log($payment, 'create_intent', $options);

            try {
                $result = $gateway->createPaymentIntent($order, $options);

                $payment->update([
                    'provider_payment_id' => $result['provider_payment_id'],
                    'provider_metadata' => $result,
                ]);

                $this->log($payment, 'create_intent_success', $result, true);

                return $payment->fresh();
            } catch (\Exception $e) {
                $payment->update(['status' => 'failed']);
                $this->log($payment, 'create_intent_failed', ['error' => $e->getMessage()], false);
                throw $e;
            }
        });
    }

    /**
     * Capture/confirm payment
     */
    public function capturePayment(Payment $payment): Payment
    {
        $gateway = $this->factory->make($payment->provider, $payment->tenant_id);

        $this->log($payment, 'capture', []);

        try {
            $result = $gateway->capturePayment($payment);

            $payment->update([
                'status' => 'completed',
                'provider_metadata' => array_merge(
                    $payment->provider_metadata ?? [],
                    $result
                ),
            ]);

            $this->log($payment, 'capture_success', $result, true);

            return $payment->fresh();
        } catch (\Exception $e) {
            $this->log($payment, 'capture_failed', ['error' => $e->getMessage()], false);
            throw $e;
        }
    }

    /**
     * Refund payment
     */
    public function refundPayment(Payment $payment, ?float $amount = null): Payment
    {
        $gateway = $this->factory->make($payment->provider, $payment->tenant_id);

        $this->log($payment, 'refund', ['amount' => $amount]);

        try {
            $result = $gateway->refundPayment($payment, $amount);

            $refundedAmount = $amount ?? $payment->amount;
            $newStatus = $refundedAmount >= $payment->amount ? 'refunded' : 'partially_refunded';

            $payment->update([
                'status' => $newStatus,
                'refunded_amount' => ($payment->refunded_amount ?? 0) + $refundedAmount,
            ]);

            $this->log($payment, 'refund_success', $result, true);

            return $payment->fresh();
        } catch (\Exception $e) {
            $this->log($payment, 'refund_failed', ['error' => $e->getMessage()], false);
            throw $e;
        }
    }

    /**
     * Get available payment methods for checkout
     */
    public function getPaymentMethods(int $tenantId, float $amount, string $currency): array
    {
        return $this->factory->getAvailableProviders($tenantId, $amount, $currency);
    }

    protected function log(Payment $payment, string $action, array $data, bool $success = true): void
    {
        PaymentProviderLog::create([
            'tenant_id' => $payment->tenant_id,
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'action' => $action,
            'request' => $data,
            'success' => $success,
        ]);
    }
}
```

### 7. Controller

```php
// app/Http/Controllers/Api/TenantClient/PaymentController.php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\Payments\UnifiedPaymentService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(protected UnifiedPaymentService $paymentService) {}

    /**
     * Get available payment methods
     */
    public function methods(Request $request, Order $order): JsonResponse
    {
        $methods = $this->paymentService->getPaymentMethods(
            $order->tenant_id,
            $order->total,
            $order->currency
        );

        return response()->json(['payment_methods' => $methods]);
    }

    /**
     * Initiate payment
     */
    public function initiate(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:stripe,paypal,klarna,apple_pay,google_pay',
            'payment_method_id' => 'nullable|string', // For Stripe
        ]);

        $payment = $this->paymentService->createPayment(
            $order,
            $request->provider,
            $request->all()
        );

        return response()->json([
            'payment' => $payment,
            'client_token' => $payment->provider_metadata['client_token'] ?? null,
            'approval_url' => $payment->provider_metadata['approval_url'] ?? null,
        ]);
    }

    /**
     * Capture/confirm payment
     */
    public function capture(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'authorization_token' => 'nullable|string', // For Klarna
        ]);

        if ($request->authorization_token) {
            $payment->update([
                'provider_metadata' => array_merge(
                    $payment->provider_metadata ?? [],
                    ['authorization_token' => $request->authorization_token]
                ),
            ]);
        }

        $payment = $this->paymentService->capturePayment($payment);

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment captured successfully',
        ]);
    }

    /**
     * Handle PayPal return
     */
    public function paypalReturn(Request $request, Order $order): JsonResponse
    {
        $payment = $order->payments()->where('provider', 'paypal')->latest()->first();

        if ($payment) {
            $payment = $this->paymentService->capturePayment($payment);
        }

        return response()->json([
            'payment' => $payment,
            'order' => $order->fresh(),
        ]);
    }
}
```

### 8. Routes

```php
// routes/api.php
Route::prefix('tenant-client/payments')->middleware(['tenant', 'auth:customer'])->group(function () {
    Route::get('/orders/{order}/methods', [PaymentController::class, 'methods']);
    Route::post('/orders/{order}/initiate', [PaymentController::class, 'initiate']);
    Route::post('/payments/{payment}/capture', [PaymentController::class, 'capture']);
    Route::get('/orders/{order}/paypal-return', [PaymentController::class, 'paypalReturn']);
});

// Webhooks
Route::prefix('webhooks')->group(function () {
    Route::post('/paypal', [PayPalWebhookController::class, 'handle']);
    Route::post('/klarna', [KlarnaWebhookController::class, 'handle']);
});
```

### 9. Configuration

```php
// config/services.php
return [
    // ... existing config

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
    ],

    'klarna' => [
        'username' => env('KLARNA_USERNAME'),
        'password' => env('KLARNA_PASSWORD'),
        'mode' => env('KLARNA_MODE', 'playground'),
    ],
];
```

### 10. Package Installation

```bash
composer require paypal/paypal-checkout-sdk
```

---

## Testing Checklist

1. [ ] PayPal checkout flow works end-to-end
2. [ ] PayPal captures payment on return
3. [ ] PayPal refunds work
4. [ ] Klarna session creation works
5. [ ] Klarna authorization and capture works
6. [ ] Klarna refunds work
7. [ ] Apple Pay via Stripe works
8. [ ] Google Pay via Stripe works
9. [ ] Payment method availability based on amount/currency
10. [ ] Provider logs are recorded
11. [ ] Webhooks update payment status
12. [ ] Multi-tenant provider config works
13. [ ] Fallback to env config works

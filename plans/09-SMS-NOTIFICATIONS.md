# SMS Notifications Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently only WhatsApp and email notifications exist. SMS adds:
1. **Broader reach**: Not everyone uses WhatsApp
2. **Reliability**: SMS works without internet/smartphones
3. **Urgency**: SMS has higher open rates (98%) than email
4. **Two-factor auth**: SMS verification for security

### What This Feature Does
- Send transactional SMS (order confirmations, reminders)
- SMS-based 2FA verification codes
- Marketing SMS campaigns (with opt-in)
- Template management for SMS content
- Delivery tracking and analytics
- Multi-provider support (Twilio primary)

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000035_create_sms_tables.php
Schema::create('sms_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->string('phone_number', 20);
    $table->text('message');
    $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
    $table->enum('type', ['transactional', 'marketing', 'verification'])->default('transactional');
    $table->enum('status', ['pending', 'queued', 'sent', 'delivered', 'failed', 'received']);
    $table->string('provider')->default('twilio');
    $table->string('provider_message_id')->nullable();
    $table->string('error_code')->nullable();
    $table->text('error_message')->nullable();
    $table->decimal('cost', 10, 4)->nullable();
    $table->string('currency', 3)->default('USD');
    $table->integer('segments')->default(1);
    $table->timestamps();

    $table->index(['tenant_id', 'status']);
    $table->index(['phone_number']);
    $table->index(['provider_message_id']);
});

Schema::create('sms_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('content');
    $table->json('variables')->nullable();
    $table->enum('type', ['transactional', 'marketing'])->default('transactional');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('sms_opt_ins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->string('phone_number', 20);
    $table->boolean('transactional_consent')->default(true);
    $table->boolean('marketing_consent')->default(false);
    $table->timestamp('marketing_consented_at')->nullable();
    $table->timestamp('marketing_revoked_at')->nullable();
    $table->string('consent_source')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'customer_id']);
});
```

### 2. Configuration

```php
// config/sms.php
return [
    'default_provider' => env('SMS_PROVIDER', 'twilio'),

    'providers' => [
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_SMS_FROM'),
        ],
    ],

    'templates' => [
        'order_confirmation' => "Your order #{order_number} is confirmed! Event: {event_name} on {event_date}. Download tickets: {ticket_url}",
        'event_reminder' => "Reminder: {event_name} is tomorrow at {event_time}. Location: {venue_name}. See you there!",
        'verification_code' => "Your verification code is: {code}. Valid for 10 minutes.",
    ],

    'limits' => [
        'max_length' => 160,
        'max_segments' => 3,
    ],
];
```

### 3. Service Class

```php
// app/Services/Sms/SmsService.php
<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\SmsOptIn;
use App\Models\Customer;
use Twilio\Rest\Client as TwilioClient;

class SmsService
{
    protected TwilioClient $twilio;

    public function __construct()
    {
        $this->twilio = new TwilioClient(
            config('sms.providers.twilio.sid'),
            config('sms.providers.twilio.token')
        );
    }

    public function send(
        string $phoneNumber,
        string $message,
        ?int $tenantId = null,
        ?int $customerId = null,
        string $type = 'transactional'
    ): SmsMessage {
        // Check opt-in for marketing
        if ($type === 'marketing' && $customerId) {
            if (!$this->hasMarketingConsent($customerId, $tenantId)) {
                throw new \Exception('Customer has not opted in to marketing SMS');
            }
        }

        // Create record
        $smsMessage = SmsMessage::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'phone_number' => $this->formatPhoneNumber($phoneNumber),
            'message' => $message,
            'type' => $type,
            'status' => 'pending',
            'segments' => ceil(strlen($message) / 160),
        ]);

        // Send via Twilio
        try {
            $result = $this->twilio->messages->create(
                $smsMessage->phone_number,
                [
                    'from' => config('sms.providers.twilio.from'),
                    'body' => $message,
                    'statusCallback' => url('/webhooks/sms/status'),
                ]
            );

            $smsMessage->update([
                'status' => 'queued',
                'provider_message_id' => $result->sid,
            ]);
        } catch (\Exception $e) {
            $smsMessage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $smsMessage;
    }

    public function sendTemplate(
        string $phoneNumber,
        string $templateSlug,
        array $variables,
        ?int $tenantId = null,
        ?int $customerId = null
    ): SmsMessage {
        $template = SmsTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $message = $this->parseTemplate($template->content, $variables);

        return $this->send($phoneNumber, $message, $tenantId, $customerId, $template->type);
    }

    public function sendVerificationCode(string $phoneNumber, string $code): SmsMessage
    {
        $message = str_replace('{code}', $code, config('sms.templates.verification_code'));
        return $this->send($phoneNumber, $message, null, null, 'verification');
    }

    public function optIn(Customer $customer, string $phoneNumber, bool $marketing = false): SmsOptIn
    {
        return SmsOptIn::updateOrCreate(
            ['customer_id' => $customer->id, 'tenant_id' => $customer->tenant_id],
            [
                'phone_number' => $this->formatPhoneNumber($phoneNumber),
                'transactional_consent' => true,
                'marketing_consent' => $marketing,
                'marketing_consented_at' => $marketing ? now() : null,
            ]
        );
    }

    public function optOut(Customer $customer): void
    {
        SmsOptIn::where('customer_id', $customer->id)->update([
            'marketing_consent' => false,
            'marketing_revoked_at' => now(),
        ]);
    }

    public function hasMarketingConsent(int $customerId, ?int $tenantId): bool
    {
        return SmsOptIn::where('customer_id', $customerId)
            ->where('tenant_id', $tenantId)
            ->where('marketing_consent', true)
            ->exists();
    }

    protected function parseTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!str_starts_with($phone, '+')) {
            $phone = '+1' . $phone; // Default to US
        }
        return $phone;
    }

    public function handleStatusWebhook(array $data): void
    {
        $messageId = $data['MessageSid'] ?? null;
        $status = $data['MessageStatus'] ?? null;

        if (!$messageId) return;

        $message = SmsMessage::where('provider_message_id', $messageId)->first();
        if (!$message) return;

        $statusMap = [
            'queued' => 'queued',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'undelivered' => 'failed',
        ];

        $message->update([
            'status' => $statusMap[$status] ?? $status,
            'error_code' => $data['ErrorCode'] ?? null,
            'error_message' => $data['ErrorMessage'] ?? null,
        ]);
    }
}
```

### 4. Controller

```php
// app/Http/Controllers/Api/SmsController.php
class SmsController extends Controller
{
    public function __construct(protected SmsService $smsService) {}

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'message' => 'required|string|max:480',
        ]);

        $message = $this->smsService->send(
            $request->phone_number,
            $request->message,
            $request->tenant_id,
            $request->customer_id
        );

        return response()->json(['message_id' => $message->id, 'status' => $message->status]);
    }

    public function sendTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'template' => 'required|string',
            'variables' => 'array',
        ]);

        $message = $this->smsService->sendTemplate(
            $request->phone_number,
            $request->template,
            $request->variables ?? [],
            $request->tenant_id,
            $request->customer_id
        );

        return response()->json(['message_id' => $message->id, 'status' => $message->status]);
    }

    public function optIn(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'marketing' => 'boolean',
        ]);

        $customer = $request->user('customer');
        $optIn = $this->smsService->optIn($customer, $request->phone_number, $request->marketing ?? false);

        return response()->json(['opt_in' => $optIn]);
    }

    public function optOut(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $this->smsService->optOut($customer);

        return response()->json(['message' => 'Opted out of marketing SMS']);
    }
}
```

### 5. Webhook Controller

```php
// app/Http/Controllers/Webhooks/TwilioSmsWebhookController.php
class TwilioSmsWebhookController extends Controller
{
    public function status(Request $request, SmsService $smsService): Response
    {
        $smsService->handleStatusWebhook($request->all());
        return response('OK', 200);
    }
}
```

### 6. Routes

```php
// routes/api.php
Route::prefix('sms')->middleware('auth:sanctum')->group(function () {
    Route::post('/send', [SmsController::class, 'send']);
    Route::post('/send-template', [SmsController::class, 'sendTemplate']);
});

Route::prefix('tenant-client/sms')->middleware(['tenant', 'auth:customer'])->group(function () {
    Route::post('/opt-in', [SmsController::class, 'optIn']);
    Route::post('/opt-out', [SmsController::class, 'optOut']);
});

// Webhook
Route::post('/webhooks/sms/status', [TwilioSmsWebhookController::class, 'status']);
```

### 7. Integration with Order Service

```php
// In OrderService or PaymentCaptured listener
public function sendOrderConfirmationSms(Order $order): void
{
    if (!$order->customer->phone) return;

    $smsService = app(SmsService::class);

    $smsService->sendTemplate(
        $order->customer->phone,
        'order_confirmation',
        [
            'order_number' => $order->order_number,
            'event_name' => $order->event->name,
            'event_date' => $order->event->start_date->format('M j, Y'),
            'ticket_url' => url("/orders/{$order->id}/tickets"),
        ],
        $order->tenant_id,
        $order->customer_id
    );
}
```

---

## Testing Checklist

1. [ ] SMS sends successfully via Twilio
2. [ ] Template variables are replaced correctly
3. [ ] Phone number formatting works
4. [ ] Status webhook updates message status
5. [ ] Marketing opt-in/opt-out works
6. [ ] Marketing SMS blocked without consent
7. [ ] Verification codes are sent
8. [ ] Error handling for failed sends
9. [ ] Multi-segment messages work
10. [ ] SMS templates can be managed

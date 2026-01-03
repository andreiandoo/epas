# WhatsApp Cloud API Completion Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
The WhatsApp integration currently uses Twilio as the primary adapter, with WhatsApp Cloud API partially implemented. This creates:
1. **Higher costs**: Twilio charges premium rates for WhatsApp messages
2. **Limited features**: Cloud API offers interactive messages, buttons, and lists not available via Twilio
3. **Direct integration**: Cloud API connects directly to Meta, reducing intermediary dependencies
4. **Incomplete webhooks**: Inbound message routing and signature verification are TODO items

### What This Feature Does
Completes the WhatsApp Cloud API integration to:
- Send all message types (text, media, templates, interactive)
- Receive and route inbound messages
- Verify webhook signatures for security
- Handle delivery receipts and status updates
- Support interactive messages (buttons, lists, products)
- Manage WhatsApp Business templates
- Enable two-way customer conversations

---

## Technical Implementation

### 1. Configuration

Update `.env.example`:

```
# WhatsApp Cloud API Configuration
WHATSAPP_PROVIDER=cloud_api
WHATSAPP_CLOUD_API_TOKEN=your_permanent_token
WHATSAPP_CLOUD_API_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_CLOUD_API_BUSINESS_ACCOUNT_ID=your_business_account_id
WHATSAPP_CLOUD_API_WEBHOOK_VERIFY_TOKEN=your_verify_token
WHATSAPP_CLOUD_API_APP_SECRET=your_app_secret
WHATSAPP_CLOUD_API_VERSION=v17.0
```

Create/Update `config/whatsapp.php`:

```php
<?php

return [
    'default_provider' => env('WHATSAPP_PROVIDER', 'twilio'),

    'providers' => [
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_WHATSAPP_NUMBER'),
        ],

        'cloud_api' => [
            'token' => env('WHATSAPP_CLOUD_API_TOKEN'),
            'phone_number_id' => env('WHATSAPP_CLOUD_API_PHONE_NUMBER_ID'),
            'business_account_id' => env('WHATSAPP_CLOUD_API_BUSINESS_ACCOUNT_ID'),
            'webhook_verify_token' => env('WHATSAPP_CLOUD_API_WEBHOOK_VERIFY_TOKEN'),
            'app_secret' => env('WHATSAPP_CLOUD_API_APP_SECRET'),
            'api_version' => env('WHATSAPP_CLOUD_API_VERSION', 'v17.0'),
            'api_base_url' => 'https://graph.facebook.com',
        ],
    ],

    'templates' => [
        'order_confirmation' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION', 'order_confirmation'),
        'event_reminder' => env('WHATSAPP_TEMPLATE_EVENT_REMINDER', 'event_reminder'),
        'ticket_ready' => env('WHATSAPP_TEMPLATE_TICKET_READY', 'ticket_ready'),
    ],

    'conversation' => [
        'timeout_hours' => 24, // WhatsApp 24-hour window
        'max_messages_per_conversation' => 100,
    ],
];
```

### 2. Database Migrations

Create `database/migrations/2026_01_03_000015_enhance_whatsapp_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhance whatsapp_messages table
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('provider')->default('twilio')->after('id');
            $table->string('message_type')->default('text')->after('provider');
            $table->json('interactive_data')->nullable()->after('message');
            $table->string('media_id')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->string('wamid')->nullable()->index(); // WhatsApp Message ID
            $table->json('webhook_data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });

        // Create conversations table
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->string('phone_number');
            $table->enum('status', ['open', 'closed', 'expired'])->default('open');
            $table->enum('type', ['user_initiated', 'business_initiated'])->default('user_initiated');
            $table->timestamp('window_expires_at');
            $table->timestamp('last_message_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'status']);
        });

        // Create templates table
        Schema::create('whatsapp_template_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('language')->default('en');
            $table->string('category'); // MARKETING, UTILITY, AUTHENTICATION
            $table->json('components');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_submissions');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn([
                'provider', 'message_type', 'interactive_data',
                'media_id', 'media_url', 'media_type', 'media_mime_type',
                'wamid', 'webhook_data', 'read_at', 'delivered_at'
            ]);
        });
    }
};
```

### 3. Models

Create `app/Models/WhatsAppConversation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'phone_number',
        'status',
        'type',
        'window_expires_at',
        'last_message_at',
        'metadata',
    ];

    protected $casts = [
        'window_expires_at' => 'datetime',
        'last_message_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'phone', 'phone_number')
            ->where('tenant_id', $this->tenant_id);
    }

    public function isWindowOpen(): bool
    {
        return $this->status === 'open' && $this->window_expires_at->isFuture();
    }

    public function extendWindow(): void
    {
        $this->window_expires_at = now()->addHours(24);
        $this->save();
    }
}
```

### 4. Cloud API Adapter

Update `app/Services/WhatsApp/Adapters/CloudApiAdapter.php`:

```php
<?php

namespace App\Services\WhatsApp\Adapters;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class CloudApiAdapter implements WhatsAppAdapterInterface
{
    protected string $apiVersion;
    protected string $baseUrl;
    protected string $token;
    protected string $phoneNumberId;
    protected string $appSecret;

    public function __construct()
    {
        $config = config('whatsapp.providers.cloud_api');

        $this->apiVersion = $config['api_version'];
        $this->baseUrl = $config['api_base_url'];
        $this->token = $config['token'];
        $this->phoneNumberId = $config['phone_number_id'];
        $this->appSecret = $config['app_secret'];
    }

    /**
     * Send a text message
     */
    public function sendText(string $to, string $message, ?int $tenantId = null): WhatsAppMessage
    {
        $response = $this->makeRequest('messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message,
            ],
        ]);

        return $this->createMessageRecord($to, $message, 'text', $response, $tenantId);
    }

    /**
     * Send a template message
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'en',
        array $components = [],
        ?int $tenantId = null
    ): WhatsAppMessage {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language,
                ],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $this->formatTemplateComponents($components);
        }

        $response = $this->makeRequest('messages', $payload);

        return $this->createMessageRecord(
            $to,
            "Template: {$templateName}",
            'template',
            $response,
            $tenantId,
            ['template_name' => $templateName, 'components' => $components]
        );
    }

    /**
     * Send an image message
     */
    public function sendImage(
        string $to,
        string $imageUrl,
        ?string $caption = null,
        ?int $tenantId = null
    ): WhatsAppMessage {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
            ],
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        $response = $this->makeRequest('messages', $payload);

        return $this->createMessageRecord(
            $to,
            $caption ?? 'Image',
            'image',
            $response,
            $tenantId,
            ['media_url' => $imageUrl]
        );
    }

    /**
     * Send a document message
     */
    public function sendDocument(
        string $to,
        string $documentUrl,
        string $filename,
        ?string $caption = null,
        ?int $tenantId = null
    ): WhatsAppMessage {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
            ],
        ];

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        $response = $this->makeRequest('messages', $payload);

        return $this->createMessageRecord(
            $to,
            $caption ?? $filename,
            'document',
            $response,
            $tenantId,
            ['media_url' => $documentUrl, 'filename' => $filename]
        );
    }

    /**
     * Send interactive buttons message
     */
    public function sendButtons(
        string $to,
        string $body,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?int $tenantId = null
    ): WhatsAppMessage {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => [
                'buttons' => array_map(function ($button, $index) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'] ?? "btn_{$index}",
                            'title' => substr($button['title'], 0, 20), // Max 20 chars
                        ],
                    ];
                }, $buttons, array_keys($buttons)),
            ],
        ];

        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        $response = $this->makeRequest('messages', $payload);

        return $this->createMessageRecord(
            $to,
            $body,
            'interactive_buttons',
            $response,
            $tenantId,
            ['interactive' => $interactive]
        );
    }

    /**
     * Send interactive list message
     */
    public function sendList(
        string $to,
        string $body,
        string $buttonText,
        array $sections,
        ?string $header = null,
        ?string $footer = null,
        ?int $tenantId = null
    ): WhatsAppMessage {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $body],
            'action' => [
                'button' => substr($buttonText, 0, 20),
                'sections' => array_map(function ($section) {
                    return [
                        'title' => $section['title'],
                        'rows' => array_map(function ($row) {
                            return [
                                'id' => $row['id'],
                                'title' => substr($row['title'], 0, 24),
                                'description' => isset($row['description'])
                                    ? substr($row['description'], 0, 72)
                                    : null,
                            ];
                        }, $section['rows']),
                    ];
                }, $sections),
            ],
        ];

        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        $response = $this->makeRequest('messages', $payload);

        return $this->createMessageRecord(
            $to,
            $body,
            'interactive_list',
            $response,
            $tenantId,
            ['interactive' => $interactive]
        );
    }

    /**
     * Download media from WhatsApp
     */
    public function downloadMedia(string $mediaId): array
    {
        // First get the media URL
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/{$this->apiVersion}/{$mediaId}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get media URL: ' . $response->body());
        }

        $mediaUrl = $response->json('url');

        // Download the actual file
        $fileResponse = Http::withToken($this->token)->get($mediaUrl);

        if (!$fileResponse->successful()) {
            throw new \Exception('Failed to download media');
        }

        return [
            'content' => $fileResponse->body(),
            'mime_type' => $response->json('mime_type'),
            'sha256' => $response->json('sha256'),
            'file_size' => $response->json('file_size'),
        ];
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): bool
    {
        $response = $this->makeRequest('messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);

        return $response['success'] ?? false;
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            Log::warning('WhatsApp webhook: Missing signature header');
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp webhook: Invalid signature');
            return false;
        }

        return true;
    }

    /**
     * Handle webhook verification challenge
     */
    public function handleVerificationChallenge(Request $request): ?string
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('whatsapp.providers.cloud_api.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }

    /**
     * Process incoming webhook
     */
    public function processWebhook(array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if ($change['field'] !== 'messages') {
                    continue;
                }

                $value = $change['value'];

                // Process messages
                $messages = $value['messages'] ?? [];
                foreach ($messages as $message) {
                    $this->processInboundMessage($message, $value);
                }

                // Process status updates
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $status) {
                    $this->processStatusUpdate($status);
                }
            }
        }
    }

    /**
     * Process an inbound message
     */
    protected function processInboundMessage(array $message, array $context): void
    {
        $from = $message['from'];
        $type = $message['type'];
        $timestamp = $message['timestamp'];
        $wamid = $message['id'];

        // Find or create conversation
        $conversation = $this->findOrCreateConversation($from);

        // Extract message content based on type
        $content = $this->extractMessageContent($message);

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'customer_id' => $conversation->customer_id,
            'phone' => $from,
            'message' => $content['text'] ?? '',
            'direction' => 'inbound',
            'status' => 'received',
            'provider' => 'cloud_api',
            'message_type' => $type,
            'wamid' => $wamid,
            'media_id' => $content['media_id'] ?? null,
            'media_type' => $content['media_type'] ?? null,
            'interactive_data' => $content['interactive'] ?? null,
            'webhook_data' => $message,
        ]);

        // Extend conversation window
        $conversation->last_message_at = now();
        $conversation->window_expires_at = now()->addHours(24);
        $conversation->save();

        // Dispatch event for handling
        event(new \App\Events\WhatsAppMessageReceived($whatsappMessage, $conversation));
    }

    /**
     * Extract content from message based on type
     */
    protected function extractMessageContent(array $message): array
    {
        $type = $message['type'];

        return match ($type) {
            'text' => ['text' => $message['text']['body']],
            'image' => [
                'text' => $message['image']['caption'] ?? 'Image received',
                'media_id' => $message['image']['id'],
                'media_type' => 'image',
            ],
            'document' => [
                'text' => $message['document']['filename'] ?? 'Document received',
                'media_id' => $message['document']['id'],
                'media_type' => 'document',
            ],
            'audio' => [
                'text' => 'Audio message received',
                'media_id' => $message['audio']['id'],
                'media_type' => 'audio',
            ],
            'video' => [
                'text' => $message['video']['caption'] ?? 'Video received',
                'media_id' => $message['video']['id'],
                'media_type' => 'video',
            ],
            'interactive' => [
                'text' => $this->extractInteractiveResponse($message['interactive']),
                'interactive' => $message['interactive'],
            ],
            'button' => [
                'text' => $message['button']['text'],
                'interactive' => ['button_payload' => $message['button']['payload']],
            ],
            default => ['text' => 'Unknown message type'],
        };
    }

    /**
     * Extract response from interactive message
     */
    protected function extractInteractiveResponse(array $interactive): string
    {
        $type = $interactive['type'];

        if ($type === 'button_reply') {
            return $interactive['button_reply']['title'];
        }

        if ($type === 'list_reply') {
            return $interactive['list_reply']['title'];
        }

        return 'Interactive response';
    }

    /**
     * Process status update
     */
    protected function processStatusUpdate(array $status): void
    {
        $wamid = $status['id'];
        $statusValue = $status['status']; // sent, delivered, read, failed

        $message = WhatsAppMessage::where('wamid', $wamid)->first();

        if (!$message) {
            return;
        }

        $message->status = $statusValue;

        if ($statusValue === 'delivered') {
            $message->delivered_at = now();
        } elseif ($statusValue === 'read') {
            $message->read_at = now();
        } elseif ($statusValue === 'failed') {
            $message->error_message = $status['errors'][0]['message'] ?? 'Unknown error';
        }

        $message->save();
    }

    /**
     * Find or create conversation
     */
    protected function findOrCreateConversation(string $phoneNumber): WhatsAppConversation
    {
        // Try to find existing open conversation
        $conversation = WhatsAppConversation::where('phone_number', $phoneNumber)
            ->where('status', 'open')
            ->where('window_expires_at', '>', now())
            ->first();

        if ($conversation) {
            return $conversation;
        }

        // Find customer by phone
        $customer = Customer::where('phone', $phoneNumber)
            ->orWhere('phone', 'LIKE', "%{$phoneNumber}")
            ->first();

        // Create new conversation
        return WhatsAppConversation::create([
            'tenant_id' => $customer?->tenant_id ?? 1, // Default tenant
            'customer_id' => $customer?->id,
            'phone_number' => $phoneNumber,
            'status' => 'open',
            'type' => 'user_initiated',
            'window_expires_at' => now()->addHours(24),
            'last_message_at' => now(),
        ]);
    }

    /**
     * Make API request
     */
    protected function makeRequest(string $endpoint, array $data): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/{$endpoint}";

        $response = Http::withToken($this->token)
            ->post($url, $data);

        if (!$response->successful()) {
            Log::error('WhatsApp Cloud API error', [
                'url' => $url,
                'response' => $response->json(),
            ]);
            throw new \Exception('WhatsApp API error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Format phone number to WhatsApp format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure it has country code
        if (!str_starts_with($phone, '1') && strlen($phone) === 10) {
            $phone = '1' . $phone; // Assume US
        }

        return $phone;
    }

    /**
     * Format template components
     */
    protected function formatTemplateComponents(array $components): array
    {
        return array_map(function ($component) {
            return [
                'type' => $component['type'],
                'parameters' => array_map(function ($param) {
                    return [
                        'type' => $param['type'] ?? 'text',
                        'text' => $param['value'] ?? $param['text'] ?? '',
                    ];
                }, $component['parameters'] ?? []),
            ];
        }, $components);
    }

    /**
     * Create message record
     */
    protected function createMessageRecord(
        string $to,
        string $message,
        string $type,
        array $response,
        ?int $tenantId,
        array $extra = []
    ): WhatsAppMessage {
        $wamid = $response['messages'][0]['id'] ?? null;

        return WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'phone' => $to,
            'message' => $message,
            'direction' => 'outbound',
            'status' => 'sent',
            'provider' => 'cloud_api',
            'message_type' => $type,
            'wamid' => $wamid,
            'media_url' => $extra['media_url'] ?? null,
            'interactive_data' => $extra['interactive'] ?? null,
        ]);
    }
}
```

### 5. Webhook Controller

Update `app/Http/Controllers/Webhooks/WhatsAppCloudWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\Adapters\CloudApiAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppCloudWebhookController extends Controller
{
    public function __construct(
        protected CloudApiAdapter $adapter
    ) {}

    /**
     * Handle webhook verification (GET request from Meta)
     */
    public function verify(Request $request): Response
    {
        $challenge = $this->adapter->handleVerificationChallenge($request);

        if ($challenge) {
            return response($challenge, 200);
        }

        return response('Verification failed', 403);
    }

    /**
     * Handle incoming webhook (POST request)
     */
    public function handle(Request $request): Response
    {
        // Verify signature
        if (!$this->adapter->verifyWebhookSignature($request)) {
            return response('Invalid signature', 403);
        }

        try {
            $payload = $request->all();

            // Process webhook asynchronously
            dispatch(function () use ($payload) {
                $this->adapter->processWebhook($payload);
            })->afterResponse();

            return response('OK', 200);

        } catch (\Exception $e) {
            \Log::error('WhatsApp webhook processing error', [
                'error' => $e->getMessage(),
            ]);

            // Return 200 to acknowledge receipt (Meta will retry on non-200)
            return response('OK', 200);
        }
    }
}
```

### 6. Event for Message Handling

Create `app/Events/WhatsAppMessageReceived.php`:

```php
<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppMessage $message,
        public WhatsAppConversation $conversation
    ) {}
}
```

### 7. Routes

Add to `routes/api.php`:

```php
// WhatsApp Cloud API Webhooks
Route::prefix('webhooks/whatsapp')->group(function () {
    Route::get('/', [WhatsAppCloudWebhookController::class, 'verify']);
    Route::post('/', [WhatsAppCloudWebhookController::class, 'handle']);
});
```

### 8. WhatsApp Service Integration

Update `app/Services/WhatsApp/WhatsAppService.php`:

```php
<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Adapters\TwilioAdapter;
use App\Services\WhatsApp\Adapters\CloudApiAdapter;
use App\Services\WhatsApp\Adapters\WhatsAppAdapterInterface;

class WhatsAppService
{
    protected WhatsAppAdapterInterface $adapter;

    public function __construct()
    {
        $provider = config('whatsapp.default_provider', 'twilio');

        $this->adapter = match ($provider) {
            'cloud_api' => app(CloudApiAdapter::class),
            default => app(TwilioAdapter::class),
        };
    }

    public function sendText(string $to, string $message, ?int $tenantId = null)
    {
        return $this->adapter->sendText($to, $message, $tenantId);
    }

    public function sendTemplate(string $to, string $template, array $params = [], ?int $tenantId = null)
    {
        return $this->adapter->sendTemplate($to, $template, 'en', $params, $tenantId);
    }

    public function sendImage(string $to, string $imageUrl, ?string $caption = null, ?int $tenantId = null)
    {
        return $this->adapter->sendImage($to, $imageUrl, $caption, $tenantId);
    }

    public function sendDocument(string $to, string $docUrl, string $filename, ?int $tenantId = null)
    {
        return $this->adapter->sendDocument($to, $docUrl, $filename, null, $tenantId);
    }

    public function sendButtons(string $to, string $body, array $buttons, ?int $tenantId = null)
    {
        return $this->adapter->sendButtons($to, $body, $buttons, null, null, $tenantId);
    }

    public function sendList(string $to, string $body, string $buttonText, array $sections, ?int $tenantId = null)
    {
        return $this->adapter->sendList($to, $body, $buttonText, $sections, null, null, $tenantId);
    }
}
```

---

## Testing Checklist

1. [ ] Webhook verification succeeds with correct token
2. [ ] Webhook signature validation works
3. [ ] Text messages send successfully
4. [ ] Template messages send correctly
5. [ ] Image messages send with captions
6. [ ] Document messages send with filenames
7. [ ] Interactive button messages work
8. [ ] Interactive list messages work
9. [ ] Inbound text messages are processed
10. [ ] Inbound media messages are processed
11. [ ] Interactive responses are captured
12. [ ] Status updates (delivered, read) are tracked
13. [ ] Conversations are created/managed correctly
14. [ ] 24-hour window is respected
15. [ ] Media download works

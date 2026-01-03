# AI Chatbot Support Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Customer support is manual and limited:
1. **Response time**: Customers wait hours for email responses
2. **Availability**: No 24/7 support coverage
3. **Repetitive queries**: Same questions asked repeatedly (ticket delivery, refunds)
4. **Scalability**: Support costs increase linearly with customer growth
5. **Language barriers**: Limited multilingual support

### What This Feature Does
- AI-powered chatbot for instant customer support
- Natural language understanding for event queries
- Ticket lookup and order status checking
- FAQ handling with trained responses
- Seamless handoff to human agents
- Conversation history and analytics

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000100_create_chatbot_tables.php
Schema::create('chat_conversations', function (Blueprint $table) {
    $table->id();
    $table->uuid('conversation_id')->unique();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->string('visitor_id')->nullable(); // For non-authenticated users
    $table->enum('status', ['active', 'waiting_human', 'with_human', 'resolved', 'abandoned'])->default('active');
    $table->string('channel')->default('web'); // web, whatsapp, api
    $table->foreignId('assigned_agent_id')->nullable()->constrained('users');
    $table->timestamp('started_at');
    $table->timestamp('resolved_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['tenant_id', 'status']);
    $table->index(['customer_id']);
});

Schema::create('chat_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
    $table->enum('sender_type', ['customer', 'bot', 'agent']);
    $table->foreignId('sender_id')->nullable(); // User ID for agents
    $table->text('content');
    $table->json('attachments')->nullable();
    $table->json('quick_replies')->nullable();
    $table->json('metadata')->nullable(); // Intent, confidence, etc.
    $table->boolean('is_read')->default(false);
    $table->timestamps();

    $table->index(['conversation_id', 'created_at']);
});

Schema::create('chat_intents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug')->index();
    $table->json('training_phrases');
    $table->json('responses');
    $table->json('actions')->nullable(); // API actions to take
    $table->boolean('requires_auth')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('priority')->default(0);
    $table->timestamps();

    $table->unique(['tenant_id', 'slug']);
});

Schema::create('chat_faqs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('category_id')->nullable();
    $table->string('question');
    $table->text('answer');
    $table->json('keywords')->nullable();
    $table->integer('helpful_count')->default(0);
    $table->integer('not_helpful_count')->default(0);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});

Schema::create('chat_faq_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug');
    $table->integer('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['tenant_id', 'slug']);
});
```

### 2. Models

```php
// app/Models/ChatConversation.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    protected $fillable = [
        'conversation_id', 'tenant_id', 'customer_id', 'visitor_id',
        'status', 'channel', 'assigned_agent_id', 'started_at',
        'resolved_at', 'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($conversation) {
            $conversation->conversation_id = $conversation->conversation_id ?? Str::uuid();
            $conversation->started_at = $conversation->started_at ?? now();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'waiting_human', 'with_human']);
    }

    public function needsHumanAgent(): bool
    {
        return $this->status === 'waiting_human';
    }
}

// app/Models/ChatMessage.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_id', 'content',
        'attachments', 'quick_replies', 'metadata', 'is_read',
    ];

    protected $casts = [
        'attachments' => 'array',
        'quick_replies' => 'array',
        'metadata' => 'array',
        'is_read' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}

// app/Models/ChatIntent.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatIntent extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'slug', 'training_phrases',
        'responses', 'actions', 'requires_auth', 'is_active', 'priority',
    ];

    protected $casts = [
        'training_phrases' => 'array',
        'responses' => 'array',
        'actions' => 'array',
        'requires_auth' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getRandomResponse(): string
    {
        $responses = $this->responses ?? [];
        return $responses[array_rand($responses)] ?? 'I can help you with that.';
    }
}
```

### 3. Chatbot Service

```php
// app/Services/Chat/ChatbotService.php
<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatIntent;
use App\Models\ChatFaq;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class ChatbotService
{
    protected array $builtInIntents = [
        'order_status' => ['check order', 'order status', 'where is my order', 'my tickets'],
        'refund' => ['refund', 'cancel order', 'get my money back', 'cancellation'],
        'event_info' => ['event details', 'when is the event', 'event location', 'start time'],
        'ticket_help' => ['download ticket', 'resend ticket', 'qr code', 'ticket not received'],
        'greeting' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon'],
        'goodbye' => ['bye', 'goodbye', 'thanks', 'thank you'],
        'human_agent' => ['speak to human', 'talk to agent', 'real person', 'customer service'],
    ];

    /**
     * Process incoming message
     */
    public function processMessage(
        ChatConversation $conversation,
        string $message,
        ?Customer $customer = null
    ): ChatMessage {
        // Save customer message
        $customerMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'content' => $message,
        ]);

        // If conversation is with human agent, don't auto-respond
        if ($conversation->status === 'with_human') {
            return $customerMessage;
        }

        // Detect intent
        $intent = $this->detectIntent($message, $conversation->tenant_id);

        // Generate response
        $response = $this->generateResponse($intent, $message, $customer, $conversation);

        // Save bot response
        $botMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'content' => $response['message'],
            'quick_replies' => $response['quick_replies'] ?? null,
            'metadata' => [
                'intent' => $intent['name'] ?? 'unknown',
                'confidence' => $intent['confidence'] ?? 0,
            ],
        ]);

        // Check if handoff needed
        if ($intent['name'] === 'human_agent' || ($intent['confidence'] ?? 0) < 0.3) {
            $this->requestHumanAgent($conversation);
        }

        return $botMessage;
    }

    /**
     * Detect intent from message
     */
    protected function detectIntent(string $message, int $tenantId): array
    {
        $message = strtolower(trim($message));

        // Check built-in intents first
        foreach ($this->builtInIntents as $intent => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($message, $phrase)) {
                    return ['name' => $intent, 'confidence' => 0.9];
                }
            }
        }

        // Check custom intents
        $customIntents = ChatIntent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($customIntents as $intent) {
            foreach ($intent->training_phrases as $phrase) {
                $similarity = similar_text($message, strtolower($phrase), $percent);
                if ($percent > 60) {
                    return ['name' => $intent->slug, 'confidence' => $percent / 100, 'intent' => $intent];
                }
            }
        }

        // Check FAQs
        $faq = $this->findMatchingFaq($message, $tenantId);
        if ($faq) {
            return ['name' => 'faq', 'confidence' => 0.8, 'faq' => $faq];
        }

        return ['name' => 'unknown', 'confidence' => 0];
    }

    /**
     * Generate response based on intent
     */
    protected function generateResponse(
        array $intent,
        string $message,
        ?Customer $customer,
        ChatConversation $conversation
    ): array {
        $intentName = $intent['name'];

        return match ($intentName) {
            'greeting' => $this->greetingResponse($customer),
            'goodbye' => $this->goodbyeResponse(),
            'order_status' => $this->orderStatusResponse($customer, $message),
            'refund' => $this->refundInfoResponse($customer),
            'event_info' => $this->eventInfoResponse($message, $conversation->tenant_id),
            'ticket_help' => $this->ticketHelpResponse($customer),
            'human_agent' => $this->humanAgentResponse(),
            'faq' => ['message' => $intent['faq']->answer, 'quick_replies' => $this->getFaqQuickReplies()],
            default => $this->fallbackResponse(),
        };
    }

    protected function greetingResponse(?Customer $customer): array
    {
        $name = $customer?->first_name ?? 'there';
        return [
            'message' => "Hi {$name}! 👋 How can I help you today?",
            'quick_replies' => [
                ['title' => 'Check my orders', 'payload' => 'order_status'],
                ['title' => 'Find events', 'payload' => 'find_events'],
                ['title' => 'Ticket help', 'payload' => 'ticket_help'],
                ['title' => 'Talk to agent', 'payload' => 'human_agent'],
            ],
        ];
    }

    protected function goodbyeResponse(): array
    {
        return [
            'message' => "Thanks for chatting! Have a great day! 👋 If you need anything else, I'm here to help.",
        ];
    }

    protected function orderStatusResponse(?Customer $customer, string $message): array
    {
        if (!$customer) {
            return [
                'message' => "To check your order status, please log in to your account first. Would you like me to help you with something else?",
                'quick_replies' => [
                    ['title' => 'Login', 'payload' => 'login'],
                    ['title' => 'Talk to agent', 'payload' => 'human_agent'],
                ],
            ];
        }

        // Check for order number in message
        preg_match('/[A-Z0-9]{6,12}/', strtoupper($message), $matches);
        $orderNumber = $matches[0] ?? null;

        if ($orderNumber) {
            $order = Order::where('customer_id', $customer->id)
                ->where('order_number', 'like', "%{$orderNumber}%")
                ->first();

            if ($order) {
                return [
                    'message' => "Found your order {$order->order_number}!\n\n" .
                        "📋 Status: {$order->status}\n" .
                        "📅 Date: {$order->created_at->format('M j, Y')}\n" .
                        "💰 Total: {$order->formatted_total}\n\n" .
                        "Would you like more details?",
                    'quick_replies' => [
                        ['title' => 'View tickets', 'payload' => "view_tickets_{$order->id}"],
                        ['title' => 'Download tickets', 'payload' => "download_{$order->id}"],
                    ],
                ];
            }
        }

        // Show recent orders
        $recentOrders = Order::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        if ($recentOrders->isEmpty()) {
            return ['message' => "I don't see any orders in your account. Would you like to browse events?"];
        }

        $orderList = $recentOrders->map(fn($o) => "• {$o->order_number} - {$o->status}")->join("\n");

        return [
            'message' => "Here are your recent orders:\n\n{$orderList}\n\nWhich order would you like to know more about?",
        ];
    }

    protected function refundInfoResponse(?Customer $customer): array
    {
        return [
            'message' => "I understand you'd like information about refunds. Here's what you should know:\n\n" .
                "📌 Refund eligibility depends on the event's cancellation policy\n" .
                "📌 Processing typically takes 5-10 business days\n" .
                "📌 Refunds are issued to the original payment method\n\n" .
                "Would you like me to connect you with our support team to process a refund request?",
            'quick_replies' => [
                ['title' => 'Yes, connect me', 'payload' => 'human_agent'],
                ['title' => 'No thanks', 'payload' => 'goodbye'],
            ],
        ];
    }

    protected function eventInfoResponse(string $message, int $tenantId): array
    {
        // Try to find event mentioned
        $events = Event::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('start_date', '>', now())
            ->where(function ($q) use ($message) {
                $words = explode(' ', $message);
                foreach ($words as $word) {
                    if (strlen($word) > 3) {
                        $q->orWhere('name', 'like', "%{$word}%");
                    }
                }
            })
            ->limit(3)
            ->get();

        if ($events->isEmpty()) {
            return [
                'message' => "I'd be happy to help you find event information! Could you tell me which event you're interested in?",
            ];
        }

        if ($events->count() === 1) {
            $event = $events->first();
            return [
                'message' => "📍 **{$event->name}**\n\n" .
                    "📅 Date: {$event->start_date->format('l, M j, Y')}\n" .
                    "⏰ Time: {$event->start_date->format('g:i A')}\n" .
                    "📍 Location: {$event->venue?->name}\n\n" .
                    "Would you like to get tickets?",
                'quick_replies' => [
                    ['title' => 'Get tickets', 'payload' => "tickets_{$event->id}"],
                    ['title' => 'More info', 'payload' => "info_{$event->id}"],
                ],
            ];
        }

        $eventList = $events->map(fn($e) => "• {$e->name} - {$e->start_date->format('M j')}")->join("\n");
        return [
            'message' => "I found these events:\n\n{$eventList}\n\nWhich one interests you?",
        ];
    }

    protected function ticketHelpResponse(?Customer $customer): array
    {
        if (!$customer) {
            return [
                'message' => "To help with your tickets, please log in first. Your tickets are also sent to your email after purchase.",
            ];
        }

        return [
            'message' => "I can help with your tickets! What do you need?\n\n" .
                "• Download your tickets from your account\n" .
                "• Tickets are also emailed after purchase\n" .
                "• Each ticket has a unique QR code for entry",
            'quick_replies' => [
                ['title' => 'Resend ticket email', 'payload' => 'resend_tickets'],
                ['title' => 'My tickets', 'payload' => 'view_tickets'],
                ['title' => 'Talk to agent', 'payload' => 'human_agent'],
            ],
        ];
    }

    protected function humanAgentResponse(): array
    {
        return [
            'message' => "I'll connect you with a support agent right away. Please hold on while I find someone to help you. 🙂",
        ];
    }

    protected function fallbackResponse(): array
    {
        return [
            'message' => "I'm not quite sure I understand. Could you rephrase that? Or I can connect you with a support agent.",
            'quick_replies' => [
                ['title' => 'Talk to agent', 'payload' => 'human_agent'],
                ['title' => 'Browse FAQs', 'payload' => 'faq'],
            ],
        ];
    }

    protected function findMatchingFaq(string $message, int $tenantId): ?ChatFaq
    {
        $faqs = ChatFaq::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $bestMatch = null;
        $bestScore = 0;

        foreach ($faqs as $faq) {
            // Check question similarity
            similar_text(strtolower($message), strtolower($faq->question), $percent);
            if ($percent > $bestScore && $percent > 50) {
                $bestScore = $percent;
                $bestMatch = $faq;
            }

            // Check keywords
            foreach ($faq->keywords ?? [] as $keyword) {
                if (str_contains(strtolower($message), strtolower($keyword))) {
                    return $faq;
                }
            }
        }

        return $bestMatch;
    }

    protected function getFaqQuickReplies(): array
    {
        return [
            ['title' => 'Was this helpful?', 'payload' => 'faq_helpful'],
            ['title' => 'Talk to agent', 'payload' => 'human_agent'],
        ];
    }

    /**
     * Request human agent
     */
    public function requestHumanAgent(ChatConversation $conversation): void
    {
        $conversation->update(['status' => 'waiting_human']);

        // Notify available agents
        // Notification::send($availableAgents, new NewChatWaiting($conversation));
    }

    /**
     * Assign agent to conversation
     */
    public function assignAgent(ChatConversation $conversation, User $agent): void
    {
        $conversation->update([
            'status' => 'with_human',
            'assigned_agent_id' => $agent->id,
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'content' => "You're now connected with {$agent->name}. They'll assist you from here.",
        ]);
    }

    /**
     * Resolve conversation
     */
    public function resolveConversation(ChatConversation $conversation): void
    {
        $conversation->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
```

### 4. Controller

```php
// app/Http/Controllers/Api/TenantClient/ChatController.php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatbotService;
use App\Models\ChatConversation;
use App\Models\ChatFaq;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(protected ChatbotService $chatbotService) {}

    /**
     * Start new conversation
     */
    public function startConversation(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $tenantId = $request->attributes->get('tenant_id');

        // Check for existing active conversation
        $existing = ChatConversation::where('tenant_id', $tenantId)
            ->where(function ($q) use ($customer, $request) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                } else {
                    $q->where('visitor_id', $request->input('visitor_id'));
                }
            })
            ->whereIn('status', ['active', 'waiting_human', 'with_human'])
            ->first();

        if ($existing) {
            return response()->json([
                'conversation' => $existing,
                'messages' => $existing->messages()->orderBy('created_at')->get(),
            ]);
        }

        $conversation = ChatConversation::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer?->id,
            'visitor_id' => $customer ? null : $request->input('visitor_id'),
            'channel' => $request->input('channel', 'web'),
        ]);

        // Send welcome message
        $this->chatbotService->processMessage($conversation, 'hello', $customer);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages()->orderBy('created_at')->get(),
        ], 201);
    }

    /**
     * Send message
     */
    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $customer = $request->user('customer');

        $message = $this->chatbotService->processMessage(
            $conversation,
            $request->message,
            $customer
        );

        // Get bot response if any
        $response = $conversation->messages()
            ->where('sender_type', 'bot')
            ->where('created_at', '>=', $message->created_at)
            ->first();

        return response()->json([
            'message' => $message,
            'response' => $response,
            'conversation' => $conversation->fresh(),
        ]);
    }

    /**
     * Get conversation history
     */
    public function getMessages(ChatConversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    /**
     * Get FAQs
     */
    public function getFaqs(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $faqs = ChatFaq::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category.name');

        return response()->json(['faqs' => $faqs]);
    }

    /**
     * Mark FAQ as helpful
     */
    public function markFaqHelpful(Request $request, ChatFaq $faq): JsonResponse
    {
        $request->validate(['helpful' => 'required|boolean']);

        if ($request->helpful) {
            $faq->increment('helpful_count');
        } else {
            $faq->increment('not_helpful_count');
        }

        return response()->json(['recorded' => true]);
    }

    /**
     * End conversation
     */
    public function endConversation(ChatConversation $conversation): JsonResponse
    {
        $this->chatbotService->resolveConversation($conversation);

        return response()->json(['message' => 'Conversation ended']);
    }
}
```

### 5. Agent Controller

```php
// app/Http/Controllers/Api/ChatAgentController.php
class ChatAgentController extends Controller
{
    public function __construct(protected ChatbotService $chatbotService) {}

    public function getWaitingConversations(Request $request): JsonResponse
    {
        $conversations = ChatConversation::where('tenant_id', $request->user()->tenant_id)
            ->where('status', 'waiting_human')
            ->with(['customer', 'messages' => fn($q) => $q->latest()->limit(5)])
            ->orderBy('updated_at')
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    public function assignToMe(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->chatbotService->assignAgent($conversation, $request->user());

        return response()->json(['conversation' => $conversation->fresh()]);
    }

    public function sendAgentMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $request->user()->id,
            'content' => $request->message,
        ]);

        // Broadcast to customer
        // broadcast(new AgentMessageSent($conversation, $message));

        return response()->json(['message' => $message]);
    }

    public function resolve(ChatConversation $conversation): JsonResponse
    {
        $this->chatbotService->resolveConversation($conversation);

        return response()->json(['message' => 'Conversation resolved']);
    }
}
```

### 6. Routes

```php
// routes/api.php
Route::prefix('tenant-client/chat')->middleware(['tenant'])->group(function () {
    Route::post('/start', [ChatController::class, 'startConversation']);
    Route::post('/{conversation}/message', [ChatController::class, 'sendMessage']);
    Route::get('/{conversation}/messages', [ChatController::class, 'getMessages']);
    Route::post('/{conversation}/end', [ChatController::class, 'endConversation']);
    Route::get('/faqs', [ChatController::class, 'getFaqs']);
    Route::post('/faqs/{faq}/helpful', [ChatController::class, 'markFaqHelpful']);
});

// Agent routes
Route::prefix('chat/agent')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/waiting', [ChatAgentController::class, 'getWaitingConversations']);
    Route::post('/{conversation}/assign', [ChatAgentController::class, 'assignToMe']);
    Route::post('/{conversation}/message', [ChatAgentController::class, 'sendAgentMessage']);
    Route::post('/{conversation}/resolve', [ChatAgentController::class, 'resolve']);
});
```

---

## Testing Checklist

1. [ ] Conversation starts with welcome message
2. [ ] Intent detection works for common queries
3. [ ] Order status lookup works for authenticated users
4. [ ] FAQ matching returns relevant answers
5. [ ] Fallback response triggers for unknown queries
6. [ ] Human agent handoff works
7. [ ] Agent can see waiting conversations
8. [ ] Agent assignment notifies customer
9. [ ] Agent messages are delivered
10. [ ] Conversation resolution works
11. [ ] Quick replies trigger correct intents
12. [ ] Non-authenticated users get appropriate responses

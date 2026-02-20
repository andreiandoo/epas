<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\MarketplaceCustomer;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ChatController extends BaseController
{
    public function __construct(
        protected ChatService $chatService,
    ) {}

    /**
     * Send a message to the AI chat
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        if (!config('openai.chat.enabled')) {
            return $this->error('Chat is not available at the moment', 503);
        }

        // Validate input
        $maxLength = config('openai.chat.max_message_length', 1000);
        $message = $request->input('message', '');

        if (empty(trim($message))) {
            return $this->error('Mesajul nu poate fi gol', 422);
        }

        if (mb_strlen($message) > $maxLength) {
            return $this->error("Mesajul nu poate depăși {$maxLength} caractere", 422);
        }

        // Get customer if authenticated
        $customer = $this->getAuthenticatedCustomer($request);

        // Rate limiting
        $rateLimitKey = $customer
            ? "chat:customer:{$customer->id}"
            : "chat:ip:{$request->ip()}";
        $rateLimit = $customer
            ? config('openai.chat.rate_limit_auth', 20)
            : config('openai.chat.rate_limit_guest', 5);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $rateLimit)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return $this->error("Prea multe mesaje. Încearcă din nou în {$seconds} secunde.", 429);
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Get or create conversation
        $sessionId = $request->input('session_id', Str::uuid()->toString());
        $pageUrl = $request->input('page_url');

        $conversation = $this->chatService->getOrCreateConversation(
            $client,
            $sessionId,
            $customer,
            $pageUrl
        );

        // Check if conversation is still open
        if ($conversation->status !== 'open') {
            return $this->error('Această conversație a fost închisă. Te rugăm să începi una nouă.', 422);
        }

        // Process message
        $result = $this->chatService->sendMessage($client, $conversation, $message, $customer);

        if (!$result['success']) {
            return $this->error($result['error'] ?? 'Eroare la procesarea mesajului', 500);
        }

        return $this->success([
            'message' => $result['message'],
            'conversation_id' => $result['conversation_id'],
            'session_id' => $conversation->session_id,
        ]);
    }

    /**
     * Get conversation history
     */
    public function getConversation(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->getAuthenticatedCustomer($request);
        $sessionId = $request->input('session_id', '');

        $query = ChatConversation::where('marketplace_client_id', $client->id)
            ->where('status', 'open');

        if ($customer) {
            $query->where('marketplace_customer_id', $customer->id);
        } else {
            if (empty($sessionId)) {
                return $this->success(['conversation' => null, 'messages' => []]);
            }
            $query->where('session_id', $sessionId)
                ->whereNull('marketplace_customer_id');
        }

        $conversation = $query->latest()->first();

        if (!$conversation) {
            return $this->success(['conversation' => null, 'messages' => []]);
        }

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'rating' => $m->rating,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return $this->success([
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'session_id' => $conversation->session_id,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Start a new conversation (close current one)
     */
    public function newConversation(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $this->getAuthenticatedCustomer($request);
        $sessionId = $request->input('session_id', '');

        $query = ChatConversation::where('marketplace_client_id', $client->id)
            ->where('status', 'open');

        if ($customer) {
            $query->where('marketplace_customer_id', $customer->id);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId)
                ->whereNull('marketplace_customer_id');
        }

        // Close all open conversations
        $query->update(['status' => 'resolved']);

        return $this->success(['message' => 'Conversație nouă creată']);
    }

    /**
     * Rate a message
     */
    public function rateMessage(Request $request, int $messageId): JsonResponse
    {
        $client = $this->requireClient($request);
        $rating = $request->input('rating'); // 1 or -1

        if (!in_array($rating, [1, -1])) {
            return $this->error('Rating invalid. Folosește 1 (pozitiv) sau -1 (negativ).', 422);
        }

        $message = ChatMessage::where('id', $messageId)
            ->where('role', 'assistant')
            ->whereHas('conversation', function ($q) use ($client) {
                $q->where('marketplace_client_id', $client->id);
            })
            ->first();

        if (!$message) {
            return $this->error('Mesajul nu a fost găsit', 404);
        }

        $message->update(['rating' => $rating]);

        return $this->success(['message' => 'Mulțumim pentru feedback!']);
    }

    /**
     * Get authenticated customer from Sanctum token (if present)
     */
    protected function getAuthenticatedCustomer(Request $request): ?MarketplaceCustomer
    {
        $user = $request->user('sanctum');

        if ($user instanceof MarketplaceCustomer) {
            return $user;
        }

        return null;
    }
}

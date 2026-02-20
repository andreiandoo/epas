<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(
        protected OpenAIClient $openAIClient,
        protected ChatContextBuilder $contextBuilder,
        protected ChatToolHandler $toolHandler,
    ) {}

    /**
     * Send a message and get AI response
     */
    public function sendMessage(
        MarketplaceClient $client,
        ChatConversation $conversation,
        string $userMessage,
        ?MarketplaceCustomer $customer = null,
    ): array {
        $config = config('openai.chat');

        // Check message limit
        $messageCount = $conversation->messages()->count();
        if ($messageCount >= $config['max_messages_per_conversation']) {
            return [
                'success' => false,
                'error' => 'Limita de mesaje per conversație a fost atinsă. Te rugăm să începi o conversație nouă.',
            ];
        }

        // Save user message
        $userMsg = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        // Build system prompt
        $systemPrompt = $this->contextBuilder->buildSystemPrompt($client);

        // Add KB context based on user message
        $kbContext = $this->contextBuilder->searchKnowledgeBase($client, $userMessage);
        if ($kbContext) {
            $systemPrompt .= "\n\n" . $kbContext;
        }

        // Add customer context if authenticated
        if ($customer) {
            $systemPrompt .= "\n\n" . $this->contextBuilder->buildCustomerContext($customer);
        }

        // Get conversation history
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'tool_calls' => $m->tool_calls,
                'tool_results' => $m->tool_results,
            ])
            ->toArray();

        $messages = $this->contextBuilder->formatMessages($history);

        // Get tool definitions
        $tools = $this->toolHandler->getToolDefinitions($customer !== null);

        // Call OpenAI
        $response = $this->openAIClient->chat($systemPrompt, $messages, $tools);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Eroare la procesarea mesajului',
            ];
        }

        // Handle tool calls if any
        $toolCallsCount = 0;
        $maxToolCalls = $config['max_tool_calls_per_turn'];

        while (!empty($response['tool_calls']) && $toolCallsCount < $maxToolCalls) {
            $toolCallsCount++;

            $toolResults = [];
            foreach ($response['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                $arguments = json_decode($toolCall['function']['arguments'], true) ?? [];

                $result = $this->toolHandler->execute(
                    $functionName,
                    $arguments,
                    $client,
                    $customer,
                    $conversation
                );

                $toolResults[] = [
                    'tool_call_id' => $toolCall['id'],
                    'output' => $result,
                ];
            }

            // Save assistant message with tool calls
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response['content'] ?? '',
                'tool_calls' => $response['tool_calls'],
                'tool_results' => $toolResults,
                'tokens_used' => $response['tokens_used'] ?? null,
                'created_at' => now(),
            ]);

            // Add tool call and results to messages for next API call
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];

            foreach ($toolResults as $result) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $result['tool_call_id'],
                    'content' => json_encode($result['output']),
                ];
            }

            // Call OpenAI again with tool results
            $response = $this->openAIClient->chat($systemPrompt, $messages, $tools);

            if (!$response['success']) {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Eroare la procesarea tool-ului',
                ];
            }
        }

        // Save final assistant message
        $assistantContent = $response['content'] ?? 'Ne pare rău, nu am putut procesa cererea. Te rugăm să încerci din nou.';

        $assistantMsg = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $assistantContent,
            'tokens_used' => $response['tokens_used'] ?? null,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => [
                'id' => $assistantMsg->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'created_at' => $assistantMsg->created_at->toIso8601String(),
            ],
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * Get or create a conversation
     */
    public function getOrCreateConversation(
        MarketplaceClient $client,
        string $sessionId,
        ?MarketplaceCustomer $customer = null,
        ?string $pageUrl = null,
    ): ChatConversation {
        // Try to find existing open conversation
        $query = ChatConversation::where('marketplace_client_id', $client->id)
            ->where('status', 'open');

        if ($customer) {
            $query->where('marketplace_customer_id', $customer->id);
        } else {
            $query->where('session_id', $sessionId)
                ->whereNull('marketplace_customer_id');
        }

        $conversation = $query->latest()->first();

        if ($conversation) {
            return $conversation;
        }

        // Create new conversation
        return ChatConversation::create([
            'marketplace_client_id' => $client->id,
            'marketplace_customer_id' => $customer?->id,
            'session_id' => $sessionId,
            'status' => 'open',
            'page_url' => $pageUrl,
            'metadata' => [
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ],
        ]);
    }
}

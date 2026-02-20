<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIClient
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key');
        $this->model = config('openai.model', 'gpt-4o-mini');
        $this->maxTokens = config('openai.max_tokens', 1024);
        $this->temperature = config('openai.temperature', 0.7);
    }

    /**
     * Send a chat completion request to OpenAI
     */
    public function chat(string $systemPrompt, array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'AI service temporarily unavailable',
                ];
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                return [
                    'success' => false,
                    'error' => 'Empty response from AI',
                ];
            }

            $message = $choice['message'];
            $usage = $data['usage'] ?? [];

            return [
                'success' => true,
                'content' => $message['content'] ?? '',
                'tool_calls' => $message['tool_calls'] ?? [],
                'finish_reason' => $choice['finish_reason'] ?? 'stop',
                'tokens_used' => ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'AI service temporarily unavailable',
            ];
        }
    }
}

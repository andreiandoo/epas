<?php

namespace App\Services\Integrations\Telegram;

use App\Models\Integrations\Telegram\TelegramBotConnection;
use App\Models\Integrations\Telegram\TelegramChat;
use App\Models\Integrations\Telegram\TelegramMessage;
use App\Models\Integrations\Telegram\TelegramSubscriber;
use App\Models\Integrations\Telegram\TelegramUpdate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class TelegramService
{
    protected string $baseUrl = 'https://api.telegram.org';

    // ==========================================
    // CONNECTION MANAGEMENT
    // ==========================================

    public function createConnection(int $tenantId, string $botToken): TelegramBotConnection
    {
        // Verify token by getting bot info
        $botInfo = $this->getBotInfo($botToken);

        if (!$botInfo) {
            throw new \Exception('Invalid bot token');
        }

        $connection = TelegramBotConnection::create([
            'tenant_id' => $tenantId,
            'bot_token' => $botToken,
            'bot_id' => (string) $botInfo['id'],
            'bot_username' => $botInfo['username'],
            'bot_name' => $botInfo['first_name'],
            'webhook_secret' => bin2hex(random_bytes(32)),
            'status' => 'active',
        ]);

        return $connection;
    }

    protected function getBotInfo(string $token): ?array
    {
        $response = Http::get("{$this->baseUrl}/bot{$token}/getMe");

        if ($response->successful() && $response->json('ok')) {
            return $response->json('result');
        }

        return null;
    }

    public function setWebhook(TelegramBotConnection $connection, string $webhookUrl): bool
    {
        $response = $this->makeRequest($connection, 'setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $connection->webhook_secret,
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member'],
        ]);

        if ($response['ok'] ?? false) {
            $connection->update(['webhook_url' => $webhookUrl]);
            return true;
        }

        return false;
    }

    public function deleteWebhook(TelegramBotConnection $connection): bool
    {
        $response = $this->makeRequest($connection, 'deleteWebhook');

        if ($response['ok'] ?? false) {
            $connection->update(['webhook_url' => null]);
            return true;
        }

        return false;
    }

    public function getConnection(int $tenantId): ?TelegramBotConnection
    {
        return TelegramBotConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    // ==========================================
    // MESSAGING
    // ==========================================

    public function sendMessage(
        TelegramBotConnection $connection,
        int $chatId,
        string $text,
        array $options = []
    ): TelegramMessage {
        $message = TelegramMessage::create([
            'connection_id' => $connection->id,
            'chat_id' => $chatId,
            'direction' => 'outbound',
            'message_type' => 'text',
            'content' => $text,
            'keyboard' => $options['reply_markup'] ?? null,
            'reply_to_message_id' => $options['reply_to_message_id'] ?? null,
            'correlation_type' => $options['correlation_type'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
            'status' => 'pending',
        ]);

        $payload = array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
            'reply_to_message_id' => $options['reply_to_message_id'] ?? null,
            'reply_markup' => isset($options['reply_markup']) ? json_encode($options['reply_markup']) : null,
            'disable_notification' => $options['disable_notification'] ?? false,
            'disable_web_page_preview' => $options['disable_web_page_preview'] ?? false,
        ]);

        try {
            $response = $this->makeRequest($connection, 'sendMessage', $payload);

            if ($response['ok'] ?? false) {
                $message->update([
                    'message_id' => $response['result']['message_id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $message->update([
                    'status' => 'failed',
                    'error_message' => $response['description'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $message->fresh();
    }

    public function sendPhoto(
        TelegramBotConnection $connection,
        int $chatId,
        string $photoUrl,
        ?string $caption = null,
        array $options = []
    ): TelegramMessage {
        return $this->sendMediaMessage($connection, $chatId, 'sendPhoto', 'photo', [
            'photo' => $photoUrl,
            'caption' => $caption,
        ], $options);
    }

    public function sendDocument(
        TelegramBotConnection $connection,
        int $chatId,
        string $documentUrl,
        ?string $caption = null,
        array $options = []
    ): TelegramMessage {
        return $this->sendMediaMessage($connection, $chatId, 'sendDocument', 'document', [
            'document' => $documentUrl,
            'caption' => $caption,
        ], $options);
    }

    protected function sendMediaMessage(
        TelegramBotConnection $connection,
        int $chatId,
        string $method,
        string $type,
        array $mediaData,
        array $options = []
    ): TelegramMessage {
        $message = TelegramMessage::create([
            'connection_id' => $connection->id,
            'chat_id' => $chatId,
            'direction' => 'outbound',
            'message_type' => $type,
            'content' => $mediaData['caption'] ?? null,
            'media' => $mediaData,
            'status' => 'pending',
        ]);

        $payload = array_merge([
            'chat_id' => $chatId,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ], $mediaData);

        try {
            $response = $this->makeRequest($connection, $method, $payload);

            if ($response['ok'] ?? false) {
                $message->update([
                    'message_id' => $response['result']['message_id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $message->update([
                    'status' => 'failed',
                    'error_message' => $response['description'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $message->fresh();
    }

    public function editMessage(
        TelegramBotConnection $connection,
        int $chatId,
        int $messageId,
        string $text,
        array $options = []
    ): bool {
        $response = $this->makeRequest($connection, 'editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
            'reply_markup' => isset($options['reply_markup']) ? json_encode($options['reply_markup']) : null,
        ]);

        return $response['ok'] ?? false;
    }

    public function deleteMessage(TelegramBotConnection $connection, int $chatId, int $messageId): bool
    {
        $response = $this->makeRequest($connection, 'deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        return $response['ok'] ?? false;
    }

    // ==========================================
    // INLINE KEYBOARDS
    // ==========================================

    public function createInlineKeyboard(array $buttons): array
    {
        return ['inline_keyboard' => $buttons];
    }

    public function createCallbackButton(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    public function createUrlButton(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    public function answerCallbackQuery(
        TelegramBotConnection $connection,
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): bool {
        $response = $this->makeRequest($connection, 'answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]));

        return $response['ok'] ?? false;
    }

    // ==========================================
    // BOT COMMANDS
    // ==========================================

    public function setCommands(TelegramBotConnection $connection, array $commands): bool
    {
        $response = $this->makeRequest($connection, 'setMyCommands', [
            'commands' => $commands,
        ]);

        if ($response['ok'] ?? false) {
            $connection->update(['commands' => $commands]);
            return true;
        }

        return false;
    }

    // ==========================================
    // WEBHOOK PROCESSING
    // ==========================================

    public function processWebhook(TelegramBotConnection $connection, array $payload): void
    {
        $update = TelegramUpdate::create([
            'connection_id' => $connection->id,
            'update_id' => $payload['update_id'],
            'update_type' => $this->determineUpdateType($payload),
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            if (isset($payload['message'])) {
                $this->processIncomingMessage($connection, $payload['message']);
            }

            if (isset($payload['callback_query'])) {
                $this->processCallbackQuery($connection, $payload['callback_query']);
            }

            if (isset($payload['my_chat_member'])) {
                $this->processChatMemberUpdate($connection, $payload['my_chat_member']);
            }

            $update->markAsProcessed();
        } catch (\Exception $e) {
            $update->markAsFailed($e->getMessage());
        }
    }

    protected function determineUpdateType(array $payload): string
    {
        if (isset($payload['message'])) return 'message';
        if (isset($payload['callback_query'])) return 'callback_query';
        if (isset($payload['my_chat_member'])) return 'my_chat_member';
        if (isset($payload['inline_query'])) return 'inline_query';
        return 'unknown';
    }

    protected function processIncomingMessage(TelegramBotConnection $connection, array $messageData): void
    {
        $chat = $messageData['chat'];
        $from = $messageData['from'] ?? null;

        // Update chat
        TelegramChat::updateOrCreate(
            ['connection_id' => $connection->id, 'chat_id' => $chat['id']],
            [
                'chat_type' => $chat['type'],
                'title' => $chat['title'] ?? null,
                'username' => $chat['username'] ?? null,
                'is_active' => true,
            ]
        );

        // Update subscriber (for private chats)
        if ($from && $chat['type'] === 'private') {
            TelegramSubscriber::updateOrCreate(
                ['connection_id' => $connection->id, 'user_id' => $from['id']],
                [
                    'username' => $from['username'] ?? null,
                    'first_name' => $from['first_name'] ?? null,
                    'last_name' => $from['last_name'] ?? null,
                    'language_code' => $from['language_code'] ?? null,
                    'is_bot' => $from['is_bot'] ?? false,
                    'subscribed_at' => now(),
                    'last_interaction_at' => now(),
                ]
            );
        }

        // Store message
        TelegramMessage::create([
            'connection_id' => $connection->id,
            'chat_id' => $chat['id'],
            'message_id' => $messageData['message_id'],
            'direction' => 'inbound',
            'message_type' => $this->determineMessageType($messageData),
            'content' => $messageData['text'] ?? $messageData['caption'] ?? null,
            'entities' => $messageData['entities'] ?? null,
            'media' => $this->extractMediaFromMessage($messageData),
            'reply_to_message_id' => $messageData['reply_to_message']['message_id'] ?? null,
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
    }

    protected function determineMessageType(array $messageData): string
    {
        if (isset($messageData['text'])) return 'text';
        if (isset($messageData['photo'])) return 'photo';
        if (isset($messageData['document'])) return 'document';
        if (isset($messageData['video'])) return 'video';
        if (isset($messageData['audio'])) return 'audio';
        if (isset($messageData['voice'])) return 'voice';
        if (isset($messageData['sticker'])) return 'sticker';
        if (isset($messageData['location'])) return 'location';
        if (isset($messageData['contact'])) return 'contact';
        return 'unknown';
    }

    protected function extractMediaFromMessage(array $messageData): ?array
    {
        if (isset($messageData['photo'])) {
            $largest = end($messageData['photo']);
            return ['file_id' => $largest['file_id'], 'file_size' => $largest['file_size'] ?? null];
        }

        foreach (['document', 'video', 'audio', 'voice', 'sticker'] as $type) {
            if (isset($messageData[$type])) {
                return [
                    'file_id' => $messageData[$type]['file_id'],
                    'file_name' => $messageData[$type]['file_name'] ?? null,
                    'file_size' => $messageData[$type]['file_size'] ?? null,
                    'mime_type' => $messageData[$type]['mime_type'] ?? null,
                ];
            }
        }

        return null;
    }

    protected function processCallbackQuery(TelegramBotConnection $connection, array $callbackQuery): void
    {
        // Just acknowledge by default - actual handling should be done by the application
        $this->answerCallbackQuery($connection, $callbackQuery['id']);
    }

    protected function processChatMemberUpdate(TelegramBotConnection $connection, array $update): void
    {
        $chat = $update['chat'];
        $newStatus = $update['new_chat_member']['status'] ?? null;

        if ($newStatus === 'member' || $newStatus === 'administrator') {
            TelegramChat::updateOrCreate(
                ['connection_id' => $connection->id, 'chat_id' => $chat['id']],
                [
                    'chat_type' => $chat['type'],
                    'title' => $chat['title'] ?? null,
                    'username' => $chat['username'] ?? null,
                    'is_active' => true,
                ]
            );
        } elseif ($newStatus === 'left' || $newStatus === 'kicked') {
            TelegramChat::where('connection_id', $connection->id)
                ->where('chat_id', $chat['id'])
                ->update(['is_active' => false]);
        }
    }

    // ==========================================
    // BROADCAST
    // ==========================================

    public function broadcastToSubscribers(
        TelegramBotConnection $connection,
        string $text,
        array $options = []
    ): array {
        $subscribers = $connection->subscribers()
            ->where('is_blocked', false)
            ->get();

        $results = ['sent' => 0, 'failed' => 0];

        foreach ($subscribers as $subscriber) {
            // Get private chat with this user
            $chat = $connection->chats()
                ->where('chat_type', 'private')
                ->whereHas('subscriber', function ($q) use ($subscriber) {
                    // This assumes there's a way to link chat to subscriber
                })
                ->first();

            if (!$chat) {
                // Try to send directly to user_id (works for private chats)
                $message = $this->sendMessage($connection, $subscriber->user_id, $text, $options);

                if ($message->status === 'sent') {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                    if (str_contains($message->error_message ?? '', 'blocked')) {
                        $subscriber->block();
                    }
                }
            }
        }

        return $results;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function makeRequest(TelegramBotConnection $connection, string $method, array $params = []): array
    {
        $url = "{$this->baseUrl}/bot{$connection->bot_token}/{$method}";

        $response = Http::post($url, array_filter($params));

        return $response->json() ?? [];
    }
}

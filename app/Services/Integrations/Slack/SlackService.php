<?php

namespace App\Services\Integrations\Slack;

use App\Models\Integrations\Slack\SlackChannel;
use App\Models\Integrations\Slack\SlackConnection;
use App\Models\Integrations\Slack\SlackMessage;
use App\Models\Integrations\Slack\SlackWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class SlackService
{
    protected string $apiBaseUrl = 'https://slack.com/api';
    protected string $authorizeUrl = 'https://slack.com/oauth/v2/authorize';
    protected string $tokenUrl = 'https://slack.com/api/oauth.v2.access';

    // ==========================================
    // OAUTH FLOW
    // ==========================================

    public function getAuthorizationUrl(int $tenantId, array $scopes = []): string
    {
        $defaultScopes = ['chat:write', 'channels:read', 'channels:join', 'users:read'];
        $scopes = array_unique(array_merge($defaultScopes, $scopes));

        $params = [
            'client_id' => config('services.slack.client_id'),
            'scope' => implode(',', $scopes),
            'redirect_uri' => config('services.slack.redirect_uri'),
            'state' => encrypt(['tenant_id' => $tenantId]),
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): SlackConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.slack.client_id'),
            'client_secret' => config('services.slack.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.slack.redirect_uri'),
        ]);

        if (!$response->successful() || !$response->json('ok')) {
            throw new \Exception('Failed to exchange code for token: ' . $response->json('error'));
        }

        $data = $response->json();

        return SlackConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'workspace_id' => $data['team']['id']],
            [
                'workspace_name' => $data['team']['name'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds($data['expires_in'])
                    : null,
                'scopes' => explode(',', $data['scope'] ?? ''),
                'bot_info' => $data['bot_user_id'] ?? null ? [
                    'bot_user_id' => $data['bot_user_id'],
                    'app_id' => $data['app_id'] ?? null,
                ] : null,
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function disconnect(SlackConnection $connection): bool
    {
        // Revoke the token
        $this->makeRequest($connection, 'auth.revoke');

        $connection->update([
            'status' => 'revoked',
            'access_token' => null,
            'refresh_token' => null,
        ]);

        return true;
    }

    public function testConnection(SlackConnection $connection): array
    {
        $response = $this->makeRequest($connection, 'auth.test');

        return [
            'success' => $response['ok'] ?? false,
            'team' => $response['team'] ?? null,
            'user' => $response['user'] ?? null,
            'bot_id' => $response['bot_id'] ?? null,
        ];
    }

    // ==========================================
    // CHANNELS
    // ==========================================

    public function syncChannels(SlackConnection $connection): Collection
    {
        $response = $this->makeRequest($connection, 'conversations.list', [
            'types' => 'public_channel,private_channel',
            'limit' => 1000,
        ]);

        if (!($response['ok'] ?? false)) {
            throw new \Exception('Failed to fetch channels: ' . ($response['error'] ?? 'Unknown error'));
        }

        $channels = collect($response['channels'] ?? []);

        foreach ($channels as $channelData) {
            SlackChannel::updateOrCreate(
                ['connection_id' => $connection->id, 'channel_id' => $channelData['id']],
                [
                    'name' => $channelData['name'],
                    'type' => $channelData['is_channel'] ? 'channel' : 'group',
                    'is_private' => $channelData['is_private'] ?? false,
                    'metadata' => [
                        'topic' => $channelData['topic']['value'] ?? null,
                        'purpose' => $channelData['purpose']['value'] ?? null,
                        'num_members' => $channelData['num_members'] ?? 0,
                    ],
                ]
            );
        }

        return $connection->channels()->get();
    }

    public function getChannels(SlackConnection $connection): Collection
    {
        return $connection->channels;
    }

    public function joinChannel(SlackConnection $connection, string $channelId): bool
    {
        $response = $this->makeRequest($connection, 'conversations.join', [
            'channel' => $channelId,
        ]);

        return $response['ok'] ?? false;
    }

    // ==========================================
    // MESSAGING
    // ==========================================

    public function sendMessage(
        SlackConnection $connection,
        string $channelId,
        string $text,
        array $options = []
    ): SlackMessage {
        $message = SlackMessage::create([
            'connection_id' => $connection->id,
            'channel_id' => $channelId,
            'direction' => 'outbound',
            'content' => $text,
            'blocks' => $options['blocks'] ?? null,
            'attachments' => $options['attachments'] ?? null,
            'status' => 'pending',
            'correlation_ref' => $options['correlation_ref'] ?? null,
        ]);

        $payload = array_filter([
            'channel' => $channelId,
            'text' => $text,
            'blocks' => $options['blocks'] ?? null,
            'attachments' => $options['attachments'] ?? null,
            'thread_ts' => $options['thread_ts'] ?? null,
            'reply_broadcast' => $options['reply_broadcast'] ?? null,
            'unfurl_links' => $options['unfurl_links'] ?? true,
            'unfurl_media' => $options['unfurl_media'] ?? true,
        ]);

        $response = $this->makeRequest($connection, 'chat.postMessage', $payload);

        if ($response['ok'] ?? false) {
            $message->update([
                'message_ts' => $response['ts'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } else {
            $message->update([
                'status' => 'failed',
                'error_details' => ['error' => $response['error'] ?? 'Unknown error'],
            ]);
        }

        $connection->update(['last_used_at' => now()]);

        return $message->fresh();
    }

    public function sendBlockMessage(
        SlackConnection $connection,
        string $channelId,
        array $blocks,
        ?string $fallbackText = null
    ): SlackMessage {
        return $this->sendMessage($connection, $channelId, $fallbackText ?? 'New message', [
            'blocks' => $blocks,
        ]);
    }

    public function updateMessage(
        SlackConnection $connection,
        string $channelId,
        string $messageTs,
        string $text,
        array $options = []
    ): bool {
        $payload = array_filter([
            'channel' => $channelId,
            'ts' => $messageTs,
            'text' => $text,
            'blocks' => $options['blocks'] ?? null,
            'attachments' => $options['attachments'] ?? null,
        ]);

        $response = $this->makeRequest($connection, 'chat.update', $payload);

        return $response['ok'] ?? false;
    }

    public function deleteMessage(
        SlackConnection $connection,
        string $channelId,
        string $messageTs
    ): bool {
        $response = $this->makeRequest($connection, 'chat.delete', [
            'channel' => $channelId,
            'ts' => $messageTs,
        ]);

        return $response['ok'] ?? false;
    }

    public function addReaction(
        SlackConnection $connection,
        string $channelId,
        string $messageTs,
        string $emoji
    ): bool {
        $response = $this->makeRequest($connection, 'reactions.add', [
            'channel' => $channelId,
            'timestamp' => $messageTs,
            'name' => $emoji,
        ]);

        return $response['ok'] ?? false;
    }

    // ==========================================
    // FILE UPLOADS
    // ==========================================

    public function uploadFile(
        SlackConnection $connection,
        string $channelId,
        string $filePath,
        array $options = []
    ): array {
        $response = Http::withToken($connection->access_token)
            ->attach('file', file_get_contents($filePath), $options['filename'] ?? basename($filePath))
            ->post($this->apiBaseUrl . '/files.upload', [
                'channels' => $channelId,
                'title' => $options['title'] ?? null,
                'initial_comment' => $options['comment'] ?? null,
            ]);

        return $response->json();
    }

    // ==========================================
    // USERS
    // ==========================================

    public function getUsers(SlackConnection $connection): array
    {
        $response = $this->makeRequest($connection, 'users.list', [
            'limit' => 1000,
        ]);

        return $response['members'] ?? [];
    }

    public function getUserInfo(SlackConnection $connection, string $userId): ?array
    {
        $response = $this->makeRequest($connection, 'users.info', [
            'user' => $userId,
        ]);

        return $response['user'] ?? null;
    }

    // ==========================================
    // WEBHOOKS
    // ==========================================

    public function registerWebhook(
        SlackConnection $connection,
        string $eventType,
        string $endpointUrl
    ): SlackWebhook {
        return SlackWebhook::create([
            'connection_id' => $connection->id,
            'event_type' => $eventType,
            'endpoint_url' => $endpointUrl,
            'secret' => bin2hex(random_bytes(32)),
            'is_active' => true,
        ]);
    }

    public function processIncomingWebhook(array $payload): void
    {
        $teamId = $payload['team_id'] ?? null;
        if (!$teamId) {
            return;
        }

        $connection = SlackConnection::where('workspace_id', $teamId)->first();
        if (!$connection) {
            return;
        }

        $eventType = $payload['event']['type'] ?? $payload['type'] ?? 'unknown';

        // Store incoming message if applicable
        if ($eventType === 'message' && isset($payload['event'])) {
            SlackMessage::create([
                'connection_id' => $connection->id,
                'channel_id' => $payload['event']['channel'],
                'message_ts' => $payload['event']['ts'],
                'direction' => 'inbound',
                'content' => $payload['event']['text'] ?? '',
                'status' => 'delivered',
                'sent_at' => now(),
            ]);
        }

        // Trigger registered webhooks
        $webhooks = $connection->webhooks()
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            $this->triggerWebhook($webhook, $payload);
        }
    }

    protected function triggerWebhook(SlackWebhook $webhook, array $payload): void
    {
        try {
            Http::withHeaders([
                'X-Webhook-Secret' => $webhook->secret,
            ])->post($webhook->endpoint_url, $payload);

            $webhook->update(['last_triggered_at' => now()]);
        } catch (\Exception $e) {
            // Log error but don't fail
        }
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function makeRequest(
        SlackConnection $connection,
        string $method,
        array $params = []
    ): array {
        $response = Http::withToken($connection->access_token)
            ->post($this->apiBaseUrl . '/' . $method, $params);

        return $response->json() ?? [];
    }

    public function getConnection(int $tenantId): ?SlackConnection
    {
        return SlackConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    public function getConnections(int $tenantId): Collection
    {
        return SlackConnection::where('tenant_id', $tenantId)->get();
    }
}

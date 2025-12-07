<?php

namespace App\Services\Hub\Adapters;

/**
 * Slack Integration Adapter
 *
 * Supports:
 * - Sending messages to channels
 * - Creating channels
 * - Receiving webhook events (message, reaction, etc.)
 */
class SlackAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'slack';
    protected string $baseUrl = 'https://slack.com/api';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $params = [
            'client_id' => $config['client_id'] ?? config('services.slack.client_id'),
            'scope' => $config['scopes'] ?? 'chat:write,channels:read,channels:write,users:read',
            'redirect_uri' => $this->getRedirectUri(),
            'state' => $connectionId,
        ];

        return $this->buildAuthorizationUrl('https://slack.com/oauth/v2/authorize', $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        return $this->exchangeCodeViaOAuth('https://slack.com/api/oauth.v2.access', [
            'client_id' => $config['client_id'] ?? config('services.slack.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.slack.client_secret'),
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ]);
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        // Slack tokens don't expire by default, but support rotation
        return $this->refreshTokensViaOAuth('https://slack.com/api/oauth.v2.access', $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.slack.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.slack.client_secret'),
        ]);
    }

    public function testConnection(array $credentials): array
    {
        $result = $this->makeRequest('POST', "{$this->baseUrl}/auth.test", $credentials);

        return [
            'success' => $result['ok'] ?? false,
            'team' => $result['team'] ?? null,
            'user' => $result['user'] ?? null,
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            'send_message' => $this->sendMessage($data, $credentials),
            'create_channel' => $this->createChannel($data, $credentials),
            'list_channels' => $this->listChannels($credentials),
            'list_users' => $this->listUsers($credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function sendMessage(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/chat.postMessage", $credentials, [
            'channel' => $data['channel'],
            'text' => $data['text'] ?? null,
            'blocks' => $data['blocks'] ?? null,
            'attachments' => $data['attachments'] ?? null,
            'thread_ts' => $data['thread_ts'] ?? null,
        ]);
    }

    protected function createChannel(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/conversations.create", $credentials, [
            'name' => $data['name'],
            'is_private' => $data['is_private'] ?? false,
        ]);
    }

    protected function listChannels(array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/conversations.list", $credentials, [
            'types' => 'public_channel,private_channel',
            'limit' => 100,
        ]);
    }

    protected function listUsers(array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/users.list", $credentials);
    }

    public function parseWebhookEventType(array $payload): string
    {
        return $payload['event']['type'] ?? $payload['type'] ?? 'unknown';
    }

    public function getSupportedActions(): array
    {
        return [
            'send_message' => 'Send a message to a channel',
            'create_channel' => 'Create a new channel',
            'list_channels' => 'List available channels',
            'list_users' => 'List workspace users',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'message' => 'New message in channel',
            'reaction_added' => 'Reaction added to message',
            'channel_created' => 'New channel created',
            'member_joined_channel' => 'User joined channel',
        ];
    }
}

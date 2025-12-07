<?php

namespace App\Services\Hub\Adapters;

/**
 * Discord Integration Adapter
 *
 * Supports:
 * - Sending messages via webhooks
 * - Bot interactions
 * - Server event webhooks
 */
class DiscordAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'discord';
    protected string $baseUrl = 'https://discord.com/api/v10';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $params = [
            'client_id' => $config['client_id'] ?? config('services.discord.client_id'),
            'permissions' => $config['permissions'] ?? '2048', // Send Messages
            'scope' => $config['scopes'] ?? 'bot webhook.incoming',
            'redirect_uri' => $this->getRedirectUri(),
            'state' => $connectionId,
            'response_type' => 'code',
        ];

        return $this->buildAuthorizationUrl('https://discord.com/api/oauth2/authorize', $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        return $this->exchangeCodeViaOAuth('https://discord.com/api/oauth2/token', [
            'client_id' => $config['client_id'] ?? config('services.discord.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ]);
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        return $this->refreshTokensViaOAuth('https://discord.com/api/oauth2/token', $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.discord.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.discord.client_secret'),
        ]);
    }

    public function testConnection(array $credentials): array
    {
        $result = $this->makeRequest('GET', "{$this->baseUrl}/users/@me", $credentials);

        return [
            'success' => isset($result['id']),
            'username' => $result['username'] ?? null,
            'discriminator' => $result['discriminator'] ?? null,
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            'send_webhook_message' => $this->sendWebhookMessage($data),
            'send_channel_message' => $this->sendChannelMessage($data, $credentials),
            'list_guilds' => $this->listGuilds($credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function sendWebhookMessage(array $data): array
    {
        $webhookUrl = $data['webhook_url'];

        $payload = [
            'content' => $data['content'] ?? null,
            'username' => $data['username'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'embeds' => $data['embeds'] ?? null,
        ];

        $response = \Illuminate\Support\Facades\Http::post($webhookUrl, $payload);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
        ];
    }

    protected function sendChannelMessage(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/channels/{$data['channel_id']}/messages", $credentials, [
            'content' => $data['content'],
            'embeds' => $data['embeds'] ?? null,
        ]);
    }

    protected function listGuilds(array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/users/@me/guilds", $credentials);
    }

    public function getSupportedActions(): array
    {
        return [
            'send_webhook_message' => 'Send message via webhook',
            'send_channel_message' => 'Send message to channel (requires bot)',
            'list_guilds' => 'List servers the bot is in',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'message_create' => 'New message created',
            'guild_member_add' => 'New member joined server',
        ];
    }
}

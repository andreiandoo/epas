<?php

namespace App\Services\Integrations\Discord;

use App\Models\Integrations\Discord\DiscordConnection;
use App\Models\Integrations\Discord\DiscordMessage;
use App\Models\Integrations\Discord\DiscordWebhook;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DiscordService
{
    protected string $apiBaseUrl = 'https://discord.com/api/v10';
    protected string $authorizeUrl = 'https://discord.com/api/oauth2/authorize';
    protected string $tokenUrl = 'https://discord.com/api/oauth2/token';

    public function getAuthorizationUrl(int $tenantId, array $scopes = []): string
    {
        $defaultScopes = ['bot', 'guilds', 'webhook.incoming'];
        $scopes = array_unique(array_merge($defaultScopes, $scopes));

        $params = [
            'client_id' => config('services.discord.client_id'),
            'scope' => implode(' ', $scopes),
            'redirect_uri' => config('services.discord.redirect_uri'),
            'response_type' => 'code',
            'state' => encrypt(['tenant_id' => $tenantId]),
            'permissions' => '2048', // Send messages
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): DiscordConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.discord.client_id'),
            'client_secret' => config('services.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.discord.redirect_uri'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        return DiscordConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'guild_id' => $data['guild']['id'] ?? null],
            [
                'guild_name' => $data['guild']['name'] ?? null,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function registerWebhook(DiscordConnection $connection, string $channelId, string $name): DiscordWebhook
    {
        $response = Http::withToken($connection->bot_token)
            ->post("{$this->apiBaseUrl}/channels/{$channelId}/webhooks", [
                'name' => $name,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create webhook: ' . $response->body());
        }

        $data = $response->json();

        return DiscordWebhook::create([
            'connection_id' => $connection->id,
            'webhook_id' => $data['id'],
            'webhook_token' => $data['token'],
            'name' => $data['name'],
            'channel_id' => $data['channel_id'],
            'is_active' => true,
        ]);
    }

    public function sendWebhookMessage(DiscordWebhook $webhook, string $content, array $options = []): DiscordMessage
    {
        $message = DiscordMessage::create([
            'connection_id' => $webhook->connection_id,
            'channel_id' => $webhook->channel_id,
            'delivery_method' => 'webhook',
            'content' => $content,
            'embeds' => $options['embeds'] ?? null,
            'status' => 'pending',
            'correlation_ref' => $options['correlation_ref'] ?? null,
        ]);

        $payload = array_filter([
            'content' => $content,
            'embeds' => $options['embeds'] ?? null,
            'username' => $options['username'] ?? null,
            'avatar_url' => $options['avatar_url'] ?? null,
        ]);

        $response = Http::post($webhook->getWebhookUrl() . '?wait=true', $payload);

        if ($response->successful()) {
            $message->update([
                'message_id' => $response->json('id'),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } else {
            $message->update([
                'status' => 'failed',
                'error_details' => ['error' => $response->body()],
            ]);
        }

        return $message->fresh();
    }

    public function sendBotMessage(DiscordConnection $connection, string $channelId, string $content, array $options = []): DiscordMessage
    {
        $message = DiscordMessage::create([
            'connection_id' => $connection->id,
            'channel_id' => $channelId,
            'delivery_method' => 'bot',
            'content' => $content,
            'embeds' => $options['embeds'] ?? null,
            'status' => 'pending',
            'correlation_ref' => $options['correlation_ref'] ?? null,
        ]);

        $payload = array_filter([
            'content' => $content,
            'embeds' => $options['embeds'] ?? null,
        ]);

        $response = Http::withToken($connection->bot_token, 'Bot')
            ->post("{$this->apiBaseUrl}/channels/{$channelId}/messages", $payload);

        if ($response->successful()) {
            $message->update([
                'message_id' => $response->json('id'),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } else {
            $message->update([
                'status' => 'failed',
                'error_details' => ['error' => $response->body()],
            ]);
        }

        $connection->update(['last_used_at' => now()]);

        return $message->fresh();
    }

    public function getGuilds(DiscordConnection $connection): array
    {
        $response = Http::withToken($connection->access_token)
            ->get("{$this->apiBaseUrl}/users/@me/guilds");

        return $response->json() ?? [];
    }

    public function getChannels(DiscordConnection $connection): array
    {
        if (!$connection->guild_id) {
            return [];
        }

        $response = Http::withToken($connection->bot_token, 'Bot')
            ->get("{$this->apiBaseUrl}/guilds/{$connection->guild_id}/channels");

        return $response->json() ?? [];
    }

    public function getConnection(int $tenantId): ?DiscordConnection
    {
        return DiscordConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}

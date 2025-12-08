<?php

namespace App\Services\Integrations\Microsoft365;

use App\Models\Integrations\Microsoft365\Microsoft365Connection;
use App\Models\Integrations\Microsoft365\MicrosoftOnedriveFile;
use App\Models\Integrations\Microsoft365\MicrosoftOutlookMessage;
use App\Models\Integrations\Microsoft365\MicrosoftTeamsMessage;
use Illuminate\Support\Facades\Http;

class Microsoft365Service
{
    protected string $authorizeUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    protected string $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    protected string $graphUrl = 'https://graph.microsoft.com/v1.0';

    public function getAuthorizationUrl(int $tenantId, array $services = ['onedrive', 'outlook', 'teams']): string
    {
        $scopeMap = [
            'onedrive' => 'Files.ReadWrite',
            'outlook' => 'Mail.Send',
            'teams' => 'ChannelMessage.Send',
            'calendar' => 'Calendars.ReadWrite',
        ];

        $scopes = array_merge(
            ['User.Read', 'offline_access'],
            array_map(fn($s) => $scopeMap[$s] ?? '', $services)
        );

        $params = [
            'client_id' => config('services.microsoft.client_id'),
            'redirect_uri' => config('services.microsoft.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', array_filter($scopes)),
            'state' => encrypt(['tenant_id' => $tenantId, 'services' => $services]),
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): Microsoft365Connection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];
        $services = $stateData['services'] ?? [];

        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.microsoft.redirect_uri'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();
        $userInfo = $this->getUserInfo($data['access_token']);

        return Microsoft365Connection::updateOrCreate(
            ['tenant_id' => $tenantId, 'microsoft_user_id' => $userInfo['id']],
            [
                'email' => $userInfo['mail'] ?? $userInfo['userPrincipalName'],
                'display_name' => $userInfo['displayName'] ?? null,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'enabled_services' => $services,
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    protected function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get("{$this->graphUrl}/me");
        return $response->json();
    }

    // OneDrive Operations
    public function uploadFile(Microsoft365Connection $connection, string $filePath, array $options = []): MicrosoftOnedriveFile
    {
        $this->ensureValidToken($connection);

        $fileName = $options['name'] ?? basename($filePath);
        $parentPath = $options['folder_path'] ?? '';
        $endpoint = $parentPath
            ? "{$this->graphUrl}/me/drive/root:/{$parentPath}/{$fileName}:/content"
            : "{$this->graphUrl}/me/drive/root:/{$fileName}:/content";

        $response = Http::withToken($connection->access_token)
            ->withBody(file_get_contents($filePath), 'application/octet-stream')
            ->put($endpoint);

        if (!$response->successful()) {
            throw new \Exception('Failed to upload file: ' . $response->body());
        }

        $data = $response->json();

        return MicrosoftOnedriveFile::create([
            'connection_id' => $connection->id,
            'item_id' => $data['id'],
            'name' => $data['name'],
            'mime_type' => $data['file']['mimeType'] ?? null,
            'size' => $data['size'] ?? null,
            'web_url' => $data['webUrl'] ?? null,
            'correlation_ref' => $options['correlation_ref'] ?? null,
            'uploaded_at' => now(),
        ]);
    }

    // Outlook Operations
    public function sendEmail(Microsoft365Connection $connection, array $emailData): MicrosoftOutlookMessage
    {
        $this->ensureValidToken($connection);

        $message = MicrosoftOutlookMessage::create([
            'connection_id' => $connection->id,
            'to_email' => $emailData['to'],
            'subject' => $emailData['subject'],
            'body' => $emailData['body'],
            'body_type' => $emailData['body_type'] ?? 'html',
            'status' => 'pending',
            'correlation_ref' => $emailData['correlation_ref'] ?? null,
        ]);

        $payload = [
            'message' => [
                'subject' => $emailData['subject'],
                'body' => [
                    'contentType' => $emailData['body_type'] ?? 'html',
                    'content' => $emailData['body'],
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $emailData['to']]],
                ],
            ],
        ];

        $response = Http::withToken($connection->access_token)
            ->post("{$this->graphUrl}/me/sendMail", $payload);

        if ($response->successful()) {
            $message->update(['status' => 'sent', 'sent_at' => now()]);
        } else {
            $message->update(['status' => 'failed', 'error_details' => ['error' => $response->body()]]);
        }

        $connection->update(['last_used_at' => now()]);

        return $message->fresh();
    }

    // Teams Operations
    public function sendTeamsMessage(Microsoft365Connection $connection, string $teamId, string $channelId, string $content): MicrosoftTeamsMessage
    {
        $this->ensureValidToken($connection);

        $message = MicrosoftTeamsMessage::create([
            'connection_id' => $connection->id,
            'team_id' => $teamId,
            'channel_id' => $channelId,
            'content' => $content,
            'status' => 'pending',
        ]);

        $response = Http::withToken($connection->access_token)
            ->post("{$this->graphUrl}/teams/{$teamId}/channels/{$channelId}/messages", [
                'body' => ['contentType' => 'html', 'content' => $content],
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $message->update(['message_id' => $data['id'], 'status' => 'sent', 'sent_at' => now()]);
        } else {
            $message->update(['status' => 'failed', 'error_details' => ['error' => $response->body()]]);
        }

        return $message->fresh();
    }

    public function getTeams(Microsoft365Connection $connection): array
    {
        $this->ensureValidToken($connection);
        $response = Http::withToken($connection->access_token)->get("{$this->graphUrl}/me/joinedTeams");
        return $response->json('value') ?? [];
    }

    public function getChannels(Microsoft365Connection $connection, string $teamId): array
    {
        $this->ensureValidToken($connection);
        $response = Http::withToken($connection->access_token)->get("{$this->graphUrl}/teams/{$teamId}/channels");
        return $response->json('value') ?? [];
    }

    protected function ensureValidToken(Microsoft365Connection $connection): void
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $this->refreshToken($connection);
        }
    }

    protected function refreshToken(Microsoft365Connection $connection): void
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $connection->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            ]);
        }
    }

    public function getConnection(int $tenantId): ?Microsoft365Connection
    {
        return Microsoft365Connection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}

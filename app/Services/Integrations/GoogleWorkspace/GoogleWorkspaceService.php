<?php

namespace App\Services\Integrations\GoogleWorkspace;

use App\Models\Integrations\GoogleWorkspace\GoogleWorkspaceConnection;
use App\Models\Integrations\GoogleWorkspace\GoogleCalendarEvent;
use App\Models\Integrations\GoogleWorkspace\GoogleDriveFile;
use App\Models\Integrations\GoogleWorkspace\GoogleGmailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GoogleWorkspaceService
{
    protected string $authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $tokenUrl = 'https://oauth2.googleapis.com/token';

    public function getAuthorizationUrl(int $tenantId, array $services = ['drive', 'calendar', 'gmail']): string
    {
        $scopeMap = [
            'drive' => 'https://www.googleapis.com/auth/drive.file',
            'calendar' => 'https://www.googleapis.com/auth/calendar',
            'gmail' => 'https://www.googleapis.com/auth/gmail.send',
        ];

        $scopes = array_merge(
            ['https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile'],
            array_map(fn($s) => $scopeMap[$s] ?? '', $services)
        );

        $params = [
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', array_filter($scopes)),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => encrypt(['tenant_id' => $tenantId, 'services' => $services]),
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): GoogleWorkspaceConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];
        $services = $stateData['services'] ?? [];

        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.google.redirect_uri'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();
        $userInfo = $this->getUserInfo($data['access_token']);

        return GoogleWorkspaceConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'google_user_id' => $userInfo['sub']],
            [
                'email' => $userInfo['email'],
                'name' => $userInfo['name'] ?? null,
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
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');
        return $response->json();
    }

    // Drive Operations
    public function uploadFile(GoogleWorkspaceConnection $connection, string $filePath, array $options = []): GoogleDriveFile
    {
        $this->ensureValidToken($connection);

        $metadata = [
            'name' => $options['name'] ?? basename($filePath),
            'parents' => $options['folder_id'] ? [$options['folder_id']] : null,
        ];

        $response = Http::withToken($connection->access_token)
            ->attach('metadata', json_encode(array_filter($metadata)), 'metadata.json')
            ->attach('file', file_get_contents($filePath), $metadata['name'])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        if (!$response->successful()) {
            throw new \Exception('Failed to upload file: ' . $response->body());
        }

        $data = $response->json();

        return GoogleDriveFile::create([
            'connection_id' => $connection->id,
            'file_id' => $data['id'],
            'name' => $data['name'],
            'mime_type' => $data['mimeType'] ?? null,
            'correlation_ref' => $options['correlation_ref'] ?? null,
            'uploaded_at' => now(),
        ]);
    }

    public function listFiles(GoogleWorkspaceConnection $connection, ?string $folderId = null): array
    {
        $this->ensureValidToken($connection);

        $query = $folderId ? "'{$folderId}' in parents" : null;
        $params = array_filter(['q' => $query, 'fields' => 'files(id,name,mimeType,size,webViewLink)']);

        $response = Http::withToken($connection->access_token)
            ->get('https://www.googleapis.com/drive/v3/files', $params);

        return $response->json('files') ?? [];
    }

    // Calendar Operations
    public function createEvent(GoogleWorkspaceConnection $connection, array $eventData): GoogleCalendarEvent
    {
        $this->ensureValidToken($connection);

        $payload = [
            'summary' => $eventData['summary'],
            'description' => $eventData['description'] ?? null,
            'location' => $eventData['location'] ?? null,
            'start' => $eventData['is_all_day']
                ? ['date' => $eventData['start_time']->format('Y-m-d')]
                : ['dateTime' => $eventData['start_time']->toIso8601String()],
            'end' => $eventData['is_all_day']
                ? ['date' => $eventData['end_time']->format('Y-m-d')]
                : ['dateTime' => $eventData['end_time']->toIso8601String()],
            'attendees' => array_map(fn($email) => ['email' => $email], $eventData['attendees'] ?? []),
        ];

        $calendarId = $eventData['calendar_id'] ?? 'primary';
        $response = Http::withToken($connection->access_token)
            ->post("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", array_filter($payload));

        if (!$response->successful()) {
            throw new \Exception('Failed to create event: ' . $response->body());
        }

        $data = $response->json();

        return GoogleCalendarEvent::create([
            'connection_id' => $connection->id,
            'event_id' => $data['id'],
            'calendar_id' => $calendarId,
            'summary' => $eventData['summary'],
            'description' => $eventData['description'] ?? null,
            'location' => $eventData['location'] ?? null,
            'start_time' => $eventData['start_time'],
            'end_time' => $eventData['end_time'],
            'is_all_day' => $eventData['is_all_day'] ?? false,
            'attendees' => $eventData['attendees'] ?? null,
            'status' => 'confirmed',
            'correlation_ref' => $eventData['correlation_ref'] ?? null,
        ]);
    }

    public function listEvents(GoogleWorkspaceConnection $connection, ?string $calendarId = 'primary'): array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", [
                'timeMin' => now()->toIso8601String(),
                'maxResults' => 100,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);

        return $response->json('items') ?? [];
    }

    // Gmail Operations
    public function sendEmail(GoogleWorkspaceConnection $connection, array $emailData): GoogleGmailMessage
    {
        $this->ensureValidToken($connection);

        $message = GoogleGmailMessage::create([
            'connection_id' => $connection->id,
            'to_email' => $emailData['to'],
            'subject' => $emailData['subject'],
            'body' => $emailData['body'],
            'is_html' => $emailData['is_html'] ?? false,
            'status' => 'pending',
            'correlation_ref' => $emailData['correlation_ref'] ?? null,
        ]);

        $rawMessage = $this->buildRawEmail($emailData, $connection->email);
        $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $response = Http::withToken($connection->access_token)
            ->post('https://www.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $encodedMessage,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $message->update([
                'message_id' => $data['id'],
                'thread_id' => $data['threadId'],
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

    protected function buildRawEmail(array $emailData, string $fromEmail): string
    {
        $contentType = ($emailData['is_html'] ?? false) ? 'text/html' : 'text/plain';
        $boundary = uniqid('boundary_');

        $headers = [
            "From: {$fromEmail}",
            "To: {$emailData['to']}",
            "Subject: {$emailData['subject']}",
            "MIME-Version: 1.0",
            "Content-Type: {$contentType}; charset=UTF-8",
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $emailData['body'];
    }

    protected function ensureValidToken(GoogleWorkspaceConnection $connection): void
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $this->refreshToken($connection);
        }
    }

    protected function refreshToken(GoogleWorkspaceConnection $connection): void
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $connection->update([
                'access_token' => $data['access_token'],
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            ]);
        }
    }

    public function getConnection(int $tenantId): ?GoogleWorkspaceConnection
    {
        return GoogleWorkspaceConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}

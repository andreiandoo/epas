<?php

namespace App\Services\Hub\Adapters;

/**
 * Google Workspace Integration Adapter
 *
 * Supports:
 * - Google Drive (files, folders)
 * - Google Calendar (events)
 * - Gmail (send emails)
 */
class GoogleWorkspaceAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'google-workspace';
    protected string $baseUrl = 'https://www.googleapis.com';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $scopes = $config['scopes'] ?? implode(' ', [
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/gmail.send',
            'openid',
            'email',
            'profile',
        ]);

        $params = [
            'client_id' => $config['client_id'] ?? config('services.google.client_id'),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $connectionId,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return $this->buildAuthorizationUrl('https://accounts.google.com/o/oauth2/v2/auth', $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        return $this->exchangeCodeViaOAuth('https://oauth2.googleapis.com/token', [
            'client_id' => $config['client_id'] ?? config('services.google.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.google.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ]);
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        return $this->refreshTokensViaOAuth('https://oauth2.googleapis.com/token', $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.google.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.google.client_secret'),
        ]);
    }

    public function revokeTokens(array $credentials): bool
    {
        $token = $credentials['access_token'];
        $response = \Illuminate\Support\Facades\Http::post("https://oauth2.googleapis.com/revoke?token={$token}");
        return $response->successful();
    }

    public function testConnection(array $credentials): array
    {
        $result = $this->makeRequest('GET', "{$this->baseUrl}/oauth2/v2/userinfo", $credentials);

        return [
            'success' => isset($result['id']),
            'email' => $result['email'] ?? null,
            'name' => $result['name'] ?? null,
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            // Drive actions
            'list_files' => $this->listFiles($data, $credentials),
            'upload_file' => $this->uploadFile($data, $credentials),
            'create_folder' => $this->createFolder($data, $credentials),
            // Calendar actions
            'list_calendars' => $this->listCalendars($credentials),
            'create_event' => $this->createCalendarEvent($data, $credentials),
            'list_events' => $this->listCalendarEvents($data, $credentials),
            // Gmail actions
            'send_email' => $this->sendEmail($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function listFiles(array $data, array $credentials): array
    {
        $params = [
            'pageSize' => $data['page_size'] ?? 100,
            'fields' => 'files(id,name,mimeType,modifiedTime,size)',
        ];

        if (isset($data['folder_id'])) {
            $params['q'] = "'{$data['folder_id']}' in parents";
        }

        return $this->makeRequest('GET', "{$this->baseUrl}/drive/v3/files", $credentials, $params);
    }

    protected function uploadFile(array $data, array $credentials): array
    {
        // For file uploads, we'd need multipart upload
        // This is a simplified metadata-only upload
        $metadata = [
            'name' => $data['name'],
            'parents' => $data['folder_id'] ? [$data['folder_id']] : null,
        ];

        return $this->makeRequest('POST', "{$this->baseUrl}/drive/v3/files", $credentials, $metadata);
    }

    protected function createFolder(array $data, array $credentials): array
    {
        $metadata = [
            'name' => $data['name'],
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $data['parent_id'] ? [$data['parent_id']] : null,
        ];

        return $this->makeRequest('POST', "{$this->baseUrl}/drive/v3/files", $credentials, $metadata);
    }

    protected function listCalendars(array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/calendar/v3/users/me/calendarList", $credentials);
    }

    protected function createCalendarEvent(array $data, array $credentials): array
    {
        $calendarId = $data['calendar_id'] ?? 'primary';

        return $this->makeRequest('POST', "{$this->baseUrl}/calendar/v3/calendars/{$calendarId}/events", $credentials, [
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'start' => [
                'dateTime' => $data['start_time'],
                'timeZone' => $data['timezone'] ?? 'UTC',
            ],
            'end' => [
                'dateTime' => $data['end_time'],
                'timeZone' => $data['timezone'] ?? 'UTC',
            ],
            'attendees' => $data['attendees'] ?? null,
        ]);
    }

    protected function listCalendarEvents(array $data, array $credentials): array
    {
        $calendarId = $data['calendar_id'] ?? 'primary';

        return $this->makeRequest('GET', "{$this->baseUrl}/calendar/v3/calendars/{$calendarId}/events", $credentials, [
            'maxResults' => $data['max_results'] ?? 100,
            'timeMin' => $data['time_min'] ?? now()->toRfc3339String(),
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ]);
    }

    protected function sendEmail(array $data, array $credentials): array
    {
        $message = "To: {$data['to']}\r\n";
        $message .= "Subject: {$data['subject']}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $data['body'];

        $raw = base64_encode($message);
        $raw = str_replace(['+', '/', '='], ['-', '_', ''], $raw);

        return $this->makeRequest('POST', "{$this->baseUrl}/gmail/v1/users/me/messages/send", $credentials, [
            'raw' => $raw,
        ]);
    }

    public function getSupportedActions(): array
    {
        return [
            'list_files' => 'List files in Drive',
            'upload_file' => 'Upload file to Drive',
            'create_folder' => 'Create folder in Drive',
            'list_calendars' => 'List calendars',
            'create_event' => 'Create calendar event',
            'list_events' => 'List calendar events',
            'send_email' => 'Send email via Gmail',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'file_created' => 'File created in Drive',
            'calendar_event_created' => 'Calendar event created',
        ];
    }
}

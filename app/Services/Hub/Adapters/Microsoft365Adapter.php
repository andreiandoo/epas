<?php

namespace App\Services\Hub\Adapters;

/**
 * Microsoft 365 Integration Adapter
 *
 * Supports:
 * - OneDrive (files)
 * - Outlook (email, calendar)
 * - Microsoft Teams (messages)
 */
class Microsoft365Adapter extends BaseConnectorAdapter
{
    protected string $slug = 'microsoft-365';
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $scopes = $config['scopes'] ?? implode(' ', [
            'openid',
            'profile',
            'email',
            'offline_access',
            'Files.ReadWrite',
            'Calendars.ReadWrite',
            'Mail.Send',
            'Chat.ReadWrite',
        ]);

        $tenantId = $config['tenant_id'] ?? 'common';

        $params = [
            'client_id' => $config['client_id'] ?? config('services.microsoft.client_id'),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $connectionId,
            'response_mode' => 'query',
        ];

        return $this->buildAuthorizationUrl("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize", $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        $tenantId = $config['tenant_id'] ?? 'common';

        return $this->exchangeCodeViaOAuth("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id' => $config['client_id'] ?? config('services.microsoft.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.microsoft.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => $config['scopes'] ?? 'openid profile email offline_access Files.ReadWrite Calendars.ReadWrite Mail.Send',
        ]);
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        $tenantId = $config['tenant_id'] ?? 'common';

        return $this->refreshTokensViaOAuth("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.microsoft.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.microsoft.client_secret'),
            'scope' => $config['scopes'] ?? 'openid profile email offline_access Files.ReadWrite Calendars.ReadWrite Mail.Send',
        ]);
    }

    public function testConnection(array $credentials): array
    {
        $result = $this->makeRequest('GET', "{$this->baseUrl}/me", $credentials);

        return [
            'success' => isset($result['id']),
            'email' => $result['mail'] ?? $result['userPrincipalName'] ?? null,
            'name' => $result['displayName'] ?? null,
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            // OneDrive actions
            'list_files' => $this->listFiles($data, $credentials),
            'create_folder' => $this->createFolder($data, $credentials),
            // Outlook Calendar actions
            'list_calendars' => $this->listCalendars($credentials),
            'create_event' => $this->createCalendarEvent($data, $credentials),
            'list_events' => $this->listCalendarEvents($data, $credentials),
            // Outlook Mail actions
            'send_email' => $this->sendEmail($data, $credentials),
            // Teams actions
            'send_teams_message' => $this->sendTeamsMessage($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function listFiles(array $data, array $credentials): array
    {
        $path = $data['path'] ?? '/me/drive/root/children';
        return $this->makeRequest('GET', "{$this->baseUrl}{$path}", $credentials);
    }

    protected function createFolder(array $data, array $credentials): array
    {
        $parentPath = $data['parent_path'] ?? '/me/drive/root';

        return $this->makeRequest('POST', "{$this->baseUrl}{$parentPath}/children", $credentials, [
            'name' => $data['name'],
            'folder' => new \stdClass(),
            '@microsoft.graph.conflictBehavior' => 'rename',
        ]);
    }

    protected function listCalendars(array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/me/calendars", $credentials);
    }

    protected function createCalendarEvent(array $data, array $credentials): array
    {
        $calendarId = $data['calendar_id'] ?? null;
        $endpoint = $calendarId
            ? "/me/calendars/{$calendarId}/events"
            : '/me/events';

        return $this->makeRequest('POST', "{$this->baseUrl}{$endpoint}", $credentials, [
            'subject' => $data['subject'],
            'body' => [
                'contentType' => 'HTML',
                'content' => $data['body'] ?? '',
            ],
            'start' => [
                'dateTime' => $data['start_time'],
                'timeZone' => $data['timezone'] ?? 'UTC',
            ],
            'end' => [
                'dateTime' => $data['end_time'],
                'timeZone' => $data['timezone'] ?? 'UTC',
            ],
            'attendees' => array_map(fn($email) => [
                'emailAddress' => ['address' => $email],
                'type' => 'required',
            ], $data['attendees'] ?? []),
        ]);
    }

    protected function listCalendarEvents(array $data, array $credentials): array
    {
        $calendarId = $data['calendar_id'] ?? null;
        $endpoint = $calendarId
            ? "/me/calendars/{$calendarId}/events"
            : '/me/events';

        return $this->makeRequest('GET', "{$this->baseUrl}{$endpoint}", $credentials, [
            '$top' => $data['limit'] ?? 100,
            '$orderby' => 'start/dateTime',
        ]);
    }

    protected function sendEmail(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/me/sendMail", $credentials, [
            'message' => [
                'subject' => $data['subject'],
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $data['body'],
                ],
                'toRecipients' => array_map(fn($email) => [
                    'emailAddress' => ['address' => $email],
                ], (array) $data['to']),
            ],
        ]);
    }

    protected function sendTeamsMessage(array $data, array $credentials): array
    {
        $chatId = $data['chat_id'];

        return $this->makeRequest('POST', "{$this->baseUrl}/chats/{$chatId}/messages", $credentials, [
            'body' => [
                'content' => $data['content'],
            ],
        ]);
    }

    public function getSupportedActions(): array
    {
        return [
            'list_files' => 'List OneDrive files',
            'create_folder' => 'Create OneDrive folder',
            'list_calendars' => 'List Outlook calendars',
            'create_event' => 'Create calendar event',
            'list_events' => 'List calendar events',
            'send_email' => 'Send email via Outlook',
            'send_teams_message' => 'Send Teams message',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'file_created' => 'File created in OneDrive',
            'calendar_event_created' => 'Calendar event created',
            'email_received' => 'Email received',
        ];
    }
}

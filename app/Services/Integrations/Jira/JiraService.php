<?php

namespace App\Services\Integrations\Jira;

use App\Models\Integrations\Jira\JiraConnection;
use App\Models\Integrations\Jira\JiraIssue;
use App\Models\Integrations\Jira\JiraProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class JiraService
{
    protected string $authorizeUrl = 'https://auth.atlassian.com/authorize';
    protected string $tokenUrl = 'https://auth.atlassian.com/oauth/token';
    protected string $resourceUrl = 'https://api.atlassian.com/oauth/token/accessible-resources';

    public function getAuthorizationUrl(int $tenantId): string
    {
        $scopes = [
            'read:jira-work', 'write:jira-work', 'read:jira-user',
            'manage:jira-project', 'offline_access',
        ];

        $params = [
            'audience' => 'api.atlassian.com',
            'client_id' => config('services.jira.client_id'),
            'scope' => implode(' ', $scopes),
            'redirect_uri' => config('services.jira.redirect_uri'),
            'state' => encrypt(['tenant_id' => $tenantId]),
            'response_type' => 'code',
            'prompt' => 'consent',
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): JiraConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.jira.client_id'),
            'client_secret' => config('services.jira.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.jira.redirect_uri'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        // Get accessible resources
        $resources = Http::withToken($data['access_token'])
            ->get($this->resourceUrl)
            ->json();

        $resource = $resources[0] ?? null;

        return JiraConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'cloud_id' => $resource['id'] ?? null],
            [
                'site_url' => $resource['url'] ?? null,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'accessible_resources' => $resources,
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function syncProjects(JiraConnection $connection): Collection
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get($this->getApiUrl($connection) . '/rest/api/3/project');

        $projects = $response->json() ?? [];

        foreach ($projects as $project) {
            JiraProject::updateOrCreate(
                ['connection_id' => $connection->id, 'project_id' => $project['id']],
                [
                    'project_key' => $project['key'],
                    'name' => $project['name'],
                    'project_type' => $project['projectTypeKey'] ?? null,
                    'is_synced' => true,
                ]
            );
        }

        return $connection->projects()->get();
    }

    public function createIssue(JiraConnection $connection, array $data): JiraIssue
    {
        $this->ensureValidToken($connection);

        $payload = [
            'fields' => [
                'project' => ['key' => $data['project_key']],
                'summary' => $data['summary'],
                'description' => isset($data['description']) ? [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $data['description']]]]],
                ] : null,
                'issuetype' => ['name' => $data['issue_type'] ?? 'Task'],
                'priority' => isset($data['priority']) ? ['name' => $data['priority']] : null,
            ],
        ];

        $response = Http::withToken($connection->access_token)
            ->post($this->getApiUrl($connection) . '/rest/api/3/issue', array_filter($payload, fn($v) => $v !== null));

        if (!$response->successful()) {
            throw new \Exception('Failed to create issue: ' . $response->body());
        }

        $result = $response->json();

        $issue = JiraIssue::create([
            'connection_id' => $connection->id,
            'issue_id' => $result['id'],
            'issue_key' => $result['key'],
            'project_key' => $data['project_key'],
            'issue_type' => $data['issue_type'] ?? 'Task',
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'status' => 'To Do',
            'priority' => $data['priority'] ?? null,
            'direction' => 'outbound',
            'correlation_ref' => $data['correlation_ref'] ?? null,
        ]);

        $connection->update(['last_used_at' => now()]);

        return $issue;
    }

    public function updateIssue(JiraConnection $connection, string $issueKey, array $data): bool
    {
        $this->ensureValidToken($connection);

        $fields = [];
        if (isset($data['summary'])) $fields['summary'] = $data['summary'];
        if (isset($data['description'])) {
            $fields['description'] = [
                'type' => 'doc',
                'version' => 1,
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $data['description']]]]],
            ];
        }
        if (isset($data['priority'])) $fields['priority'] = ['name' => $data['priority']];

        $response = Http::withToken($connection->access_token)
            ->put($this->getApiUrl($connection) . "/rest/api/3/issue/{$issueKey}", ['fields' => $fields]);

        return $response->successful();
    }

    public function transitionIssue(JiraConnection $connection, string $issueKey, string $transitionId): bool
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->post($this->getApiUrl($connection) . "/rest/api/3/issue/{$issueKey}/transitions", [
                'transition' => ['id' => $transitionId],
            ]);

        return $response->successful();
    }

    public function addComment(JiraConnection $connection, string $issueKey, string $comment): bool
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->post($this->getApiUrl($connection) . "/rest/api/3/issue/{$issueKey}/comment", [
                'body' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $comment]]]],
                ],
            ]);

        return $response->successful();
    }

    public function searchIssues(JiraConnection $connection, string $jql, int $maxResults = 50): array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get($this->getApiUrl($connection) . '/rest/api/3/search', [
                'jql' => $jql,
                'maxResults' => $maxResults,
            ]);

        return $response->json('issues') ?? [];
    }

    public function getIssue(JiraConnection $connection, string $issueKey): ?array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get($this->getApiUrl($connection) . "/rest/api/3/issue/{$issueKey}");

        return $response->successful() ? $response->json() : null;
    }

    protected function getApiUrl(JiraConnection $connection): string
    {
        return "https://api.atlassian.com/ex/jira/{$connection->cloud_id}";
    }

    protected function ensureValidToken(JiraConnection $connection): void
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $this->refreshToken($connection);
        }
    }

    protected function refreshToken(JiraConnection $connection): void
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.jira.client_id'),
            'client_secret' => config('services.jira.client_secret'),
            'refresh_token' => $connection->refresh_token,
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

    public function getConnection(int $tenantId): ?JiraConnection
    {
        return JiraConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}

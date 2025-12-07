<?php

namespace App\Services\Hub\Adapters;

/**
 * Jira Integration Adapter
 *
 * Supports:
 * - Issue management (create, update, transition)
 * - Project management
 * - User management
 * - Webhook events
 */
class JiraAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'jira';
    protected string $baseUrl;

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $scopes = $config['scopes'] ?? implode(' ', [
            'read:jira-work',
            'write:jira-work',
            'read:jira-user',
            'manage:jira-project',
            'manage:jira-webhook',
            'offline_access',
        ]);

        $params = [
            'audience' => 'api.atlassian.com',
            'client_id' => $config['client_id'] ?? config('services.jira.client_id'),
            'scope' => $scopes,
            'redirect_uri' => $this->getRedirectUri(),
            'state' => $connectionId,
            'response_type' => 'code',
            'prompt' => 'consent',
        ];

        return $this->buildAuthorizationUrl('https://auth.atlassian.com/authorize', $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        $result = $this->exchangeCodeViaOAuth('https://auth.atlassian.com/oauth/token', [
            'client_id' => $config['client_id'] ?? config('services.jira.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.jira.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ]);

        // Get accessible resources (cloud instances)
        $resources = $this->getAccessibleResources($result['access_token']);
        if (!empty($resources)) {
            $result['cloud_id'] = $resources[0]['id'];
            $result['site_url'] = $resources[0]['url'];
        }

        return $result;
    }

    protected function getAccessibleResources(string $accessToken): array
    {
        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get('https://api.atlassian.com/oauth/token/accessible-resources');

        return $response->json() ?? [];
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        return $this->refreshTokensViaOAuth('https://auth.atlassian.com/oauth/token', $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.jira.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.jira.client_secret'),
        ]);
    }

    public function testConnection(array $credentials): array
    {
        $cloudId = $credentials['cloud_id'];
        $this->baseUrl = "https://api.atlassian.com/ex/jira/{$cloudId}/rest/api/3";

        $result = $this->makeRequest('GET', "{$this->baseUrl}/myself", $credentials);

        return [
            'success' => isset($result['accountId']),
            'account_id' => $result['accountId'] ?? null,
            'email' => $result['emailAddress'] ?? null,
            'display_name' => $result['displayName'] ?? null,
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        $cloudId = $credentials['cloud_id'];
        $this->baseUrl = "https://api.atlassian.com/ex/jira/{$cloudId}/rest/api/3";

        return match ($action) {
            // Issue actions
            'create_issue' => $this->createIssue($data, $credentials),
            'update_issue' => $this->updateIssue($data, $credentials),
            'get_issue' => $this->getIssue($data, $credentials),
            'transition_issue' => $this->transitionIssue($data, $credentials),
            'add_comment' => $this->addComment($data, $credentials),
            'search_issues' => $this->searchIssues($data, $credentials),
            // Project actions
            'list_projects' => $this->listProjects($credentials),
            'get_project' => $this->getProject($data, $credentials),
            // User actions
            'search_users' => $this->searchUsers($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function createIssue(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/issue", $credentials, [
            'fields' => [
                'project' => ['key' => $data['project_key']],
                'summary' => $data['summary'],
                'description' => $this->formatDescription($data['description'] ?? ''),
                'issuetype' => ['name' => $data['issue_type'] ?? 'Task'],
                'priority' => isset($data['priority']) ? ['name' => $data['priority']] : null,
                'assignee' => isset($data['assignee_id']) ? ['accountId' => $data['assignee_id']] : null,
                'labels' => $data['labels'] ?? null,
            ],
        ]);
    }

    protected function formatDescription(string $text): array
    {
        // Jira Cloud uses Atlassian Document Format (ADF)
        return [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => $text,
                ]],
            ]],
        ];
    }

    protected function updateIssue(array $data, array $credentials): array
    {
        $issueKey = $data['issue_key'];
        unset($data['issue_key']);

        $fields = [];
        if (isset($data['summary'])) $fields['summary'] = $data['summary'];
        if (isset($data['description'])) $fields['description'] = $this->formatDescription($data['description']);
        if (isset($data['priority'])) $fields['priority'] = ['name' => $data['priority']];
        if (isset($data['assignee_id'])) $fields['assignee'] = ['accountId' => $data['assignee_id']];

        return $this->makeRequest('PUT', "{$this->baseUrl}/issue/{$issueKey}", $credentials, [
            'fields' => $fields,
        ]);
    }

    protected function getIssue(array $data, array $credentials): array
    {
        $issueKey = $data['issue_key'];
        return $this->makeRequest('GET', "{$this->baseUrl}/issue/{$issueKey}", $credentials);
    }

    protected function transitionIssue(array $data, array $credentials): array
    {
        $issueKey = $data['issue_key'];

        return $this->makeRequest('POST', "{$this->baseUrl}/issue/{$issueKey}/transitions", $credentials, [
            'transition' => ['id' => $data['transition_id']],
            'fields' => $data['fields'] ?? null,
        ]);
    }

    protected function addComment(array $data, array $credentials): array
    {
        $issueKey = $data['issue_key'];

        return $this->makeRequest('POST', "{$this->baseUrl}/issue/{$issueKey}/comment", $credentials, [
            'body' => $this->formatDescription($data['body']),
        ]);
    }

    protected function searchIssues(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/search", $credentials, [
            'jql' => $data['jql'] ?? 'order by created DESC',
            'maxResults' => $data['limit'] ?? 50,
            'startAt' => $data['offset'] ?? 0,
            'fields' => $data['fields'] ?? ['summary', 'status', 'assignee', 'priority', 'created'],
        ]);
    }

    protected function listProjects(array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/project/search", $credentials, [
            'maxResults' => 100,
        ]);
    }

    protected function getProject(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/project/{$data['project_key']}", $credentials);
    }

    protected function searchUsers(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/user/search", $credentials, [
            'query' => $data['query'],
            'maxResults' => $data['limit'] ?? 50,
        ]);
    }

    public function parseWebhookEventType(array $payload): string
    {
        return $payload['webhookEvent'] ?? 'unknown';
    }

    public function getSupportedActions(): array
    {
        return [
            'create_issue' => 'Create a new issue',
            'update_issue' => 'Update an existing issue',
            'get_issue' => 'Get issue details',
            'transition_issue' => 'Transition issue to new status',
            'add_comment' => 'Add comment to issue',
            'search_issues' => 'Search issues with JQL',
            'list_projects' => 'List all projects',
            'get_project' => 'Get project details',
            'search_users' => 'Search for users',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'jira:issue_created' => 'Issue was created',
            'jira:issue_updated' => 'Issue was updated',
            'jira:issue_deleted' => 'Issue was deleted',
            'comment_created' => 'Comment was added',
            'project_created' => 'Project was created',
        ];
    }
}

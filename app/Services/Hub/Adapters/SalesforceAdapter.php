<?php

namespace App\Services\Hub\Adapters;

/**
 * Salesforce Integration Adapter
 *
 * Supports:
 * - Contacts management
 * - Leads management
 * - Opportunities management
 * - Account management
 */
class SalesforceAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'salesforce';
    protected string $baseUrl;

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $instanceUrl = $config['instance_url'] ?? 'https://login.salesforce.com';

        $params = [
            'client_id' => $config['client_id'] ?? config('services.salesforce.client_id'),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'state' => $connectionId,
        ];

        return $this->buildAuthorizationUrl("{$instanceUrl}/services/oauth2/authorize", $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        $instanceUrl = $config['instance_url'] ?? 'https://login.salesforce.com';

        $result = $this->exchangeCodeViaOAuth("{$instanceUrl}/services/oauth2/token", [
            'client_id' => $config['client_id'] ?? config('services.salesforce.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.salesforce.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ]);

        // Store instance URL for API calls
        $result['instance_url'] = $result['instance_url'] ?? $instanceUrl;

        return $result;
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        $instanceUrl = $config['instance_url'] ?? 'https://login.salesforce.com';

        return $this->refreshTokensViaOAuth("{$instanceUrl}/services/oauth2/token", $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.salesforce.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.salesforce.client_secret'),
        ]);
    }

    public function revokeTokens(array $credentials): bool
    {
        $instanceUrl = $credentials['instance_url'] ?? 'https://login.salesforce.com';
        $token = $credentials['access_token'];

        $response = \Illuminate\Support\Facades\Http::asForm()
            ->post("{$instanceUrl}/services/oauth2/revoke", ['token' => $token]);

        return $response->successful();
    }

    public function testConnection(array $credentials): array
    {
        $instanceUrl = $credentials['instance_url'];
        $result = $this->makeRequest('GET', "{$instanceUrl}/services/data/v58.0/sobjects", $credentials);

        return [
            'success' => isset($result['sobjects']),
            'objects_count' => count($result['sobjects'] ?? []),
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        $this->baseUrl = $credentials['instance_url'] . '/services/data/v58.0';

        return match ($action) {
            // Contact actions
            'create_contact' => $this->createContact($data, $credentials),
            'update_contact' => $this->updateContact($data, $credentials),
            'get_contact' => $this->getContact($data, $credentials),
            'list_contacts' => $this->listContacts($data, $credentials),
            // Lead actions
            'create_lead' => $this->createLead($data, $credentials),
            'convert_lead' => $this->convertLead($data, $credentials),
            // Opportunity actions
            'create_opportunity' => $this->createOpportunity($data, $credentials),
            'update_opportunity' => $this->updateOpportunity($data, $credentials),
            // Query
            'query' => $this->query($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function createContact(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/sobjects/Contact", $credentials, [
            'FirstName' => $data['first_name'] ?? null,
            'LastName' => $data['last_name'],
            'Email' => $data['email'] ?? null,
            'Phone' => $data['phone'] ?? null,
            'AccountId' => $data['account_id'] ?? null,
        ]);
    }

    protected function updateContact(array $data, array $credentials): array
    {
        $id = $data['id'];
        unset($data['id']);

        return $this->makeRequest('PATCH', "{$this->baseUrl}/sobjects/Contact/{$id}", $credentials, $data);
    }

    protected function getContact(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/sobjects/Contact/{$data['id']}", $credentials);
    }

    protected function listContacts(array $data, array $credentials): array
    {
        $query = $data['query'] ?? "SELECT Id, FirstName, LastName, Email FROM Contact LIMIT 100";
        return $this->query(['query' => $query], $credentials);
    }

    protected function createLead(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/sobjects/Lead", $credentials, [
            'FirstName' => $data['first_name'] ?? null,
            'LastName' => $data['last_name'],
            'Email' => $data['email'] ?? null,
            'Phone' => $data['phone'] ?? null,
            'Company' => $data['company'] ?? 'Unknown',
            'Status' => $data['status'] ?? 'New',
        ]);
    }

    protected function convertLead(array $data, array $credentials): array
    {
        // Lead conversion requires Apex REST or composite API
        return $this->makeRequest('POST', "{$this->baseUrl}/sobjects/Lead/{$data['id']}/convert", $credentials, [
            'convertedStatus' => $data['converted_status'] ?? 'Closed - Converted',
        ]);
    }

    protected function createOpportunity(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/sobjects/Opportunity", $credentials, [
            'Name' => $data['name'],
            'StageName' => $data['stage'] ?? 'Prospecting',
            'CloseDate' => $data['close_date'] ?? now()->addDays(30)->format('Y-m-d'),
            'Amount' => $data['amount'] ?? null,
            'AccountId' => $data['account_id'] ?? null,
        ]);
    }

    protected function updateOpportunity(array $data, array $credentials): array
    {
        $id = $data['id'];
        unset($data['id']);

        return $this->makeRequest('PATCH', "{$this->baseUrl}/sobjects/Opportunity/{$id}", $credentials, $data);
    }

    protected function query(array $data, array $credentials): array
    {
        $query = urlencode($data['query']);
        return $this->makeRequest('GET', "{$this->baseUrl}/query?q={$query}", $credentials);
    }

    public function getSupportedActions(): array
    {
        return [
            'create_contact' => 'Create a contact',
            'update_contact' => 'Update a contact',
            'get_contact' => 'Get contact details',
            'list_contacts' => 'List contacts',
            'create_lead' => 'Create a lead',
            'convert_lead' => 'Convert lead to contact',
            'create_opportunity' => 'Create an opportunity',
            'update_opportunity' => 'Update an opportunity',
            'query' => 'Run SOQL query',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'contact_created' => 'Contact was created',
            'lead_created' => 'Lead was created',
            'opportunity_updated' => 'Opportunity was updated',
        ];
    }
}

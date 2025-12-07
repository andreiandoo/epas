<?php

namespace App\Services\Hub\Adapters;

/**
 * HubSpot Integration Adapter
 *
 * Supports:
 * - Contacts management
 * - Deals management
 * - Companies management
 * - Email campaigns
 */
class HubSpotAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'hubspot';
    protected string $baseUrl = 'https://api.hubapi.com';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        $scopes = $config['scopes'] ?? implode(' ', [
            'crm.objects.contacts.read',
            'crm.objects.contacts.write',
            'crm.objects.deals.read',
            'crm.objects.deals.write',
            'crm.objects.companies.read',
            'crm.objects.companies.write',
        ]);

        $params = [
            'client_id' => $config['client_id'] ?? config('services.hubspot.client_id'),
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => $scopes,
            'state' => $connectionId,
        ];

        return $this->buildAuthorizationUrl('https://app.hubspot.com/oauth/authorize', $params);
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        return $this->exchangeCodeViaOAuth('https://api.hubapi.com/oauth/v1/token', [
            'client_id' => $config['client_id'] ?? config('services.hubspot.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.hubspot.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ]);
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        return $this->refreshTokensViaOAuth('https://api.hubapi.com/oauth/v1/token', $refreshToken, [
            'client_id' => $config['client_id'] ?? config('services.hubspot.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.hubspot.client_secret'),
        ]);
    }

    public function testConnection(array $credentials): array
    {
        $result = $this->makeRequest('GET', "{$this->baseUrl}/oauth/v1/access-tokens/{$credentials['access_token']}", $credentials);

        return [
            'success' => isset($result['user_id']),
            'hub_id' => $result['hub_id'] ?? null,
            'user_id' => $result['user_id'] ?? null,
        ];
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            // Contact actions
            'create_contact' => $this->createContact($data, $credentials),
            'update_contact' => $this->updateContact($data, $credentials),
            'get_contact' => $this->getContact($data, $credentials),
            'list_contacts' => $this->listContacts($data, $credentials),
            'search_contacts' => $this->searchContacts($data, $credentials),
            // Deal actions
            'create_deal' => $this->createDeal($data, $credentials),
            'update_deal' => $this->updateDeal($data, $credentials),
            'list_deals' => $this->listDeals($data, $credentials),
            // Company actions
            'create_company' => $this->createCompany($data, $credentials),
            'list_companies' => $this->listCompanies($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function createContact(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/crm/v3/objects/contacts", $credentials, [
            'properties' => [
                'email' => $data['email'],
                'firstname' => $data['first_name'] ?? null,
                'lastname' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
            ],
        ]);
    }

    protected function updateContact(array $data, array $credentials): array
    {
        $id = $data['id'];
        unset($data['id']);

        return $this->makeRequest('PATCH', "{$this->baseUrl}/crm/v3/objects/contacts/{$id}", $credentials, [
            'properties' => $data,
        ]);
    }

    protected function getContact(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/crm/v3/objects/contacts/{$data['id']}", $credentials);
    }

    protected function listContacts(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/crm/v3/objects/contacts", $credentials, [
            'limit' => $data['limit'] ?? 100,
            'after' => $data['after'] ?? null,
        ]);
    }

    protected function searchContacts(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/crm/v3/objects/contacts/search", $credentials, [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => $data['property'] ?? 'email',
                    'operator' => $data['operator'] ?? 'CONTAINS_TOKEN',
                    'value' => $data['value'],
                ]],
            ]],
            'limit' => $data['limit'] ?? 100,
        ]);
    }

    protected function createDeal(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/crm/v3/objects/deals", $credentials, [
            'properties' => [
                'dealname' => $data['name'],
                'amount' => $data['amount'] ?? null,
                'dealstage' => $data['stage'] ?? 'appointmentscheduled',
                'closedate' => $data['close_date'] ?? null,
                'pipeline' => $data['pipeline'] ?? 'default',
            ],
        ]);
    }

    protected function updateDeal(array $data, array $credentials): array
    {
        $id = $data['id'];
        unset($data['id']);

        return $this->makeRequest('PATCH', "{$this->baseUrl}/crm/v3/objects/deals/{$id}", $credentials, [
            'properties' => $data,
        ]);
    }

    protected function listDeals(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/crm/v3/objects/deals", $credentials, [
            'limit' => $data['limit'] ?? 100,
        ]);
    }

    protected function createCompany(array $data, array $credentials): array
    {
        return $this->makeRequest('POST', "{$this->baseUrl}/crm/v3/objects/companies", $credentials, [
            'properties' => [
                'name' => $data['name'],
                'domain' => $data['domain'] ?? null,
                'industry' => $data['industry'] ?? null,
                'phone' => $data['phone'] ?? null,
            ],
        ]);
    }

    protected function listCompanies(array $data, array $credentials): array
    {
        return $this->makeRequest('GET', "{$this->baseUrl}/crm/v3/objects/companies", $credentials, [
            'limit' => $data['limit'] ?? 100,
        ]);
    }

    public function parseWebhookEventType(array $payload): string
    {
        return $payload['subscriptionType'] ?? $payload['eventType'] ?? 'unknown';
    }

    public function getSupportedActions(): array
    {
        return [
            'create_contact' => 'Create a contact',
            'update_contact' => 'Update a contact',
            'get_contact' => 'Get contact details',
            'list_contacts' => 'List all contacts',
            'search_contacts' => 'Search contacts',
            'create_deal' => 'Create a deal',
            'update_deal' => 'Update a deal',
            'list_deals' => 'List all deals',
            'create_company' => 'Create a company',
            'list_companies' => 'List all companies',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'contact.creation' => 'Contact was created',
            'contact.propertyChange' => 'Contact property changed',
            'deal.creation' => 'Deal was created',
            'deal.propertyChange' => 'Deal property changed',
        ];
    }
}

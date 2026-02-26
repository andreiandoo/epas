<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceCustomer;
use App\Services\MarketplaceNotificationService;
use Illuminate\Support\Facades\Log;

class MarketplaceCustomerObserver
{
    public function __construct(
        protected MarketplaceNotificationService $notificationService
    ) {}

    /**
     * Handle the MarketplaceCustomer "created" event.
     */
    public function created(MarketplaceCustomer $customer): void
    {
        $this->syncDynamicLists($customer);
        $this->sendRegistrationNotification($customer);
        $this->syncToCoreCustomer($customer);
    }

    /**
     * Send notification about new customer registration
     */
    protected function sendRegistrationNotification(MarketplaceCustomer $customer): void
    {
        if (!$customer->marketplace_client_id) {
            return;
        }

        try {
            $this->notificationService->notifyCustomerRegistration(
                $customer->marketplace_client_id,
                $customer->name ?? ($customer->first_name . ' ' . $customer->last_name) ?? 'Client nou',
                $customer->email ?? '',
                $customer,
                route('filament.marketplace.resources.customers.edit', ['record' => $customer->id])
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create customer registration notification', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the MarketplaceCustomer "updated" event.
     */
    public function updated(MarketplaceCustomer $customer): void
    {
        // Only sync if relevant fields changed
        $relevantFields = [
            'accepts_marketing',
            'total_orders',
            'city',
            'state',
            'birth_date',
            'status',
        ];

        if ($customer->wasChanged($relevantFields)) {
            $this->syncDynamicLists($customer);
        }

        // Sync to core customers if personal data changed
        $coreRelevantFields = [
            'first_name', 'last_name', 'phone', 'city', 'country', 'birth_date', 'email',
        ];

        if ($customer->wasChanged($coreRelevantFields)) {
            $this->syncToCoreCustomer($customer);
        }
    }

    /**
     * Sync customer to matching dynamic lists
     */
    protected function syncDynamicLists(MarketplaceCustomer $customer): void
    {
        if ($customer->status !== 'active') {
            return;
        }

        // Get all active dynamic lists for this marketplace
        $dynamicLists = MarketplaceContactList::query()
            ->where('marketplace_client_id', $customer->marketplace_client_id)
            ->where('list_type', 'dynamic')
            ->where('is_active', true)
            ->whereNotNull('rules')
            ->get();

        foreach ($dynamicLists as $list) {
            // Check if customer matches the list rules
            $matches = $list->buildMatchingCustomersQuery()
                ->where('id', $customer->id)
                ->exists();

            if ($matches) {
                // Add customer to list if not already subscribed
                $isSubscribed = $list->activeSubscribers()
                    ->where('marketplace_customers.id', $customer->id)
                    ->exists();

                if (!$isSubscribed) {
                    $list->addSubscriber($customer);
                }
            }
        }
    }

    /**
     * Sync marketplace customer to core customers table.
     * Ensures all marketplace customers appear in /admin/customers.
     */
    protected function syncToCoreCustomer(MarketplaceCustomer $mc): void
    {
        $tenantId = $this->resolveTenantId($mc);

        if (!$tenantId) {
            Log::info('Cannot sync marketplace customer to core: no tenant found', [
                'marketplace_customer_id' => $mc->id,
                'marketplace_client_id' => $mc->marketplace_client_id,
            ]);
            return;
        }

        try {
            $customer = Customer::where('tenant_id', $tenantId)
                ->where('email', $mc->email)
                ->first();

            if (!$customer) {
                // Create new core customer
                Customer::create([
                    'tenant_id' => $tenantId,
                    'email' => $mc->email,
                    'first_name' => $mc->first_name,
                    'last_name' => $mc->last_name,
                    'phone' => $mc->phone,
                    'city' => $mc->city,
                    'country' => $mc->country,
                    'date_of_birth' => $mc->birth_date,
                    'primary_tenant_id' => $tenantId,
                ]);
            } else {
                // Update only empty fields on existing customer
                $updates = [];
                if (!$customer->first_name && $mc->first_name) $updates['first_name'] = $mc->first_name;
                if (!$customer->last_name && $mc->last_name) $updates['last_name'] = $mc->last_name;
                if (!$customer->phone && $mc->phone) $updates['phone'] = $mc->phone;
                if (!$customer->city && $mc->city) $updates['city'] = $mc->city;
                if (!$customer->country && $mc->country) $updates['country'] = $mc->country;

                if (!empty($updates)) {
                    $customer->update($updates);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync marketplace customer to core', [
                'marketplace_customer_id' => $mc->id,
                'email' => $mc->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve tenant ID from marketplace client's associated tenants.
     */
    protected function resolveTenantId(MarketplaceCustomer $mc): ?int
    {
        if (!$mc->marketplace_client_id) {
            return null;
        }

        $marketplaceClient = $mc->marketplaceClient;
        if (!$marketplaceClient) {
            return null;
        }

        // Try to get first active tenant
        $tenant = $marketplaceClient->activeTenants()->first();
        if ($tenant) {
            return $tenant->id;
        }

        // Fallback: try any tenant
        $tenant = $marketplaceClient->tenants()->first();
        return $tenant?->id;
    }
}

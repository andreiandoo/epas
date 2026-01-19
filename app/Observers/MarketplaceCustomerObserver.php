<?php

namespace App\Observers;

use App\Models\MarketplaceContactList;
use App\Models\MarketplaceCustomer;

class MarketplaceCustomerObserver
{
    /**
     * Handle the MarketplaceCustomer "created" event.
     */
    public function created(MarketplaceCustomer $customer): void
    {
        $this->syncDynamicLists($customer);
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
}

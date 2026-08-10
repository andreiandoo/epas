<?php

namespace App\Services\Identity;

use App\Models\Customer;
use App\Models\MarketplaceCustomer;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge between the two buyer identities.
 *
 * EPAS has two: `Customer` (tenant-scoped — owns loyalty points, referrals,
 * cashless) and `MarketplaceCustomer` (marketplace-scoped — the mobile app's
 * buyer, owns tickets, orders, favourites). Shorts gamification (D11) needs to
 * write points, which live on the wrong side.
 *
 * TODO(owner): docs/plans/friends-social.md §0 calls this out as the blocking
 * architectural decision and recommends option A — a nullable
 * `marketplace_customers.customer_id` column plus this service. That column does
 * not exist yet, so resolve() returns null and the Shorts gamification loop
 * keeps its state marketplace-side (short_streaks) instead of guessing at a
 * link. Add the column, then this resolves and points flow to the real ledger
 * with no change at the call sites.
 */
class IdentityBridge
{
    /**
     * The `Customer` behind a marketplace account, when the link exists.
     */
    public function resolve(MarketplaceCustomer $customer): ?Customer
    {
        if (! Schema::hasColumn('marketplace_customers', 'customer_id')) {
            return null;
        }

        $customerId = $customer->getAttribute('customer_id');

        return $customerId ? Customer::find($customerId) : null;
    }

    /**
     * Whether the bridge is usable — callers branch on this instead of
     * discovering a null halfway through a points award.
     */
    public function isAvailable(): bool
    {
        return Schema::hasColumn('marketplace_customers', 'customer_id');
    }
}

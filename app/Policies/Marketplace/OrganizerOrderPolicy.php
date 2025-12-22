<?php

namespace App\Policies\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizerUser;
use App\Models\Order;

/**
 * Policy for orders in the Organizer panel.
 * Ensures organizers can only view their own orders.
 */
class OrganizerOrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(MarketplaceOrganizerUser $user): bool
    {
        return true; // Query is scoped by organizer_id in resource
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(MarketplaceOrganizerUser $user, Order $order): bool
    {
        return $order->organizer_id === $user->organizer_id;
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(MarketplaceOrganizerUser $user): bool
    {
        return false; // Orders are created by customers
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(MarketplaceOrganizerUser $user, Order $order): bool
    {
        return false; // Organizers can view but not modify orders
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(MarketplaceOrganizerUser $user, Order $order): bool
    {
        return false; // Orders cannot be deleted
    }

    /**
     * Determine whether the user can restore the order.
     */
    public function restore(MarketplaceOrganizerUser $user, Order $order): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the order.
     */
    public function forceDelete(MarketplaceOrganizerUser $user, Order $order): bool
    {
        return false;
    }
}

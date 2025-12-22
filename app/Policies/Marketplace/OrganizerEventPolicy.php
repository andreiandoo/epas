<?php

namespace App\Policies\Marketplace;

use App\Models\Event;
use App\Models\Marketplace\MarketplaceOrganizerUser;

/**
 * Policy for events in the Organizer panel.
 * Ensures organizers can only access their own events.
 */
class OrganizerEventPolicy
{
    /**
     * Determine whether the user can view any events.
     */
    public function viewAny(MarketplaceOrganizerUser $user): bool
    {
        return true; // Query is scoped by organizer_id in resource
    }

    /**
     * Determine whether the user can view the event.
     */
    public function view(MarketplaceOrganizerUser $user, Event $event): bool
    {
        return $event->organizer_id === $user->organizer_id;
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(MarketplaceOrganizerUser $user): bool
    {
        $organizer = $user->organizer;
        return $organizer && $organizer->status === 'active';
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(MarketplaceOrganizerUser $user, Event $event): bool
    {
        return $event->organizer_id === $user->organizer_id;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(MarketplaceOrganizerUser $user, Event $event): bool
    {
        // Can only delete if no tickets have been sold
        if ($event->organizer_id !== $user->organizer_id) {
            return false;
        }

        return $event->tickets()->count() === 0;
    }

    /**
     * Determine whether the user can restore the event.
     */
    public function restore(MarketplaceOrganizerUser $user, Event $event): bool
    {
        return $event->organizer_id === $user->organizer_id;
    }

    /**
     * Determine whether the user can permanently delete the event.
     */
    public function forceDelete(MarketplaceOrganizerUser $user, Event $event): bool
    {
        return false; // Never allow force delete
    }
}

<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\HostedEventCreatedNotification;
use Illuminate\Support\Facades\Log;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->notifyVenueOwnerIfHosted($event);
    }

    /**
     * Handle the Event "updated" event.
     * Notify if venue changed to a different owner's venue.
     */
    public function updated(Event $event): void
    {
        // Only check if venue_id changed
        if ($event->isDirty('venue_id') && $event->venue_id) {
            $this->notifyVenueOwnerIfHosted($event);
        }
    }

    /**
     * Notify venue owner if this event is at their venue but organized by another tenant.
     */
    protected function notifyVenueOwnerIfHosted(Event $event): void
    {
        try {
            // Skip if no venue
            if (!$event->venue_id) {
                return;
            }

            $venue = $event->venue;
            if (!$venue) {
                return;
            }

            // Skip if the event organizer is the venue owner
            if ($event->tenant_id === $venue->tenant_id) {
                return;
            }

            // Get the venue owner tenant
            $venueOwnerTenant = $venue->tenant;
            if (!$venueOwnerTenant) {
                return;
            }

            // Get users belonging to the venue owner tenant
            $venueOwnerUsers = User::where('tenant_id', $venueOwnerTenant->id)->get();

            if ($venueOwnerUsers->isEmpty()) {
                return;
            }

            // Notify all users of the venue owner tenant
            foreach ($venueOwnerUsers as $user) {
                $user->notify(new HostedEventCreatedNotification($event, $venue));
            }

            Log::info('Notified venue owner about hosted event', [
                'event_id' => $event->id,
                'venue_id' => $venue->id,
                'venue_owner_tenant_id' => $venueOwnerTenant->id,
                'event_organizer_tenant_id' => $event->tenant_id,
                'users_notified' => $venueOwnerUsers->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify venue owner about hosted event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        // Could notify venue owner about cancellation
    }
}

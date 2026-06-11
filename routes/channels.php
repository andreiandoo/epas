<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Seat-status updates go on PUBLIC channels keyed by event id. Anyone with
| the event id can subscribe (no auth) — seat availability isn't sensitive
| (any buyer can see it on the public ticket page). Channel:
|   event.{eventId}.seats
|
| Private channels (per-organizer dashboards, etc.) belong here as well
| when added, with auth callbacks returning a user object.
|
*/

// Public channel — no auth callback needed. Defined for completeness.
Broadcast::channel('event.{eventId}.seats', function () {
    return true;
});

// Sales channel — broadcast every time an order transitions to a paid /
// confirmed state. Mobile dashboards subscribe to it for instant counter
// refresh (no 30 s polling lag).
Broadcast::channel('event.{eventId}.sales', function () {
    return true;
});

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

/*
| Live Chat microservice channels (used only when chat.transport=reverb).
| Inert until an Echo client subscribes, so they have no runtime effect while
| the widget/console stay on polling.
|
|   chat.operators.{marketplaceClientId}  — staff pool: an operator (marketplace
|       admin) of that marketplace may subscribe.
|   chat.conversation.{conversationId}    — a single thread: the assigned/allowed
|       operator of the owning marketplace may subscribe. (Guest openers stay on
|       polling — they cannot authenticate a private channel.)
*/
Broadcast::channel('chat.operators.{marketplaceClientId}', function ($user, $marketplaceClientId) {
    $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user() ?: $user;
    return $admin && (int) ($admin->marketplace_client_id ?? 0) === (int) $marketplaceClientId;
}, ['guards' => ['marketplace_admin', 'web']]);

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user() ?: $user;
    if (!$admin) {
        return false;
    }
    $conversation = \App\Models\Chat\ChatConversation::withoutGlobalScopes()->find($conversationId);
    return $conversation
        && (int) $conversation->marketplace_client_id === (int) ($admin->marketplace_client_id ?? 0);
}, ['guards' => ['marketplace_admin', 'web']]);

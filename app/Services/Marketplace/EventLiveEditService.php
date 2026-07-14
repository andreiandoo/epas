<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceEventCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies organizer event edits — the "safe" subset that is allowed on a LIVE
 * (published) event. Deliberately excludes ticket-type restructuring, which is
 * handled separately (and never on a live event, since deleting + recreating
 * ticket types would destroy already-sold tickets).
 *
 * Shared by:
 *  - the organizer API (EventsController) for draft edits + live edits
 *  - the pending-changes approval flow (organizer without allow_live_edits →
 *    changes parked on the event, applied here when a marketplace admin
 *    approves them).
 */
class EventLiveEditService
{
    /**
     * Map the validated organizer payload to Event column values. Pure — no
     * side effects. Only keys present in $validated are mapped, so callers can
     * pass a partial set.
     */
    public function mapToEventData(array $validated, Event $event): array
    {
        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['title'] = ['ro' => $validated['name'], 'en' => $validated['name']];
        }
        if (array_key_exists('description', $validated)) {
            $desc = $validated['description'] ?? '';
            $updateData['description'] = ['ro' => $desc, 'en' => $desc];
        }
        if (array_key_exists('ticket_terms', $validated)) {
            $terms = $validated['ticket_terms'] ?? '';
            $updateData['ticket_terms'] = ['ro' => $terms, 'en' => $terms];
        }
        if (array_key_exists('thank_you_message', $validated)) {
            $tym = $validated['thank_you_message'] ?? '';
            $updateData['thank_you_message'] = ($tym !== '' && $tym !== null)
                ? ['ro' => $tym, 'en' => $tym]
                : null;
        }
        if (array_key_exists('short_description', $validated)) {
            $short = $validated['short_description'] ?? '';
            $updateData['short_description'] = ['ro' => $short, 'en' => $short];
        }

        $durationMode = $validated['duration_mode'] ?? $event->duration_mode ?? 'single_day';
        if (isset($validated['duration_mode'])) {
            $updateData['duration_mode'] = $durationMode;
        }

        if (isset($validated['starts_at']) || isset($validated['ends_at'])) {
            if ($durationMode === 'range') {
                $updateData['event_date'] = null;
                $updateData['start_time'] = null;
                $updateData['end_time'] = null;
                $updateData['door_time'] = null;
                if (isset($validated['starts_at'])) {
                    $startsAt = Carbon::parse($validated['starts_at']);
                    $updateData['range_start_date'] = $startsAt->toDateString();
                    $updateData['range_start_time'] = $startsAt->format('H:i');
                }
                if (isset($validated['ends_at'])) {
                    $endsAt = Carbon::parse($validated['ends_at']);
                    $updateData['range_end_date'] = $endsAt->toDateString();
                    $updateData['range_end_time'] = $endsAt->format('H:i');
                }
            } else {
                $updateData['range_start_date'] = null;
                $updateData['range_end_date'] = null;
                $updateData['range_start_time'] = null;
                $updateData['range_end_time'] = null;
                if (isset($validated['starts_at'])) {
                    $startsAt = Carbon::parse($validated['starts_at']);
                    $updateData['event_date'] = $startsAt->toDateString();
                    $updateData['start_time'] = $startsAt->format('H:i');
                }
                if (isset($validated['ends_at'])) {
                    $endsAt = Carbon::parse($validated['ends_at']);
                    $updateData['end_time'] = $endsAt->format('H:i');
                }
            }
        }
        if (isset($validated['doors_open_at'])) {
            $updateData['door_time'] = Carbon::parse($validated['doors_open_at'])->format('H:i');
        }
        if (isset($validated['venue_id'])) {
            $updateData['venue_id'] = $validated['venue_id'];
            $updateData['suggested_venue_name'] = null;
        } elseif (isset($validated['venue_name'])) {
            $updateData['suggested_venue_name'] = $validated['venue_name'];
        }
        if (isset($validated['venue_address'])) {
            $updateData['address'] = $validated['venue_address'];
        }
        foreach (['marketplace_event_category_id', 'website_url', 'event_website_url', 'facebook_url', 'video_url'] as $key) {
            if (isset($validated[$key])) {
                $updateData[$key] = $validated[$key];
            }
        }

        return $updateData;
    }

    /**
     * Sync the relation fields (category-driven event types, genres, artists).
     */
    public function syncRelations(Event $event, array $validated): void
    {
        if (isset($validated['marketplace_event_category_id'])) {
            $category = MarketplaceEventCategory::find($validated['marketplace_event_category_id']);
            if ($category && !empty($category->event_type_ids)) {
                $event->eventTypes()->sync($category->event_type_ids);
            }
        }
        if (isset($validated['genre_ids'])) {
            $event->eventGenres()->sync($validated['genre_ids']);
        }
        if (isset($validated['artist_ids'])) {
            $event->artists()->sync($validated['artist_ids']);
        }
    }

    /**
     * Apply the safe fields + relations to the event immediately (used for
     * drafts, allow_live_edits organizers, and admin approval of pending
     * changes). Never touches ticket types.
     */
    public function applyFields(Event $event, array $validated): void
    {
        DB::transaction(function () use ($event, $validated) {
            $updateData = $this->mapToEventData($validated, $event);
            if (!empty($updateData)) {
                $event->update($updateData);
            }
            $this->syncRelations($event, $validated);
        });
    }

    /**
     * Park a live edit as a pending change set — the live event is untouched.
     */
    public function storePending(Event $event, array $validated): void
    {
        unset($validated['ticket_types']); // never live-edit ticket types
        $event->update([
            'pending_changes' => $validated,
            'pending_changes_status' => 'pending',
            'pending_changes_submitted_at' => now(),
        ]);
    }

    /**
     * Approve parked changes: apply them, then clear the pending state.
     */
    public function approvePending(Event $event): void
    {
        $data = is_array($event->pending_changes) ? $event->pending_changes : [];
        if (!empty($data)) {
            $this->applyFields($event, $data);
        }
        $event->update([
            'pending_changes' => null,
            'pending_changes_status' => null,
            'pending_changes_submitted_at' => null,
        ]);
    }

    /**
     * Reject parked changes: discard them, leave the live event as-is.
     */
    public function rejectPending(Event $event): void
    {
        $event->update([
            'pending_changes' => null,
            'pending_changes_status' => 'rejected',
            'pending_changes_submitted_at' => null,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventSchedulingService
{
    /**
     * Process event scheduling after save
     * Creates/updates child events for multi-day and recurring modes
     */
    public function processEventScheduling(Event $event): void
    {
        if ($event->isChild()) {
            return;
        }

        match ($event->duration_mode) {
            'multi_day' => $this->processMultiDayEvent($event),
            'recurring' => $this->processRecurringEvent($event),
            default => null,
        };
    }

    /**
     * Process multi-day event - sync child events for each slot.
     * PRESERVES existing children and their IDs/slugs/URLs.
     */
    protected function processMultiDayEvent(Event $event): void
    {
        $multiSlots = $event->multi_slots ?? [];

        if (empty($multiSlots)) {
            return;
        }

        DB::transaction(function () use ($event, $multiSlots) {
            $event->update(['is_template' => true]);

            $existingChildren = $event->children()->orderBy('occurrence_number')->get();

            // Index existing children by occurrence_number
            $childrenByOccurrence = $existingChildren->keyBy('occurrence_number');

            $processedIds = [];

            foreach ($multiSlots as $index => $slot) {
                $occurrenceNumber = $index + 1;
                $dateData = [
                    'event_date' => $slot['date'] ?? null,
                    'start_time' => $slot['start_time'] ?? null,
                    'door_time' => $slot['door_time'] ?? null,
                    'end_time' => $slot['end_time'] ?? null,
                ];

                if (isset($childrenByOccurrence[$occurrenceNumber])) {
                    // UPDATE existing child — preserve ID, slug, URL
                    $child = $childrenByOccurrence[$occurrenceNumber];
                    $this->updateChildEvent($event, $child, $dateData);
                    $processedIds[] = $child->id;
                } else {
                    // CREATE new child
                    $child = $this->createChildEvent($event, $dateData, $occurrenceNumber);
                    $processedIds[] = $child->id;
                }
            }

            // Remove children that no longer have a slot (only if no tickets sold)
            $event->children()
                ->whereNotIn('id', $processedIds)
                ->get()
                ->each(function (Event $orphan) {
                    $hasTickets = $orphan->ticketTypes()
                        ->whereHas('tickets', fn ($q) => $q->where('status', '!=', 'cancelled'))
                        ->exists();

                    if (!$hasTickets) {
                        Log::info("[EventScheduling] Deleting orphan child event #{$orphan->id}");
                        $orphan->delete();
                    } else {
                        Log::warning("[EventScheduling] Keeping orphan child #{$orphan->id} — has sold tickets");
                    }
                });

            Log::info("[EventScheduling] Synced " . count($multiSlots) . " child events for multi-day event #{$event->id}");
        });
    }

    /**
     * Process recurring event - sync child events based on recurrence pattern.
     * PRESERVES existing children.
     */
    protected function processRecurringEvent(Event $event): void
    {
        $recurringStartDate = $event->recurring_start_date;
        $recurringFrequency = $event->recurring_frequency;
        $recurringCount = (int) ($event->recurring_count ?? 1);

        if (!$recurringStartDate || !$recurringFrequency || $recurringCount < 1) {
            return;
        }

        DB::transaction(function () use ($event, $recurringStartDate, $recurringFrequency, $recurringCount) {
            $event->update(['is_template' => true]);

            $dates = $this->generateRecurringDates(
                Carbon::parse($recurringStartDate),
                $recurringFrequency,
                $recurringCount,
                $event->recurring_week_of_month ?? null
            );

            $existingChildren = $event->children()->orderBy('occurrence_number')->get();
            $childrenByOccurrence = $existingChildren->keyBy('occurrence_number');

            $processedIds = [];

            foreach ($dates as $index => $date) {
                $occurrenceNumber = $index + 1;
                $dateData = [
                    'event_date' => $date->format('Y-m-d'),
                    'start_time' => $event->recurring_start_time,
                    'door_time' => $event->recurring_door_time,
                    'end_time' => $event->recurring_end_time,
                ];

                if (isset($childrenByOccurrence[$occurrenceNumber])) {
                    $child = $childrenByOccurrence[$occurrenceNumber];
                    $this->updateChildEvent($event, $child, $dateData);
                    $processedIds[] = $child->id;
                } else {
                    $child = $this->createChildEvent($event, $dateData, $occurrenceNumber);
                    $processedIds[] = $child->id;
                }
            }

            // Remove excess children (only if no tickets sold)
            $event->children()
                ->whereNotIn('id', $processedIds)
                ->get()
                ->each(function (Event $orphan) {
                    $hasTickets = $orphan->ticketTypes()
                        ->whereHas('tickets', fn ($q) => $q->where('status', '!=', 'cancelled'))
                        ->exists();

                    if (!$hasTickets) {
                        $orphan->delete();
                    }
                });

            Log::info("[EventScheduling] Synced " . count($dates) . " child events for recurring event #{$event->id}");
        });
    }

    /**
     * Update an existing child event with new date data from parent.
     * Preserves: id, slug, ticket types, orders, tickets.
     * Updates: dates/times, shared fields from parent.
     */
    protected function updateChildEvent(Event $parent, Event $child, array $dateData): void
    {
        $child->update([
            'event_date' => $dateData['event_date'],
            'start_time' => $dateData['start_time'],
            'door_time' => $dateData['door_time'],
            'end_time' => $dateData['end_time'],
            // Sync shared fields from parent
            'title' => $parent->title,
            'subtitle' => $parent->subtitle,
            'short_description' => $parent->short_description,
            'description' => $parent->description,
            'venue_id' => $parent->venue_id,
            'address' => $parent->address,
            'poster_url' => $parent->poster_url,
            'hero_image_url' => $parent->hero_image_url,
            'ticket_terms' => $parent->ticket_terms,
            'is_published' => $parent->is_published,
            'is_cancelled' => $parent->is_cancelled,
            'is_postponed' => $parent->is_postponed,
            'marketplace_client_id' => $parent->marketplace_client_id,
            'marketplace_organizer_id' => $parent->marketplace_organizer_id,
            'marketplace_city_id' => $parent->marketplace_city_id,
            'marketplace_event_category_id' => $parent->marketplace_event_category_id,
        ]);

        // Sync taxonomy relationships
        $this->copyEventRelationships($parent, $child);
    }

    /**
     * Create a child event from parent
     */
    protected function createChildEvent(Event $parent, array $dateData, int $occurrenceNumber): Event
    {
        $childData = $parent->replicate()->toArray();

        unset(
            $childData['id'],
            $childData['created_at'],
            $childData['updated_at'],
            $childData['multi_slots'],
            $childData['recurring_start_date'],
            $childData['recurring_frequency'],
            $childData['recurring_count'],
            $childData['recurring_week_of_month'],
            $childData['recurring_start_time'],
            $childData['recurring_door_time'],
            $childData['recurring_end_time'],
            $childData['recurring_weekday']
        );

        $childData['parent_id'] = $parent->id;
        $childData['is_template'] = false;
        $childData['occurrence_number'] = $occurrenceNumber;
        $childData['duration_mode'] = 'single_day';
        $childData['event_date'] = $dateData['event_date'];
        $childData['start_time'] = $dateData['start_time'];
        $childData['door_time'] = $dateData['door_time'];
        $childData['end_time'] = $dateData['end_time'];

        // Temporary slug until we have the ID
        $childData['slug'] = $parent->slug . '-' . $occurrenceNumber . '-' . Str::random(6);

        $child = Event::create($childData);

        // Permanent slug with actual ID (stable URL)
        $child->update(['slug' => $parent->slug . '-' . $child->id]);

        $this->copyEventRelationships($parent, $child);

        return $child;
    }

    /**
     * Generate dates based on recurrence pattern
     */
    protected function generateRecurringDates(
        Carbon $startDate,
        string $frequency,
        int $count,
        ?int $weekOfMonth = null
    ): array {
        $dates = [];
        $current = $startDate->copy();

        for ($i = 0; $i < $count; $i++) {
            if ($i === 0) {
                $dates[] = $current->copy();
            } else {
                switch ($frequency) {
                    case 'weekly':
                        $current->addWeek();
                        $dates[] = $current->copy();
                        break;

                    case 'monthly_nth':
                        $weekday = $startDate->dayOfWeekIso;
                        $current->addMonth();

                        $targetDate = $this->getNthWeekdayOfMonth(
                            $current->year,
                            $current->month,
                            $weekday,
                            $weekOfMonth ?? 1
                        );

                        if ($targetDate) {
                            $dates[] = $targetDate;
                            $current = $targetDate->copy();
                        }
                        break;
                }
            }
        }

        return $dates;
    }

    protected function getNthWeekdayOfMonth(int $year, int $month, int $weekday, int $nth): ?Carbon
    {
        $firstOfMonth = Carbon::create($year, $month, 1);
        $lastOfMonth = $firstOfMonth->copy()->endOfMonth();

        if ($nth === -1) {
            $date = $lastOfMonth->copy();
            while ($date->dayOfWeekIso !== $weekday) {
                $date->subDay();
            }
            return $date;
        }

        $date = $firstOfMonth->copy();
        while ($date->dayOfWeekIso !== $weekday) {
            $date->addDay();
        }

        $date->addWeeks($nth - 1);

        if ($date->month !== $month) {
            return null;
        }

        return $date;
    }

    /**
     * Copy event relationships from parent to child
     */
    protected function copyEventRelationships(Event $parent, Event $child): void
    {
        if ($parent->artists()->exists()) {
            $child->artists()->sync($parent->artists()->pluck('id'));
        }
        if ($parent->eventTypes()->exists()) {
            $child->eventTypes()->sync($parent->eventTypes()->pluck('id'));
        }
        if ($parent->eventGenres()->exists()) {
            $child->eventGenres()->sync($parent->eventGenres()->pluck('id'));
        }
        if ($parent->tags()->exists()) {
            $child->tags()->sync($parent->tags()->pluck('id'));
        }
    }

    /**
     * Sync child events when parent is updated.
     * Now PRESERVES existing children instead of delete+recreate.
     */
    public function syncChildEvents(Event $parent): void
    {
        if ($parent->isChild()) {
            return;
        }

        $this->processEventScheduling($parent);
    }

    /**
     * Delete all child events when parent is deleted
     */
    public function deleteChildEvents(Event $parent): void
    {
        if ($parent->isChild()) {
            return;
        }

        $parent->children()->delete();
    }
}

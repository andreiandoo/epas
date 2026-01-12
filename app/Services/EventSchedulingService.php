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
     * Creates child events for multi-day and recurring modes
     */
    public function processEventScheduling(Event $event): void
    {
        // Only process parent events (not children)
        if ($event->isChild()) {
            return;
        }

        $durationMode = $event->duration_mode;

        match ($durationMode) {
            'multi_day' => $this->processMultiDayEvent($event),
            'recurring' => $this->processRecurringEvent($event),
            default => null, // single_day and range don't need child events
        };
    }

    /**
     * Process multi-day event - create child event for each slot
     */
    protected function processMultiDayEvent(Event $event): void
    {
        $multiSlots = $event->multi_slots ?? [];

        if (empty($multiSlots)) {
            return;
        }

        DB::transaction(function () use ($event, $multiSlots) {
            // Mark parent as template
            $event->update(['is_template' => true]);

            // Delete existing children to recreate
            $event->children()->delete();

            // Create child for each slot
            foreach ($multiSlots as $index => $slot) {
                $this->createChildEvent($event, [
                    'event_date' => $slot['date'] ?? null,
                    'start_time' => $slot['start_time'] ?? null,
                    'door_time' => $slot['door_time'] ?? null,
                    'end_time' => $slot['end_time'] ?? null,
                ], $index + 1);
            }

            Log::info("[EventScheduling] Created " . count($multiSlots) . " child events for multi-day event #{$event->id}");
        });
    }

    /**
     * Process recurring event - generate events based on recurrence pattern
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
            // Mark parent as template
            $event->update(['is_template' => true]);

            // Delete existing children to recreate
            $event->children()->delete();

            $dates = $this->generateRecurringDates(
                Carbon::parse($recurringStartDate),
                $recurringFrequency,
                $recurringCount,
                $event->recurring_week_of_month ?? null
            );

            foreach ($dates as $index => $date) {
                $this->createChildEvent($event, [
                    'event_date' => $date->format('Y-m-d'),
                    'start_time' => $event->recurring_start_time,
                    'door_time' => $event->recurring_door_time,
                    'end_time' => $event->recurring_end_time,
                ], $index + 1);
            }

            Log::info("[EventScheduling] Created " . count($dates) . " child events for recurring event #{$event->id}");
        });
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
                        // Get the Nth weekday of next month
                        $weekday = $startDate->dayOfWeekIso; // 1=Monday, 7=Sunday
                        $current->addMonth();

                        // Find the Nth occurrence of this weekday in the month
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

    /**
     * Get the Nth occurrence of a weekday in a month
     *
     * @param int $year
     * @param int $month
     * @param int $weekday 1=Monday, 7=Sunday
     * @param int $nth 1=First, 2=Second, -1=Last
     * @return Carbon|null
     */
    protected function getNthWeekdayOfMonth(int $year, int $month, int $weekday, int $nth): ?Carbon
    {
        $firstOfMonth = Carbon::create($year, $month, 1);
        $lastOfMonth = $firstOfMonth->copy()->endOfMonth();

        if ($nth === -1) {
            // Last occurrence of the weekday
            $date = $lastOfMonth->copy();
            while ($date->dayOfWeekIso !== $weekday) {
                $date->subDay();
            }
            return $date;
        }

        // Find first occurrence
        $date = $firstOfMonth->copy();
        while ($date->dayOfWeekIso !== $weekday) {
            $date->addDay();
        }

        // Add weeks to get to Nth occurrence
        $date->addWeeks($nth - 1);

        // Make sure it's still in the same month
        if ($date->month !== $month) {
            return null;
        }

        return $date;
    }

    /**
     * Create a child event from parent
     */
    protected function createChildEvent(Event $parent, array $dateData, int $occurrenceNumber): Event
    {
        // Copy parent data
        $childData = $parent->replicate()->toArray();

        // Remove fields that shouldn't be copied
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

        // Set child-specific data
        $childData['parent_id'] = $parent->id;
        $childData['is_template'] = false;
        $childData['occurrence_number'] = $occurrenceNumber;
        $childData['duration_mode'] = 'single_day';

        // Set the specific date for this occurrence
        $childData['event_date'] = $dateData['event_date'];
        $childData['start_time'] = $dateData['start_time'];
        $childData['door_time'] = $dateData['door_time'];
        $childData['end_time'] = $dateData['end_time'];

        // Generate unique slug: parent-slug-{id} will be set after create
        // For now use temporary slug
        $childData['slug'] = $parent->slug . '-' . $occurrenceNumber . '-' . Str::random(6);

        // Create the child event
        $child = Event::create($childData);

        // Update slug with actual ID
        $child->update(['slug' => $parent->slug . '-' . $child->id]);

        // Copy relationships
        $this->copyEventRelationships($parent, $child);

        return $child;
    }

    /**
     * Copy event relationships from parent to child
     */
    protected function copyEventRelationships(Event $parent, Event $child): void
    {
        // Copy artists
        if ($parent->artists()->exists()) {
            $child->artists()->sync($parent->artists()->pluck('id'));
        }

        // Copy event types
        if ($parent->eventTypes()->exists()) {
            $child->eventTypes()->sync($parent->eventTypes()->pluck('id'));
        }

        // Copy event genres
        if ($parent->eventGenres()->exists()) {
            $child->eventGenres()->sync($parent->eventGenres()->pluck('id'));
        }

        // Copy tags
        if ($parent->tags()->exists()) {
            $child->tags()->sync($parent->tags()->pluck('id'));
        }

        // Note: Ticket types are NOT copied - they should be created separately for each child
        // This allows different pricing/availability per occurrence
    }

    /**
     * Sync child events when parent is updated
     */
    public function syncChildEvents(Event $parent): void
    {
        if ($parent->isChild()) {
            return;
        }

        // Re-process scheduling which will delete and recreate children
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

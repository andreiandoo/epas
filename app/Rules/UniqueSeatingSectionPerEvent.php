<?php

namespace App\Rules;

use App\Models\Seating\SeatingSection;
use App\Models\TicketType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueSeatingSectionPerEvent implements ValidationRule
{
    public function __construct(
        protected ?int $eventId = null,
        protected ?int $currentTicketTypeId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || !is_array($value)) {
            return;
        }

        if (!$this->eventId) {
            return;
        }

        // Get all ticket types for this event except the current one
        $existingTicketTypes = TicketType::where('event_id', $this->eventId)
            ->when($this->currentTicketTypeId, fn($q) => $q->where('id', '!=', $this->currentTicketTypeId))
            ->with('seatingSections')
            ->get();

        // Get already assigned section IDs
        $assignedSectionIds = $existingTicketTypes
            ->flatMap(fn($tt) => $tt->seatingSections->pluck('id'))
            ->unique()
            ->toArray();

        // Check for overlapping sections
        $overlappingSections = array_intersect($value, $assignedSectionIds);

        if (!empty($overlappingSections)) {
            $sectionNames = SeatingSection::whereIn('id', $overlappingSections)
                ->pluck('name')
                ->join(', ');

            $fail("The following sections are already assigned to another ticket type: {$sectionNames}");
        }
    }
}

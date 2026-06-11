<?php

namespace App\Services\Leisure;

use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\ResourceRental;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Start / end rental sessions, compute overtime surcharge.
 *
 * Surcharge formula (when leisure_is_overtime_chargeable on the TicketType):
 *   intervals_exceeded = ceil(overtime_minutes / overtime_interval_minutes)
 *   surcharge          = intervals_exceeded * overtime_surcharge_cents
 *
 * Both start and end are transactional with row-locking to prevent two
 * operators starting the same resource concurrently.
 */
class RentalService
{
    /**
     * Begin a rental: validate ticket + resource, lock resource row, create
     * ResourceRental record. Returns the created rental.
     *
     * @throws RuntimeException on invalid state
     */
    public function start(
        Ticket $ticket,
        PhysicalResource $resource,
        ?int $teamMemberUserId = null,
        ?int $explicitDurationMinutes = null,
    ): ResourceRental {
        if ($ticket->status !== null && $ticket->status !== 'valid' && $ticket->status !== 'active') {
            throw new RuntimeException("Ticket is not in valid state (status={$ticket->status}).");
        }

        $ticketType = $ticket->ticketType;
        if (! $ticketType) {
            throw new RuntimeException('Ticket has no associated TicketType.');
        }

        if (! $resource->isAllowedForTicketType($ticketType->id)) {
            throw new RuntimeException('Resource is not allowed for this ticket type.');
        }

        $duration = $explicitDurationMinutes
            ?? $ticketType->getDefaultDurationMinutes()
            ?? 60;

        return DB::transaction(function () use ($ticket, $resource, $teamMemberUserId, $duration) {
            $locked = PhysicalResource::query()->lockForUpdate()->find($resource->id);
            if (! $locked) {
                throw new RuntimeException('Resource disappeared.');
            }
            if ($locked->status !== PhysicalResource::STATUS_AVAILABLE) {
                throw new RuntimeException("Resource is not available (status={$locked->status}).");
            }
            if ($locked->tenant_id !== $resource->tenant_id) {
                throw new RuntimeException('Resource tenant mismatch.');
            }

            $now = CarbonImmutable::now();
            $rental = ResourceRental::create([
                'tenant_id' => $resource->tenant_id,
                'ticket_id' => $ticket->id,
                'physical_resource_id' => $resource->id,
                'started_by_user_id' => $teamMemberUserId,
                'started_at' => $now,
                'planned_end_at' => $now->addMinutes($duration),
            ]);

            $locked->update(['status' => PhysicalResource::STATUS_IN_USE]);

            return $rental->fresh();
        });
    }

    /**
     * End a rental, compute overtime & surcharge, release the resource.
     */
    public function end(ResourceRental $rental, ?int $teamMemberUserId = null): ResourceRental
    {
        if ($rental->ended_at !== null) {
            throw new RuntimeException('Rental is already ended.');
        }

        return DB::transaction(function () use ($rental, $teamMemberUserId) {
            $locked = ResourceRental::query()->lockForUpdate()->findOrFail($rental->id);
            if ($locked->ended_at !== null) {
                throw new RuntimeException('Rental was ended concurrently.');
            }

            $now = CarbonImmutable::now();
            $overtime = $this->computeOvertimeMinutes($locked->planned_end_at, $now);
            $surcharge = $this->computeSurchargeCents(
                $locked->ticket?->ticketType,
                $overtime,
            );

            $locked->update([
                'ended_at' => $now,
                'ended_by_user_id' => $teamMemberUserId,
                'overtime_minutes' => $overtime,
                'overtime_surcharge_cents' => $surcharge,
            ]);

            $resource = PhysicalResource::query()->lockForUpdate()->find($locked->physical_resource_id);
            if ($resource && $resource->status === PhysicalResource::STATUS_IN_USE) {
                $resource->update(['status' => PhysicalResource::STATUS_AVAILABLE]);
            }

            return $locked->fresh();
        });
    }

    /**
     * Compute overtime in whole minutes. Returns 0 if not overdue.
     */
    public function computeOvertimeMinutes($plannedEndAt, $endedAt): int
    {
        if (! $plannedEndAt || ! $endedAt) {
            return 0;
        }
        $planned = CarbonImmutable::parse($plannedEndAt);
        $ended = CarbonImmutable::parse($endedAt);
        if ($ended->lessThanOrEqualTo($planned)) {
            return 0;
        }
        return (int) $planned->diffInMinutes($ended);
    }

    /**
     * Compute surcharge in cents using ceil-intervals.
     *
     * Examples (interval=30, surcharge=500 cents):
     *   overtime=0  → 0
     *   overtime=1  → 500   (1 partial interval)
     *   overtime=30 → 500
     *   overtime=31 → 1000  (2 intervals)
     *   overtime=60 → 1000
     */
    public function computeSurchargeCents(?TicketType $ticketType, int $overtimeMinutes): int
    {
        if (! $ticketType || ! $ticketType->leisure_is_overtime_chargeable || $overtimeMinutes <= 0) {
            return 0;
        }
        $interval = (int) ($ticketType->leisure_overtime_interval_minutes ?? 30);
        $perInterval = (int) ($ticketType->leisure_overtime_surcharge_cents ?? 0);
        if ($interval <= 0 || $perInterval <= 0) {
            return 0;
        }
        $intervalsExceeded = (int) ceil($overtimeMinutes / $interval);
        return $intervalsExceeded * $perInterval;
    }
}

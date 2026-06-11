<?php

namespace App\Services\EventImport\DTOs;

class ImportResult
{
    public function __construct(
        public readonly int $eventId,
        public readonly int $totalTickets,
        public readonly int $totalOrders,
        public readonly int $customersCreated,
        public readonly int $customersEnriched,
        public readonly int $ticketTypesCreated,
        public readonly array $ticketTypesSummary, // [{name, count, price}]
        public readonly int $anonymousOrders,
        public readonly array $errors = [],
    ) {}

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'total_tickets' => $this->totalTickets,
            'total_orders' => $this->totalOrders,
            'customers_created' => $this->customersCreated,
            'customers_enriched' => $this->customersEnriched,
            'ticket_types_created' => $this->ticketTypesCreated,
            'ticket_types_summary' => $this->ticketTypesSummary,
            'anonymous_orders' => $this->anonymousOrders,
            'errors' => $this->errors,
        ];
    }
}

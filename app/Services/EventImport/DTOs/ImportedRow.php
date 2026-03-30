<?php

namespace App\Services\EventImport\DTOs;

class ImportedRow
{
    public function __construct(
        public readonly ?string $orderId,
        public readonly ?string $orderDate,
        public readonly ?string $clientName,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $ticketTypeName,
        public readonly ?string $seatLabel,
        public readonly ?float  $ticketPrice,
        public readonly ?string $currency,
        public readonly bool    $isInvitation,
        public readonly ?string $barcode,
        public readonly ?string $fiscalSeries,
        public readonly ?string $validated,
        public readonly ?string $orderStatus,
        public readonly ?string $ticketStatus,
    ) {}

    /**
     * Split clientName into [firstName, lastName].
     * "Ion Popescu" => ["Ion", "Popescu"]
     * "Ion" => ["Ion", null]
     * "" => [null, null]
     */
    public function splitName(): array
    {
        $name = trim($this->clientName ?? '');
        if ($name === '') {
            return [null, null];
        }

        $parts = explode(' ', $name, 2);
        return [
            $parts[0],
            $parts[1] ?? null,
        ];
    }
}

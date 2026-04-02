<?php

namespace App\Services\Cashless\Nfc;

class ChargeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $balanceBeforeCents,
        public readonly int $balanceAfterCents,
        public readonly ?string $errorMessage = null,
        public readonly ?string $chipTransactionRef = null,
    ) {}

    public static function success(int $before, int $after, ?string $chipRef = null): self
    {
        return new self(true, $before, $after, chipTransactionRef: $chipRef);
    }

    public static function failure(int $currentBalance, string $message): self
    {
        return new self(false, $currentBalance, $currentBalance, $message);
    }
}

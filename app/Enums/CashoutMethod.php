<?php

namespace App\Enums;

enum CashoutMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case CardRefund = 'card_refund';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank Transfer',
            self::Cash => 'Cash',
            self::CardRefund => 'Card Refund',
        };
    }
}

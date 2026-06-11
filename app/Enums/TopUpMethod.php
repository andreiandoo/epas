<?php

namespace App\Enums;

enum TopUpMethod: string
{
    case Card = 'card';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Voucher = 'voucher';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Card',
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Voucher => 'Voucher',
        };
    }
}

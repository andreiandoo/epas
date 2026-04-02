<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Refunded = 'refunded';
    case PartialRefund = 'partial_refund';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Refunded => 'Refunded',
            self::PartialRefund => 'Partial Refund',
            self::Voided => 'Voided',
        };
    }
}

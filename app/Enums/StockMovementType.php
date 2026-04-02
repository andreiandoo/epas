<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Delivery = 'delivery';
    case Allocation = 'allocation';
    case Sale = 'sale';
    case ReturnToSupplier = 'return_to_supplier';
    case ReturnToFestival = 'return_to_festival';
    case Waste = 'waste';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Allocation => 'Allocation',
            self::Sale => 'Sale',
            self::ReturnToSupplier => 'Return to Supplier',
            self::ReturnToFestival => 'Return to Festival',
            self::Waste => 'Waste',
            self::Correction => 'Correction',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [self::Delivery, self::ReturnToFestival, self::Correction]);
    }

    public function isOutbound(): bool
    {
        return in_array($this, [self::Allocation, self::Sale, self::ReturnToSupplier, self::Waste]);
    }
}

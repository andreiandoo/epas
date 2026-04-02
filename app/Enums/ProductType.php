<?php

namespace App\Enums;

enum ProductType: string
{
    case Food = 'food';
    case Drink = 'drink';
    case Alcohol = 'alcohol';
    case Tobacco = 'tobacco';
    case Merch = 'merch';
    case Service = 'service';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food',
            self::Drink => 'Drink',
            self::Alcohol => 'Alcohol',
            self::Tobacco => 'Tobacco',
            self::Merch => 'Merchandise',
            self::Service => 'Service',
            self::Other => 'Other',
        };
    }

    public function isAgeRestricted(): bool
    {
        return $this === self::Alcohol || $this === self::Tobacco;
    }
}

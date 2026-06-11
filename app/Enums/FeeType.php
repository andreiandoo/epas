<?php

namespace App\Enums;

enum FeeType: string
{
    case FixedDaily = 'fixed_daily';
    case FixedPeriod = 'fixed_period';
    case PercentageSales = 'percentage_sales';
    case FixedPerTransaction = 'fixed_per_transaction';
    case PercentagePerCategory = 'percentage_per_category';

    public function label(): string
    {
        return match ($this) {
            self::FixedDaily => 'Fixed Daily',
            self::FixedPeriod => 'Fixed Period',
            self::PercentageSales => 'Percentage of Sales',
            self::FixedPerTransaction => 'Fixed per Transaction',
            self::PercentagePerCategory => 'Percentage per Category',
        };
    }
}

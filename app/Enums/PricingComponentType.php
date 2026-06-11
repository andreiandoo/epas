<?php

namespace App\Enums;

enum PricingComponentType: string
{
    case BasePrice = 'base_price';
    case MarkupFixed = 'markup_fixed';
    case MarkupPercentage = 'markup_percentage';
    case Vat = 'vat';
    case Sgr = 'sgr';
    case EcoTax = 'eco_tax';
    case ServiceFee = 'service_fee';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::BasePrice => 'Base Price',
            self::MarkupFixed => 'Fixed Markup',
            self::MarkupPercentage => 'Percentage Markup',
            self::Vat => 'VAT',
            self::Sgr => 'SGR (Recycling Tax)',
            self::EcoTax => 'Eco Tax',
            self::ServiceFee => 'Service Fee',
            self::Custom => 'Custom',
        };
    }
}

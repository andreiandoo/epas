<?php

namespace App\Enums;

enum TenantType: string
{
    case TenantArtist = 'tenant-artist'; // Base tenant type
    case Artist = 'artist';
    case Agency = 'agency';
    case Venue = 'venue';
    case Speaker = 'speaker';
    case Competition = 'competition';
    case StadiumArena = 'stadium-arena';
    case Philharmonic = 'philharmonic';
    case Opera = 'opera';
    case Theater = 'theater';
    case Museum = 'museum';
    case Festival = 'festival';
    case Leisure = 'leisure';

    public function label(): string
    {
        return match ($this) {
            self::TenantArtist => 'Tenant',
            self::Artist => 'Artist / Band',
            self::Agency => 'Agency',
            self::Venue => 'Venue',
            self::Speaker => 'Speaker',
            self::Competition => 'Competition',
            self::StadiumArena => 'Stadium / Arena',
            self::Philharmonic => 'Philharmonic',
            self::Opera => 'Opera',
            self::Theater => 'Theater',
            self::Museum => 'Museum',
            self::Festival => 'Festival',
            self::Leisure => 'Leisure Venue',
        };
    }

    /**
     * Microservice slugs recommended by default for this tenant type.
     */
    public function defaultMicroserviceSlugs(): array
    {
        return match ($this) {
            self::TenantArtist => [
                'analytics',
                'crm',
                'shop',
                'affiliate-tracking',
            ],
            self::Artist => [
                'analytics',
                'crm',
                'shop',
                'affiliate-tracking',
            ],
            self::Agency => [
                'analytics',
                'crm',
                'efactura',
                'accounting',
            ],
            self::Venue, self::StadiumArena => [
                'analytics',
                'crm',
                'door-sales',
                'ticket-customizer',
            ],
            self::Speaker => [
                'analytics',
                'crm',
            ],
            self::Competition => [
                'analytics',
                'crm',
                'shop',
            ],
            self::Philharmonic, self::Opera, self::Theater => [
                'analytics',
                'crm',
                'door-sales',
                'ticket-customizer',
                'efactura',
            ],
            self::Museum => [
                'analytics',
                'crm',
                'door-sales',
                'ticket-customizer',
            ],
            self::Festival => [
                'analytics',
                'crm',
                'shop',
                'door-sales',
                'affiliate-tracking',
                'efactura',
            ],
            self::Leisure => [
                'analytics',
                'crm',
                'door-sales',
                'efactura',
                'accounting',
                'leisure-core',
                'leisure-pos',
                'leisure-rentals',
                'leisure-multi-society',
                'leisure-embed',
            ],
        };
    }

    /**
     * Default feature flags applied to tenants of this type on creation.
     * Stored in tenants.features JSON column.
     */
    public function defaultFeatures(): array
    {
        return match ($this) {
            self::Leisure => [
                'leisure' => [
                    'enabled' => true,
                    'rentals' => ['enabled' => true],
                    'pos' => ['enabled' => true],
                    'time_slots' => ['enabled' => false],
                    'physical_inventory' => ['enabled' => true],
                    'multi_society' => ['enabled' => false],
                    'channel_pricing' => ['enabled' => false],
                    'embed' => ['enabled' => false],
                    'crm' => ['enabled' => true],
                ],
            ],
            default => [],
        };
    }
}

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
        };
    }
}

<?php

namespace App\Enums;

enum TenantType: string
{
    case TenantArtist = 'tenant-artist'; // Base tenant type (renamed from "Artist / Band")
    case Artist = 'artist';
    case Agency = 'agency';
    case Venue = 'venue';
    case Theater = 'theater';
    case Festival = 'festival';

    public function label(): string
    {
        return match ($this) {
            self::TenantArtist => 'Tenant',
            self::Artist => 'Artist / Band',
            self::Agency => 'Agency',
            self::Venue => 'Venue',
            self::Theater => 'Theater / Opera / Philharmonic',
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
            self::Venue => [
                'analytics',
                'crm',
                'door-sales',
                'ticket-customizer',
            ],
            self::Theater => [
                'analytics',
                'crm',
                'door-sales',
                'ticket-customizer',
                'efactura',
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

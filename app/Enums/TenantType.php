<?php

namespace App\Enums;

enum TenantType: string
{
    case TenantArtist = 'tenant-artist';
    case Agency = 'agency';
    case Theater = 'theater';
    case Festival = 'festival';

    public function label(): string
    {
        return match ($this) {
            self::TenantArtist => 'Artist / Band',
            self::Agency => 'Agency',
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
            self::Agency => [
                'analytics',
                'crm',
                'efactura',
                'accounting',
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

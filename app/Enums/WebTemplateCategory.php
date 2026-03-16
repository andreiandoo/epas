<?php

namespace App\Enums;

enum WebTemplateCategory: string
{
    case SimpleOrganizer = 'simple-organizer';
    case Marketplace = 'marketplace';
    case ArtistAgency = 'artist-agency';
    case Theater = 'theater';
    case Festival = 'festival';
    case Stadium = 'stadium';

    public function label(): string
    {
        return match ($this) {
            self::SimpleOrganizer => 'Organizator Simplu',
            self::Marketplace => 'Marketplace',
            self::ArtistAgency => 'Agenție Artiști',
            self::Theater => 'Teatru / Operă / Filarmonică',
            self::Festival => 'Festival',
            self::Stadium => 'Stadion / Arenă',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SimpleOrganizer => 'heroicon-o-ticket',
            self::Marketplace => 'heroicon-o-shopping-bag',
            self::ArtistAgency => 'heroicon-o-musical-note',
            self::Theater => 'heroicon-o-building-library',
            self::Festival => 'heroicon-o-fire',
            self::Stadium => 'heroicon-o-building-office-2',
        };
    }

    public function compatibleTenantTypes(): array
    {
        return match ($this) {
            self::SimpleOrganizer => [TenantType::TenantArtist],
            self::Marketplace => [],
            self::ArtistAgency => [TenantType::Agency],
            self::Theater => [TenantType::Theater],
            self::Festival => [TenantType::Festival],
            self::Stadium => [TenantType::Festival],
        };
    }
}

<?php

namespace App\Enums;

enum TheaterSubtype: string
{
    case Theater = 'theater';
    case Opera = 'opera';
    case Philharmonic = 'philharmonic';

    public function label(): string
    {
        return match ($this) {
            self::Theater => 'Theater',
            self::Opera => 'Opera',
            self::Philharmonic => 'Philharmonic',
        };
    }

    /**
     * Default artist roles available for this theater subtype.
     */
    public function defaultArtistRoles(): array
    {
        return match ($this) {
            self::Theater => ['actor', 'director', 'set_designer', 'costume_designer', 'lighting_designer', 'stage_manager'],
            self::Opera => ['soloist', 'conductor', 'director', 'chorus_member', 'ballet_dancer', 'choreographer', 'set_designer'],
            self::Philharmonic => ['musician', 'conductor', 'soloist', 'concertmaster', 'section_leader'],
        };
    }
}

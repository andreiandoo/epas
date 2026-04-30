<?php

namespace App\Filament\Marketplace\Resources\ArtistAccountResource\Pages;

use App\Filament\Marketplace\Resources\ArtistAccountResource;
use Filament\Resources\Pages\ViewRecord;

class ViewArtistAccount extends ViewRecord
{
    protected static string $resource = ArtistAccountResource::class;

    /**
     * The lifecycle actions live in the table row, not on the View page —
     * keeps the source of truth in one place. We intentionally don't add
     * header actions here; admins approve/reject from the list view.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

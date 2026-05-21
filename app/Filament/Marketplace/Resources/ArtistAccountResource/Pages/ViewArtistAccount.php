<?php

namespace App\Filament\Marketplace\Resources\ArtistAccountResource\Pages;

use App\Filament\Marketplace\Resources\ArtistAccountResource;
use Filament\Resources\Pages\ViewRecord;

class ViewArtistAccount extends ViewRecord
{
    protected static string $resource = ArtistAccountResource::class;

    /**
     * Expose the same lifecycle actions (Aprobă / Respinge / Suspendă /
     * Reactivează) as the table row. Each action's own ->visible() closure
     * gates by status, so the header only shows what makes sense for the
     * current record.
     */
    protected function getHeaderActions(): array
    {
        return ArtistAccountResource::getRecordActionsForPage();
    }
}

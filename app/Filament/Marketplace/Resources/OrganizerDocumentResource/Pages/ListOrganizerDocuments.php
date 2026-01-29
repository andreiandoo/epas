<?php

namespace App\Filament\Marketplace\Resources\OrganizerDocumentResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganizerDocuments extends ListRecords
{
    protected static string $resource = OrganizerDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

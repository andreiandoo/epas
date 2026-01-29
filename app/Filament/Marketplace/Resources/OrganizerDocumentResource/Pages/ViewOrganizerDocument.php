<?php

namespace App\Filament\Marketplace\Resources\OrganizerDocumentResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerDocumentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewOrganizerDocument extends ViewRecord
{
    protected static string $resource = OrganizerDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => $this->record->download_url)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->file_path),
        ];
    }
}

<?php
namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Exports\VenueExporter;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add venue')
                ->icon('heroicon-m-plus'),

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => VenueResource::getUrl('import')),

            ExportAction::make()
                ->exporter(VenueExporter::class)
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->chunkSize(500)
                ->columnMapping(false)
                ->formats([ExportFormat::Csv]),
        ];
    }
}

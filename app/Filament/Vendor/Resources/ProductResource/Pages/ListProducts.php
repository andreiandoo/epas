<?php

namespace App\Filament\Vendor\Resources\ProductResource\Pages;

use App\Filament\Vendor\Resources\ProductResource;
use App\Services\Cashless\ProductImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('import_csv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $employee = Auth::guard('vendor_employee')->user();

                    // Get the active edition
                    $edition = $employee->vendor->editions()
                        ->whereHas('edition', fn ($q) => $q->where('status', 'active'))
                        ->first();

                    if (! $edition) {
                        Notification::make()
                            ->title('No active edition')
                            ->danger()
                            ->send();

                        return;
                    }

                    $filePath = storage_path('app/public/' . $data['csv_file']);

                    try {
                        $result = app(ProductImportService::class)->importFromCsv(
                            $filePath,
                            $employee->vendor_id,
                            $edition->festival_edition_id,
                        );

                        Notification::make()
                            ->title("Import complete: {$result['created']} created, {$result['updated']} updated, {$result['errors']} errors")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Import failed: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

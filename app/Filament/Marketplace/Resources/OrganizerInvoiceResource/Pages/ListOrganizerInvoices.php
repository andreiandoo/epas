<?php

namespace App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Services\InvoiceGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOrganizerInvoices extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = OrganizerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generează Facturi')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generează facturi')
                ->modalDescription('Se vor genera facturi pentru toți organizatorii care au comenzi finalizate. Facturile existente nu vor fi suprascrise.')
                ->action(function () {
                    $marketplace = static::getMarketplaceClient();
                    if (!$marketplace) {
                        Notification::make()->danger()->title('Marketplace nu a fost găsit.')->send();
                        return;
                    }

                    $service = new InvoiceGeneratorService();
                    $count = $service->generateForAllOrganizers($marketplace);

                    Notification::make()
                        ->success()
                        ->title("Facturi generate: {$count}")
                        ->body($count > 0 ? 'Facturile noi au fost create cu succes.' : 'Nu au fost găsite facturi noi de generat.')
                        ->send();
                }),
        ];
    }
}

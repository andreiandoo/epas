<?php

namespace App\Filament\Resources\MarketplaceOrganizerResource\Pages;

use App\Filament\Resources\MarketplaceOrganizerResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ListMarketplaceOrganizers extends ListRecords
{
    protected static string $resource = MarketplaceOrganizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                        ->required()
                        ->helperText(
                            'CSV columns: email (required), marketplace_client_id sau marketplace_client_name (required), ' .
                            'name, contact_name, phone, description, website, person_type (pj/pf), work_mode (exclusive/non_exclusive), ' .
                            'organizer_type (agency/promoter/venue/artist/ngo/other), company_name, company_tax_id, company_registration, ' .
                            'vat_payer (1/0), company_address, company_city, company_county, company_zip, past_contract, ' .
                            'representative_first_name, representative_last_name, guarantor_first_name, guarantor_last_name, ' .
                            'guarantor_cnp, guarantor_address, guarantor_city, guarantor_id_type (ci/bi), guarantor_id_series, ' .
                            'guarantor_id_number, guarantor_id_issued_by, guarantor_id_issued_date (YYYY-MM-DD), ' .
                            'city, state, bank_name, iban, commission_rate, fixed_commission_default, ticket_terms, status (active/pending/suspended/rejected). ' .
                            'Deduplicare dupa email — daca exista, se actualizeaza.'
                        )
                        ->disk('local')
                        ->directory('imports')
                        ->storeFileNamesIn('original_filename')
                        ->visibility('private'),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['file']);

                    if (!Storage::disk('local')->exists($data['file'])) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('File not found: ' . $data['file'])
                            ->danger()
                            ->send();
                        return;
                    }

                    Artisan::call('import:marketplace-organizers', [
                        'file' => $filePath,
                    ]);

                    $output = Artisan::output();

                    Notification::make()
                        ->title('Import completed')
                        ->body($output)
                        ->success()
                        ->send();

                    Storage::disk('local')->delete($data['file']);
                }),
        ];
    }
}

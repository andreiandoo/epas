<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ListOrganizers extends ListRecords
{
    protected static string $resource = OrganizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('download_template')
                ->label('Model CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $columns = [
                        'email', 'name', 'contact_name', 'phone', 'description', 'website',
                        'person_type', 'work_mode', 'organizer_type',
                        'company_name', 'company_tax_id', 'company_registration',
                        'vat_payer', 'company_address', 'company_city', 'company_county', 'company_zip',
                        'past_contract',
                        'representative_first_name', 'representative_last_name',
                        'guarantor_first_name', 'guarantor_last_name',
                        'guarantor_cnp', 'guarantor_address', 'guarantor_city',
                        'guarantor_id_type', 'guarantor_id_series', 'guarantor_id_number',
                        'guarantor_id_issued_by', 'guarantor_id_issued_date',
                        'city', 'state', 'bank_name', 'iban',
                        'commission_rate', 'fixed_commission_default', 'ticket_terms', 'status',
                    ];

                    $example = [
                        'organizator@exemplu.ro', 'Organizator SRL', 'Ion Popescu', '0721000000',
                        'Organizator de evenimente culturale', 'https://exemplu.ro',
                        'pj', 'exclusive', 'promoter',
                        'Organizator SRL', 'RO12345678', 'J40/1234/2020',
                        '1', 'Str. Exemplu 10', 'București', 'Ilfov', '010101',
                        '',
                        'Ion', 'Popescu',
                        '', '', '', '', '',
                        'ci', '', '', '', '',
                        'București', 'Ilfov', 'BCR', 'RO49AAAA1B31007593840000',
                        '5', '', 'Termeni și condiții bilete.', 'active',
                    ];

                    $csv = implode(',', $columns) . "\n";
                    $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $example)) . "\n";

                    return response()->streamDownload(
                        fn () => print($csv),
                        'model-import-organizatori.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8']
                    );
                }),

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Fișier CSV')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->visibility('private')
                        ->helperText(
                            'Coloane suportate: email (obligatoriu), name, contact_name, phone, description, website, ' .
                            'person_type (pj/pf), work_mode (exclusive/non_exclusive), ' .
                            'organizer_type (agency/promoter/venue/artist/ngo/other), ' .
                            'company_name, company_tax_id, company_registration, vat_payer (1/0), ' .
                            'company_address, company_city, company_county, company_zip, past_contract, ' .
                            'representative_first_name, representative_last_name, ' .
                            'guarantor_first_name, guarantor_last_name, guarantor_cnp, guarantor_address, guarantor_city, ' .
                            'guarantor_id_type (ci/bi), guarantor_id_series, guarantor_id_number, ' .
                            'guarantor_id_issued_by, guarantor_id_issued_date (YYYY-MM-DD), ' .
                            'city, state, bank_name, iban, commission_rate, fixed_commission_default, ' .
                            'ticket_terms, status (active/pending/suspended/rejected). ' .
                            'Deduplicare după email — dacă există, se actualizează.'
                        ),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['file']);

                    if (!Storage::disk('local')->exists($data['file'])) {
                        Notification::make()
                            ->title('Import eșuat')
                            ->body('Fișierul nu a fost găsit.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $marketplace = OrganizerResource::getMarketplaceClient();

                    Artisan::call('import:marketplace-organizers', [
                        'file'            => $filePath,
                        '--marketplace'   => $marketplace?->id,
                    ]);

                    $output = Artisan::output();

                    Storage::disk('local')->delete($data['file']);

                    Notification::make()
                        ->title('Import finalizat')
                        ->body($output)
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'suspended' => Tab::make('Suspended')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended')),
        ];
    }
}

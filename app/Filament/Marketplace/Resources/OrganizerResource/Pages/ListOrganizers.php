<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListOrganizers extends ListRecords
{
    protected static string $resource = OrganizerResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Organizatori <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => {
                    const toolbar = document.querySelector('.fi-ta-header-toolbar');
                    if (!toolbar) return;
                    const nav = \$el.querySelector('.fi-tabs');
                    if (!nav) return;
                    toolbar.prepend(nav);
                    nav.style.order = '-1';
                })",
            ]);
    }

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

                    $user = auth()->user();
                    $marketplace = ($user && method_exists($user, 'marketplaceClient'))
                        ? $user->marketplaceClient
                        : null;

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

            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalHeading('Export organizatori în CSV')
                ->modalDescription('Selectează câmpurile pe care vrei să le incluzi în fișierul CSV. Câmpurile bifate implicit sunt cele mai folosite.')
                ->modalSubmitActionLabel('Descarcă CSV')
                ->modalWidth('4xl')
                ->form([
                    Forms\Components\CheckboxList::make('fields')
                        ->label('Câmpuri de exportat')
                        ->options(collect(static::getExportableFields())->map(fn ($def) => $def['label'])->toArray())
                        ->default(static::getDefaultExportFields())
                        ->bulkToggleable()
                        ->columns(2)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $selectedKeys = $data['fields'] ?? [];
                    if (empty($selectedKeys)) {
                        Notification::make()->title('Selectează cel puțin un câmp')->warning()->send();
                        return;
                    }

                    $fieldMap = static::getExportableFields();

                    // Scope to the current marketplace so an admin never
                    // accidentally exports another marketplace's org list.
                    $user = auth()->user();
                    $marketplaceId = $user?->marketplace_client_id;
                    if (!$marketplaceId) {
                        Notification::make()->title('Marketplace necunoscut')->danger()->send();
                        return;
                    }

                    $rows = \App\Models\MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
                        ->orderBy('company_name')
                        ->get();

                    $filename = 'organizatori-' . now()->format('Ymd-His') . '.csv';

                    return response()->streamDownload(function () use ($rows, $selectedKeys, $fieldMap) {
                        // UTF-8 BOM so Excel opens diacritics correctly.
                        echo "\xEF\xBB\xBF";
                        $out = fopen('php://output', 'w');

                        $header = [];
                        foreach ($selectedKeys as $key) {
                            $header[] = $fieldMap[$key]['label'] ?? $key;
                        }
                        fputcsv($out, $header);

                        foreach ($rows as $o) {
                            $row = [];
                            foreach ($selectedKeys as $key) {
                                $extractor = $fieldMap[$key]['value'] ?? null;
                                $row[] = $extractor ? (string) ($extractor($o) ?? '') : '';
                            }
                            fputcsv($out, $row);
                        }

                        fclose($out);
                    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }

    /**
     * Central catalog of everything the Export CSV button can emit.
     * Keys must be stable — they end up in form state; labels are what
     * the admin sees in the modal and become CSV header row entries.
     * Add a new row here to expose a new field in the export UI.
     */
    protected static function getExportableFields(): array
    {
        return [
            'id' => ['label' => 'ID intern', 'value' => fn ($o) => $o->id],
            'name' => ['label' => 'Nume public', 'value' => fn ($o) => $o->name],
            'status' => ['label' => 'Status', 'value' => fn ($o) => $o->status],
            'created_at' => ['label' => 'Data înregistrării', 'value' => fn ($o) => $o->created_at?->format('Y-m-d')],
            'verified_at' => ['label' => 'Data verificării', 'value' => fn ($o) => $o->verified_at?->format('Y-m-d')],

            'email' => ['label' => 'Email', 'value' => fn ($o) => $o->email],
            'phone' => ['label' => 'Telefon', 'value' => fn ($o) => $o->phone],
            'contact_name' => ['label' => 'Persoană de contact', 'value' => fn ($o) => $o->contact_name],
            'website' => ['label' => 'Website', 'value' => fn ($o) => $o->website],

            'company_name' => ['label' => 'Denumire firmă', 'value' => fn ($o) => $o->company_name],
            'company_tax_id' => ['label' => 'CUI', 'value' => fn ($o) => $o->company_tax_id],
            'company_registration' => ['label' => 'Nr. reg. com.', 'value' => fn ($o) => $o->company_registration],
            'company_address' => ['label' => 'Adresă companie', 'value' => fn ($o) => $o->company_address],
            'company_city' => ['label' => 'Oraș / localitate', 'value' => fn ($o) => $o->company_city],
            'company_county' => ['label' => 'Județ', 'value' => fn ($o) => $o->company_county],
            'company_zip' => ['label' => 'Cod poștal', 'value' => fn ($o) => $o->company_zip],

            'vat_payer' => ['label' => 'Plătitor TVA', 'value' => fn ($o) => $o->vat_payer ? 'Da' : 'Nu'],
            'primary_vat_rate' => ['label' => 'Cotă TVA (%)', 'value' => fn ($o) => $o->primary_vat_rate],

            'bank_name' => ['label' => 'Banca', 'value' => fn ($o) => $o->bank_name],
            'iban' => ['label' => 'Cont IBAN', 'value' => fn ($o) => $o->iban],

            'commission_rate' => ['label' => 'Taxa ticketing procentuală (%)', 'value' => fn ($o) => $o->commission_rate],
            'fixed_commission_default' => ['label' => 'Taxa ticketing fixă (RON/bilet)', 'value' => fn ($o) => $o->fixed_commission_default],
            'default_commission_mode' => ['label' => 'Mod comision default', 'value' => fn ($o) => $o->default_commission_mode],

            'contract_number_series' => ['label' => 'Nr. contract', 'value' => fn ($o) => $o->contract_number_series],
            'contract_date' => ['label' => 'Data contract', 'value' => fn ($o) => $o->contract_date instanceof \Carbon\Carbon ? $o->contract_date->format('Y-m-d') : (string) ($o->contract_date ?? '')],
            'contract_signed_at' => ['label' => 'Data semnare contract', 'value' => fn ($o) => $o->contract_signed_at?->format('Y-m-d H:i')],
            'past_contract' => ['label' => 'Contract precedent', 'value' => fn ($o) => $o->past_contract],

            'person_type' => ['label' => 'Tip persoană (pj/pf)', 'value' => fn ($o) => $o->person_type],
            'work_mode' => ['label' => 'Mod colaborare', 'value' => fn ($o) => $o->work_mode],
            'organizer_type' => ['label' => 'Tip organizator', 'value' => fn ($o) => $o->organizer_type],

            'representative_first_name' => ['label' => 'Reprezentant — Prenume', 'value' => fn ($o) => $o->representative_first_name],
            'representative_last_name' => ['label' => 'Reprezentant — Nume', 'value' => fn ($o) => $o->representative_last_name],

            'guarantor_first_name' => ['label' => 'Garant — Prenume', 'value' => fn ($o) => $o->guarantor_first_name],
            'guarantor_last_name' => ['label' => 'Garant — Nume', 'value' => fn ($o) => $o->guarantor_last_name],
            'guarantor_cnp' => ['label' => 'Garant — CNP', 'value' => fn ($o) => $o->guarantor_cnp],
            'guarantor_address' => ['label' => 'Garant — Adresă', 'value' => fn ($o) => $o->guarantor_address],
            'guarantor_city' => ['label' => 'Garant — Oraș', 'value' => fn ($o) => $o->guarantor_city],

            'total_events' => ['label' => 'Nr. total evenimente', 'value' => fn ($o) => $o->total_events],
            'total_tickets_sold' => ['label' => 'Nr. total bilete vândute', 'value' => fn ($o) => $o->total_tickets_sold],
            'total_revenue' => ['label' => 'Venit total', 'value' => fn ($o) => $o->total_revenue],
            'available_balance' => ['label' => 'Sold disponibil', 'value' => fn ($o) => $o->available_balance],
            'pending_balance' => ['label' => 'Sold pending', 'value' => fn ($o) => $o->pending_balance],

            'link' => ['label' => 'Link admin (tab financiar)', 'value' => fn ($o) => 'https://core.tixello.com/marketplace/organizers/' . $o->id . '?tab=financiar'],
        ];
    }

    /**
     * Pre-checked set in the Export modal — matches the fields the ops
     * team asked to have exported by default. Everything else stays
     * unchecked but visible so it can be added ad-hoc.
     */
    protected static function getDefaultExportFields(): array
    {
        return [
            'company_name', 'company_tax_id', 'company_registration',
            'company_address', 'company_city', 'company_county',
            'iban', 'bank_name', 'email', 'phone',
            'commission_rate', 'contract_number_series',
            'vat_payer', 'primary_vat_rate', 'link',
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

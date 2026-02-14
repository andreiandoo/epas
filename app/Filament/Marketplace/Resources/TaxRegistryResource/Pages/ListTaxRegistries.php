<?php

namespace App\Filament\Marketplace\Resources\TaxRegistryResource\Pages;

use App\Filament\Marketplace\Resources\TaxRegistryResource;
use App\Models\MarketplaceTaxRegistry;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTaxRegistries extends ListRecords
{
    protected static string $resource = TaxRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTemplate')
                ->label('Descarcă Template CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    return response()->streamDownload(function () {
                        $header = ['name', 'subname', 'country', 'county', 'city', 'address', 'phone', 'email', 'cif', 'iban', 'is_active'];
                        $example = ['SC Exemplu SRL', 'Departament Vanzari', 'Romania', 'București', 'București', 'Str. Exemplu nr. 1', '0721000000', 'contact@exemplu.ro', 'RO12345678', 'RO49AAAA1B31007593840000', '1'];

                        $out = fopen('php://output', 'w');
                        // UTF-8 BOM for Excel compatibility
                        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                        fputcsv($out, $header);
                        fputcsv($out, $example);
                        fclose($out);
                    }, 'tax-registry-template.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Actions\Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->form([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('Fișier CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText('Coloane acceptate: name, subname, country, county, city, address, phone, email, cif, iban, is_active. Câmpul CIF este folosit ca identificator unic.'),

                    Forms\Components\Toggle::make('overwrite')
                        ->label('Suprascrie înregistrările existente (pe baza CIF)')
                        ->default(true)
                        ->helperText('Dacă este activ, intrările existente cu același CIF vor fi actualizate cu datele din CSV.'),
                ])
                ->action(function (array $data): void {
                    $this->importCsvData($data);
                }),

            Actions\CreateAction::make(),
        ];
    }

    protected function importCsvData(array $data): void
    {
        $marketplace = TaxRegistryResource::getMarketplaceClient();
        if (!$marketplace) {
            Notification::make()->title('Marketplace nu a fost găsit.')->danger()->send();
            return;
        }

        $filePath = storage_path('app/private/' . $data['csv_file']);
        if (!file_exists($filePath)) {
            // Try without 'private/' prefix
            $filePath = storage_path('app/' . $data['csv_file']);
        }

        if (!file_exists($filePath)) {
            Notification::make()->title('Fișierul CSV nu a fost găsit.')->danger()->send();
            return;
        }

        $overwrite = $data['overwrite'] ?? true;

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            Notification::make()->title('Nu s-a putut deschide fișierul CSV.')->danger()->send();
            return;
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            Notification::make()->title('Fișierul CSV este gol.')->danger()->send();
            return;
        }

        // Clean BOM from first column header
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $header = array_map('trim', array_map('strtolower', $header));

        $allowedColumns = ['name', 'subname', 'country', 'county', 'city', 'address', 'phone', 'email', 'cif', 'iban', 'is_active'];
        $headerMap = [];
        foreach ($header as $index => $col) {
            if (in_array($col, $allowedColumns)) {
                $headerMap[$col] = $index;
            }
        }

        if (!isset($headerMap['cif'])) {
            fclose($handle);
            Notification::make()
                ->title('Coloana CIF lipsește din fișierul CSV.')
                ->body('Coloanele găsite: ' . implode(', ', $header))
                ->danger()
                ->send();
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $getValue = fn (string $col) => isset($headerMap[$col]) ? trim($row[$headerMap[$col]] ?? '') : '';

            $cif = $getValue('cif');
            if (empty($cif)) {
                $skipped++;
                continue;
            }

            $rowData = [
                'marketplace_client_id' => $marketplace->id,
                'name' => $getValue('name') ?: null,
                'subname' => $getValue('subname') ?: null,
                'country' => $getValue('country') ?: 'Romania',
                'county' => $getValue('county') ?: null,
                'city' => $getValue('city') ?: null,
                'address' => $getValue('address') ?: null,
                'phone' => $getValue('phone') ?: null,
                'email' => $getValue('email') ?: null,
                'cif' => $cif,
                'iban' => $getValue('iban') ?: null,
                'is_active' => in_array(strtolower($getValue('is_active')), ['1', 'true', 'yes', 'da'], true),
            ];

            // Check if entry with this CIF exists for this marketplace
            $existing = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                ->where('cif', $cif)
                ->first();

            if ($existing) {
                if ($overwrite) {
                    $existing->update($rowData);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                MarketplaceTaxRegistry::create($rowData);
                $created++;
            }
        }

        fclose($handle);

        // Clean up the uploaded file
        @unlink($filePath);

        Notification::make()
            ->title('Import finalizat')
            ->body("Create: {$created} | Actualizate: {$updated} | Ignorate: {$skipped}")
            ->success()
            ->send();

        Log::channel('marketplace')->info('Tax Registry CSV import completed', [
            'marketplace_id' => $marketplace->id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}

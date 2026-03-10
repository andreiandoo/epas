<?php

namespace App\Filament\Tenant\Resources\FestivalEditionResource\Pages;

use App\Filament\Tenant\Resources\FestivalEditionResource;
use App\Models\FestivalExternalTicket;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

class ImportFestivalExternalTickets extends Page
{
    use WithFileUploads;
    protected static string $resource = FestivalEditionResource::class;
    protected string $view = 'filament.tenant.resources.festival-edition-resource.pages.import-festival-external-tickets';
    protected static ?string $title = 'Import Bilete Externe';

    public $record;

    // Form state
    public ?string $csv_file = null;
    public ?string $col_barcode = null;
    public ?string $col_first_name = null;
    public ?string $col_last_name = null;
    public ?string $col_email = null;
    public ?string $col_ticket_type = null;
    public ?string $col_original_id = null;
    public ?string $source_name = null;

    public array $csvHeaders = [];
    public array $csvPreview = [];
    public int $csvTotalRows = 0;
    public ?string $importResult = null;

    public function mount(int|string $record): void
    {
        $this->record = \App\Models\FestivalEdition::findOrFail($record);
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Import Bilete Externe — ' . ($this->record->name ?: 'Editie');
    }

    public function getBreadcrumbs(): array
    {
        return [
            FestivalEditionResource::getUrl() => 'Editii Festival',
            FestivalEditionResource::getUrl('edit', ['record' => $this->record]) => $this->record->name ?: 'Editie',
            '' => 'Import Bilete Externe',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Înapoi la Editie')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => FestivalEditionResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $header = ['cod_bilet', 'prenume', 'nume', 'email', 'tip_bilet', 'id'];
            $sample = [
                ['ABC123456', 'Ion', 'Popescu', 'ion@exemplu.ro', 'VIP', '1001'],
                ['DEF789012', 'Maria', 'Ionescu', 'maria@exemplu.ro', 'General Admission', '1002'],
                ['GHI345678', 'Andrei', 'Dumitrescu', 'andrei@exemplu.ro', 'VIP', '1003'],
            ];

            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $header);
            foreach ($sample as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'model-import-bilete-festival.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function uploadCsv(): void
    {
        if (! $this->csv_file) {
            Notification::make()->title('Selectează un fișier CSV')->danger()->send();
            return;
        }

        $path = storage_path('app/public/' . $this->csv_file);
        if (! file_exists($path)) {
            Notification::make()->title('Fișierul nu a fost găsit')->danger()->send();
            return;
        }

        $separator = $this->detectSeparator($path);
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, $separator);
        $header = array_map('trim', $header);
        $this->csvHeaders = $header;

        // Auto-detect column mappings
        $this->col_barcode = $this->autoDetect($header, ['barcode', 'cod_bilet', 'ticket_code', 'code', 'cod', 'qr', 'qr_code']);
        $this->col_first_name = $this->autoDetect($header, ['prenume', 'first_name', 'firstname', 'prename']);
        $this->col_last_name = $this->autoDetect($header, ['nume', 'last_name', 'lastname', 'surname', 'name', 'nume_familie']);
        $this->col_email = $this->autoDetect($header, ['email', 'e-mail', 'email_address']);
        $this->col_ticket_type = $this->autoDetect($header, ['tip_bilet', 'ticket_type', 'type', 'tip', 'categorie', 'category']);
        $this->col_original_id = $this->autoDetect($header, ['id', 'external_id', 'original_id', 'ticket_id', 'id_bilet']);

        // Read preview rows + count total
        $preview = [];
        $count = 0;
        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $count++;
            if (count($preview) < 5) {
                $preview[] = array_combine($header, array_pad($row, count($header), ''));
            }
        }
        fclose($handle);

        $this->csvPreview = $preview;
        $this->csvTotalRows = $count;
        $this->importResult = null;
    }

    public function runImport(): void
    {
        if (! $this->col_barcode) {
            Notification::make()->title('Selectează coloana pentru codul biletului (barcode)')->danger()->send();
            return;
        }

        $path = storage_path('app/public/' . $this->csv_file);
        if (! file_exists($path)) {
            Notification::make()->title('Fișierul CSV nu mai există')->danger()->send();
            return;
        }

        $tenant = auth()->user()->tenant;
        $batchId = (string) Str::ulid();
        $separator = $this->detectSeparator($path);

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, $separator);
        $header = array_map('trim', $header);

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $data = array_combine($header, array_pad($row, count($header), ''));

            $barcode = trim($data[$this->col_barcode] ?? '');
            if (empty($barcode)) {
                $errors++;
                continue;
            }

            $exists = FestivalExternalTicket::where('festival_edition_id', $this->record->id)
                ->where('barcode', $barcode)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Build meta from unmapped columns
            $mappedCols = array_filter([
                $this->col_barcode, $this->col_first_name, $this->col_last_name,
                $this->col_email, $this->col_ticket_type, $this->col_original_id,
            ]);
            $meta = [];
            foreach ($data as $key => $value) {
                if (! in_array($key, $mappedCols) && ! empty($value)) {
                    $meta[$key] = $value;
                }
            }

            FestivalExternalTicket::create([
                'tenant_id'           => $tenant->id,
                'festival_edition_id' => $this->record->id,
                'import_batch_id'     => $batchId,
                'source_name'         => $this->source_name ?: null,
                'barcode'             => $barcode,
                'attendee_first_name' => $this->col_first_name ? trim($data[$this->col_first_name] ?? '') : null,
                'attendee_last_name'  => $this->col_last_name ? trim($data[$this->col_last_name] ?? '') : null,
                'attendee_email'      => $this->col_email ? trim($data[$this->col_email] ?? '') : null,
                'ticket_type_name'    => $this->col_ticket_type ? trim($data[$this->col_ticket_type] ?? '') : null,
                'original_id'         => $this->col_original_id ? trim($data[$this->col_original_id] ?? '') : null,
                'meta'                => ! empty($meta) ? $meta : null,
            ]);

            $imported++;
        }

        fclose($handle);
        @unlink($path);

        $this->importResult = "Import finalizat: {$imported} importate, {$skipped} duplicate ignorate, {$errors} erori.";
        $this->csvHeaders = [];
        $this->csvPreview = [];

        Notification::make()
            ->title("Import finalizat")
            ->body("{$imported} bilete importate" . ($skipped ? ", {$skipped} duplicate ignorate" : ''))
            ->success()
            ->send();
    }

    protected function autoDetect(array $headers, array $candidates): ?string
    {
        foreach ($headers as $header) {
            $normalized = Str::lower(Str::ascii(trim($header)));
            foreach ($candidates as $candidate) {
                if ($normalized === $candidate || $normalized === str_replace('_', ' ', $candidate)) {
                    return $header;
                }
            }
        }
        return null;
    }

    protected function detectSeparator(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = fgets($handle);
        fclose($handle);

        $commaCount = substr_count($firstLine, ',');
        $semiCount = substr_count($firstLine, ';');

        return $semiCount > $commaCount ? ';' : ',';
    }
}

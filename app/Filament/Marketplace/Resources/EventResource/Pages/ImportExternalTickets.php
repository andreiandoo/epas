<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Models\ExternalTicket;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class ImportExternalTickets extends Page
{
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;
    protected string $view = 'filament.marketplace.resources.event-resource.pages.import-external-tickets';
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
        $this->record = \App\Models\Event::findOrFail($record);
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Import Bilete Externe — ' . ($this->record->name ?: 'Event');
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventResource::getUrl() => 'Evenimente',
            EventResource::getUrl('edit', ['record' => $this->record]) => $this->record->name ?: 'Event',
            '' => 'Import Bilete Externe',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Înapoi la Eveniment')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $header = ['cod_bilet', 'prenume', 'nume', 'email', 'tip_bilet', 'id'];
            $sample = [
                ['ABC123456', 'Ion', 'Popescu', 'ion@exemplu.ro', 'VIP', '1001'],
                ['DEF789012', 'Maria', 'Ionescu', 'maria@exemplu.ro', 'General', '1002'],
                ['GHI345678', 'Andrei', 'Dumitrescu', 'andrei@exemplu.ro', 'VIP', '1003'],
            ];

            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $header);
            foreach ($sample as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'model-import-bilete.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function uploadCsv(): void
    {
        if (!$this->csv_file) {
            Notification::make()->title('Selectează un fișier CSV')->danger()->send();
            return;
        }

        $path = storage_path('app/public/' . $this->csv_file);
        if (!file_exists($path)) {
            Notification::make()->title('Fișierul nu a fost găsit')->danger()->send();
            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ',');

        // Try semicolon separator if only 1 column detected
        if (count($header) <= 1) {
            rewind($handle);
            $header = fgetcsv($handle, 0, ';');
        }

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
        while (($row = fgetcsv($handle, 0, count($header) > 1 ? ',' : ';')) !== false) {
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
        if (!$this->col_barcode) {
            Notification::make()->title('Selectează coloana pentru codul biletului (barcode)')->danger()->send();
            return;
        }

        $path = storage_path('app/public/' . $this->csv_file);
        if (!file_exists($path)) {
            Notification::make()->title('Fișierul CSV nu mai există')->danger()->send();
            return;
        }

        $marketplace = static::getMarketplaceClient();
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

            // Skip if barcode already exists for this event
            $exists = ExternalTicket::where('event_id', $this->record->id)
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
                if (!in_array($key, $mappedCols) && !empty($value)) {
                    $meta[$key] = $value;
                }
            }

            ExternalTicket::create([
                'event_id' => $this->record->id,
                'marketplace_client_id' => $marketplace->id,
                'import_batch_id' => $batchId,
                'source_name' => $this->source_name ?: null,
                'barcode' => $barcode,
                'attendee_first_name' => $this->col_first_name ? trim($data[$this->col_first_name] ?? '') : null,
                'attendee_last_name' => $this->col_last_name ? trim($data[$this->col_last_name] ?? '') : null,
                'attendee_email' => $this->col_email ? trim($data[$this->col_email] ?? '') : null,
                'ticket_type_name' => $this->col_ticket_type ? trim($data[$this->col_ticket_type] ?? '') : null,
                'original_id' => $this->col_original_id ? trim($data[$this->col_original_id] ?? '') : null,
                'meta' => !empty($meta) ? $meta : null,
            ]);

            $imported++;
        }

        fclose($handle);

        // Clean up uploaded file
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

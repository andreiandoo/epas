<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\EventDocumentFiling;
use App\Models\EventGeneratedDocument;
use App\Models\MarketplaceAdmin;
use App\Models\OrganizerDocument;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The city hall's registration of a filed document: its number and date,
 * and the registered copy kept with the filing.
 *
 * The copy is either our own PDF completed with the number and date (only
 * for documents whose HTML was saved at generation), or the PDF the city
 * hall sent back, uploaded by an operator. It is never a new version of the
 * document: the filing keeps covering what was filed. The organizer sees it
 * in their account as a separate document.
 */
class EventDocumentRegistration
{
    public const MAX_UPLOAD_KB = 10240;

    protected static ?bool $columns = null;

    public function __construct(protected EventDocumentFilingService $filings)
    {
    }

    /** False until the registration details migration has run. */
    public static function available(): bool
    {
        return static::$columns ??= EventDocumentFilingService::available()
            && Schema::hasColumn('event_document_filings', 'registered_file_path');
    }

    /**
     * Save the number and date the city hall gave a filed document, then
     * complete our PDF with them unless the city hall's own copy is uploaded.
     *
     * @return array{success: bool, message: string}
     */
    public function save(Event $event, string $type, string $number, ?string $date, MarketplaceAdmin $admin): array
    {
        $number = trim($number);
        if ($number === '' || mb_strlen($number) > 100) {
            return ['success' => false, 'message' => 'Completează numărul de înregistrare (maximum 100 de caractere).'];
        }

        $filing = $this->currentFiling($event, $type);
        if (! $filing) {
            return ['success' => false, 'message' => 'Documentul nu este marcat ca depus.'];
        }

        $data = [
            'registration_number' => $number,
            'registration_number_at' => now(),
            'registration_number_by_name' => $admin->name,
        ];
        if (static::available()) {
            $day = $this->day($event, $date, $filing);
            if ($day === false) {
                return ['success' => false, 'message' => 'Alege o dată a înregistrării validă, cel târziu azi.'];
            }
            $data['registration_date'] = $day;
        }
        $filing->update($data);

        if (! static::available() || $filing->registered_source === EventDocumentFiling::REGISTERED_UPLOADED) {
            return ['success' => true, 'message' => 'Număr de înregistrare salvat.'];
        }

        $completed = $this->complete($event, $filing->fresh(), $admin);

        return ['success' => true, 'message' => 'Număr de înregistrare salvat. ' . $completed];
    }

    /**
     * Keep the PDF the city hall sent back, with its number and date. A
     * document not marked as filed yet is marked now, on the registration day.
     *
     * @return array{success: bool, message: string}
     */
    public function upload(Event $event, string $type, ?UploadedFile $file, string $number, ?string $date, MarketplaceAdmin $admin): array
    {
        if (! static::available()) {
            return ['success' => false, 'message' => 'Încărcarea nu este disponibilă momentan.'];
        }
        if (! $file || ! $file->isValid()) {
            return ['success' => false, 'message' => 'Alege fișierul PDF primit de la primărie.'];
        }
        if ($file->getSize() > self::MAX_UPLOAD_KB * 1024) {
            return ['success' => false, 'message' => 'Fișierul are mai mult de 10 MB.'];
        }
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 5);
        if ($head !== '%PDF-') {
            return ['success' => false, 'message' => 'Se acceptă doar fișiere PDF.'];
        }

        $number = trim($number);
        if ($number === '' || mb_strlen($number) > 100) {
            return ['success' => false, 'message' => 'Completează numărul de înregistrare (maximum 100 de caractere).'];
        }

        $filing = $this->currentFiling($event, $type);
        $day = $this->day($event, $date, $filing);
        if ($day === false) {
            return ['success' => false, 'message' => 'Alege o dată a înregistrării validă, cel târziu azi.'];
        }

        if (! $filing) {
            // Registered means filed: mark it, on the day it was registered.
            $marked = $this->filings->confirmManual($event, $type, $admin, $day);
            if (! $marked['success']) {
                return $marked;
            }
            $filing = $this->currentFiling($event, $type);
            if (! $filing) {
                return ['success' => false, 'message' => 'Depunerea nu a putut fi înregistrată.'];
            }
        }

        $path = $this->directory($event) . '/' . $this->baseName($event, $type) . '_inregistrat_' . now()->format('Y-m-d_His') . '.pdf';
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $name = Str::limit(preg_replace('/[^\w.\- ]+/u', '_', (string) $file->getClientOriginalName()), 180, '') ?: basename($path);
        if (! Str::endsWith(Str::lower($name), '.pdf')) {
            $name .= '.pdf';
        }

        $filing->update([
            'registration_number' => $number,
            'registration_number_at' => now(),
            'registration_number_by_name' => $admin->name,
            'registration_date' => $day,
            'registered_file_path' => $path,
            'registered_file_name' => $name,
            'registered_source' => EventDocumentFiling::REGISTERED_UPLOADED,
            'registered_at' => now(),
            'registered_by_name' => $admin->name,
        ]);
        $this->syncOrganizerCopy($event, $filing->fresh());

        return ['success' => true, 'message' => 'Exemplarul înregistrat a fost încărcat.'];
    }

    /**
     * Our PDF of the filed version with the number and date filled in, from
     * the HTML saved when it was generated. Returns what happened, as text.
     */
    public function complete(Event $event, EventDocumentFiling $filing, ?MarketplaceAdmin $admin = null): string
    {
        $source = $this->filedDocument($event, $filing);
        $snapshot = $source?->meta['html_snapshot'] ?? null;
        if (! $snapshot || ! Storage::disk('local')->exists($snapshot)) {
            return $source
                ? 'PDF-ul nu poate fi completat automat (generat înainte de această funcție sau fără câmpul de înregistrare în șablon). Încarcă exemplarul primit de la primărie.'
                : 'Documentul depus nu e generat în platformă. Încarcă exemplarul primit de la primărie.';
        }

        try {
            $date = $filing->registration_date
                ? Carbon::parse($filing->registration_date)->format('d.m.Y')
                : null;
            $html = EventGeneratedDocument::fillRegistration(Storage::disk('local')->get($snapshot), $filing->registration_number, $date);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', ($source->meta['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait');

            $path = $this->directory($event) . '/' . pathinfo((string) $source->filename, PATHINFO_FILENAME) . '_inregistrat.pdf';
            Storage::disk('public')->put($path, $pdf->output());
        } catch (\Throwable $e) {
            Log::warning('EventDocumentRegistration: completing the PDF failed', ['event_id' => $event->id, 'filing_id' => $filing->id, 'error' => $e->getMessage()]);

            return 'PDF-ul completat nu a putut fi generat. Încarcă exemplarul primit de la primărie.';
        }

        $filing->update([
            'registered_file_path' => $path,
            'registered_file_name' => basename($path),
            'registered_source' => EventDocumentFiling::REGISTERED_COMPLETED,
            'registered_at' => now(),
            'registered_by_name' => $admin?->name ?? $filing->registration_number_by_name,
        ]);
        $this->syncOrganizerCopy($event, $filing->fresh());

        return 'PDF-ul a fost completat cu numărul și data înregistrării.';
    }

    /**
     * Take the organizer's copy of voided filings out of their account (the
     * files stay on disk).
     */
    public function removeOrganizerCopies(array $filingIds): void
    {
        if (empty($filingIds) || ! static::available()) {
            return;
        }

        $documentIds = EventDocumentFiling::whereIn('id', $filingIds)
            ->whereNotNull('registered_organizer_document_id')
            ->pluck('registered_organizer_document_id')
            ->all();
        if ($documentIds) {
            OrganizerDocument::whereIn('id', $documentIds)
                ->whereIn('document_type', array_map([EventDocumentFiling::class, 'registeredType'], OrganizerFiscalDocuments::TYPES))
                ->delete();
        }
    }

    /** The organizer's copy: created once, then kept in step with the filing. */
    protected function syncOrganizerCopy(Event $event, EventDocumentFiling $filing): void
    {
        if (! $event->marketplace_organizer_id || ! $filing->registered_file_path) {
            return;
        }

        $label = EventDocumentFilingService::TYPE_LABELS[$filing->document_type] ?? $filing->document_type;
        $date = $filing->registration_date ? Carbon::parse($filing->registration_date)->format('d.m.Y') : null;
        $data = [
            'marketplace_client_id' => $event->marketplace_client_id,
            'marketplace_organizer_id' => $event->marketplace_organizer_id,
            'event_id' => $event->id,
            'title' => $label . ' · înregistrat nr. ' . $filing->registration_number . ($date ? ' din ' . $date : ''),
            'document_type' => EventDocumentFiling::registeredType($filing->document_type),
            'file_path' => $filing->registered_file_path,
            'file_name' => $filing->registered_file_name,
            'file_size' => Storage::disk('public')->exists($filing->registered_file_path) ? Storage::disk('public')->size($filing->registered_file_path) : null,
            'document_data' => [
                'source' => 'registered_copy',
                'filing_id' => $filing->id,
                'registered_source' => $filing->registered_source,
                'registration_number' => $filing->registration_number,
                'registration_date' => $date,
            ],
            'issued_at' => now(),
        ];

        $wasSkipping = OrganizerDocument::$skipNotificationOnCreate;
        // Organizer notifications about documents are on hold for now.
        OrganizerDocument::$skipNotificationOnCreate = true;
        try {
            $existing = $filing->registered_organizer_document_id
                ? OrganizerDocument::find($filing->registered_organizer_document_id)
                : null;
            if ($existing) {
                $existing->update($data);
            } else {
                $copy = OrganizerDocument::create($data);
                $filing->update(['registered_organizer_document_id' => $copy->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('EventDocumentRegistration: organizer copy failed', ['event_id' => $event->id, 'filing_id' => $filing->id, 'error' => $e->getMessage()]);
        } finally {
            OrganizerDocument::$skipNotificationOnCreate = $wasSkipping;
        }
    }

    /** The valid filing that covers the current version of the document, if any. */
    protected function currentFiling(Event $event, string $type): ?EventDocumentFiling
    {
        $row = $this->filings->documentStatus($event, $type)['row'] ?? null;
        if (! $row || ! $row['filed'] || ! $row['filing']) {
            return null;
        }

        return EventDocumentFiling::valid()->where('event_id', $event->id)->find($row['filing']['id']);
    }

    /** The generated document the filing was made for. */
    protected function filedDocument(Event $event, EventDocumentFiling $filing): ?EventGeneratedDocument
    {
        if (! $filing->document_generated_at) {
            return null;
        }

        // The filing keeps the newest of the generated row and its mirrored
        // organizer copy, which can be a second later.
        return EventGeneratedDocument::where('event_id', $event->id)
            ->whereHas('template', fn ($q) => $q->where('type', $filing->document_type))
            ->where('created_at', '<=', $filing->document_generated_at->copy()->addSeconds(5))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Registration day as Y-m-d: the one picked, else the filing day. False
     * for a bad or future date.
     */
    protected function day(Event $event, ?string $date, ?EventDocumentFiling $filing): string|false
    {
        $tz = MarketplaceTz::tz($event->marketplaceClient);
        $date = trim((string) $date);
        if ($date === '') {
            return ($filing?->filed_at ?? now())->copy()->setTimezone($tz)->toDateString();
        }

        try {
            $day = Carbon::createFromFormat('!Y-m-d', $date, $tz);
        } catch (\Throwable $e) {
            return false;
        }
        if (! $day || $day->format('Y-m-d') !== $date || $day->year < 2000 || $day->gt(Carbon::now($tz)->startOfDay())) {
            return false;
        }

        return $day->toDateString();
    }

    protected function directory(Event $event): string
    {
        $directory = "documents/events/{$event->id}";
        Storage::disk('public')->makeDirectory($directory);

        return $directory;
    }

    protected function baseName(Event $event, string $type): string
    {
        $title = $event->title;
        $title = is_array($title) ? ($title['ro'] ?? $title['en'] ?? (reset($title) ?: '')) : (string) $title;

        return trim(Str::slug($title ?: 'eveniment') . '_' . Str::slug(EventDocumentFilingService::TYPE_LABELS[$type] ?? $type), '_');
    }
}

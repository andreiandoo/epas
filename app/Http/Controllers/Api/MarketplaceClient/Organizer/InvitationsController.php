<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\MarketplaceOrganizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Organizer-facing invitations API.
 *
 * The invite system is shared with the admin panel (App\Filament\Marketplace\Pages\Invitations).
 * This controller lets an organizer bulk-create a batch of zero-value invitations for one of
 * their own events, render PDFs and download them as a ZIP. The organizer-side events come
 * from the events table (Event model), so batches store the event id in InviteBatch.event_ref
 * and leave marketplace_event_id null.
 */
class InvitationsController extends BaseController
{
    /**
     * List invitation batches for the organizer (optionally scoped to one event).
     * GET /api/marketplace-client/organizer/invitations?event_id=...
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = InviteBatch::where('marketplace_organizer_id', $organizer->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('event_id')) {
            $query->where('event_ref', (string) $request->integer('event_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $batches = $query->paginate($perPage);

        return $this->paginated($batches, fn (InviteBatch $b) => $this->formatBatch($b));
    }

    /**
     * Create a batch + recipients + generate PDFs in one call.
     *
     * Request body:
     *   - event_id   (int,    required) Event owned by the authed organizer
     *   - name       (string, optional) Batch label; defaults to event title + date
     *   - recipients (array,  required) [{first_name, last_name, email, phone?, company?, notes?}, ...]
     *                                   Length must be between 1 and 1000.
     *   - watermark  (string, optional) Printed at the top of each invitation; default "INVITATIE"
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'name' => 'nullable|string|max:255',
            'watermark' => 'nullable|string|max:50',
            'recipients' => 'required|array|min:1|max:1000',
            'recipients.*.first_name' => 'required|string|max:100',
            'recipients.*.last_name' => 'required|string|max:100',
            'recipients.*.email' => 'required|email|max:180',
            'recipients.*.phone' => 'nullable|string|max:50',
            'recipients.*.company' => 'nullable|string|max:150',
            'recipients.*.notes' => 'nullable|string|max:500',
        ]);

        $event = Event::where('id', $validated['event_id'])
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found or not owned by you', 404);
        }

        $recipients = $validated['recipients'];
        $quantity = count($recipients);
        $watermark = $validated['watermark'] ?? 'INVITATIE';
        $batchName = $validated['name'] ?? $this->defaultBatchName($event, $quantity);

        $batch = InviteBatch::create([
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'tenant_id' => $event->tenant_id,
            'event_ref' => (string) $event->id,
            'name' => $batchName,
            'qty_planned' => $quantity,
            'options' => [
                'watermark' => $watermark,
                'expires_after_event' => true,
            ],
            'status' => 'draft',
        ]);

        // Create one Invite per recipient
        foreach ($recipients as $rec) {
            $fullName = trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
            $invite = Invite::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'batch_id' => $batch->id,
                'tenant_id' => $event->tenant_id,
                'status' => 'created',
                'recipient' => [
                    'first_name' => $rec['first_name'],
                    'last_name' => $rec['last_name'],
                    'name' => $fullName,
                    'email' => $rec['email'],
                    'phone' => $rec['phone'] ?? null,
                    'company' => $rec['company'] ?? null,
                    'notes' => $rec['notes'] ?? null,
                ],
            ]);
            $batch->increment('qty_generated');
        }

        // Synchronously render all PDFs — user is waiting on the UI
        $rendered = $this->renderBatchPdfs($batch, $event, $watermark);

        $batch->update(['status' => 'ready']);

        return $this->success([
            'batch' => $this->formatBatch($batch->fresh()),
            'rendered' => $rendered,
            'download_url' => route('api.marketplace-client.organizer.invitations.download', ['batch' => $batch->id]),
        ], "{$rendered} invitations generated", 201);
    }

    /**
     * Get a single batch with its invites.
     * GET /api/marketplace-client/organizer/invitations/{batch}
     */
    public function show(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $invites = $batch->invites()->orderBy('id')->get()
            ->map(fn (Invite $i) => $this->formatInvite($i));

        return $this->success([
            'batch' => $this->formatBatch($batch),
            'invites' => $invites,
        ]);
    }

    /**
     * Re-render PDFs for a batch (e.g. after a template change).
     * POST /api/marketplace-client/organizer/invitations/{batch}/generate
     */
    public function generate(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $event = Event::find((int) $batch->event_ref);
        if (!$event) return $this->error('Event linked to this batch no longer exists', 404);

        // Wipe previous PDFs and re-render
        Storage::disk('local')->deleteDirectory($this->batchStoragePath($batch));
        $watermark = data_get($batch->options, 'watermark', 'INVITATIE');
        $rendered = $this->renderBatchPdfs($batch, $event, $watermark);

        $batch->update(['status' => 'ready']);

        return $this->success([
            'batch' => $this->formatBatch($batch->fresh()),
            'rendered' => $rendered,
        ], "{$rendered} invitations re-rendered");
    }

    /**
     * List invitations for a batch.
     * GET /api/marketplace-client/organizer/invitations/{batch}/invites
     */
    public function invitations(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $perPage = min((int) $request->get('per_page', 100), 500);
        $invites = $batch->invites()->orderBy('id')->paginate($perPage);

        return $this->paginated($invites, fn (Invite $i) => $this->formatInvite($i));
    }

    /**
     * Stream a ZIP of all rendered PDFs in the batch.
     * GET /api/marketplace-client/organizer/invitations/{batch}/download
     */
    public function download(Request $request, int $batchId)
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $invites = $batch->invites()->get()->filter(fn (Invite $i) => $i->getPdfUrl());
        if ($invites->isEmpty()) {
            return $this->error('No PDFs available. Generate them first.', 400);
        }

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $zipFilename = Str::slug($batch->name ?: 'invitations') . '-' . now()->format('Ymd-His') . '.zip';
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->error('Could not create ZIP archive', 500);
        }

        foreach ($invites as $invite) {
            $pdfPath = $invite->getPdfUrl();
            if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                $content = Storage::disk('local')->get($pdfPath);
                $recipientSlug = Str::slug($invite->getRecipientName() ?: 'guest');
                $entry = "{$recipientSlug}-{$invite->invite_code}.pdf";
                $zip->addFromString($entry, $content);

                if (!$invite->downloaded_at) {
                    $invite->markAsDownloaded();
                }
            }
        }
        $zip->close();

        return response()->download($zipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Serve a CSV template the organizer can fill and re-upload.
     * GET /api/marketplace-client/organizer/invitations/csv-template
     */
    public function csvTemplate(Request $request)
    {
        $this->requireOrganizer($request);

        $rows = [
            ['first_name', 'last_name', 'email', 'phone', 'company', 'notes'],
            ['Ion', 'Popescu', 'ion.popescu@example.com', '0712345678', 'Acme SRL', 'VIP'],
            ['Maria', 'Ionescu', 'maria.ionescu@example.com', '', '', ''],
        ];

        $fh = fopen('php://temp', 'w+');
        fputs($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens diacritics correctly
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invitatii-template.csv"',
        ]);
    }

    /**
     * Delete a batch (only while it's still in draft and has no rendered PDFs).
     * DELETE /api/marketplace-client/organizer/invitations/{batch}
     */
    public function destroy(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        if ((int) $batch->qty_rendered > 0 || $batch->status !== 'draft') {
            return $this->error('Only empty draft batches can be deleted', 400);
        }

        Storage::disk('local')->deleteDirectory($this->batchStoragePath($batch));
        $batch->invites()->delete();
        $batch->delete();

        return $this->success(null, 'Batch deleted');
    }

    /**
     * Stub to stay compatible with existing route registration.
     * The organizer flow generates PDFs synchronously in store(); sending by email is not wired yet.
     */
    public function send(Request $request, int $batchId): JsonResponse
    {
        return $this->error('Email sending is not available yet for organizer self-service invitations', 501);
    }

    public function void(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $data = $request->validate([
            'invite_ids' => 'required|array|min:1',
            'invite_ids.*' => 'integer',
        ]);

        $voided = 0;
        foreach ($batch->invites()->whereIn('id', $data['invite_ids'])->get() as $invite) {
            if ($invite->canBeVoided()) {
                $invite->markAsVoid();
                $voided++;
            }
        }

        return $this->success(['voided' => $voided], "{$voided} invitations voided");
    }

    public function stats(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        return $this->success([
            'batch_id' => $batch->id,
            'name' => $batch->name,
            'stats' => [
                'planned' => $batch->qty_planned,
                'generated' => $batch->qty_generated,
                'rendered' => $batch->qty_rendered,
                'downloaded' => $batch->qty_downloaded,
                'voided' => $batch->qty_voided,
            ],
        ]);
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $user = $request->user();
        if (!$user instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }
        return $user;
    }

    protected function findBatch(MarketplaceOrganizer $organizer, int $batchId): ?InviteBatch
    {
        return InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();
    }

    protected function batchStoragePath(InviteBatch $batch): string
    {
        return 'invitations/' . $batch->id;
    }

    protected function defaultBatchName(Event $event, int $quantity): string
    {
        $title = is_array($event->title)
            ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: 'Eveniment')
            : ($event->title ?? 'Eveniment');
        $date = $event->event_date?->format('d.m.Y')
            ?? $event->range_start_date?->format('d.m.Y')
            ?? now()->format('d.m.Y');
        return "{$quantity} invitatii — {$title} ({$date})";
    }

    /**
     * Render a PDF for every Invite in the batch. Returns the count of successfully rendered invites.
     */
    protected function renderBatchPdfs(InviteBatch $batch, Event $event, string $watermark): int
    {
        $storagePath = $this->batchStoragePath($batch);
        Storage::disk('local')->makeDirectory($storagePath);

        [$eventTitle, $eventSubtitle, $eventDate, $eventTime, $venueName] = $this->buildEventContext($event);
        $rendered = 0;

        foreach ($batch->invites()->get() as $invite) {
            try {
                $qrData = url('/verify/' . $invite->invite_code);
                $qrCode = $this->generateQrCode($qrData);

                $pdf = Pdf::loadView('pdf.invitation', [
                    'invite' => $invite,
                    'eventTitle' => $eventTitle,
                    'eventSubtitle' => $eventSubtitle,
                    'eventDate' => $eventDate,
                    'eventTime' => $eventTime,
                    'venueName' => $venueName,
                    'watermark' => $watermark,
                    'qrCode' => $qrCode,
                ]);
                $pdf->setPaper('a4', 'portrait');
                $pdfOutput = $pdf->output();

                $pdfPath = $storagePath . '/' . $invite->invite_code . '.pdf';
                Storage::disk('local')->put($pdfPath, $pdfOutput);

                $invite->setUrls([
                    'pdf' => $pdfPath,
                    'generated_at' => now()->toIso8601String(),
                ]);
                $invite->update(['qr_data' => $qrData]);
                $invite->markAsRendered();
                $rendered++;
            } catch (\Throwable $e) {
                Log::error('Organizer invitation PDF render failed', [
                    'invite_id' => $invite->id,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $rendered;
    }

    /**
     * Pull title/subtitle/date/time/venue strings for the PDF blade from an Event.
     *
     * @return array{0:string,1:?string,2:string,3:?string,4:?string}
     */
    protected function buildEventContext(Event $event): array
    {
        $title = is_array($event->title)
            ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: 'Eveniment')
            : ($event->title ?? 'Eveniment');

        $subtitle = null;
        if (isset($event->subtitle)) {
            $subtitle = is_array($event->subtitle)
                ? ($event->subtitle['ro'] ?? $event->subtitle['en'] ?? reset($event->subtitle) ?: null)
                : ($event->subtitle ?: null);
        }

        $date = 'TBA';
        if ($event->event_date) {
            $date = $event->event_date->format('d.m.Y');
        } elseif ($event->range_start_date) {
            $date = $event->range_start_date->format('d.m.Y')
                . ($event->range_end_date ? ' - ' . $event->range_end_date->format('d.m.Y') : '');
        }

        $time = null;
        if (!empty($event->start_time)) {
            $time = $event->start_time;
            if (!empty($event->door_time)) {
                $time = "Doors: {$event->door_time} | Start: {$event->start_time}";
            }
        }

        $venueName = null;
        if ($event->relationLoaded('venue') || $event->venue()->exists()) {
            $venue = $event->venue ?? $event->venue()->first();
            if ($venue) {
                $name = $venue->name;
                $venueName = is_array($name)
                    ? ($name['ro'] ?? $name['en'] ?? reset($name) ?: null)
                    : ($name ?: null);
                if ($venueName && !empty($venue->city)) {
                    $venueName .= ', ' . $venue->city;
                }
            }
        }

        return [$title, $subtitle, $date, $time, $venueName];
    }

    /**
     * Fetch a QR code PNG via a public QR service and return it base64-encoded
     * (matches the admin implementation to keep the PDF output consistent).
     */
    protected function generateQrCode(string $data): string
    {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($data) . '&format=png&margin=5';
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'user_agent' => 'Tixello/1.0'],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $imageData = @file_get_contents($url, false, $context);
            if ($imageData !== false && strlen($imageData) > 100) {
                return base64_encode($imageData);
            }
        } catch (\Throwable $e) {
            Log::warning('QR fetch failed for organizer invitation', ['error' => $e->getMessage()]);
        }
        return '';
    }

    protected function formatBatch(InviteBatch $batch): array
    {
        $event = null;
        if ($batch->event_ref && is_numeric($batch->event_ref)) {
            $ev = Event::find((int) $batch->event_ref);
            if ($ev) {
                $title = is_array($ev->title)
                    ? ($ev->title['ro'] ?? $ev->title['en'] ?? reset($ev->title) ?: 'Eveniment')
                    : $ev->title;
                $event = [
                    'id' => $ev->id,
                    'name' => $title,
                    'slug' => $ev->slug ?? null,
                    'date' => $ev->event_date?->toIso8601String() ?? $ev->range_start_date?->toIso8601String(),
                ];
            }
        }

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'event' => $event,
            'status' => $batch->status,
            'qty_planned' => (int) $batch->qty_planned,
            'qty_generated' => (int) $batch->qty_generated,
            'qty_rendered' => (int) $batch->qty_rendered,
            'qty_downloaded' => (int) $batch->qty_downloaded,
            'qty_voided' => (int) $batch->qty_voided,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    protected function formatInvite(Invite $invite): array
    {
        $r = $invite->recipient ?? [];
        return [
            'id' => $invite->id,
            'code' => $invite->invite_code,
            'status' => $invite->status,
            'recipient' => [
                'first_name' => $r['first_name'] ?? null,
                'last_name' => $r['last_name'] ?? null,
                'name' => $r['name'] ?? $invite->getRecipientName(),
                'email' => $r['email'] ?? null,
                'phone' => $r['phone'] ?? null,
                'company' => $r['company'] ?? null,
                'notes' => $r['notes'] ?? null,
            ],
            'has_pdf' => !empty($invite->getPdfUrl()),
            'rendered_at' => $invite->rendered_at?->toIso8601String(),
            'downloaded_at' => $invite->downloaded_at?->toIso8601String(),
        ];
    }
}

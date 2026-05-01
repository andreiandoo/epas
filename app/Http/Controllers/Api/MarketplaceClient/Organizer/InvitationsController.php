<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\MarketplaceOrganizer;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Models\TicketType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $perPage = min((int) $request->input('per_page', 20), 100);
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
            'recipients.*.first_name' => 'nullable|string|max:100',
            'recipients.*.last_name' => 'nullable|string|max:100',
            'recipients.*.email' => 'nullable|email|max:180',
            'recipients.*.phone' => 'nullable|string|max:50',
            'recipients.*.company' => 'nullable|string|max:150',
            'recipients.*.notes' => 'nullable|string|max:500',
            // Seated events: each recipient is paired with an already-picked
            // seat. The organizer's seat picker UI ensures seats[] and
            // recipients[] are the same length and in the same order.
            'event_seating_id' => 'nullable|integer',
            'seats' => 'nullable|array',
            'seats.*.seat_uid' => 'required_with:seats|string|max:100',
            'seats.*.section_name' => 'nullable|string|max:100',
            'seats.*.row_label' => 'nullable|string|max:20',
            'seats.*.seat_label' => 'nullable|string|max:20',
        ]);

        $event = Event::where('id', $validated['event_id'])
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found or not owned by you', 404);
        }

        // Defensive: \$validated['recipients'] is normally guaranteed by the
        // required|array|min:1 rule above, but fall back to the raw request
        // body if Laravel ever omits the key (rare edge cases with malformed
        // input). Anonymous batches are allowed — the organizer can issue N
        // invitations without filling any names; each gets an 'Invitat N'
        // placeholder so the PDF still renders with the QR + code.
        $recipients = $validated['recipients'] ?? $request->input('recipients', []);

        if (empty($recipients)) {
            return $this->error(
                'Adaugă cel puțin un invitat înainte de a genera invitațiile.',
                422
            );
        }

        foreach ($recipients as $i => &$rec) {
            $hasAnyData = !empty($rec['first_name']) || !empty($rec['last_name']) || !empty($rec['email']);
            if (!$hasAnyData) {
                $rec['first_name'] = 'Invitat';
                $rec['last_name'] = (string) ($i + 1);
            }
        }
        unset($rec);

        $quantity = count($recipients);
        $watermark = $validated['watermark'] ?? 'INVITATIE';
        $batchName = $validated['name'] ?? $this->defaultBatchName($event, $quantity);

        // If the organizer selected seats on the map, pair them 1:1 with
        // recipients and atomically reserve them on the event_seating.
        // SeatHoldService::confirmPurchase flips each event_seats row from
        // available/held → sold in a single transaction — any seat someone
        // else grabbed in the meantime causes the whole thing to roll back
        // so we never double-allocate.
        $seats = $validated['seats'] ?? [];
        $eventSeatingId = $validated['event_seating_id'] ?? null;
        if (!empty($seats)) {
            if (!$eventSeatingId) {
                return $this->error('event_seating_id este obligatoriu când furnizezi locuri.', 422);
            }
            if (count($seats) !== $quantity) {
                return $this->error('Numărul de locuri selectate trebuie să fie egal cu numărul de invitați.', 422);
            }

            $holdService = app(\App\Services\Seating\SeatHoldService::class);
            $seatUids = array_map(fn ($s) => $s['seat_uid'], $seats);
            $sessionUid = $this->buildOrganizerSeatSessionUid($organizer->id, $event->id);
            $result = $holdService->confirmPurchase($eventSeatingId, $seatUids, $sessionUid, 0);
            if (!empty($result['failed'])) {
                return $this->error('Unele locuri nu mai sunt disponibile — reîmprospătează harta și alege altele.', 409, [
                    'unavailable_seats' => array_map(fn ($f) => $f['seat_uid'], $result['failed']),
                ]);
            }
        }

        // Resolve the ticket template for this batch: prefer event.ticket_template_id
        // (the design the organizer chose for real tickets), fall back to the marketplace
        // default. Stored on the batch so admin-side tools see the same template.
        $templateId = $this->resolveTemplateId($event, $organizer);

        // Wrap batch + invites creation in a single DB transaction so any
        // failure rolls back the batch row instead of leaving an orphan
        // (planned > 0, generated = 0). We render PDFs OUTSIDE the
        // transaction (renderBatchPdfs hits the filesystem); a render
        // failure leaves the batch as 'draft' but invite rows already
        // exist and can be re-rendered via the /generate endpoint.
        try {
            $batch = DB::transaction(function () use ($organizer, $event, $batchName, $quantity, $templateId, $watermark, $eventSeatingId, $recipients, $seats) {
                $batch = InviteBatch::create([
                    'marketplace_client_id' => $organizer->marketplace_client_id,
                    'marketplace_organizer_id' => $organizer->id,
                    'tenant_id' => $event->tenant_id,
                    'event_ref' => (string) $event->id,
                    'name' => $batchName,
                    'qty_planned' => $quantity,
                    'template_id' => $templateId,
                    'options' => [
                        'watermark' => $watermark,
                        'expires_after_event' => true,
                        'event_seating_id' => $eventSeatingId,
                    ],
                    'status' => 'draft',
                ]);

                foreach ($recipients as $i => $rec) {
                    $fullName = trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
                    $seatForRecipient = $seats[$i] ?? null;
                    $seatRef = $this->formatSeatRef($seatForRecipient);
                    $recipientPayload = [
                        'first_name' => $rec['first_name'] ?? null,
                        'last_name' => $rec['last_name'] ?? null,
                        'name' => $fullName,
                        'email' => $rec['email'] ?? null,
                        'phone' => $rec['phone'] ?? null,
                        'company' => $rec['company'] ?? null,
                        'notes' => $rec['notes'] ?? null,
                    ];
                    if ($seatForRecipient) {
                        $recipientPayload['seat'] = [
                            'uid' => $seatForRecipient['seat_uid'],
                            'section' => $seatForRecipient['section_name'] ?? null,
                            'row' => $seatForRecipient['row_label'] ?? null,
                            'label' => $seatForRecipient['seat_label'] ?? null,
                            'event_seating_id' => $eventSeatingId,
                        ];
                    }

                    $invite = Invite::create([
                        'marketplace_client_id' => $organizer->marketplace_client_id,
                        'batch_id' => $batch->id,
                        'tenant_id' => $event->tenant_id,
                        'status' => 'created',
                        'seat_ref' => $seatRef,
                        'recipient' => $recipientPayload,
                    ]);

                    Log::info('[InvitationsController.store] Invite created', [
                        'batch_id' => $batch->id,
                        'invite_id' => $invite->id,
                        'invite_code' => $invite->invite_code,
                        'recipient_index' => $i,
                        'tenant_id' => $event->tenant_id,
                    ]);

                    $batch->increment('qty_generated');
                }

                return $batch;
            });
        } catch (\Throwable $e) {
            // Compensating action: release any seats we already flipped to
            // 'sold' via confirmPurchase so they don't stay reserved for a
            // batch that no longer exists.
            if (!empty($seats) && $eventSeatingId) {
                try {
                    $seatUids = array_map(fn ($s) => $s['seat_uid'], $seats);
                    \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeatingId)
                        ->whereIn('seat_uid', $seatUids)
                        ->where('status', 'sold')
                        ->update(['status' => 'available', 'last_change_at' => now()]);
                } catch (\Throwable $inner) {
                    Log::error('[InvitationsController.store] Failed to release seats after rollback', [
                        'event_seating_id' => $eventSeatingId,
                        'error' => $inner->getMessage(),
                    ]);
                }
            }

            Log::error('[InvitationsController.store] Batch creation rolled back', [
                'organizer_id' => $organizer->id,
                'event_id' => $event->id,
                'recipients_count' => $quantity,
                'has_seats' => !empty($seats),
                'event_seating_id' => $eventSeatingId,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
                'trace' => collect(explode("\n", $e->getTraceAsString()))->take(10)->implode("\n"),
            ]);

            return $this->error('Crearea invitațiilor a eșuat. Locurile au fost eliberate; reia procesul.', 500);
        }

        // Render PDFs synchronously — user is waiting on the UI. A render
        // failure here is non-fatal: invites exist in DB, organizer can
        // hit "Regenerează" which calls /generate to retry.
        try {
            $rendered = $this->renderBatchPdfs($batch, $event, $watermark);
            $batch->update(['status' => 'ready']);
        } catch (\Throwable $e) {
            Log::error('[InvitationsController.store] Render failed but batch persists', [
                'batch_id' => $batch->id,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);
            $rendered = 0;
            $batch->update(['status' => 'draft']);
        }

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

        $perPage = min((int) $request->input('per_page', 100), 500);
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
     * Stream the PDF for a single invitation. Marks the invite as
     * downloaded the first time it's served so the UI can show a
     * 'Descărcat la …' badge next to the row.
     * GET /api/marketplace-client/organizer/invitations/{batch}/invites/{invite}/download
     */
    public function downloadInvite(Request $request, int $batchId, int $inviteId)
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $invite = $batch->invites()->where('id', $inviteId)->first();
        if (!$invite) return $this->error('Invitation not found in this batch', 404);

        $pdfPath = $invite->getPdfUrl();
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            return $this->error('PDF nu este disponibil. Regenerează batch-ul.', 404);
        }

        if (!$invite->downloaded_at) {
            $invite->markAsDownloaded();
        }

        $recipientSlug = Str::slug($invite->getRecipientName() ?: 'invitatie');
        $filename = "{$recipientSlug}-{$invite->invite_code}.pdf";

        return Storage::disk('local')->download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
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

    /**
     * Hard-delete specific invites from a batch.
     *
     * For seated invitations this also releases the locked seat back to
     * available on the map, deletes the paired Ticket record, and removes
     * the stored PDF — undoing the full side-effect chain from store().
     * Empties are caught so one bad invite can't block the rest.
     *
     * DELETE /api/marketplace-client/organizer/invitations/{batch}/invites
     */
    public function deleteInvites(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $batch = $this->findBatch($organizer, $batchId);
        if (!$batch) return $this->error('Batch not found', 404);

        $data = $request->validate([
            'invite_ids' => 'required|array|min:1',
            'invite_ids.*' => 'integer',
        ]);

        $deleted = 0;
        $seatsReleased = 0;
        $batchSeatingId = data_get($batch->options, 'event_seating_id');

        foreach ($batch->invites()->whereIn('id', $data['invite_ids'])->get() as $invite) {
            try {
                $seat = $invite->recipient['seat'] ?? null;
                $seatUid = $seat['uid'] ?? null;
                $esid = $seat['event_seating_id'] ?? $batchSeatingId;

                if ($seatUid && $esid) {
                    // Only flip rows we ourselves flipped to sold. whereIn on
                    // status avoids blowing away a seat that, for some
                    // reason, was already re-used by a real purchase.
                    $released = \App\Models\Seating\EventSeat::where('event_seating_id', $esid)
                        ->where('seat_uid', $seatUid)
                        ->whereIn('status', ['sold', 'held'])
                        ->update([
                            'status' => 'available',
                            'version' => \DB::raw('version + 1'),
                            'last_change_at' => now(),
                        ]);
                    if ($released > 0) $seatsReleased++;
                }

                // Drop the paired Ticket (carries the same invite_code)
                Ticket::where('code', $invite->invite_code)->delete();

                // Remove the PDF on disk if rendered
                $pdfPath = $invite->getPdfUrl();
                if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                    Storage::disk('local')->delete($pdfPath);
                }

                $invite->delete();
                $deleted++;
            } catch (\Throwable $e) {
                Log::warning('Invite delete failed', [
                    'invite_id' => $invite->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Keep counters roughly in sync so the UI doesn't lie about how many
        // invites are in the batch; floor to 0 to be safe.
        $remaining = $batch->invites()->count();
        $batch->update([
            'qty_generated' => max(0, (int) $batch->qty_generated - $deleted),
            'qty_rendered' => $remaining === 0 ? 0 : $batch->qty_rendered,
        ]);

        // If nothing left in the batch, drop it — otherwise leftover empty
        // batches clutter the "Serii de invitații" list forever.
        if ($remaining === 0) {
            Storage::disk('local')->deleteDirectory($this->batchStoragePath($batch));
            $batch->delete();
        }

        return $this->success([
            'deleted' => $deleted,
            'seats_released' => $seatsReleased,
            'batch_remaining' => $remaining,
        ], "{$deleted} invitații șterse" . ($seatsReleased > 0 ? " · {$seatsReleased} locuri eliberate" : ''));
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

    /**
     * Pre-hold seats while the organizer fills the recipients form.
     * POST /api/marketplace-client/organizer/invitations/hold-seats
     *
     * The actual batch creation re-confirms each seat atomically, so
     * pre-holding is just a UX niceness — it flashes "held" to other
     * visitors the moment the organizer picks seats in the modal.
     */
    public function holdSeats(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $data = $request->validate([
            'event_id' => 'required|integer',
            'event_seating_id' => 'required|integer',
            'seat_uids' => 'required|array|min:1|max:1000',
            'seat_uids.*' => 'required|string|max:100',
        ]);

        $event = Event::where('id', $data['event_id'])
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();
        if (!$event) return $this->error('Event not found or not owned by you', 404);

        $sessionUid = $this->buildOrganizerSeatSessionUid($organizer->id, $event->id);
        $result = app(\App\Services\Seating\SeatHoldService::class)
            ->holdSeats((int) $data['event_seating_id'], $data['seat_uids'], $sessionUid);

        return $this->success($result);
    }

    /**
     * Release the organizer's pre-hold (e.g. user closed the modal or
     * removed a seat from the picker).
     * DELETE /api/marketplace-client/organizer/invitations/hold-seats
     */
    public function releaseSeats(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $data = $request->validate([
            'event_id' => 'required|integer',
            'event_seating_id' => 'required|integer',
            'seat_uids' => 'required|array|min:1',
            'seat_uids.*' => 'required|string|max:100',
        ]);

        $event = Event::where('id', $data['event_id'])
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();
        if (!$event) return $this->error('Event not found or not owned by you', 404);

        $sessionUid = $this->buildOrganizerSeatSessionUid($organizer->id, $event->id);
        $result = app(\App\Services\Seating\SeatHoldService::class)
            ->releaseSeats((int) $data['event_seating_id'], $data['seat_uids'], $sessionUid);

        return $this->success($result);
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Stable per-(organizer,event) session UID used when holding or
     * confirming seats for invitations. SeatHolds rows are keyed on
     * session_uid, so reusing the same string lets a later release/
     * confirm from the same organizer find its own holds.
     */
    protected function buildOrganizerSeatSessionUid(int $organizerId, int $eventId): string
    {
        return 'org-inv-' . hash('sha256', $organizerId . '-' . $eventId);
    }

    /**
     * Human-readable seat reference stored on Invite.seat_ref and printed
     * on the PDF (via $data['ticket']['seat']). Falls back to the seat_uid
     * when section/row/label are missing.
     */
    protected function formatSeatRef(?array $seat): ?string
    {
        if (!$seat) return null;

        $parts = [];
        if (!empty($seat['section_name'])) {
            $parts[] = $seat['section_name'];
        }
        if (!empty($seat['row_label'])) {
            $parts[] = 'Rând ' . $seat['row_label'];
        }
        if (!empty($seat['seat_label'])) {
            $parts[] = 'Loc ' . $seat['seat_label'];
        }

        if (empty($parts)) {
            return $seat['seat_uid'] ?? null;
        }

        return implode(' · ', $parts);
    }

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
     *
     * Tries the custom TicketTemplate (the design attached to the event or the marketplace
     * default) first so the invitation PDF looks like the real event ticket; falls back to
     * the hardcoded pdf.invitation blade. Also creates a Ticket record per invite so the
     * invitations show up in /marketplace/events/{id} sales and organizer analytics.
     */
    protected function renderBatchPdfs(InviteBatch $batch, Event $event, string $watermark): int
    {
        $storagePath = $this->batchStoragePath($batch);
        Storage::disk('local')->makeDirectory($storagePath);

        [$eventTitle, $eventSubtitle, $eventDate, $eventTime, $venueName] = $this->buildEventContext($event);

        $template = $batch->template
            ?? ($batch->template_id ? TicketTemplate::find($batch->template_id) : null);

        // Find-or-create the event's "Invitatie" ticket type so Ticket records can be attached.
        // quota_total = -1 means unlimited, and price_cents = 0 marks it as complimentary.
        // meta.is_invitation = true is the flag every public endpoint (MarketplaceEventsController,
        // TenantClientController, ...) uses to hide it from the buyer-facing ticket picker.
        $invitationTicketType = $this->ensureInvitationTicketType($event);

        $rendered = 0;

        foreach ($batch->invites()->get() as $invite) {
            try {
                $qrData = url('/verify/' . $invite->invite_code);
                $qrCode = $this->generateQrCode($qrData);

                $pdfOutput = $this->renderInvitationPdf(
                    $invite,
                    $template,
                    $event,
                    [
                        'eventTitle' => $eventTitle,
                        'eventSubtitle' => $eventSubtitle,
                        'eventDate' => $eventDate,
                        'eventTime' => $eventTime,
                        'venueName' => $venueName,
                        'watermark' => $watermark,
                        'qrCode' => $qrCode,
                        'qrData' => $qrData,
                    ]
                );

                $pdfPath = $storagePath . '/' . $invite->invite_code . '.pdf';
                Storage::disk('local')->put($pdfPath, $pdfOutput);

                $invite->setUrls([
                    'pdf' => $pdfPath,
                    'generated_at' => now()->toIso8601String(),
                ]);
                $invite->update(['qr_data' => $qrData]);
                $invite->markAsRendered();

                // Create a Ticket row so analytics/sales pages count this invitation.
                if (!Ticket::where('code', $invite->invite_code)->exists()) {
                    Ticket::create([
                        'order_id' => null,
                        'ticket_type_id' => $invitationTicketType->id,
                        'performance_id' => null,
                        'code' => $invite->invite_code,
                        'status' => 'valid',
                        'seat_label' => $invite->seat_ref,
                        'meta' => [
                            'is_invitation' => true,
                            'invite_batch_id' => $batch->id,
                            'beneficiary' => [
                                'name' => $invite->getRecipientName(),
                                'email' => $invite->getRecipientEmail(),
                                'phone' => $invite->getRecipientPhone(),
                                'company' => $invite->getRecipientCompany(),
                            ],
                        ],
                    ]);
                }

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
     * Find-or-create the event's "Invitatie" ticket type and guarantee that
     * meta.is_invitation = true (heals records that existed before we started
     * tagging, so the public ticket picker keeps them hidden). Returns the row.
     */
    protected function ensureInvitationTicketType(Event $event): TicketType
    {
        $tt = TicketType::where('event_id', $event->id)
            ->where('name', 'Invitatie')
            ->first();

        if (!$tt) {
            $tt = TicketType::create([
                'event_id' => $event->id,
                'name' => 'Invitatie',
                'price_cents' => 0,
                'currency' => 'RON',
                'quota_total' => -1,
                'quota_sold' => 0,
                'meta' => ['is_invitation' => true],
            ]);
        }

        // Heal: ensure meta.is_invitation is present + status is 'active'
        $meta = $tt->meta ?? [];
        $needsSave = false;
        if (empty($meta['is_invitation'])) {
            $meta['is_invitation'] = true;
            $tt->meta = $meta;
            $needsSave = true;
        }
        if (($tt->status ?? 'active') !== 'active') {
            $tt->status = 'active';
            $needsSave = true;
        }
        if ($needsSave) {
            $tt->save();
        }

        return $tt;
    }

    /**
     * Resolve the TicketTemplate id to use for a batch. Prefers the event's own
     * ticket_template_id, falls back to the marketplace's default active template.
     */
    protected function resolveTemplateId(Event $event, MarketplaceOrganizer $organizer): ?int
    {
        if (!empty($event->ticket_template_id)) {
            return (int) $event->ticket_template_id;
        }
        $default = TicketTemplate::where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('status', 'active')
            ->where('is_default', true)
            ->first();
        return $default?->id;
    }

    /**
     * Render a single invitation PDF.
     *
     * When a TicketTemplate with layers is available, render through the
     * TicketPreviewGenerator (same path the admin flow uses, so the visual
     * matches the event's real ticket). Fall back to the pdf.invitation blade.
     *
     * @param array<string,mixed> $ctx
     */
    protected function renderInvitationPdf(Invite $invite, ?TicketTemplate $template, Event $event, array $ctx): string
    {
        if ($template && !empty($template->template_data['layers'] ?? null)) {
            try {
                $generator = app(\App\Services\TicketCustomizer\TicketPreviewGenerator::class);
                $variableService = app(\App\Services\TicketCustomizer\TicketVariableService::class);

                // Build the full template dictionary from scratch (no
                // getSampleData fallback) so we don't leak demo strings like
                // "Live Nation Romania" / "Strada Victoriei 25" through any
                // unmapped {{organizer.*}} / {{venue.*}} / {{date.*}} variable.
                $data = $this->buildTemplateData($invite, $event, $ctx, $variableService);

                $content = $generator->renderToHtml($template->template_data, $data);

                if (!empty(trim($content))) {
                    $size = $template->getSize();
                    $widthPt = round(($size['width'] ?? 210) * 2.8346, 2);
                    $heightPt = round(($size['height'] ?? 297) * 2.8346, 2);
                    $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';
                    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                        . "<style>@page { margin: 0; size: {$widthPt}pt {$heightPt}pt; } * { margin: 0; padding: 0; } body { margin: 0; padding: 0; width: {$widthPt}pt; height: {$heightPt}pt; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; overflow: hidden; }</style>"
                        . "</head><body>{$content}</body></html>";

                    $customPdf = Pdf::loadHTML($html)
                        ->setPaper([0, 0, $widthPt, $heightPt])
                        ->setOption('isRemoteEnabled', true)
                        ->setOption('isHtml5ParserEnabled', true);

                    return $customPdf->output();
                }
            } catch (\Throwable $e) {
                Log::warning('Organizer invitation custom PDF failed, falling back to blade', [
                    'invite_id' => $invite->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $pdf = Pdf::loadView('pdf.invitation', [
            'invite' => $invite,
            'eventTitle' => $ctx['eventTitle'],
            'eventSubtitle' => $ctx['eventSubtitle'] ?? null,
            'eventDate' => $ctx['eventDate'],
            'eventTime' => $ctx['eventTime'] ?? null,
            'venueName' => $ctx['venueName'] ?? null,
            'watermark' => $ctx['watermark'],
            'qrCode' => $ctx['qrCode'],
        ]);
        $pdf->setPaper('a4', 'portrait');
        return $pdf->output();
    }

    /**
     * Build the {{event.*}}, {{venue.*}}, {{date.*}}, {{ticket.*}},
     * {{buyer.*}}, {{order.*}}, {{organizer.*}}, {{legal.*}}, {{barcode}}
     * and {{qrcode}} dictionary that TicketPreviewGenerator expects.
     *
     * Mirrors the shape of TicketVariableService::getSampleData() (and the
     * post-purchase resolveTicketData($ticket)), but populates everything
     * from real Event / Venue / Organizer / Invite values rather than from
     * sample stubs. Missing values are rendered as empty strings, never
     * "Live Nation Romania" / "ion.popescu@example.com" / etc.
     */
    protected function buildTemplateData(
        Invite $invite,
        Event $event,
        array $ctx,
        \App\Services\TicketCustomizer\TicketVariableService $variableService
    ): array {
        $event->loadMissing(['venue', 'marketplaceOrganizer']);
        $venue = $event->venue;
        $organizer = $event->marketplaceOrganizer;
        $recipient = $invite->recipient ?? [];

        $recipientName = trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''))
            ?: ($recipient['name'] ?? $invite->getRecipientName() ?? 'Invitat');
        $recipientEmail = $recipient['email'] ?? $invite->getRecipientEmail() ?? '';
        $nameParts = explode(' ', $recipientName, 2);

        // Event description (translatable JSON) → ro/en/first
        $description = $event->description ?? null;
        $eventDescription = is_array($description)
            ? ($description['ro'] ?? $description['en'] ?? (reset($description) ?: ''))
            : ($description ?? '');

        // Date components
        $dateStartRaw = $event->event_date?->format('Y-m-d') ?? '';
        $startFormatted = $ctx['eventDate'] ?? '';
        $startTime = $event->start_time ? substr((string) $event->start_time, 0, 5) : '';
        $doorTime = $event->door_time ? substr((string) $event->door_time, 0, 5) : '';
        $dayName = $event->event_date?->translatedFormat('l') ?? '';

        // Venue address — translatable name handled by ctx['venueName'] already
        $venueAddress = $venue?->address ?? '';
        $venueCity = $venue?->city ?? '';

        // Seat placement: when the invite was created with seats[] in store(),
        // recipient.seat carries the structured fields {section_name, row_label,
        // seat_label, ...}. Render with Romanian prefix to match resolveTicketData.
        $seatStruct = $recipient['seat'] ?? null;
        $sectionName = is_array($seatStruct) ? ($seatStruct['section_name'] ?? '') : '';
        $rowLabel = is_array($seatStruct) ? ($seatStruct['row_label'] ?? '') : '';
        $seatLabel = is_array($seatStruct) ? ($seatStruct['seat_label'] ?? '') : '';
        $sectionStr = $sectionName !== '' ? 'Sectiunea ' . $sectionName : '';
        $rowStr = $rowLabel !== '' ? 'Randul ' . $rowLabel : '';
        $seatStr = $seatLabel !== '' ? 'Locul ' . $seatLabel : ($invite->seat_ref ?? '');

        // Organizer column names vary across the codebase; try common variants
        // before falling back to empty string so {{organizer.*}} can't show
        // demo data.
        $orgGet = function (?MarketplaceOrganizer $o, string ...$cols) {
            if (!$o) return '';
            foreach ($cols as $c) {
                $v = $o->{$c} ?? null;
                if (!empty($v)) return is_string($v) ? $v : (string) $v;
            }
            return '';
        };

        return [
            'event' => [
                'name' => $ctx['eventTitle'] ?? '',
                'description' => $eventDescription,
                'category' => '',
                'image' => $variableService->resolveEventImageUrl($event),
            ],
            'venue' => [
                'name' => $ctx['venueName'] ?? '',
                'address' => $venueAddress,
                'city' => $venueCity,
            ],
            'date' => [
                'start' => $dateStartRaw,
                'start_formatted' => $startFormatted,
                'time' => $startTime,
                'doors_open' => $doorTime,
                'day_name' => $dayName,
            ],
            'ticket' => [
                'type' => 'INVITAȚIE',
                'price' => 'GRATUIT',
                'section' => $sectionStr,
                'row' => $rowStr,
                'seat' => $seatStr,
                'number' => $invite->invite_code,
                'code_short' => $invite->invite_code,
                'code_long' => $invite->invite_code,
                'serial' => $invite->invite_code,
                'is_insured' => 'false',
                'insurance_badge' => '',
                'insurance_label' => '',
                'price_detail' => 'Invitație',
                'fees_text' => '',
                'verify_url' => url('/verify/' . $invite->invite_code),
                'description' => '',
                'perks' => '',
            ],
            'buyer' => [
                'name' => $recipientName,
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'email' => $recipientEmail,
            ],
            'order' => [
                'code' => '',
                'date' => '',
                'total' => '',
            ],
            'barcode' => $invite->invite_code,
            'qrcode' => $ctx['qrData'] ?? url('/verify/' . $invite->invite_code),
            'organizer' => [
                'name' => $organizer?->name ?? '',
                'company_name' => $orgGet($organizer, 'company_name'),
                'tax_id' => $orgGet($organizer, 'company_tax_id', 'tax_id', 'cui'),
                'company_address' => $orgGet($organizer, 'company_address', 'address'),
                'city' => $orgGet($organizer, 'company_city', 'city'),
                'website' => $orgGet($organizer, 'website'),
                'phone' => $orgGet($organizer, 'phone'),
                'email' => $orgGet($organizer, 'email', 'billing_email'),
                'ticket_terms' => $orgGet($organizer, 'ticket_terms'),
            ],
            'legal' => [
                'terms' => $orgGet($organizer, 'ticket_terms')
                    ?: (is_array($event->ticket_terms ?? null)
                        ? ($event->ticket_terms['ro'] ?? $event->ticket_terms['en'] ?? '')
                        : ($event->ticket_terms ?? '')),
                'disclaimer' => '',
            ],
        ];
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
            'seat_ref' => $invite->seat_ref,
            'recipient' => [
                'first_name' => $r['first_name'] ?? null,
                'last_name' => $r['last_name'] ?? null,
                'name' => $r['name'] ?? $invite->getRecipientName(),
                'email' => $r['email'] ?? null,
                'phone' => $r['phone'] ?? null,
                'company' => $r['company'] ?? null,
                'notes' => $r['notes'] ?? null,
                'seat' => isset($r['seat']) && is_array($r['seat']) ? [
                    'uid' => $r['seat']['uid'] ?? null,
                    'section' => $r['seat']['section'] ?? null,
                    'row' => $r['seat']['row'] ?? null,
                    'label' => $r['seat']['label'] ?? null,
                ] : null,
            ],
            'has_pdf' => !empty($invite->getPdfUrl()),
            'rendered_at' => $invite->rendered_at?->toIso8601String(),
            'downloaded_at' => $invite->downloaded_at?->toIso8601String(),
            'download_url' => !empty($invite->getPdfUrl())
                ? route('api.marketplace-client.organizer.invitations.download-invite', [
                    'batch' => $invite->batch_id,
                    'invite' => $invite->id,
                ])
                : null,
        ];
    }
}

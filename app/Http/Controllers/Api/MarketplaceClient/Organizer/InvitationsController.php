<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\InviteBatch;
use App\Models\Invite;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class InvitationsController extends BaseController
{
    /**
     * List invitation batches for organizer
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Check if invitations are enabled
        if (!$organizer->invitations_enabled) {
            return $this->error('Invitations are not enabled for your account', 403);
        }

        $query = InviteBatch::where('marketplace_organizer_id', $organizer->id)
            ->with(['marketplaceEvent:id,name,slug,starts_at'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('event_id')) {
            $query->where('marketplace_event_id', $request->event_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $batches = $query->paginate($perPage);

        return $this->paginated($batches, function ($batch) {
            return $this->formatBatch($batch);
        });
    }

    /**
     * Create a new invitation batch
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        if (!$organizer->invitations_enabled) {
            return $this->error('Invitations are not enabled for your account', 403);
        }

        $validated = $request->validate([
            'event_id' => 'required|integer|exists:marketplace_events,id',
            'name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1|max:1000',
            'options' => 'nullable|array',
            'options.watermark' => 'nullable|string|max:50',
            'options.guest_names' => 'nullable|array',
            'options.guest_names.*' => 'string|max:100',
            'options.message' => 'nullable|string|max:500',
            'options.expires_after_event' => 'nullable|boolean',
        ]);

        // Verify event belongs to organizer
        $event = MarketplaceEvent::where('id', $validated['event_id'])
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Check event is published
        if (!$event->isPublished()) {
            return $this->error('Cannot create invitations for unpublished events', 400);
        }

        // Create the batch
        $batch = InviteBatch::create([
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'marketplace_event_id' => $event->id,
            'event_ref' => $event->slug,
            'name' => $validated['name'],
            'qty_planned' => $validated['quantity'],
            'options' => array_merge([
                'watermark' => 'INVITATION',
                'expires_after_event' => true,
            ], $validated['options'] ?? []),
            'status' => 'draft',
        ]);

        return $this->success([
            'batch' => $this->formatBatch($batch->load('marketplaceEvent:id,name,slug,starts_at')),
        ], 'Invitation batch created', 201);
    }

    /**
     * Get a single batch
     */
    public function show(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->with(['marketplaceEvent:id,name,slug,starts_at'])
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        return $this->success([
            'batch' => $this->formatBatchDetailed($batch),
        ]);
    }

    /**
     * Generate invitations for a batch
     */
    public function generate(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        if (!in_array($batch->status, ['draft', 'ready'])) {
            return $this->error('Batch cannot be generated in current status', 400);
        }

        $guestNames = $batch->getOption('guest_names', []);
        $invitesCreated = 0;

        // Generate invitations
        for ($i = 0; $i < $batch->qty_planned; $i++) {
            $guestName = $guestNames[$i] ?? null;

            Invite::create([
                'batch_id' => $batch->id,
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'code' => $this->generateInviteCode(),
                'guest_name' => $guestName,
                'status' => 'active',
                'event_ref' => $batch->event_ref,
            ]);

            $invitesCreated++;
            $batch->incrementGenerated();
        }

        $batch->updateStatus('ready');

        return $this->success([
            'batch' => $this->formatBatch($batch->fresh(['marketplaceEvent:id,name,slug,starts_at'])),
            'invites_created' => $invitesCreated,
        ], "{$invitesCreated} invitations generated");
    }

    /**
     * List invitations in a batch
     */
    public function invitations(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        $query = Invite::where('batch_id', $batch->id)
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('guest_name', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $invites = $query->paginate($perPage);

        return $this->paginated($invites, function ($invite) {
            return $this->formatInvite($invite);
        });
    }

    /**
     * Send invitations by email
     */
    public function send(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        if (!$batch->canSendEmails()) {
            return $this->error('Batch is not ready for sending', 400);
        }

        $validated = $request->validate([
            'invite_ids' => 'nullable|array',
            'invite_ids.*' => 'integer|exists:invites,id',
            'emails' => 'nullable|array',
            'emails.*.invite_id' => 'required|integer',
            'emails.*.email' => 'required|email',
            'emails.*.name' => 'nullable|string|max:100',
        ]);

        $sentCount = 0;
        $errors = [];

        // If specific invite/email pairs provided
        if (!empty($validated['emails'])) {
            foreach ($validated['emails'] as $emailData) {
                $invite = Invite::where('id', $emailData['invite_id'])
                    ->where('batch_id', $batch->id)
                    ->first();

                if (!$invite) {
                    $errors[] = "Invite {$emailData['invite_id']} not found";
                    continue;
                }

                if ($invite->status === 'voided') {
                    $errors[] = "Invite {$invite->code} is voided";
                    continue;
                }

                // Update invite with guest info
                $invite->update([
                    'guest_email' => $emailData['email'],
                    'guest_name' => $emailData['name'] ?? $invite->guest_name,
                    'emailed_at' => now(),
                ]);

                // TODO: Queue email sending job
                // SendInvitationEmail::dispatch($invite);

                $batch->incrementEmailed();
                $sentCount++;
            }
        }
        // If just invite IDs, send to existing emails
        elseif (!empty($validated['invite_ids'])) {
            $invites = Invite::whereIn('id', $validated['invite_ids'])
                ->where('batch_id', $batch->id)
                ->whereNotNull('guest_email')
                ->where('status', 'active')
                ->get();

            foreach ($invites as $invite) {
                $invite->update(['emailed_at' => now()]);
                // TODO: Queue email sending job
                $batch->incrementEmailed();
                $sentCount++;
            }
        }

        $batch->updateStatus('sending');

        return $this->success([
            'sent_count' => $sentCount,
            'errors' => $errors,
            'batch' => $this->formatBatch($batch->fresh(['marketplaceEvent:id,name,slug,starts_at'])),
        ], "{$sentCount} invitations queued for sending");
    }

    /**
     * Download invitations as PDF
     */
    public function download(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        if ($batch->qty_generated === 0) {
            return $this->error('No invitations generated yet', 400);
        }

        $validated = $request->validate([
            'invite_ids' => 'nullable|array',
            'invite_ids.*' => 'integer',
            'format' => 'nullable|in:pdf,png,zip',
        ]);

        $format = $validated['format'] ?? 'pdf';

        // Get invites to download
        $query = Invite::where('batch_id', $batch->id)
            ->where('status', 'active');

        if (!empty($validated['invite_ids'])) {
            $query->whereIn('id', $validated['invite_ids']);
        }

        $invites = $query->get();

        if ($invites->isEmpty()) {
            return $this->error('No invitations to download', 400);
        }

        // Mark as downloaded
        foreach ($invites as $invite) {
            if (!$invite->downloaded_at) {
                $invite->update(['downloaded_at' => now()]);
                $batch->incrementDownloaded();
            }
        }

        // Generate download URL (would be handled by a job/service)
        // For now, return the data that would be used for PDF generation
        return $this->success([
            'download' => [
                'format' => $format,
                'count' => $invites->count(),
                'batch_name' => $batch->name,
                'event_name' => $batch->marketplaceEvent?->name,
                // In production, this would be a signed URL to download the generated file
                'download_url' => route('api.marketplace-client.organizer.invitations.download-file', [
                    'batch' => $batch->id,
                    'token' => encrypt([
                        'batch_id' => $batch->id,
                        'organizer_id' => $organizer->id,
                        'invite_ids' => $invites->pluck('id')->toArray(),
                        'expires_at' => now()->addHour()->timestamp,
                    ]),
                ]),
            ],
            'invites' => $invites->map(fn($invite) => $this->formatInvite($invite)),
        ]);
    }

    /**
     * Void specific invitations
     */
    public function void(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        $validated = $request->validate([
            'invite_ids' => 'required|array|min:1',
            'invite_ids.*' => 'integer|exists:invites,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $voidedCount = 0;

        foreach ($validated['invite_ids'] as $inviteId) {
            $invite = Invite::where('id', $inviteId)
                ->where('batch_id', $batch->id)
                ->where('status', 'active')
                ->first();

            if ($invite) {
                $invite->update([
                    'status' => 'voided',
                    'voided_at' => now(),
                    'void_reason' => $validated['reason'] ?? null,
                ]);
                $batch->incrementVoided();
                $voidedCount++;
            }
        }

        return $this->success([
            'voided_count' => $voidedCount,
            'batch' => $this->formatBatch($batch->fresh(['marketplaceEvent:id,name,slug,starts_at'])),
        ], "{$voidedCount} invitations voided");
    }

    /**
     * Get batch statistics
     */
    public function stats(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        return $this->success([
            'batch_id' => $batch->id,
            'name' => $batch->name,
            'stats' => [
                'planned' => $batch->qty_planned,
                'generated' => $batch->qty_generated,
                'emailed' => $batch->qty_emailed,
                'downloaded' => $batch->qty_downloaded,
                'opened' => $batch->qty_opened,
                'checked_in' => $batch->qty_checked_in,
                'voided' => $batch->qty_voided,
            ],
            'percentages' => [
                'emailed' => $batch->getEmailedPercentage(),
                'downloaded' => $batch->getDownloadedPercentage(),
                'completion' => $batch->getCompletionPercentage(),
            ],
        ]);
    }

    /**
     * Delete a batch (only if draft)
     */
    public function destroy(Request $request, int $batchId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $batch = InviteBatch::where('id', $batchId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$batch) {
            return $this->error('Batch not found', 404);
        }

        if ($batch->status !== 'draft') {
            return $this->error('Only draft batches can be deleted', 400);
        }

        $batch->delete();

        return $this->success(null, 'Batch deleted');
    }

    /**
     * Generate unique invite code
     */
    protected function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (Invite::where('code', $code)->exists());

        return $code;
    }

    /**
     * Format batch for response
     */
    protected function formatBatch(InviteBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'event' => $batch->marketplaceEvent ? [
                'id' => $batch->marketplaceEvent->id,
                'name' => $batch->marketplaceEvent->name,
                'slug' => $batch->marketplaceEvent->slug,
                'date' => $batch->marketplaceEvent->starts_at?->toIso8601String(),
            ] : null,
            'status' => $batch->status,
            'qty_planned' => $batch->qty_planned,
            'qty_generated' => $batch->qty_generated,
            'qty_emailed' => $batch->qty_emailed,
            'qty_downloaded' => $batch->qty_downloaded,
            'qty_checked_in' => $batch->qty_checked_in,
            'qty_voided' => $batch->qty_voided,
            'created_at' => $batch->created_at->toIso8601String(),
        ];
    }

    /**
     * Format batch with full details
     */
    protected function formatBatchDetailed(InviteBatch $batch): array
    {
        return array_merge($this->formatBatch($batch), [
            'options' => $batch->options,
            'percentages' => [
                'emailed' => $batch->getEmailedPercentage(),
                'downloaded' => $batch->getDownloadedPercentage(),
                'completion' => $batch->getCompletionPercentage(),
            ],
        ]);
    }

    /**
     * Format invite for response
     */
    protected function formatInvite(Invite $invite): array
    {
        return [
            'id' => $invite->id,
            'code' => $invite->code,
            'guest_name' => $invite->guest_name,
            'guest_email' => $invite->guest_email,
            'status' => $invite->status,
            'emailed_at' => $invite->emailed_at?->toIso8601String(),
            'downloaded_at' => $invite->downloaded_at?->toIso8601String(),
            'opened_at' => $invite->opened_at?->toIso8601String(),
            'checked_in_at' => $invite->checked_in_at?->toIso8601String(),
            'voided_at' => $invite->voided_at?->toIso8601String(),
            'created_at' => $invite->created_at->toIso8601String(),
        ];
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}

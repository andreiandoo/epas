<?php

namespace App\Services\Invitations;

use App\Models\InviteBatch;
use App\Models\Invite;
use App\Models\InviteLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Invite Batch Service
 *
 * Handles batch creation and CSV import of recipients
 */
class InviteBatchService
{
    public function __construct(
        protected TicketIssueAdapter $ticketAdapter
    ) {}

    /**
     * Create a new invitation batch
     *
     * @param array $data
     * @param User|null $creator
     * @return InviteBatch
     * @throws ValidationException
     */
    public function createBatch(array $data, ?User $creator = null): InviteBatch
    {
        // Validate input
        $validator = Validator::make($data, [
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'event_ref' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'qty_planned' => 'required|integer|min:1|max:10000',
            'template_id' => 'nullable|uuid|exists:ticket_templates,id',
            'options' => 'nullable|array',
            'options.watermark' => 'nullable|string|max:50',
            'options.seat_mode' => 'nullable|in:auto,manual,none',
            'options.notes' => 'nullable|string|max:1000',
            'options.send_immediately' => 'nullable|boolean',
            'options.expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Set defaults for options
        $options = $validated['options'] ?? [];
        $options['watermark'] = $options['watermark'] ?? 'INVITATION';
        $options['seat_mode'] = $options['seat_mode'] ?? 'none';

        DB::beginTransaction();

        try {
            // Create batch
            $batch = InviteBatch::create([
                'tenant_id' => $validated['tenant_id'],
                'event_ref' => $validated['event_ref'],
                'name' => $validated['name'],
                'qty_planned' => $validated['qty_planned'],
                'template_id' => $validated['template_id'] ?? null,
                'options' => $options,
                'status' => 'draft',
                'created_by' => $creator?->id,
            ]);

            // Generate invitations (without recipients)
            $this->generateInvitations($batch, $validated['qty_planned']);

            DB::commit();

            return $batch->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate N invitations for a batch
     *
     * @param InviteBatch $batch
     * @param int $quantity
     * @return void
     */
    protected function generateInvitations(InviteBatch $batch, int $quantity): void
    {
        for ($i = 0; $i < $quantity; $i++) {
            $invite = Invite::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'status' => 'created',
            ]);

            // Issue zero-value ticket
            $ticketData = $this->ticketAdapter->issueInviteTicket([
                'event_ref' => $batch->event_ref,
                'seat_ref' => null,
                'invite_ref' => $invite->invite_code,
            ]);

            $invite->update([
                'ticket_ref' => $ticketData['ticket_ref'],
                'qr_data' => $ticketData['qr_data'],
            ]);

            // Log generation
            InviteLog::logGenerate($invite);

            $batch->incrementGenerated();
        }
    }

    /**
     * Import recipients from CSV
     *
     * CSV format: name,email,phone,company,seat_ref
     *
     * @param InviteBatch $batch
     * @param string $csvPath
     * @param array $mapping Column mapping
     * @return array {imported, errors}
     * @throws \Exception
     */
    public function importRecipients(InviteBatch $batch, string $csvPath, array $mapping = []): array
    {
        if (!file_exists($csvPath)) {
            throw new \Exception("CSV file not found: {$csvPath}");
        }

        // Default column mapping
        $defaultMapping = [
            'name' => 0,
            'email' => 1,
            'phone' => 2,
            'company' => 3,
            'seat_ref' => 4,
        ];

        $mapping = array_merge($defaultMapping, $mapping);

        $imported = 0;
        $errors = [];
        $row = 0;

        // Open CSV
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new \Exception("Failed to open CSV file");
        }

        // Skip header row
        fgetcsv($handle);

        // Get unused invites
        $availableInvites = $batch->invites()
            ->whereNull('recipient')
            ->orderBy('created_at')
            ->get();

        $inviteIndex = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;

            try {
                // Extract data based on mapping
                $recipientData = [
                    'name' => $data[$mapping['name']] ?? null,
                    'email' => $data[$mapping['email']] ?? null,
                    'phone' => $data[$mapping['phone']] ?? null,
                    'company' => $data[$mapping['company']] ?? null,
                ];

                $seatRef = $data[$mapping['seat_ref']] ?? null;

                // Validate email
                if (!filter_var($recipientData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$row}: Invalid email address";
                    continue;
                }

                // Get next available invite
                if ($inviteIndex >= $availableInvites->count()) {
                    // No more invites available, create new one if allowed
                    if ($batch->qty_generated >= $batch->qty_planned) {
                        $errors[] = "Row {$row}: No more invitations available (consider increasing batch size)";
                        continue;
                    }

                    // Create additional invite
                    $invite = Invite::create([
                        'batch_id' => $batch->id,
                        'tenant_id' => $batch->tenant_id,
                        'status' => 'created',
                    ]);

                    $ticketData = $this->ticketAdapter->issueInviteTicket([
                        'event_ref' => $batch->event_ref,
                        'seat_ref' => $seatRef,
                        'invite_ref' => $invite->invite_code,
                    ]);

                    $invite->update([
                        'ticket_ref' => $ticketData['ticket_ref'],
                        'qr_data' => $ticketData['qr_data'],
                        'seat_ref' => $seatRef,
                        'recipient' => $recipientData,
                    ]);

                    InviteLog::logGenerate($invite);
                    $batch->incrementGenerated();
                } else {
                    // Use existing invite
                    $invite = $availableInvites[$inviteIndex];
                    $invite->setRecipient($recipientData);

                    if ($seatRef) {
                        $invite->update(['seat_ref' => $seatRef]);
                    }

                    InviteLog::logRecipientUpdate($invite, [], $recipientData);
                }

                $imported++;
                $inviteIndex++;
            } catch (\Exception $e) {
                $errors[] = "Row {$row}: " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Update batch status
     *
     * @param InviteBatch $batch
     * @param string $status
     * @return void
     */
    public function updateStatus(InviteBatch $batch, string $status): void
    {
        $batch->updateStatus($status);
    }

    /**
     * Cancel a batch (soft delete)
     *
     * @param InviteBatch $batch
     * @return void
     */
    public function cancelBatch(InviteBatch $batch): void
    {
        DB::beginTransaction();

        try {
            // Void all non-checked-in invites
            $batch->invites()
                ->where('status', '!=', 'checked_in')
                ->where('status', '!=', 'void')
                ->each(function (Invite $invite) {
                    $invite->markAsVoid();

                    if ($invite->ticket_ref) {
                        $this->ticketAdapter->voidTicket($invite->ticket_ref);
                    }

                    InviteLog::logVoid($invite, null, 'Batch cancelled');
                });

            // Update batch status
            $batch->updateStatus('cancelled');
            $batch->delete(); // Soft delete

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get batch statistics
     *
     * @param InviteBatch $batch
     * @return array
     */
    public function getBatchStats(InviteBatch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'qty_planned' => $batch->qty_planned,
            'qty_generated' => $batch->qty_generated,
            'qty_rendered' => $batch->qty_rendered,
            'qty_emailed' => $batch->qty_emailed,
            'qty_downloaded' => $batch->qty_downloaded,
            'qty_opened' => $batch->qty_opened,
            'qty_checked_in' => $batch->qty_checked_in,
            'qty_voided' => $batch->qty_voided,
            'completion_percentage' => $batch->getCompletionPercentage(),
            'emailed_percentage' => $batch->getEmailedPercentage(),
            'downloaded_percentage' => $batch->getDownloadedPercentage(),
            'created_at' => $batch->created_at->toIso8601String(),
            'updated_at' => $batch->updated_at->toIso8601String(),
        ];
    }
}

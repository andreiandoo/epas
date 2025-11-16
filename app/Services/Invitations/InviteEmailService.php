<?php

namespace App\Services\Invitations;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendInvitationEmailJob;

/**
 * Invite Email Service
 *
 * Handles email delivery for the Invitations microservice with:
 * - Chunked sending
 * - Queue-based processing
 * - Retry logic
 * - Delivery tracking
 */
class InviteEmailService
{
    /**
     * Send invitation emails for a batch
     *
     * @param string $batchId Batch ID to send emails for
     * @param array $options Additional options (chunk_size, delay, etc.)
     * @return array {queued: int, failed: int, errors: array}
     */
    public function sendBatchEmails(string $batchId, array $options = []): array
    {
        try {
            $chunkSize = $options['chunk_size'] ?? 100;
            $delay = $options['delay'] ?? 0;

            // Get all unsent invitations for this batch
            $invitations = DB::table('inv_invites')
                ->where('batch_id', $batchId)
                ->where('status', '!=', 'void')
                ->whereNull('emailed_at')
                ->get();

            if ($invitations->isEmpty()) {
                return [
                    'queued' => 0,
                    'failed' => 0,
                    'errors' => ['No pending invitations to send'],
                ];
            }

            $queued = 0;
            $failed = 0;
            $errors = [];

            // Process in chunks
            $chunks = $invitations->chunk($chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                // Calculate delay for this chunk (stagger sending)
                $chunkDelay = $delay + ($chunkIndex * 60); // 1 minute between chunks

                foreach ($chunk as $invitation) {
                    try {
                        // Validate email address
                        $recipientData = json_decode($invitation->recipient_data, true);
                        $email = $recipientData['email'] ?? null;

                        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $failed++;
                            $errors[] = "Invitation {$invitation->id}: Invalid email address";
                            continue;
                        }

                        // Queue email job
                        SendInvitationEmailJob::dispatch($invitation->id, $invitation->tenant_id)
                            ->delay(now()->addSeconds($chunkDelay))
                            ->onQueue('emails');

                        // Mark as queued
                        DB::table('inv_invites')
                            ->where('id', $invitation->id)
                            ->update([
                                'email_status' => 'pending',
                                'updated_at' => now(),
                            ]);

                        $queued++;

                    } catch (\Exception $e) {
                        $failed++;
                        $errors[] = "Invitation {$invitation->id}: " . $e->getMessage();

                        Log::error('Failed to queue invitation email', [
                            'invitation_id' => $invitation->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Update batch status
            DB::table('inv_batches')
                ->where('id', $batchId)
                ->update([
                    'status' => 'sending',
                    'updated_at' => now(),
                ]);

            return [
                'queued' => $queued,
                'failed' => $failed,
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send batch emails', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            return [
                'queued' => 0,
                'failed' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Send email for individual invitations
     *
     * @param array $invitationIds Array of invitation IDs
     * @param array $options Additional options
     * @return array {queued: int, failed: int, errors: array}
     */
    public function sendIndividualEmails(array $invitationIds, array $options = []): array
    {
        $delay = $options['delay'] ?? 0;
        $queued = 0;
        $failed = 0;
        $errors = [];

        foreach ($invitationIds as $invitationId) {
            try {
                $invitation = DB::table('inv_invites')
                    ->where('id', $invitationId)
                    ->first();

                if (!$invitation) {
                    $failed++;
                    $errors[] = "Invitation {$invitationId}: Not found";
                    continue;
                }

                if ($invitation->status === 'void') {
                    $failed++;
                    $errors[] = "Invitation {$invitationId}: Voided";
                    continue;
                }

                // Validate email address
                $recipientData = json_decode($invitation->recipient_data, true);
                $email = $recipientData['email'] ?? null;

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    $errors[] = "Invitation {$invitationId}: Invalid email address";
                    continue;
                }

                // Queue email job
                SendInvitationEmailJob::dispatch($invitationId, $invitation->tenant_id)
                    ->delay(now()->addSeconds($delay))
                    ->onQueue('emails');

                // Mark as queued
                DB::table('inv_invites')
                    ->where('id', $invitationId)
                    ->update([
                        'email_status' => 'pending',
                        'updated_at' => now(),
                    ]);

                $queued++;

            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Invitation {$invitationId}: " . $e->getMessage();

                Log::error('Failed to queue individual invitation email', [
                    'invitation_id' => $invitationId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'queued' => $queued,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Actually send the email (called by queue job)
     *
     * @param string $invitationId Invitation ID
     * @param string $tenantId Tenant ID
     * @return array {success: bool, message: string}
     */
    public function sendEmail(string $invitationId, string $tenantId): array
    {
        try {
            // Get invitation details
            $invitation = DB::table('inv_invites')
                ->where('id', $invitationId)
                ->first();

            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found',
                ];
            }

            if ($invitation->status === 'void') {
                return [
                    'success' => false,
                    'message' => 'Invitation is voided',
                ];
            }

            // Get recipient data
            $recipientData = json_decode($invitation->recipient_data, true);
            $email = $recipientData['email'] ?? null;
            $name = $recipientData['name'] ?? 'Guest';

            if (!$email) {
                return [
                    'success' => false,
                    'message' => 'No email address',
                ];
            }

            // Get batch and event details
            $batch = DB::table('inv_batches')
                ->where('id', $invitation->batch_id)
                ->first();

            $event = DB::table('events')
                ->where('id', $batch->event_ref ?? '')
                ->first();

            // Generate signed download URL
            $downloadUrl = $this->generateDownloadUrl($invitation);

            // Get tenant settings for email customization
            $tenantSettings = $this->getTenantEmailSettings($tenantId);

            // Send email using Laravel Mail
            Mail::send('emails.invitation', [
                'name' => $name,
                'invitation' => $invitation,
                'recipientData' => $recipientData,
                'batch' => $batch,
                'event' => $event,
                'downloadUrl' => $downloadUrl,
                'tenantSettings' => $tenantSettings,
            ], function ($message) use ($email, $name, $event, $tenantSettings) {
                $message->to($email, $name)
                    ->subject($tenantSettings['subject'] ?? "Your invitation to {$event->name ?? 'our event'}")
                    ->from(
                        $tenantSettings['from_email'] ?? config('mail.from.address'),
                        $tenantSettings['from_name'] ?? config('mail.from.name')
                    );
            });

            // Update invitation record
            DB::table('inv_invites')
                ->where('id', $invitationId)
                ->update([
                    'status' => 'emailed',
                    'email_status' => 'sent',
                    'emailed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Update batch statistics
            $this->updateBatchStatistics($invitation->batch_id);

            // Log the send
            $this->logEmailSent($invitationId, $email);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
            ]);

            // Update email status to failed
            DB::table('inv_invites')
                ->where('id', $invitationId)
                ->update([
                    'email_status' => 'failed',
                    'updated_at' => now(),
                ]);

            // Log the failure
            $this->logEmailFailed($invitationId, $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resend invitation email
     *
     * @param string $invitationId Invitation ID
     * @param string $tenantId Tenant ID
     * @return array {success: bool, message: string}
     */
    public function resendEmail(string $invitationId, string $tenantId): array
    {
        try {
            // Queue the resend
            SendInvitationEmailJob::dispatch($invitationId, $tenantId)
                ->onQueue('emails');

            // Log the resend
            DB::table('inv_logs')->insert([
                'invite_id' => $invitationId,
                'action' => 'resend',
                'actor' => 'system',
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Email queued for resending',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resend invitation email', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate signed download URL for invitation
     */
    protected function generateDownloadUrl($invitation): string
    {
        // Use URL signing for security
        return URL::temporarySignedRoute(
            'invitation.download',
            now()->addDays(30),
            [
                'id' => $invitation->id,
                'code' => $invitation->invite_code,
            ]
        );
    }

    /**
     * Get tenant-specific email settings
     */
    protected function getTenantEmailSettings(string $tenantId): array
    {
        $settings = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'invitation_email_settings')
            ->first();

        if ($settings && $settings->value) {
            return json_decode($settings->value, true);
        }

        return [
            'subject' => 'Your invitation',
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'footer_text' => 'Thank you for your attendance',
        ];
    }

    /**
     * Update batch email statistics
     */
    protected function updateBatchStatistics(string $batchId): void
    {
        $stats = DB::table('inv_invites')
            ->where('batch_id', $batchId)
            ->selectRaw('
                COUNT(CASE WHEN emailed_at IS NOT NULL THEN 1 END) as qty_emailed
            ')
            ->first();

        DB::table('inv_batches')
            ->where('id', $batchId)
            ->update([
                'qty_emailed' => $stats->qty_emailed ?? 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * Log successful email send
     */
    protected function logEmailSent(string $invitationId, string $email): void
    {
        DB::table('inv_logs')->insert([
            'invite_id' => $invitationId,
            'action' => 'email',
            'actor' => 'system',
            'details' => json_encode(['email' => $email]),
            'created_at' => now(),
        ]);
    }

    /**
     * Log failed email send
     */
    protected function logEmailFailed(string $invitationId, string $error): void
    {
        DB::table('inv_logs')->insert([
            'invite_id' => $invitationId,
            'action' => 'error',
            'actor' => 'system',
            'details' => json_encode(['error' => $error, 'type' => 'email_failed']),
            'created_at' => now(),
        ]);
    }
}

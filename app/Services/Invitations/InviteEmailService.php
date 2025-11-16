<?php

namespace App\Services\Invitations;

use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\InviteLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Invite Email Service
 *
 * Handles email delivery with queuing and chunking
 */
class InviteEmailService
{
    /**
     * Send email for a single invitation
     *
     * @param Invite $invite
     * @return bool
     */
    public function sendInviteEmail(Invite $invite): bool
    {
        if (!$invite->hasRecipient()) {
            return false;
        }

        $email = $invite->getRecipientEmail();

        if (!$email) {
            return false;
        }

        try {
            // TODO: Implement actual email sending with Laravel Mail
            // For now, this is a placeholder

            $data = [
                'invite_code' => $invite->invite_code,
                'recipient_name' => $invite->getRecipientName(),
                'event_name' => 'Event ' . $invite->batch->event_ref,
                'download_url' => $invite->getPublicDownloadUrl(),
                'batch_name' => $invite->batch->name,
            ];

            // Mail::to($email)->send(new InvitationEmail($data));

            $invite->recordSendAttempt(true);
            $invite->markAsEmailed();

            InviteLog::logEmail($invite, [
                'to' => $email,
                'subject' => "Your invitation to {$data['event_name']}",
                'status' => 'sent',
            ]);

            return true;
        } catch (\Exception $e) {
            $invite->recordSendAttempt(false, $e->getMessage());

            InviteLog::logError($invite, 'EMAIL_ERROR', $e->getMessage(), [
                'email' => $email,
            ]);

            return false;
        }
    }

    /**
     * Send emails for batch with chunking
     *
     * @param InviteBatch $batch
     * @param int $chunkSize
     * @return array {queued, failed}
     */
    public function sendBatchEmails(InviteBatch $batch, int $chunkSize = 100): array
    {
        $batch->updateStatus('sending');

        $queued = 0;
        $failed = 0;

        $invites = $batch->invites()
            ->whereNotNull('recipient')
            ->where('status', '!=', 'void')
            ->get();

        $chunks = $invites->chunk($chunkSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $invite) {
                if ($this->sendInviteEmail($invite)) {
                    $queued++;
                } else {
                    $failed++;
                }
            }

            // Add delay between chunks to avoid rate limiting
            if ($chunks->count() > 1) {
                sleep(1);
            }
        }

        $batch->updateStatus('completed');

        return [
            'queued' => $queued,
            'failed' => $failed,
        ];
    }

    /**
     * Resend email for an invitation
     *
     * @param Invite $invite
     * @return bool
     */
    public function resendEmail(Invite $invite): bool
    {
        if (!$invite->canResend()) {
            return false;
        }

        InviteLog::logResend($invite);

        return $this->sendInviteEmail($invite);
    }
}

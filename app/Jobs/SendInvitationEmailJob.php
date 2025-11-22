<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Invitations\InviteEmailService;

/**
 * Send Invitation Email Job
 *
 * Queue job for sending individual invitation emails with retry logic
 */
class SendInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $invitationId,
        public string $tenantId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InviteEmailService $emailService): void
    {
        $result = $emailService->sendEmail($this->invitationId, $this->tenantId);

        if (!$result['success']) {
            // Job will retry based on $tries and $backoff settings
            throw new \RuntimeException('Failed to send invitation email: ' . $result['message']);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('SendInvitationEmailJob failed permanently', [
            'invitation_id' => $this->invitationId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);

        // Mark email as permanently failed
        \DB::table('inv_invites')
            ->where('id', $this->invitationId)
            ->update([
                'email_status' => 'failed',
                'updated_at' => now(),
            ]);
    }
}

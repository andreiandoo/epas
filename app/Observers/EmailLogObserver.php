<?php

namespace App\Observers;

use App\Logging\SystemErrorRecorder;
use App\Models\EmailLog;

/**
 * Mirrors tenant-side email failures into system_errors. Counterpart to
 * MarketplaceEmailLogObserver but for the tenant email pipeline.
 */
class EmailLogObserver
{
    public function __construct(protected SystemErrorRecorder $recorder) {}

    public function updated(EmailLog $log): void
    {
        if (!$log->wasChanged('status')) {
            return;
        }
        if ($log->status !== 'failed') {
            return;
        }

        $this->recorder->record([
            'level' => 400,
            'channel' => 'mail',
            'source' => 'email_log',
            'message' => sprintf(
                'Email failed: → %s — %s',
                $log->recipient_email ?? '?',
                $log->error_message ?? '(no detail)'
            ),
            'context' => [
                'email_log_id' => $log->id,
                'tenant_id' => $log->tenant_id,
                'template_id' => $log->email_template_id,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'subject' => $log->subject,
            ],
        ]);
    }
}

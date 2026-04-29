<?php

namespace App\Observers;

use App\Logging\SystemErrorRecorder;
use App\Models\MarketplaceEmailLog;

/**
 * Mirrors marketplace email failures into system_errors so a single
 * dashboard surfaces all delivery problems alongside other categories.
 *
 * Only fires when status transitions into a failure-like state. Sent or
 * opened emails are not interesting.
 */
class MarketplaceEmailLogObserver
{
    public function __construct(protected SystemErrorRecorder $recorder) {}

    public function updated(MarketplaceEmailLog $log): void
    {
        if (!$log->wasChanged('status')) {
            return;
        }
        $newStatus = $log->status;
        if (!in_array($newStatus, ['failed', 'bounced'], true)) {
            return;
        }

        $this->recorder->record([
            'level' => 400,
            'channel' => 'marketplace',
            'source' => 'marketplace_email_log',
            'message' => sprintf(
                'Marketplace email %s: %s → %s — %s',
                $newStatus,
                $log->from_email ?? '?',
                $log->to_email ?? '?',
                $log->error_message ?? $log->subject ?? '(no detail)'
            ),
            'context' => [
                'marketplace_email_log_id' => $log->id,
                'marketplace_client_id' => $log->marketplace_client_id,
                'marketplace_organizer_id' => $log->marketplace_organizer_id,
                'marketplace_event_id' => $log->marketplace_event_id,
                'order_id' => $log->order_id,
                'template_slug' => $log->template_slug,
                'status' => $newStatus,
                'error_message' => $log->error_message,
                'subject' => $log->subject,
            ],
        ]);
    }
}

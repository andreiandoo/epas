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

        // A failed TRANSACTIONAL email (order confirmation, tickets, password
        // reset, invoice…) means a real user is stuck — surface it as ERROR.
        // A newsletter/marketing bounce is expected list hygiene → WARNING, so
        // the genuinely urgent ones don't drown in normal bounce noise.
        $isMarketing = str_starts_with((string) $log->template_slug, 'newsletter');
        $level = $isMarketing ? 300 : 400;

        $this->recorder->record([
            'level' => $level,
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

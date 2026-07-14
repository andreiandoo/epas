<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceEmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrevoWebhookController extends Controller
{
    /**
     * Handle Brevo webhook events for email tracking.
     *
     * Brevo sends POST requests with events like:
     * - delivered, soft_bounce, hard_bounce, complaint, unsubscribed
     * - opened (unique_opened), click
     *
     * Docs: https://developers.brevo.com/docs/how-to-use-webhooks
     */
    public function handle(Request $request)
    {
        $event = $request->input('event');
        $messageId = $request->input('message-id') ?? $request->input('messageId');
        $email = $request->input('email');
        $timestamp = $request->input('ts_event') ?? $request->input('date') ?? now()->timestamp;

        if (!$event || !$messageId) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing event or message-id'], 200);
        }

        // Clean message-id: Brevo may send with or without angle brackets
        $messageId = trim($messageId, '<>');

        // Find the email log by message_id
        $log = MarketplaceEmailLog::where('message_id', $messageId)
            ->orWhere('message_id', '<' . $messageId . '>')
            ->orWhere('message_id', 'like', '%' . $messageId . '%')
            ->first();

        if (!$log) {
            Log::channel('marketplace')->debug('Brevo webhook: message_id not found', [
                'event' => $event,
                'message_id' => $messageId,
                'email' => $email,
            ]);
            return response()->json(['status' => 'ignored', 'reason' => 'message_id not found'], 200);
        }

        $eventTime = is_numeric($timestamp) ? \Carbon\Carbon::createFromTimestamp($timestamp) : now();

        // Store raw event in metadata
        $metadata = $log->metadata ?? [];
        $metadata['brevo_events'][] = [
            'event' => $event,
            'timestamp' => $eventTime->toIso8601String(),
            'ip' => $request->input('ip') ?? null,
            'link' => $request->input('link') ?? null,
            'reason' => $request->input('reason') ?? null,
        ];

        $updates = ['metadata' => $metadata];

        switch ($event) {
            case 'delivered':
                if (!$log->delivered_at) {
                    $updates['delivered_at'] = $eventTime;
                    $updates['status'] = 'delivered';
                }
                break;

            case 'opened':
            case 'unique_opened':
            case 'first_opening':
                if (!$log->opened_at) {
                    $updates['opened_at'] = $eventTime;
                    $updates['status'] = 'opened';
                }
                break;

            case 'click':
                if (!$log->clicked_at) {
                    $updates['clicked_at'] = $eventTime;
                    $updates['status'] = 'clicked';
                }
                // Also mark as opened if not yet
                if (!$log->opened_at) {
                    $updates['opened_at'] = $eventTime;
                }
                break;

            case 'hard_bounce':
            case 'soft_bounce':
                if (!$log->bounced_at) {
                    $updates['bounced_at'] = $eventTime;
                    $updates['status'] = 'bounced';
                }
                break;

            case 'spam':
            case 'complaint':
                $updates['status'] = 'complained';
                $updates['bounced_at'] = $eventTime;
                break;

            case 'unsubscribed':
                $updates['status'] = 'unsubscribed';
                break;

            case 'blocked':
            case 'invalid_email':
            case 'error':
                $updates['status'] = 'failed';
                $updates['bounced_at'] = $eventTime;
                break;

            // Deferred (temporary retry) and loaded_by_proxy (Apple MPP / bot
            // prefetch — a false open) are intentionally NOT mapped to any
            // status change; we only keep them in the metadata trail above.
        }

        $log->update($updates);

        // Mirror bounces onto the linked newsletter recipient so (a) the
        // open/click tracking suppresses false opens on a bounced send and
        // (b) the address can be excluded from future sends. Newsletter logs
        // carry metadata->recipient_id; other logs skip this.
        $bounceEvents = ['hard_bounce', 'soft_bounce', 'spam', 'complaint', 'blocked', 'invalid_email', 'error'];
        if (in_array($event, $bounceEvents, true)) {
            $recipientId = is_array($log->metadata) ? ($log->metadata['recipient_id'] ?? null) : null;
            if ($recipientId) {
                $recipient = \App\Models\MarketplaceNewsletterRecipient::find($recipientId);
                if ($recipient && $recipient->status !== 'bounced') {
                    $recipient->update([
                        'status' => 'bounced',
                        'bounced_at' => $eventTime,
                        'error_message' => 'brevo:' . $event . ($request->input('reason') ? ':' . $request->input('reason') : ''),
                    ]);
                }
            }
        }

        // Keep the customer's deliverability state in sync so audience filters
        // exclude bad addresses and heal soft bounces on the next success.
        $customer = $this->resolveCustomer($log);
        if ($customer) {
            if (in_array($event, ['hard_bounce', 'blocked', 'invalid_email', 'spam', 'complaint', 'error'], true)) {
                $customer->markHardSuppressed('brevo_' . $event, $eventTime);
            } elseif ($event === 'soft_bounce') {
                $customer->markSoftBounce($eventTime);
            } elseif (in_array($event, ['delivered', 'opened', 'unique_opened', 'click'], true)) {
                // A later success proves a soft-bounced address works again.
                $customer->clearSoftBounce();
            }
        }

        Log::channel('marketplace')->info('Brevo webhook processed', [
            'event' => $event,
            'message_id' => $messageId,
            'email' => $email,
            'log_id' => $log->id,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Resolve the MarketplaceCustomer behind an email log — by FK when present,
     * otherwise by (marketplace, lowercased email).
     */
    protected function resolveCustomer(MarketplaceEmailLog $log): ?\App\Models\MarketplaceCustomer
    {
        if ($log->marketplace_customer_id) {
            return \App\Models\MarketplaceCustomer::find($log->marketplace_customer_id);
        }
        if ($log->to_email && $log->marketplace_client_id) {
            return \App\Models\MarketplaceCustomer::where('marketplace_client_id', $log->marketplace_client_id)
                ->whereRaw('lower(email) = ?', [strtolower($log->to_email)])
                ->first();
        }
        return null;
    }
}

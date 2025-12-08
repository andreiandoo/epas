<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Integrations\WhatsAppCloud\WhatsAppCloudWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API Webhook Controller
 *
 * Handles webhook verification and incoming events from Meta/Facebook
 */
class WhatsAppCloudWebhookController extends Controller
{
    /**
     * Verify webhook subscription (GET request from Facebook)
     *
     * Facebook sends:
     * - hub.mode = 'subscribe'
     * - hub.challenge = random string to return
     * - hub.verify_token = the token we configured in Facebook
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('WhatsApp Cloud webhook verification attempt', [
            'mode' => $mode,
            'token_received' => $token ? 'yes' : 'no',
            'challenge' => $challenge ? 'yes' : 'no',
        ]);

        // Get verify token from settings
        $settings = Setting::first();
        $verifyToken = $settings?->whatsapp_cloud_verify_token;

        if (!$verifyToken) {
            Log::error('WhatsApp Cloud verify token not configured in settings');
            return response('Verify token not configured', 500);
        }

        // Validate the request
        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp Cloud webhook verified successfully');
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp Cloud webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken ? 'yes' : 'no',
        ]);

        return response('Verification failed', 403);
    }

    /**
     * Handle incoming webhook events (POST request from Facebook)
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();

        Log::info('WhatsApp Cloud webhook received', [
            'object' => $payload['object'] ?? 'unknown',
        ]);

        // Verify this is a WhatsApp webhook
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response('Not a WhatsApp webhook', 400);
        }

        // Process entries
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $this->processChange($entry['id'] ?? null, $change);
            }
        }

        // Always return 200 quickly to acknowledge receipt
        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Process a single change from the webhook
     */
    protected function processChange(?string $wabaId, array $change): void
    {
        $field = $change['field'] ?? 'unknown';
        $value = $change['value'] ?? [];

        Log::info('Processing WhatsApp webhook change', [
            'waba_id' => $wabaId,
            'field' => $field,
        ]);

        try {
            // Store the webhook event for processing
            // Note: connection_id is nullable - will be resolved during processing
            WhatsAppCloudWebhookEvent::create([
                'connection_id' => null,
                'event_type' => $field,
                'payload' => array_merge($value, ['waba_id' => $wabaId]),
                'status' => 'pending',
            ]);

            // Handle different event types
            match ($field) {
                'messages' => $this->handleMessages($wabaId, $value),
                'message_status' => $this->handleMessageStatus($wabaId, $value),
                'message_template_status_update' => $this->handleTemplateStatus($wabaId, $value),
                default => Log::info("Unhandled WhatsApp webhook field: {$field}"),
            };
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp webhook', [
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle incoming messages
     */
    protected function handleMessages(?string $wabaId, array $value): void
    {
        $messages = $value['messages'] ?? [];
        $contacts = $value['contacts'] ?? [];
        $metadata = $value['metadata'] ?? [];

        foreach ($messages as $message) {
            Log::info('WhatsApp message received', [
                'from' => $message['from'] ?? 'unknown',
                'type' => $message['type'] ?? 'unknown',
                'phone_number_id' => $metadata['phone_number_id'] ?? null,
            ]);

            // TODO: Route message to appropriate tenant based on phone_number_id
            // and process the message (auto-reply, store, etc.)
        }
    }

    /**
     * Handle message status updates (sent, delivered, read, failed)
     */
    protected function handleMessageStatus(?string $wabaId, array $value): void
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? 'unknown';
            $timestamp = $status['timestamp'] ?? null;
            $recipientId = $status['recipient_id'] ?? null;

            Log::info('WhatsApp message status update', [
                'message_id' => $messageId,
                'status' => $statusValue,
                'recipient' => $recipientId,
            ]);

            // TODO: Update message status in database
            // WhatsAppCloudMessage::where('meta_message_id', $messageId)
            //     ->update(['status' => $statusValue, 'status_updated_at' => now()]);
        }
    }

    /**
     * Handle template status updates
     */
    protected function handleTemplateStatus(?string $wabaId, array $value): void
    {
        $event = $value['event'] ?? 'unknown';
        $templateName = $value['message_template_name'] ?? null;
        $templateId = $value['message_template_id'] ?? null;
        $reason = $value['reason'] ?? null;

        Log::info('WhatsApp template status update', [
            'template_name' => $templateName,
            'template_id' => $templateId,
            'event' => $event,
            'reason' => $reason,
        ]);

        // TODO: Update template status
        // WhatsAppCloudTemplate::where('meta_template_id', $templateId)
        //     ->update(['status' => $event]);
    }
}

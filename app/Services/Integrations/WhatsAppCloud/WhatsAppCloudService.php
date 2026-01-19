<?php

namespace App\Services\Integrations\WhatsAppCloud;

use App\Models\Integrations\WhatsAppCloud\WhatsAppCloudConnection;
use App\Models\Integrations\WhatsAppCloud\WhatsAppCloudContact;
use App\Models\Integrations\WhatsAppCloud\WhatsAppCloudMessage;
use App\Models\Integrations\WhatsAppCloud\WhatsAppCloudTemplate;
use App\Models\Integrations\WhatsAppCloud\WhatsAppCloudWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class WhatsAppCloudService
{
    protected string $apiVersion = 'v18.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    // ==========================================
    // CONNECTION MANAGEMENT
    // ==========================================

    public function createConnection(
        int $tenantId,
        string $phoneNumberId,
        string $phoneNumber,
        string $businessAccountId,
        string $accessToken,
        ?string $displayName = null
    ): WhatsAppCloudConnection {
        $connection = WhatsAppCloudConnection::create([
            'tenant_id' => $tenantId,
            'phone_number_id' => $phoneNumberId,
            'phone_number' => $phoneNumber,
            'business_account_id' => $businessAccountId,
            'access_token' => $accessToken,
            'display_name' => $displayName,
            'webhook_verify_token' => bin2hex(random_bytes(16)),
            'status' => 'pending',
        ]);

        // Verify connection by getting phone number info
        if ($this->verifyConnection($connection)) {
            $connection->update([
                'status' => 'active',
                'verified_at' => now(),
            ]);
        }

        return $connection->fresh();
    }

    public function verifyConnection(WhatsAppCloudConnection $connection): bool
    {
        try {
            $response = $this->makeRequest(
                $connection,
                'GET',
                "/{$connection->phone_number_id}"
            );

            return isset($response['id']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getConnection(int $tenantId): ?WhatsAppCloudConnection
    {
        return WhatsAppCloudConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    // ==========================================
    // TEXT MESSAGES
    // ==========================================

    public function sendTextMessage(
        WhatsAppCloudConnection $connection,
        string $recipientPhone,
        string $text,
        ?string $correlationType = null,
        ?int $correlationId = null,
        ?string $replyToMessageId = null
    ): WhatsAppCloudMessage {
        $message = WhatsAppCloudMessage::create([
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'recipient_phone' => $recipientPhone,
            'message_type' => 'text',
            'content' => $text,
            'context_message_id' => $replyToMessageId,
            'correlation_type' => $correlationType,
            'correlation_id' => $correlationId,
            'status' => 'pending',
        ]);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($recipientPhone),
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        if ($replyToMessageId) {
            $payload['context'] = ['message_id' => $replyToMessageId];
        }

        try {
            $response = $this->makeRequest(
                $connection,
                'POST',
                "/{$connection->phone_number_id}/messages",
                $payload
            );

            if (isset($response['messages'][0]['id'])) {
                $message->update([
                    'wamid' => $response['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $message->addStatusHistory('sent');
                $this->updateContactConversation($connection, $recipientPhone);
            }
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $message->fresh();
    }

    // ==========================================
    // TEMPLATE MESSAGES
    // ==========================================

    public function sendTemplateMessage(
        WhatsAppCloudConnection $connection,
        string $recipientPhone,
        string $templateName,
        string $language = 'en',
        array $parameters = [],
        ?string $correlationType = null,
        ?int $correlationId = null
    ): WhatsAppCloudMessage {
        $message = WhatsAppCloudMessage::create([
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'recipient_phone' => $recipientPhone,
            'message_type' => 'template',
            'template_name' => $templateName,
            'template_params' => $parameters,
            'correlation_type' => $correlationType,
            'correlation_id' => $correlationId,
            'status' => 'pending',
        ]);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($recipientPhone),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (!empty($parameters)) {
            $payload['template']['components'] = $this->buildTemplateComponents($parameters);
        }

        try {
            $response = $this->makeRequest(
                $connection,
                'POST',
                "/{$connection->phone_number_id}/messages",
                $payload
            );

            if (isset($response['messages'][0]['id'])) {
                $message->update([
                    'wamid' => $response['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $message->addStatusHistory('sent');
            }
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
        }

        return $message->fresh();
    }

    protected function buildTemplateComponents(array $parameters): array
    {
        $components = [];

        if (isset($parameters['header'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => $this->formatParameters($parameters['header']),
            ];
        }

        if (isset($parameters['body'])) {
            $components[] = [
                'type' => 'body',
                'parameters' => $this->formatParameters($parameters['body']),
            ];
        }

        if (isset($parameters['buttons'])) {
            foreach ($parameters['buttons'] as $index => $button) {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => $button['type'] ?? 'quick_reply',
                    'index' => $index,
                    'parameters' => $this->formatParameters($button['parameters'] ?? []),
                ];
            }
        }

        return $components;
    }

    protected function formatParameters(array $params): array
    {
        return array_map(function ($param) {
            if (is_string($param)) {
                return ['type' => 'text', 'text' => $param];
            }
            return $param;
        }, $params);
    }

    // ==========================================
    // MEDIA MESSAGES
    // ==========================================

    public function sendImageMessage(
        WhatsAppCloudConnection $connection,
        string $recipientPhone,
        string $imageUrl,
        ?string $caption = null
    ): WhatsAppCloudMessage {
        return $this->sendMediaMessage($connection, $recipientPhone, 'image', [
            'link' => $imageUrl,
            'caption' => $caption,
        ]);
    }

    public function sendDocumentMessage(
        WhatsAppCloudConnection $connection,
        string $recipientPhone,
        string $documentUrl,
        ?string $filename = null,
        ?string $caption = null
    ): WhatsAppCloudMessage {
        return $this->sendMediaMessage($connection, $recipientPhone, 'document', [
            'link' => $documentUrl,
            'filename' => $filename,
            'caption' => $caption,
        ]);
    }

    protected function sendMediaMessage(
        WhatsAppCloudConnection $connection,
        string $recipientPhone,
        string $type,
        array $mediaData
    ): WhatsAppCloudMessage {
        $message = WhatsAppCloudMessage::create([
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'recipient_phone' => $recipientPhone,
            'message_type' => $type,
            'content' => $mediaData['caption'] ?? null,
            'media' => $mediaData,
            'status' => 'pending',
        ]);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($recipientPhone),
            'type' => $type,
            $type => array_filter($mediaData),
        ];

        try {
            $response = $this->makeRequest(
                $connection,
                'POST',
                "/{$connection->phone_number_id}/messages",
                $payload
            );

            if (isset($response['messages'][0]['id'])) {
                $message->update([
                    'wamid' => $response['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $message->fresh();
    }

    // ==========================================
    // TEMPLATE MANAGEMENT
    // ==========================================

    public function syncTemplates(WhatsAppCloudConnection $connection): Collection
    {
        $response = $this->makeRequest(
            $connection,
            'GET',
            "/{$connection->business_account_id}/message_templates",
            ['limit' => 250]
        );

        $templates = collect($response['data'] ?? []);

        foreach ($templates as $templateData) {
            WhatsAppCloudTemplate::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'template_name' => $templateData['name'],
                    'language' => $templateData['language'],
                ],
                [
                    'template_id' => $templateData['id'],
                    'category' => $templateData['category'],
                    'status' => strtolower($templateData['status']),
                    'components' => $templateData['components'] ?? [],
                    'approved_at' => $templateData['status'] === 'APPROVED' ? now() : null,
                ]
            );
        }

        return $connection->templates()->get();
    }

    public function createTemplate(
        WhatsAppCloudConnection $connection,
        string $name,
        string $category,
        array $components,
        string $language = 'en'
    ): WhatsAppCloudTemplate {
        $response = $this->makeRequest(
            $connection,
            'POST',
            "/{$connection->business_account_id}/message_templates",
            [
                'name' => $name,
                'category' => strtoupper($category),
                'components' => $components,
                'language' => $language,
            ]
        );

        return WhatsAppCloudTemplate::create([
            'connection_id' => $connection->id,
            'template_name' => $name,
            'template_id' => $response['id'] ?? null,
            'language' => $language,
            'category' => $category,
            'status' => 'pending',
            'components' => $components,
            'submitted_at' => now(),
        ]);
    }

    // ==========================================
    // CONTACTS
    // ==========================================

    protected function updateContactConversation(WhatsAppCloudConnection $connection, string $phone): void
    {
        $waId = $this->normalizePhone($phone);

        WhatsAppCloudContact::updateOrCreate(
            ['connection_id' => $connection->id, 'wa_id' => $waId],
            [
                'phone_number' => $phone,
                'last_message_at' => now(),
                'conversation_expires_at' => now()->addHours(24),
            ]
        );
    }

    public function getOrCreateContact(WhatsAppCloudConnection $connection, string $phone, ?string $name = null): WhatsAppCloudContact
    {
        $waId = $this->normalizePhone($phone);

        return WhatsAppCloudContact::firstOrCreate(
            ['connection_id' => $connection->id, 'wa_id' => $waId],
            [
                'phone_number' => $phone,
                'profile_name' => $name,
            ]
        );
    }

    // ==========================================
    // WEBHOOK HANDLING
    // ==========================================

    public function verifyWebhook(string $mode, string $token, string $challenge, WhatsAppCloudConnection $connection): ?string
    {
        if ($mode === 'subscribe' && $token === $connection->webhook_verify_token) {
            return $challenge;
        }
        return null;
    }

    public function processWebhook(array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (!$phoneNumberId) {
                    continue;
                }

                $connection = WhatsAppCloudConnection::where('phone_number_id', $phoneNumberId)->first();
                if (!$connection) {
                    continue;
                }

                // Log webhook event
                $event = WhatsAppCloudWebhookEvent::create([
                    'connection_id' => $connection->id,
                    'event_type' => $change['field'] ?? 'messages',
                    'payload' => $change,
                    'status' => 'pending',
                ]);

                try {
                    // Process messages
                    if (isset($value['messages'])) {
                        foreach ($value['messages'] as $messageData) {
                            $this->processIncomingMessage($connection, $messageData, $value['contacts'][0] ?? null);
                        }
                    }

                    // Process status updates
                    if (isset($value['statuses'])) {
                        foreach ($value['statuses'] as $statusData) {
                            $this->processStatusUpdate($connection, $statusData);
                        }
                    }

                    $event->markAsProcessed();
                } catch (\Exception $e) {
                    $event->markAsFailed($e->getMessage());
                }
            }
        }
    }

    protected function processIncomingMessage(WhatsAppCloudConnection $connection, array $messageData, ?array $contact): void
    {
        $waId = $messageData['from'];

        // Update or create contact
        $contactRecord = $this->getOrCreateContact(
            $connection,
            $waId,
            $contact['profile']['name'] ?? null
        );
        $contactRecord->update([
            'last_message_at' => now(),
            'conversation_expires_at' => now()->addHours(24),
        ]);

        // Store message
        WhatsAppCloudMessage::create([
            'connection_id' => $connection->id,
            'wamid' => $messageData['id'],
            'direction' => 'inbound',
            'recipient_phone' => $waId,
            'message_type' => $messageData['type'],
            'content' => $messageData['text']['body'] ?? $messageData['caption'] ?? null,
            'media' => $this->extractMediaData($messageData),
            'context_message_id' => $messageData['context']['id'] ?? null,
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
    }

    protected function processStatusUpdate(WhatsAppCloudConnection $connection, array $statusData): void
    {
        $message = WhatsAppCloudMessage::where('wamid', $statusData['id'])->first();
        if (!$message) {
            return;
        }

        $status = $statusData['status'];
        $timestamp = isset($statusData['timestamp'])
            ? \Carbon\Carbon::createFromTimestamp($statusData['timestamp'])
            : now();

        $updates = ['status' => $status];

        switch ($status) {
            case 'sent':
                $updates['sent_at'] = $timestamp;
                break;
            case 'delivered':
                $updates['delivered_at'] = $timestamp;
                break;
            case 'read':
                $updates['read_at'] = $timestamp;
                break;
            case 'failed':
                $updates['error_code'] = $statusData['errors'][0]['code'] ?? null;
                $updates['error_message'] = $statusData['errors'][0]['message'] ?? null;
                break;
        }

        $message->update($updates);
        $message->addStatusHistory($status, $timestamp->toIso8601String());
    }

    protected function extractMediaData(array $messageData): ?array
    {
        $type = $messageData['type'];
        if (!in_array($type, ['image', 'document', 'audio', 'video', 'sticker'])) {
            return null;
        }

        $media = $messageData[$type] ?? [];
        return [
            'id' => $media['id'] ?? null,
            'mime_type' => $media['mime_type'] ?? null,
            'filename' => $media['filename'] ?? null,
            'sha256' => $media['sha256'] ?? null,
        ];
    }

    // ==========================================
    // BUSINESS USE CASES
    // ==========================================

    public function sendOrderConfirmation(
        WhatsAppCloudConnection $connection,
        string $phone,
        int $orderId,
        string $orderNumber,
        float $total,
        string $currency = 'USD'
    ): WhatsAppCloudMessage {
        return $this->sendTemplateMessage(
            $connection,
            $phone,
            'order_confirmation',
            'en',
            [
                'body' => [$orderNumber, number_format($total, 2), $currency],
            ],
            'order',
            $orderId
        );
    }

    public function sendTicketConfirmation(
        WhatsAppCloudConnection $connection,
        string $phone,
        int $ticketId,
        string $eventName,
        string $ticketCode,
        string $eventDate
    ): WhatsAppCloudMessage {
        return $this->sendTemplateMessage(
            $connection,
            $phone,
            'ticket_confirmation',
            'en',
            [
                'body' => [$eventName, $ticketCode, $eventDate],
            ],
            'ticket',
            $ticketId
        );
    }

    public function sendEventReminder(
        WhatsAppCloudConnection $connection,
        string $phone,
        string $eventName,
        string $eventDate,
        string $eventTime,
        ?string $location = null
    ): WhatsAppCloudMessage {
        return $this->sendTemplateMessage(
            $connection,
            $phone,
            'event_reminder',
            'en',
            [
                'body' => array_filter([$eventName, $eventDate, $eventTime, $location]),
            ]
        );
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function makeRequest(
        WhatsAppCloudConnection $connection,
        string $method,
        string $endpoint,
        array $data = []
    ): array {
        $url = "{$this->baseUrl}/{$this->apiVersion}{$endpoint}";

        $request = Http::withToken($connection->access_token);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            $error = $response->json('error') ?? [];
            throw new \Exception(
                $error['message'] ?? 'WhatsApp API request failed',
                $error['code'] ?? $response->status()
            );
        }

        $connection->update(['last_used_at' => now()]);

        return $response->json() ?? [];
    }

    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove leading + if present
        return ltrim($phone, '+');
    }
}

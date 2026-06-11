<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppOptIn;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSchedule;
use App\Services\WhatsApp\Adapters\BspAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

/**
 * WhatsApp Service
 *
 * Manages WhatsApp messaging lifecycle:
 * - Order confirmations (idempotent)
 * - Event reminders (D-7, D-3, D-1)
 * - Promo campaigns
 * - Template management
 * - Opt-in/opt-out handling
 */
class WhatsAppService
{
    protected array $adapters = [];
    protected ?BspAdapterInterface $defaultAdapter = null;

    /**
     * Register BSP adapter
     */
    public function registerAdapter(string $key, BspAdapterInterface $adapter): void
    {
        $this->adapters[$key] = $adapter;

        if ($key === 'default' || $this->defaultAdapter === null) {
            $this->defaultAdapter = $adapter;
        }
    }

    /**
     * Get adapter for tenant (with authentication)
     */
    protected function getAdapter(string $tenantId): BspAdapterInterface
    {
        // Use default adapter for now
        // In production, load tenant-specific adapter type from config
        $adapter = $this->defaultAdapter ?? $this->adapters['mock'] ?? null;

        if (!$adapter) {
            throw new \Exception('No BSP adapter configured');
        }

        // Load and decrypt tenant credentials
        $credentials = $this->getTenantCredentials($tenantId);
        if ($credentials) {
            $adapter->authenticate($credentials);
        }

        return $adapter;
    }

    /**
     * Get tenant BSP credentials from secure storage
     */
    protected function getTenantCredentials(string $tenantId): ?array
    {
        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'whatsapp_credentials')
            ->first();

        if (!$config || !$config->value) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($config->value), true);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt WhatsApp credentials', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send order confirmation (idempotent)
     */
    public function sendOrderConfirmation(string $tenantId, string $orderRef, array $orderData): array
    {
        $templateName = $orderData['template_name'] ?? 'order_confirmation';

        // Check idempotency: already sent?
        if (WhatsAppMessage::alreadyExists($tenantId, $orderRef, $templateName)) {
            $existing = WhatsAppMessage::where('tenant_id', $tenantId)
                ->where('correlation_ref', $orderRef)
                ->where('template_name', $templateName)
                ->first();

            return [
                'success' => true,
                'message_id' => $existing->id,
                'status' => 'already_sent',
                'message' => 'Order confirmation already sent',
            ];
        }

        // Normalize phone
        $phone = WhatsAppOptIn::normalizePhone(
            $orderData['customer_phone'],
            $orderData['country_code'] ?? '+40'
        );

        // Check opt-in
        if (!WhatsAppOptIn::hasOptedIn($tenantId, $phone)) {
            Log::warning('Order confirmation skipped: no opt-in', [
                'tenant_id' => $tenantId,
                'order_ref' => $orderRef,
                'phone' => $phone,
            ]);

            return [
                'success' => false,
                'message' => 'Customer has not opted in to WhatsApp',
                'fallback' => 'email', // Suggest fallback to email
            ];
        }

        // Load template
        $template = WhatsAppTemplate::approved($tenantId)
            ->where('name', $templateName)
            ->first();

        if (!$template) {
            return [
                'success' => false,
                'message' => "Template '{$templateName}' not found or not approved",
            ];
        }

        // Prepare variables
        $variables = $this->prepareOrderVariables($orderData);

        // Create message record
        $message = WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'type' => WhatsAppMessage::TYPE_ORDER_CONFIRM,
            'to_phone' => $phone,
            'template_name' => $templateName,
            'variables' => $variables,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'correlation_ref' => $orderRef,
        ]);

        // Send via BSP
        try {
            $adapter = $this->getAdapter($tenantId);
            $result = $adapter->sendTemplate($phone, $templateName, $variables);

            if ($result['success']) {
                $message->markAsSent($result['message_id'], $result['cost']);

                // Deduct cost from tenant balance (if tracking)
                $this->deductBalance($tenantId, $result['cost'] ?? 0);

                Log::info('Order confirmation sent', [
                    'tenant_id' => $tenantId,
                    'order_ref' => $orderRef,
                    'message_id' => $result['message_id'],
                ]);

                return [
                    'success' => true,
                    'message_id' => $message->id,
                    'bsp_message_id' => $result['message_id'],
                    'status' => 'sent',
                ];
            } else {
                $message->markAsFailed($result['error_code'], $result['error_message']);

                return [
                    'success' => false,
                    'message' => $result['error_message'],
                    'error_code' => $result['error_code'],
                ];
            }

        } catch (\Exception $e) {
            $message->markAsFailed('EXCEPTION', $e->getMessage());

            Log::error('Order confirmation failed', [
                'tenant_id' => $tenantId,
                'order_ref' => $orderRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Schedule event reminders (D-7, D-3, D-1)
     */
    public function scheduleReminders(string $tenantId, string $orderRef, array $eventData): array
    {
        // Get tenant timezone
        $timezone = $this->getTenantTimezone($tenantId);

        // Parse event start time
        $eventStartAt = new \DateTime($eventData['event_start_at'], new \DateTimeZone($timezone));

        // Prepare recipient data
        $phone = WhatsAppOptIn::normalizePhone(
            $eventData['customer_phone'],
            $eventData['country_code'] ?? '+40'
        );

        $recipientData = [
            'order_ref' => $orderRef,
            'phone' => $phone,
            'template_name' => $eventData['template_name'] ?? 'event_reminder',
            'variables' => $this->prepareReminderVariables($eventData),
        ];

        // Create reminder schedules (idempotent)
        $schedules = WhatsAppSchedule::createReminders(
            $tenantId,
            $orderRef,
            $eventStartAt,
            $recipientData,
            $timezone
        );

        Log::info('Event reminders scheduled', [
            'tenant_id' => $tenantId,
            'order_ref' => $orderRef,
            'count' => count($schedules),
            'event_start_at' => $eventStartAt->format('Y-m-d H:i:s T'),
        ]);

        return [
            'success' => true,
            'scheduled_count' => count($schedules),
            'reminders' => array_map(function ($schedule) {
                return [
                    'type' => $schedule->message_type,
                    'run_at' => $schedule->run_at->toIso8601String(),
                ];
            }, $schedules),
        ];
    }

    /**
     * Send promo campaign to segment
     */
    public function sendPromo(string $tenantId, array $campaignData): array
    {
        $templateName = $campaignData['template_name'];
        $recipients = $campaignData['recipients'] ?? [];
        $variablesBase = $campaignData['variables'] ?? [];
        $dryRun = $campaignData['dry_run'] ?? false;

        // Load template
        $template = WhatsAppTemplate::approved($tenantId)
            ->where('name', $templateName)
            ->first();

        if (!$template) {
            return [
                'success' => false,
                'message' => "Template '{$templateName}' not found or not approved",
            ];
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        // Apply rate limiting
        $rateLimits = $this->getRateLimits($tenantId);
        $adapter = $this->getAdapter($tenantId);

        foreach ($recipients as $recipient) {
            // Normalize phone
            $phone = WhatsAppOptIn::normalizePhone($recipient['phone']);

            // Check opt-in
            if (!WhatsAppOptIn::hasOptedIn($tenantId, $phone)) {
                $skipped++;
                continue;
            }

            // Merge base variables with recipient-specific variables
            $variables = array_merge($variablesBase, $recipient['variables'] ?? []);

            if ($dryRun) {
                $results[] = [
                    'phone' => $phone,
                    'status' => 'dry_run',
                    'variables' => $variables,
                ];
                continue;
            }

            // Create message record
            $message = WhatsAppMessage::create([
                'tenant_id' => $tenantId,
                'type' => WhatsAppMessage::TYPE_PROMO,
                'to_phone' => $phone,
                'template_name' => $templateName,
                'variables' => $variables,
                'status' => WhatsAppMessage::STATUS_QUEUED,
                'correlation_ref' => $campaignData['campaign_id'] ?? null,
            ]);

            // Send via BSP
            try {
                $result = $adapter->sendTemplate($phone, $templateName, $variables);

                if ($result['success']) {
                    $message->markAsSent($result['message_id'], $result['cost']);
                    $this->deductBalance($tenantId, $result['cost'] ?? 0);
                    $sent++;

                    $results[] = [
                        'phone' => $phone,
                        'status' => 'sent',
                        'message_id' => $result['message_id'],
                    ];
                } else {
                    $message->markAsFailed($result['error_code'], $result['error_message']);
                    $failed++;

                    $results[] = [
                        'phone' => $phone,
                        'status' => 'failed',
                        'error' => $result['error_message'],
                    ];
                }

            } catch (\Exception $e) {
                $message->markAsFailed('EXCEPTION', $e->getMessage());
                $failed++;

                $results[] = [
                    'phone' => $phone,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }

            // Throttle based on rate limits
            if ($sent % $rateLimits['batch_size'] === 0) {
                usleep($rateLimits['delay_ms'] * 1000);
            }
        }

        return [
            'success' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'results' => $results,
        ];
    }

    /**
     * Process scheduled reminders (called by cron job)
     */
    public function processScheduledReminders(int $limit = 50): array
    {
        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $schedules = WhatsAppSchedule::pending()
            ->limit($limit)
            ->get();

        foreach ($schedules as $schedule) {
            $processed++;

            // Check opt-in
            $phone = $schedule->payload['phone'];
            if (!WhatsAppOptIn::hasOptedIn($schedule->tenant_id, $phone)) {
                $schedule->markAsSkipped('No opt-in');
                $skipped++;
                continue;
            }

            // Check if order is still valid (not refunded/cancelled)
            if ($this->isOrderCancelled($schedule->tenant_id, $schedule->correlation_ref)) {
                $schedule->markAsSkipped('Order cancelled/refunded');
                $skipped++;
                continue;
            }

            // Send reminder
            try {
                $adapter = $this->getAdapter($schedule->tenant_id);
                $result = $adapter->sendTemplate(
                    $phone,
                    $schedule->payload['template_name'],
                    $schedule->payload['variables']
                );

                if ($result['success']) {
                    // Create message record
                    $message = WhatsAppMessage::create([
                        'tenant_id' => $schedule->tenant_id,
                        'type' => WhatsAppMessage::TYPE_REMINDER,
                        'to_phone' => $phone,
                        'template_name' => $schedule->payload['template_name'],
                        'variables' => $schedule->payload['variables'],
                        'status' => WhatsAppMessage::STATUS_SENT,
                        'bsp_message_id' => $result['message_id'],
                        'correlation_ref' => $schedule->correlation_ref,
                        'sent_at' => now(),
                        'cost' => $result['cost'],
                    ]);

                    $this->deductBalance($schedule->tenant_id, $result['cost'] ?? 0);

                    $schedule->markAsRun([
                        'message_id' => $message->id,
                        'bsp_message_id' => $result['message_id'],
                    ]);

                    $sent++;
                } else {
                    $schedule->markAsFailed($result['error_message']);
                    $failed++;
                }

            } catch (\Exception $e) {
                $schedule->markAsFailed($e->getMessage());
                $failed++;

                Log::error('Reminder failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Handle webhook from BSP
     */
    public function handleWebhook(string $tenantId, array $payload): array
    {
        $adapter = $this->getAdapter($tenantId);
        $event = $adapter->webhookHandler($payload);

        if ($event['type'] === 'message_status' && $event['message_id']) {
            $message = WhatsAppMessage::where('tenant_id', $tenantId)
                ->where('bsp_message_id', $event['message_id'])
                ->first();

            if ($message) {
                switch ($event['status']) {
                    case 'delivered':
                        $message->markAsDelivered();
                        break;
                    case 'read':
                        $message->markAsRead();
                        break;
                    case 'failed':
                        $message->markAsFailed($event['error_code'], $event['error_message']);
                        break;
                }

                return [
                    'success' => true,
                    'message' => 'Webhook processed',
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Webhook ignored',
        ];
    }

    /**
     * Prepare order variables for template
     */
    protected function prepareOrderVariables(array $orderData): array
    {
        return [
            'first_name' => $orderData['customer_first_name'] ?? '',
            'last_name' => $orderData['customer_last_name'] ?? '',
            'order_code' => $orderData['order_ref'] ?? '',
            'event_name' => $orderData['event_name'] ?? '',
            'event_date' => $orderData['event_date'] ?? '',
            'venue_name' => $orderData['venue_name'] ?? '',
            'ticket_count' => $orderData['ticket_count'] ?? 1,
            'total_amount' => $orderData['total_amount'] ?? '',
            'download_url' => $orderData['download_url'] ?? '',
        ];
    }

    /**
     * Prepare reminder variables for template
     */
    protected function prepareReminderVariables(array $eventData): array
    {
        return [
            'first_name' => $eventData['customer_first_name'] ?? '',
            'event_name' => $eventData['event_name'] ?? '',
            'event_date' => $eventData['event_date'] ?? '',
            'event_time' => $eventData['event_time'] ?? '',
            'venue_name' => $eventData['venue_name'] ?? '',
            'venue_address' => $eventData['venue_address'] ?? '',
        ];
    }

    /**
     * Get tenant timezone
     */
    protected function getTenantTimezone(string $tenantId): string
    {
        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'timezone')
            ->value('value');

        return $config ?? 'Europe/Bucharest';
    }

    /**
     * Check if order is cancelled/refunded
     */
    protected function isOrderCancelled(string $tenantId, string $orderRef): bool
    {
        // Mock implementation - in production, check orders table
        return false;
    }

    /**
     * Get rate limits for tenant
     */
    protected function getRateLimits(string $tenantId): array
    {
        return [
            'batch_size' => 10,
            'delay_ms' => 100, // 100ms delay between batches
        ];
    }

    /**
     * Deduct balance from tenant (cost tracking)
     */
    protected function deductBalance(string $tenantId, float $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        // Mock implementation - in production, deduct from prepaid balance
        Log::info('WhatsApp cost deducted', [
            'tenant_id' => $tenantId,
            'cost' => $cost,
        ]);
    }

    /**
     * Get statistics for tenant
     */
    public function getStats(string $tenantId, int $days = 30): array
    {
        $stats = WhatsAppMessage::forTenant($tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('type, status, COUNT(*) as count, SUM(cost) as total_cost')
            ->groupBy(['type', 'status'])
            ->get();

        $result = [];
        foreach ($stats as $stat) {
            $result[$stat->type][$stat->status] = [
                'count' => $stat->count,
                'cost' => (float) $stat->total_cost,
            ];
        }

        return $result;
    }
}

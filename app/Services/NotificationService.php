<?php

namespace App\Services;

use App\Models\TenantNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Tenant Notification Service
 *
 * Handles creating and sending notifications to tenants across multiple channels
 */
class NotificationService
{
    /**
     * Create and send a notification
     */
    public function notify(
        string $tenantId,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): TenantNotification {
        // Create notification in database
        $notification = TenantNotification::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'priority' => $options['priority'] ?? TenantNotification::PRIORITY_MEDIUM,
            'title' => $title,
            'message' => $message,
            'data' => $options['data'] ?? null,
            'action_url' => $options['action_url'] ?? null,
            'action_text' => $options['action_text'] ?? null,
            'channels' => $options['channels'] ?? ['database', 'email'],
            'related_type' => $options['related_type'] ?? null,
            'related_id' => $options['related_id'] ?? null,
        ]);

        // Send via requested channels
        $channels = $notification->channels ?? [];

        if (in_array('email', $channels)) {
            $this->sendEmail($notification);
        }

        if (in_array('whatsapp', $channels)) {
            $this->sendWhatsApp($notification);
        }

        $notification->update(['sent_at' => now()]);

        Log::info('Notification created', [
            'notification_id' => $notification->id,
            'tenant_id' => $tenantId,
            'type' => $type,
            'priority' => $notification->priority,
        ]);

        return $notification;
    }

    /**
     * Send email notification
     */
    protected function sendEmail(TenantNotification $notification): void
    {
        try {
            // Get tenant email
            $tenantEmail = $this->getTenantEmail($notification->tenant_id);

            if (!$tenantEmail) {
                Log::warning('No email found for tenant', ['tenant_id' => $notification->tenant_id]);
                return;
            }

            // Send email
            Mail::send('emails.tenant-notification', [
                'notification' => $notification,
            ], function ($message) use ($notification, $tenantEmail) {
                $message->to($tenantEmail)
                    ->subject($notification->title);

                if ($notification->isUrgent()) {
                    $message->priority(1); // High priority
                }
            });

            $notification->update(['sent_email' => true]);

            Log::info('Email notification sent', [
                'notification_id' => $notification->id,
                'tenant_id' => $notification->tenant_id,
                'email' => $tenantEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send WhatsApp notification
     */
    protected function sendWhatsApp(TenantNotification $notification): void
    {
        try {
            // Get tenant phone
            $tenantPhone = $this->getTenantPhone($notification->tenant_id);

            if (!$tenantPhone) {
                Log::warning('No phone found for tenant', ['tenant_id' => $notification->tenant_id]);
                return;
            }

            // Use WhatsApp service to send
            $whatsAppService = app(\App\Services\WhatsApp\WhatsAppService::class);

            // TODO: Send via WhatsApp (requires template approval)
            // For now, just log it
            Log::info('WhatsApp notification would be sent', [
                'notification_id' => $notification->id,
                'tenant_id' => $notification->tenant_id,
                'phone' => $tenantPhone,
            ]);

            $notification->update(['sent_whatsapp' => true]);

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get tenant email address
     */
    protected function getTenantEmail(string $tenantId): ?string
    {
        // TODO: Get from tenants table when available
        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'notification_email')
            ->value('value');

        return $config ?? null;
    }

    /**
     * Get tenant phone number
     */
    protected function getTenantPhone(string $tenantId): ?string
    {
        // TODO: Get from tenants table when available
        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'notification_phone')
            ->value('value');

        return $config ?? null;
    }

    /**
     * Notify about microservice expiring soon
     */
    public function notifyMicroserviceExpiring(string $tenantId, array $subscriptionData): void
    {
        $microserviceName = $this->getMicroserviceName($subscriptionData['microservice_id']);
        $daysRemaining = now()->diffInDays($subscriptionData['expires_at']);

        $this->notify(
            $tenantId,
            TenantNotification::TYPE_MICROSERVICE_EXPIRING,
            "Microservice Expiring Soon: {$microserviceName}",
            "Your subscription to {$microserviceName} will expire in {$daysRemaining} days on " .
            date('d M Y', strtotime($subscriptionData['expires_at'])) . ". Please renew to continue using this service.",
            [
                'priority' => TenantNotification::PRIORITY_HIGH,
                'data' => [
                    'microservice_id' => $subscriptionData['microservice_id'],
                    'microservice_name' => $microserviceName,
                    'expires_at' => $subscriptionData['expires_at'],
                    'days_remaining' => $daysRemaining,
                ],
                'action_url' => '/admin/microservices/' . $subscriptionData['microservice_id'] . '/renew',
                'action_text' => 'Renew Now',
                'channels' => ['database', 'email'],
                'related_type' => 'App\Models\TenantMicroservice',
                'related_id' => $subscriptionData['id'] ?? null,
            ]
        );
    }

    /**
     * Notify about microservice suspended
     */
    public function notifyMicroserviceSuspended(string $tenantId, array $subscriptionData): void
    {
        $microserviceName = $this->getMicroserviceName($subscriptionData['microservice_id']);

        $this->notify(
            $tenantId,
            TenantNotification::TYPE_MICROSERVICE_SUSPENDED,
            "Microservice Suspended: {$microserviceName}",
            "Your subscription to {$microserviceName} has been suspended due to expiration. Please renew to restore access.",
            [
                'priority' => TenantNotification::PRIORITY_URGENT,
                'data' => [
                    'microservice_id' => $subscriptionData['microservice_id'],
                    'microservice_name' => $microserviceName,
                ],
                'action_url' => '/admin/microservices/' . $subscriptionData['microservice_id'] . '/renew',
                'action_text' => 'Renew Now',
                'channels' => ['database', 'email'],
                'related_type' => 'App\Models\TenantMicroservice',
                'related_id' => $subscriptionData['id'] ?? null,
            ]
        );
    }

    /**
     * Notify about eFactura rejection
     */
    public function notifyEFacturaRejected(string $tenantId, array $queueData): void
    {
        $this->notify(
            $tenantId,
            TenantNotification::TYPE_EFACTURA_REJECTED,
            "eFactura Rejected by ANAF",
            "Invoice #{$queueData['invoice_id']} was rejected by ANAF. Reason: {$queueData['error_message']}. Please review and resubmit.",
            [
                'priority' => TenantNotification::PRIORITY_HIGH,
                'data' => [
                    'queue_id' => $queueData['id'],
                    'invoice_id' => $queueData['invoice_id'],
                    'error_message' => $queueData['error_message'],
                ],
                'action_url' => '/admin/efactura/queue/' . $queueData['id'],
                'action_text' => 'View Details',
                'channels' => ['database', 'email'],
            ]
        );
    }

    /**
     * Notify about WhatsApp credits low
     */
    public function notifyWhatsAppCreditsLow(string $tenantId, int $remainingCredits): void
    {
        $this->notify(
            $tenantId,
            TenantNotification::TYPE_WHATSAPP_CREDITS_LOW,
            "WhatsApp Credits Running Low",
            "Your WhatsApp message credits are running low. You have {$remainingCredits} credits remaining. Please top up to continue sending messages.",
            [
                'priority' => TenantNotification::PRIORITY_MEDIUM,
                'data' => [
                    'remaining_credits' => $remainingCredits,
                ],
                'action_url' => '/admin/whatsapp/credits/topup',
                'action_text' => 'Top Up Credits',
                'channels' => ['database', 'email'],
            ]
        );
    }

    /**
     * Notify about invitation batch completed
     */
    public function notifyInvitationBatchCompleted(string $tenantId, array $batchData): void
    {
        $this->notify(
            $tenantId,
            TenantNotification::TYPE_INVITATION_BATCH_COMPLETED,
            "Invitation Batch Completed",
            "Your invitation batch '{$batchData['name']}' has been processed. " .
            "{$batchData['qty_emailed']} invitations sent successfully.",
            [
                'priority' => TenantNotification::PRIORITY_LOW,
                'data' => [
                    'batch_id' => $batchData['id'],
                    'batch_name' => $batchData['name'],
                    'qty_emailed' => $batchData['qty_emailed'],
                    'qty_total' => $batchData['qty_planned'],
                ],
                'action_url' => '/admin/invitations/batch/' . $batchData['id'],
                'action_text' => 'View Batch',
                'channels' => ['database'],
            ]
        );
    }

    /**
     * Get microservice name by ID
     */
    protected function getMicroserviceName(int $microserviceId): string
    {
        $microservice = DB::table('microservices')
            ->where('id', $microserviceId)
            ->value('name');

        return $microservice ?? 'Unknown Microservice';
    }

    /**
     * Get unread count for tenant
     */
    public function getUnreadCount(string $tenantId): int
    {
        return TenantNotification::forTenant($tenantId)
            ->unread()
            ->count();
    }

    /**
     * Mark all as read for tenant
     */
    public function markAllAsRead(string $tenantId): void
    {
        TenantNotification::forTenant($tenantId)
            ->unread()
            ->update([
                'status' => TenantNotification::STATUS_READ,
                'read_at' => now(),
            ]);
    }
}

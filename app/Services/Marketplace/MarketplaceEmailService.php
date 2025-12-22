<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceEmailLog;
use App\Models\Marketplace\MarketplaceEmailTemplate;
use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\TenantMailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * MarketplaceEmailService
 *
 * Handles sending and logging emails for marketplace tenants.
 * Uses marketplace-specific email templates when available.
 */
class MarketplaceEmailService
{
    public function __construct(
        protected TenantMailService $tenantMailService
    ) {}

    /**
     * Send an email using a marketplace template.
     *
     * @param Tenant $tenant The marketplace tenant
     * @param string $trigger The event trigger type
     * @param string $recipientEmail The recipient email address
     * @param string $recipientName The recipient name
     * @param array $variables Template variables
     * @param string $recipientType The recipient type (customer, organizer, admin)
     * @param MarketplaceOrganizer|null $organizer Related organizer
     * @return bool Whether the email was sent successfully
     */
    public function sendTemplate(
        Tenant $tenant,
        string $trigger,
        string $recipientEmail,
        string $recipientName,
        array $variables = [],
        string $recipientType = MarketplaceEmailLog::RECIPIENT_CUSTOMER,
        ?MarketplaceOrganizer $organizer = null
    ): bool {
        // Find template
        $template = MarketplaceEmailTemplate::findForTrigger($tenant->id, $trigger);

        if (!$template) {
            Log::warning('No active email template found', [
                'tenant_id' => $tenant->id,
                'trigger' => $trigger,
            ]);
            return false;
        }

        // Add common variables
        $variables['marketplace_name'] = $tenant->public_name ?? $tenant->name;

        // Process template
        $processed = $template->processTemplate($variables);

        return $this->send(
            tenant: $tenant,
            recipientEmail: $recipientEmail,
            recipientName: $recipientName,
            subject: $processed['subject'],
            body: $processed['body'],
            recipientType: $recipientType,
            templateId: $template->id,
            organizerId: $organizer?->id,
            metadata: ['trigger' => $trigger, 'variables' => array_keys($variables)]
        );
    }

    /**
     * Send an email and log it.
     *
     * @param Tenant $tenant The marketplace tenant
     * @param string $recipientEmail The recipient email address
     * @param string $recipientName The recipient name
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $recipientType The recipient type
     * @param int|null $templateId The template ID used
     * @param int|null $organizerId Related organizer ID
     * @param array $metadata Additional metadata
     * @return bool Whether the email was sent successfully
     */
    public function send(
        Tenant $tenant,
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $body,
        string $recipientType = MarketplaceEmailLog::RECIPIENT_CUSTOMER,
        ?int $templateId = null,
        ?int $organizerId = null,
        array $metadata = []
    ): bool {
        try {
            // Send email using tenant's mail configuration
            $this->tenantMailService->send($tenant, function ($message) use ($recipientEmail, $recipientName, $subject, $body) {
                $message->to($recipientEmail, $recipientName)
                    ->subject($subject)
                    ->html($this->wrapInEmailLayout($body));
            });

            // Log successful send
            MarketplaceEmailLog::logSent(
                tenantId: $tenant->id,
                recipientEmail: $recipientEmail,
                recipientName: $recipientName,
                subject: $subject,
                body: $body,
                recipientType: $recipientType,
                templateId: $templateId,
                organizerId: $organizerId,
                metadata: $metadata
            );

            Log::info('Marketplace email sent', [
                'tenant_id' => $tenant->id,
                'recipient' => $recipientEmail,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Exception $e) {
            // Log failed send
            MarketplaceEmailLog::logFailed(
                tenantId: $tenant->id,
                recipientEmail: $recipientEmail,
                recipientName: $recipientName,
                subject: $subject,
                body: $body,
                errorMessage: $e->getMessage(),
                recipientType: $recipientType,
                templateId: $templateId,
                organizerId: $organizerId,
                metadata: $metadata
            );

            Log::error('Failed to send marketplace email', [
                'tenant_id' => $tenant->id,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send organizer approval notification.
     */
    public function sendOrganizerApproved(MarketplaceOrganizer $organizer): bool
    {
        $tenant = $organizer->tenant;
        $adminUser = $organizer->adminUsers()->first();

        if (!$adminUser) {
            return false;
        }

        return $this->sendTemplate(
            tenant: $tenant,
            trigger: 'organizer_approved',
            recipientEmail: $adminUser->email,
            recipientName: $adminUser->name,
            variables: [
                'organizer_name' => $organizer->name,
                'organizer_email' => $adminUser->email,
                'company_name' => $organizer->company_name,
                'login_url' => url('/organizer'),
            ],
            recipientType: MarketplaceEmailLog::RECIPIENT_ORGANIZER,
            organizer: $organizer
        );
    }

    /**
     * Send organizer rejection notification.
     */
    public function sendOrganizerRejected(MarketplaceOrganizer $organizer, string $reason): bool
    {
        $tenant = $organizer->tenant;
        $adminUser = $organizer->adminUsers()->first();

        if (!$adminUser) {
            return false;
        }

        return $this->sendTemplate(
            tenant: $tenant,
            trigger: 'organizer_rejected',
            recipientEmail: $adminUser->email,
            recipientName: $adminUser->name,
            variables: [
                'organizer_name' => $organizer->name,
                'organizer_email' => $adminUser->email,
                'company_name' => $organizer->company_name,
                'rejection_reason' => $reason,
            ],
            recipientType: MarketplaceEmailLog::RECIPIENT_ORGANIZER,
            organizer: $organizer
        );
    }

    /**
     * Send organizer suspension notification.
     */
    public function sendOrganizerSuspended(MarketplaceOrganizer $organizer, string $reason): bool
    {
        $tenant = $organizer->tenant;
        $adminUser = $organizer->adminUsers()->first();

        if (!$adminUser) {
            return false;
        }

        return $this->sendTemplate(
            tenant: $tenant,
            trigger: 'organizer_suspended',
            recipientEmail: $adminUser->email,
            recipientName: $adminUser->name,
            variables: [
                'organizer_name' => $organizer->name,
                'organizer_email' => $adminUser->email,
                'company_name' => $organizer->company_name,
                'suspension_reason' => $reason,
            ],
            recipientType: MarketplaceEmailLog::RECIPIENT_ORGANIZER,
            organizer: $organizer
        );
    }

    /**
     * Send new order notification to organizer.
     */
    public function sendOrganizerNewOrder(Order $order): bool
    {
        $organizer = $order->organizer;
        if (!$organizer) {
            return false;
        }

        $tenant = $order->tenant;
        $adminUsers = $organizer->adminUsers()->get();

        $success = true;
        foreach ($adminUsers as $adminUser) {
            $result = $this->sendTemplate(
                tenant: $tenant,
                trigger: 'organizer_new_order',
                recipientEmail: $adminUser->email,
                recipientName: $adminUser->name,
                variables: [
                    'organizer_name' => $organizer->name,
                    'order_number' => $order->order_number ?? $order->id,
                    'order_total' => number_format($order->total, 2) . ' ' . ($order->currency ?? 'RON'),
                    'customer_name' => $order->customer_name ?? 'Customer',
                    'event_name' => $order->tickets->first()?->ticketType?->event?->title ?? 'Event',
                    'ticket_count' => $order->tickets->count(),
                    'organizer_revenue' => number_format($order->organizer_revenue ?? 0, 2) . ' ' . ($order->currency ?? 'RON'),
                ],
                recipientType: MarketplaceEmailLog::RECIPIENT_ORGANIZER,
                organizer: $organizer
            );

            if (!$result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Wrap email content in a basic layout.
     */
    protected function wrapInEmailLayout(string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 { color: #111; }
        a { color: #2563eb; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    {$content}
</body>
</html>
HTML;
    }
}

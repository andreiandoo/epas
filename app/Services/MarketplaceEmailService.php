<?php

namespace App\Services;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceEmailLog;
use App\Models\Order;
use App\Models\MarketplaceRefundRequest;
use App\Models\MarketplaceOrganizer;
use App\Support\EmailRouting;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Log;

class MarketplaceEmailService
{
    protected MarketplaceClient $marketplace;

    public function __construct(MarketplaceClient $marketplace)
    {
        $this->marketplace = $marketplace;
    }

    /**
     * Send a notification to the marketplace admin recipient configured in
     * marketplace_clients.settings.admin_notifications.{$settingKey}.
     *
     * Falls back to a hardcoded subject/HTML when no DB template with $slug
     * exists, so notifications work out of the box without seeders.
     */
    public function sendAdminNotification(
        string $slug,
        string $settingKey,
        array $variables,
        string $fallbackSubject,
        string $fallbackHtml
    ): bool {
        $settings = $this->marketplace->settings ?? [];
        $recipient = $settings['admin_notifications'][$settingKey] ?? '';

        if (!$recipient) {
            return false;
        }

        // Admin notifications are transactional content (order receipts,
        // refund alerts, stock warnings) — route them through the
        // platform-owned transactional provider (ambilet.ro server) so
        // they don't ride on the bulk newsletter Brevo account. Either
        // transport being configured is enough; sendTransactionalEmail
        // transparently falls back primary→transactional or vice versa.
        $hasTransactional = $this->marketplace->hasTransactionalMailConfigured();
        $hasPrimary = $this->marketplace->hasMailConfigured();
        if (!$hasTransactional && !$hasPrimary) {
            Log::warning('Admin notification skipped — neither transactional nor primary SMTP configured', [
                'slug' => $slug,
                'marketplace_id' => $this->marketplace->id,
            ]);
            return false;
        }

        $template = MarketplaceEmailTemplate::where('marketplace_client_id', $this->marketplace->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($template) {
            $rendered = $template->render($variables);
            $subject = $rendered['subject'];
            $html = $rendered['body_html'];
        } else {
            $subject = $this->renderFallback($fallbackSubject, $variables);
            $html = $this->renderFallback($fallbackHtml, $variables);
        }

        // Route through transactional when configured (ambilet.ro). Prior
        // version used primary getMailTransport() directly which on
        // Ambilet pointed at the Brevo bulk account — every admin
        // notification silently failed once the Brevo cred required
        // reactivation. Same call shape sendTemplatedEmail uses.
        $useTransactional = $hasTransactional;
        $fromAddress = $useTransactional
            ? $this->marketplace->getTransactionalEmailFromAddress()
            : $this->marketplace->getEmailFromAddress();
        $fromName = $useTransactional
            ? $this->marketplace->getTransactionalEmailFromName()
            : $this->marketplace->getEmailFromName();

        // Always create a MarketplaceEmailLog row so the admin can see
        // attempts + failures in the EmailLogs UI. Previously this code
        // path bypassed logging entirely — the operator only learned of
        // delivery failures by noticing missing emails in their inbox.
        $log = MarketplaceEmailLog::create([
            'marketplace_client_id' => $this->marketplace->id,
            'marketplace_customer_id' => null,
            'template_slug' => $slug,
            'to_email' => $recipient,
            'to_name' => null,
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'subject' => $subject,
            'body_html' => $html,
            'status' => 'pending',
        ]);

        try {
            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->to(new Address($recipient))
                ->subject($subject)
                ->html($html);

            if ($useTransactional) {
                $result = $this->marketplace->sendTransactionalEmail($email);
                if ($result['success']) {
                    $log->markSent($result['message_id'] ?? null);
                    return true;
                }
                $log->markFailed($result['error'] ?? 'Transactional send failed');
                Log::error('Admin notification send failed (transactional)', [
                    'slug' => $slug,
                    'recipient' => $recipient,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return false;
            }

            // No transactional configured — primary is the only option.
            $transport = $this->marketplace->getMailTransport();
            if (!$transport) {
                $log->markFailed('Could not create primary SMTP transport');
                return false;
            }
            $sent = $transport->send($email);
            $log->markSent($sent?->getMessageId());
            return true;
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('Admin notification send failed', [
                'slug' => $slug,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Replace {{var}} placeholders in a fallback template string.
     */
    protected function renderFallback(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $template);
        }
        return $template;
    }

    /**
     * Send ticket purchase confirmation
     */
    public function sendTicketPurchaseEmail(Order $order): bool
    {
        $customer = $order->marketplaceCustomer;
        $event = $order->marketplaceEvent ?? $order->event;

        $variables = [
            'customer_name' => $customer?->full_name ?? $order->customer_name ?? 'Customer',
            'customer_email' => $customer?->email ?? $order->customer_email,
            'order_number' => $order->order_number ?? str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'event_name' => $event?->name ?? 'Event',
            'event_date' => $event?->start_date?->format('d M Y, H:i') ?? '',
            'event_venue' => $event?->venue?->name ?? '',
            'tickets_count' => $order->tickets()->count(),
            'total_amount' => number_format($order->total, 2) . ' ' . ($order->currency ?? 'RON'),
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'ticket_purchase',
            $customer?->email ?? $order->customer_email,
            $customer?->full_name ?? $order->customer_name,
            $variables,
            $customer?->id
        );
    }

    /**
     * Send welcome email to new customer
     */
    public function sendWelcomeEmail(MarketplaceCustomer $customer): bool
    {
        $variables = [
            'customer_name' => $customer->full_name,
            'customer_email' => $customer->email,
            'marketplace_name' => $this->marketplace->name,
            'login_url' => $this->marketplace->domain ? "https://{$this->marketplace->domain}/login" : '',
        ];

        return $this->sendTemplatedEmail(
            'welcome',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    /**
     * Send points earned notification
     */
    public function sendPointsEarnedEmail(MarketplaceCustomer $customer, int $points, string $reason, int $totalPoints): bool
    {
        $variables = [
            'customer_name' => $customer->full_name,
            'points_amount' => $points,
            'reason' => $reason,
            'total_points' => $totalPoints,
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'points_earned',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    /**
     * Send refund approved email
     */
    public function sendRefundApprovedEmail(MarketplaceRefundRequest $refund): bool
    {
        $customer = $refund->customer;
        $order = $refund->order;

        $variables = [
            'customer_name' => $customer->full_name,
            'order_number' => $order->order_number ?? str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'refund_amount' => number_format($refund->approved_amount, 2) . ' RON',
            'refund_reference' => $refund->refund_reference ?? $refund->request_number,
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'refund_approved',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    /**
     * Send refund rejected email
     */
    public function sendRefundRejectedEmail(MarketplaceRefundRequest $refund): bool
    {
        $customer = $refund->customer;
        $order = $refund->order;

        $variables = [
            'customer_name' => $customer->full_name,
            'order_number' => $order->order_number ?? str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'rejection_reason' => $refund->admin_notes ?? 'Your refund request could not be approved.',
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'refund_rejected',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    /**
     * Send refund processed email (admin-initiated refund)
     */
    public function sendRefundProcessedEmail(MarketplaceRefundRequest $refund): bool
    {
        $customer = $refund->customer;
        $order = $refund->order;

        if (!$customer?->email) return false;

        // Build refunded tickets list
        $refundedTickets = '';
        $items = $refund->refundItems()->with('ticket.ticketType')->get();
        foreach ($items as $item) {
            $ticket = $item->ticket;
            if (!$ticket) continue;
            $code = $ticket->code ?? '—';
            $typeName = $ticket->ticketType?->name ?? 'Bilet';
            $amount = number_format($item->refund_amount, 2);
            $refundedTickets .= "• {$typeName} (#{$code}) — {$amount} {$order->currency}\n";
        }

        $variables = [
            'customer_name' => $customer->full_name ?? $customer->name ?? $order->customer_name ?? '',
            'order_number' => $order->order_number ?? str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'refund_amount' => number_format($refund->approved_amount ?? $refund->requested_amount, 2) . ' ' . ($order->currency ?? 'RON'),
            'refund_reason' => $refund->reason ?? '',
            'refund_reference' => $refund->reference ?? '',
            'refunded_tickets' => $refundedTickets ?: 'Toate biletele din comandă',
            'marketplace_name' => $this->marketplace->name ?? $this->marketplace->company_name ?? '',
            'marketplace_email' => $this->marketplace->contact_email ?? '',
        ];

        return $this->sendTemplatedEmail(
            'refund_processed',
            $customer->email,
            $customer->full_name ?? $customer->name ?? '',
            $variables,
            $customer->id ?? null
        );
    }

    /**
     * Send event reminder
     */
    public function sendEventReminderEmail(MarketplaceCustomer $customer, Order $order): bool
    {
        $event = $order->marketplaceEvent ?? $order->event;

        $variables = [
            'customer_name' => $customer->full_name,
            'event_name' => $event?->name ?? 'Event',
            'event_date' => $event?->start_date?->format('d M Y, H:i') ?? '',
            'event_venue' => $event?->venue?->name ?? '',
            'tickets_count' => $order->tickets()->count(),
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'event_reminder',
            $customer->email,
            $customer->full_name,
            $variables,
            $customer->id
        );
    }

    /**
     * Send payout notification to organizer
     */
    public function sendOrganizerPayoutEmail(MarketplaceOrganizer $organizer, float $amount, string $reference, string $period): bool
    {
        $variables = [
            'organizer_name' => $organizer->name,
            'payout_amount' => number_format($amount, 2) . ' RON',
            'payout_reference' => $reference,
            'period' => $period,
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'organizer_payout',
            $organizer->email,
            $organizer->name,
            $variables
        );
    }

    /**
     * Send report to organizer
     */
    public function sendOrganizerReportEmail(MarketplaceOrganizer $organizer, string $period, array $stats): bool
    {
        $variables = [
            'organizer_name' => $organizer->name,
            'period' => $period,
            'total_sales' => number_format($stats['total_sales'] ?? 0, 2) . ' RON',
            'commission' => number_format($stats['commission'] ?? 0, 2) . ' RON',
            'net_amount' => number_format($stats['net_amount'] ?? 0, 2) . ' RON',
            'orders_count' => $stats['orders_count'] ?? 0,
            'tickets_count' => $stats['tickets_count'] ?? 0,
            'marketplace_name' => $this->marketplace->name,
        ];

        return $this->sendTemplatedEmail(
            'organizer_report',
            $organizer->email,
            $organizer->name,
            $variables
        );
    }

    /**
     * Send a templated email
     */
    public function sendTemplatedEmail(
        string $templateSlug,
        string $toEmail,
        ?string $toName = null,
        array $variables = [],
        ?int $customerId = null
    ): bool {
        // Get template
        $template = MarketplaceEmailTemplate::where('marketplace_client_id', $this->marketplace->id)
            ->where('slug', $templateSlug)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::warning("Email template '{$templateSlug}' not found for marketplace {$this->marketplace->id}");
            return false;
        }

        // Decide which transport this template uses. Transactional slugs route to
        // the platform-owned provider; everything else stays on the primary one.
        // Both transactional getters fall back to the primary when not configured.
        $useTransactional = EmailRouting::isTransactional($templateSlug);
        $fromAddress = $useTransactional
            ? $this->marketplace->getTransactionalEmailFromAddress()
            : $this->marketplace->getEmailFromAddress();
        $fromName = $useTransactional
            ? $this->marketplace->getTransactionalEmailFromName()
            : $this->marketplace->getEmailFromName();

        // For transactional sends we don't require the primary to be configured
        // because the secondary may be set instead; either of the two transports
        // is enough. Non-transactional still requires the primary.
        if (!$useTransactional && !$this->marketplace->hasMailConfigured()) {
            Log::warning("SMTP not configured for marketplace {$this->marketplace->id}");
            return false;
        }
        if ($useTransactional
            && !$this->marketplace->hasTransactionalMailConfigured()
            && !$this->marketplace->hasMailConfigured()
        ) {
            Log::warning("Neither transactional nor primary SMTP configured for marketplace {$this->marketplace->id}");
            return false;
        }

        // Render template
        $rendered = $template->render($variables);
        $subject = $rendered['subject'];
        $bodyHtml = $rendered['body_html'];
        $bodyText = $rendered['body_text'] ?? null;

        // Create log entry
        $log = MarketplaceEmailLog::create([
            'marketplace_client_id' => $this->marketplace->id,
            'marketplace_customer_id' => $customerId,
            'template_slug' => $templateSlug,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'status' => 'pending',
        ]);

        try {
            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->to(new Address($toEmail, $toName ?? ''))
                ->subject($subject)
                ->html($bodyHtml);

            if ($bodyText) {
                $email->text($bodyText);
            }

            if ($useTransactional) {
                // Try transactional, fall back to primary on exception.
                $result = $this->marketplace->sendTransactionalEmail($email);
                if ($result['success']) {
                    $log->markSent($result['message_id']);
                    if ($result['transport_used'] === 'primary_fallback') {
                        Log::channel('marketplace')->info("Email sent via primary fallback (transactional failed)", [
                            'marketplace_client_id' => $this->marketplace->id,
                            'template_slug' => $templateSlug,
                            'to' => $toEmail,
                        ]);
                    }
                    return true;
                }
                $log->markFailed($result['error'] ?? 'Both transactional and primary failed');
                return false;
            }

            // Non-transactional — primary only
            $transport = $this->marketplace->getMailTransport();
            if (!$transport) {
                $log->markFailed('Could not create SMTP transport');
                return false;
            }

            $sentMessage = $transport->send($email);
            $messageId = $sentMessage?->getMessageId();

            $log->markSent($messageId);
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send email to {$toEmail}: {$e->getMessage()}");
            $log->markFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send custom email (not from template)
     */
    public function sendCustomEmail(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $toName = null,
        ?string $bodyText = null,
        ?int $customerId = null
    ): bool {
        if (!$this->marketplace->hasMailConfigured()) {
            return false;
        }

        $log = MarketplaceEmailLog::create([
            'marketplace_client_id' => $this->marketplace->id,
            'marketplace_customer_id' => $customerId,
            'template_slug' => 'custom',
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $this->marketplace->getEmailFromAddress(),
            'from_name' => $this->marketplace->getEmailFromName(),
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'status' => 'pending',
        ]);

        try {
            $transport = $this->marketplace->getMailTransport();

            if (!$transport) {
                $log->markFailed('Could not create SMTP transport');
                return false;
            }

            $email = (new Email())
                ->from(new Address($this->marketplace->getEmailFromAddress(), $this->marketplace->getEmailFromName()))
                ->to(new Address($toEmail, $toName ?? ''))
                ->subject($subject)
                ->html($bodyHtml);

            if ($bodyText) {
                $email->text($bodyText);
            }

            $sentMessage = $transport->send($email);
            $messageId = $sentMessage?->getMessageId();

            $log->markSent($messageId);
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send custom email to {$toEmail}: {$e->getMessage()}");
            $log->markFailed($e->getMessage());
            return false;
        }
    }
}

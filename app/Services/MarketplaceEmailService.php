<?php

namespace App\Services;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceEmailLog;
use App\Models\Order;
use App\Models\MarketplaceRefundRequest;
use App\Models\MarketplaceOrganizer;
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

        if (!$this->marketplace->hasMailConfigured()) {
            Log::warning('Admin notification skipped — SMTP not configured', [
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

        try {
            $transport = $this->marketplace->getMailTransport();
            $email = (new Email())
                ->from(new Address(
                    $this->marketplace->getEmailFromAddress(),
                    $this->marketplace->getEmailFromName()
                ))
                ->to(new Address($recipient))
                ->subject($subject)
                ->html($html);

            $transport->send($email);
            return true;
        } catch (\Throwable $e) {
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

        // Check SMTP
        if (!$this->marketplace->hasMailConfigured()) {
            Log::warning("SMTP not configured for marketplace {$this->marketplace->id}");
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

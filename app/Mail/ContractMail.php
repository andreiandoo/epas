<?php

namespace App\Mail;

use App\Http\Controllers\ContractSigningController;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\ContractPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Helpers\HtmlSanitizer;

class ContractMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Tenant $tenant;
    public ?string $contractPath;
    public ?Setting $settings;

    /**
     * Create a new message instance.
     */
    public function __construct(Tenant $tenant, ?string $contractPath = null)
    {
        $this->tenant = $tenant;
        $this->contractPath = $contractPath;
        $this->settings = Setting::first();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->getSubject();

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $signingToken = ContractSigningController::generateToken($this->tenant);

        return new Content(
            view: 'emails.contract',
            with: [
                'tenant' => $this->tenant,
                'settings' => $this->settings,
                'emailContent' => $this->getEmailContent(),
                'signingUrl' => route('contract.view', $signingToken),
                'signUrl' => route('contract.sign', $signingToken),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        // Attach contract PDF
        if ($this->contractPath && Storage::disk('public')->exists($this->contractPath)) {
            $filename = 'Contract-' . ($this->tenant->contract_number ?? 'CTR-' . $this->tenant->id) . '.pdf';

            $attachments[] = Attachment::fromStorage($this->contractPath)
                ->as($filename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    /**
     * Get the email subject
     */
    protected function getSubject(): string
    {
        // Try to get from email template
        $template = EmailTemplate::where('event_trigger', 'contract_generated')
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $this->processVariables($template->subject);
        }

        // Default subject
        $platformName = $this->settings?->company_name ?? 'Tixello';
        return "Your Contract with {$platformName}";
    }

    /**
     * Get the email content
     */
    protected function getEmailContent(): string
    {
        // Try to get from email template
        $template = EmailTemplate::where('event_trigger', 'contract_generated')
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $this->processVariables($template->body);
        }

        // Default content
        $tenantName = $this->tenant->contact_first_name ?? $this->tenant->public_name ?? $this->tenant->name;
        $platformName = $this->settings?->company_name ?? 'Tixello';

        return "
            <p>Dear {$tenantName},</p>

            <p>Thank you for registering with {$platformName}. We're excited to have you on board!</p>

            <p>Please find attached your contract for your records. This document outlines the terms and conditions of our partnership.</p>

            <p>Your contract details:</p>
            <ul>
                <li><strong>Contract Number:</strong> " . ($this->tenant->contract_number ?? 'N/A') . "</li>
                <li><strong>Company:</strong> " . ($this->tenant->company_name ?? $this->tenant->name) . "</li>
                <li><strong>Work Method:</strong> " . $this->formatWorkMethod($this->tenant->work_method) . "</li>
            </ul>

            <p>If you have any questions about your contract, please don't hesitate to contact us.</p>

            <p>Best regards,<br>The {$platformName} Team</p>
        ";
    }

    /**
     * Process template variables
     * SECURITY FIX: Escape variable values to prevent XSS when injected into HTML templates
     */
    protected function processVariables(string $content): string
    {
        $platformName = $this->settings?->company_name ?? 'Tixello';

        // SECURITY: Escape all variable values to prevent XSS injection
        $variables = [
            '{{tenant_name}}' => HtmlSanitizer::escape($this->tenant->contact_first_name ?? $this->tenant->public_name ?? $this->tenant->name),
            '{{tenant_company_name}}' => HtmlSanitizer::escape($this->tenant->company_name ?? $this->tenant->name),
            '{{tenant_email}}' => HtmlSanitizer::escape($this->tenant->contact_email ?? ''),
            '{{contract_number}}' => HtmlSanitizer::escape($this->tenant->contract_number ?? ''),
            '{{work_method}}' => HtmlSanitizer::escape($this->formatWorkMethod($this->tenant->work_method)),
            '{{commission_rate}}' => HtmlSanitizer::escape($this->tenant->commission_rate ?? ''),
            '{{platform_name}}' => HtmlSanitizer::escape($platformName),
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Format work method for display
     */
    protected function formatWorkMethod(?string $workMethod): string
    {
        return match ($workMethod) {
            'exclusive' => 'Exclusive (1%)',
            'mixed' => 'Mixed (2%)',
            'reseller' => 'Reseller (3%)',
            default => $workMethod ?? 'N/A',
        };
    }

    /**
     * After sending, log the email and update tenant
     */
    public function sent($message): void
    {
        // Log the email
        EmailLog::create([
            'email_template_id' => EmailTemplate::where('event_trigger', 'contract_generated')->first()?->id,
            'recipient_email' => $this->tenant->contact_email,
            'recipient_name' => $this->tenant->contact_first_name . ' ' . $this->tenant->contact_last_name,
            'subject' => $this->getSubject(),
            'body' => $this->getEmailContent(),
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => [
                'type' => 'contract',
                'tenant_id' => $this->tenant->id,
                'contract_number' => $this->tenant->contract_number,
            ],
        ]);

        // Update tenant record
        $this->tenant->update([
            'contract_sent_at' => now(),
        ]);

        Log::info('Contract email sent', [
            'tenant_id' => $this->tenant->id,
            'email' => $this->tenant->contact_email,
        ]);
    }
}

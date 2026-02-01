<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Helpers\HtmlSanitizer;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public EmailTemplate $template;
    public Setting $settings;
    public string $processedSubject;
    public string $processedBody;

    public function __construct(Invoice $invoice, string $eventTrigger)
    {
        $this->invoice = $invoice;
        $this->settings = Setting::current();
        $this->template = EmailTemplate::where('event_trigger', $eventTrigger)
            ->where('is_active', true)
            ->firstOrFail();

        // Process template variables
        $this->processTemplate();
    }

    protected function processTemplate(): void
    {
        $variables = $this->getTemplateVariables();

        $this->processedSubject = $this->template->processTemplate($variables)['subject'];
        $this->processedBody = $this->template->processTemplate($variables)['body'];
    }

    protected function getTemplateVariables(): array
    {
        $tenant = $this->invoice->tenant;

        $paymentDetails = '';
        if ($this->settings->bank_name) {
            $paymentDetails .= "Bank: {$this->settings->bank_name}\n";
        }
        if ($this->settings->bank_account) {
            $paymentDetails .= "IBAN: {$this->settings->bank_account}\n";
        }
        if ($this->settings->bank_swift) {
            $paymentDetails .= "SWIFT/BIC: {$this->settings->bank_swift}\n";
        }

        $billingPeriod = '';
        if ($this->invoice->period_start && $this->invoice->period_end) {
            $billingPeriod = $this->invoice->period_start->format('M d, Y') . ' - ' .
                           $this->invoice->period_end->format('M d, Y');
        }

        $daysOverdue = 0;
        if ($this->invoice->due_date && $this->invoice->due_date->isPast()) {
            $daysOverdue = $this->invoice->due_date->diffInDays(now());
        }

        return [
            'tenant_name' => $tenant->name ?? '',
            'company_name' => $this->settings->company_name ?? '',
            'invoice_number' => $this->invoice->number ?? '',
            'invoice_amount' => number_format($this->invoice->amount ?? 0, 2),
            'currency' => $this->invoice->currency ?? 'RON',
            'issue_date' => $this->invoice->issue_date?->format('M d, Y') ?? '',
            'due_date' => $this->invoice->due_date?->format('M d, Y') ?? '',
            'payment_date' => now()->format('M d, Y'),
            'cancellation_date' => now()->format('M d, Y'),
            'invoice_status' => strtoupper($this->invoice->status ?? ''),
            'billing_period' => $billingPeriod,
            'payment_details' => nl2br($paymentDetails),
            'days_overdue' => $daysOverdue,
            'cancellation_reason' => $this->invoice->meta['cancellation_reason'] ?? 'N/A',
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->processedSubject,
        );
    }

    public function build()
    {
        // SECURITY FIX: Sanitize HTML content to prevent XSS
        return $this->html(HtmlSanitizer::sanitize($this->processedBody));
    }

    public function attachments(): array
    {
        // Generate PDF
        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice' => $this->invoice,
            'tenant' => $this->invoice->tenant,
            'settings' => $this->settings,
        ]);

        $pdfContent = $pdf->output();
        $filename = "invoice-{$this->invoice->number}.pdf";

        // Store temporarily
        Storage::put("temp/{$filename}", $pdfContent);

        return [
            Attachment::fromPath(storage_path("app/temp/{$filename}"))
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}

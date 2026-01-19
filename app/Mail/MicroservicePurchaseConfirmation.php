<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class MicroservicePurchaseConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public Tenant $tenant;
    public Collection $microservices;
    public Invoice $invoice;
    public $session;

    /**
     * Create a new message instance.
     */
    public function __construct(Tenant $tenant, $microservices, Invoice $invoice, $session)
    {
        $this->tenant = $tenant;
        $this->microservices = collect($microservices);
        $this->invoice = $invoice;
        $this->session = $session;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Microservice Purchase Confirmation - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Get email template from database
        $template = EmailTemplate::where('slug', 'microservice-purchase-confirmation')->first();

        if ($template) {
            // Replace placeholders in template
            $html = $this->replacePlaceholders($template->html_content);
            $text = $this->replacePlaceholders($template->text_content ?? '');

            return new Content(
                htmlString: $html,
                text: $text ?: null,
            );
        }

        // Fallback to default view
        return new Content(
            view: 'emails.microservice-purchase-confirmation',
            with: [
                'tenant' => $this->tenant,
                'microservices' => $this->microservices,
                'invoice' => $this->invoice,
                'session' => $this->session,
            ],
        );
    }

    /**
     * Replace placeholders in email content
     */
    protected function replacePlaceholders(string $content): string
    {
        $microservicesList = $this->microservices->map(function ($ms) {
            return "• {$ms->name} - " . number_format($ms->price, 2) . " RON";
        })->implode("\n");

        $replacements = [
            '{{tenant_name}}' => $this->tenant->public_name ?? $this->tenant->name,
            '{{tenant_email}}' => $this->tenant->contact_email ?? $this->tenant->owner->email ?? '',
            '{{invoice_number}}' => $this->invoice->number,
            '{{invoice_date}}' => $this->invoice->issue_date->format('d/m/Y'),
            '{{invoice_amount}}' => number_format($this->invoice->amount, 2),
            '{{invoice_currency}}' => $this->invoice->currency,
            '{{microservices_list}}' => $microservicesList,
            '{{microservices_count}}' => $this->microservices->count(),
            '{{stripe_session_id}}' => $this->session->id,
            '{{payment_date}}' => now()->format('d/m/Y H:i'),
            '{{admin_url}}' => url('/admin'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Generate PDF invoice
        try {
            $pdf = Pdf::loadView('invoices.microservice-purchase', [
                'invoice' => $this->invoice,
                'tenant' => $this->tenant,
                'microservices' => $this->microservices,
            ]);

            return [
                Attachment::fromData(fn () => $pdf->output(), "invoice-{$this->invoice->number}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            // Log error but don't fail email send
            \Log::error('Failed to attach invoice PDF', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

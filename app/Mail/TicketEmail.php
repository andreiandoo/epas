<?php

namespace App\Mail;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $eventTitle;
    public ?string $venueName;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $event = $ticket->ticketType?->event;
        $this->eventTitle = is_array($event?->title)
            ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
            : ($event?->title ?? 'Event');
        $venue = $event?->venue;
        $this->venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Ticket for {$this->eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket',
            with: [
                'ticket' => $this->ticket,
                'eventTitle' => $this->eventTitle,
                'venueName' => $this->venueName,
                'event' => $this->ticket->ticketType?->event,
            ],
        );
    }

    public function attachments(): array
    {
        $pdfData = $this->generatePdfData();

        return [
            Attachment::fromData(fn () => $pdfData, "ticket-{$this->ticket->code}.pdf")
                ->withMime('application/pdf'),
        ];
    }

    protected function generatePdfData(): string
    {
        $ticket = $this->ticket;
        $event = $ticket->ticketType?->event;
        $venue = $event?->venue;
        $tenant = $ticket->order?->tenant;

        $pdf = Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'event' => $event,
            'eventTitle' => $this->eventTitle,
            'venue' => $venue,
            'venueName' => $this->venueName,
            'beneficiary' => $ticket->meta['beneficiary'] ?? null,
            'tenant' => $tenant,
            'ticketTerms' => $tenant?->ticket_terms ?? null,
        ]);

        return $pdf->output();
    }
}

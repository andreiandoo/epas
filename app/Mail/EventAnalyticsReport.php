<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EventAnalyticsReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Event $event;
    public array $reportData;
    public array $attachmentPaths;

    public function __construct(Event $event, array $reportData, array $attachmentPaths = [])
    {
        $this->event = $event;
        $this->reportData = $reportData;
        $this->attachmentPaths = $attachmentPaths;
    }

    public function envelope(): Envelope
    {
        $eventName = $this->event->title_translated ?? $this->event->title ?? 'Event';
        $periodLabel = $this->reportData['schedule']['frequency'] ?? 'Analytics';

        return new Envelope(
            subject: "{$periodLabel} Report: {$eventName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.analytics.report',
            with: [
                'event' => $this->event,
                'data' => $this->reportData,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentPaths as $path) {
            if (file_exists($path)) {
                $attachments[] = Attachment::fromPath($path);
            }
        }

        return $attachments;
    }
}

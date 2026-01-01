<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WinBackEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function envelope(): Envelope
    {
        $tier = $this->data['tier'] ?? 'win_back';
        $tenantName = $this->data['tenant']->name ?? 'Events';

        $subject = match ($tier) {
            'early_warning' => "We miss you at {$tenantName}!",
            'gentle_nudge' => "Something special waiting for you at {$tenantName}",
            'win_back' => "Come back to {$tenantName} - Here's a special offer",
            'last_chance' => "We'd love to see you again - Exclusive offer inside",
            default => "We miss you at {$tenantName}!",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.winback',
            with: [
                'tenant' => $this->data['tenant'],
                'customer' => $this->data['customer'],
                'tier' => $this->data['tier'],
                'offer' => $this->data['offer'],
                'recommendations' => $this->data['recommendations'],
                'campaignId' => $this->data['campaign_id'],
            ],
        );
    }
}

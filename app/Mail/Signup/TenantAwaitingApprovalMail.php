<?php

namespace App\Mail\Signup;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAwaitingApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tenant nou de aprobat: ' . $this->tenant->public_name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signup.awaiting-approval');
    }
}

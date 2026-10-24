<?php

namespace App\Mail\Signup;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignupCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Codul tău de verificare: ' . $this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signup.code');
    }
}

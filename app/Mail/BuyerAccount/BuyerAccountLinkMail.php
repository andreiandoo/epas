<?php

namespace App\Mail\BuyerAccount;

use App\Models\TixelloBuyerAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Emailurile contului de cumpărător Tixello:
 *  welcome   – după bifa de la plată: contul e gata, iată linkul;
 *  marketing – după bifa de informări, fără cont;
 *  login     – linkul cerut din /contul-meu;
 *  create    – cerere din /contul-meu pentru o adresă fără cont.
 */
class BuyerAccountLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $actionUrl;

    public ?string $unsubscribeUrl;

    public function __construct(public TixelloBuyerAccount $account, public string $kind)
    {
        $this->actionUrl = $kind === 'create' || ! $account->exists
            ? URL::temporarySignedRoute('buyer-account.create', now()->addMinutes(30), ['email' => $account->email])
            : $account->loginUrl();
        $this->unsubscribeUrl = $account->exists ? $account->unsubscribeUrl() : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'welcome' => 'Biletele tale sunt și în contul Tixello',
            'marketing' => 'Te-ai abonat la recomandările Tixello',
            'create' => 'Creează-ți contul Tixello',
            default => 'Linkul de intrare în contul Tixello',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.buyer-account.link');
    }
}

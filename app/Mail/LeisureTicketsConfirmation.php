<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail tranzacțional pentru biletele leisure (Sf. Ana, alte locații de agrement).
 * Locale-ul biletului = $order->locale (RO/HU/EN), cu fallback la 'ro'.
 *
 * Conține:
 *   - Header brand cu firma emitentă
 *   - Detalii vizită (data + venue + opțional slot orar)
 *   - Listă bilete cu QR inline (CID embed) — scaner mobile + manual fallback
 *   - Footer brand "Ticketing prin AmBilet.ro" + link verificare
 *
 * Trimis prin SendLeisureTicketsEmailJob (queued) după ce comanda e
 * confirmată (paid/completed). Eseuc trimitere → retry 3x, dacă tot eseuc,
 * marketplace admin primește alertă (deja există MarketplaceEmailService).
 */
class LeisureTicketsConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public array $tickets,      // [{ code, ticket_type, service_category, issuing_company, qr_data_uri }]
        public array $issuer,       // primary issuer object
        public ?array $issuerSecondary,
        public string $eventName,
        public string $visitDate,
        public string $locale,      // 'ro' | 'hu' | 'en'
    ) {
    }

    public function envelope(): Envelope
    {
        // Subiect adaptat per locale
        $subjects = [
            'ro' => 'Biletele tale - ' . $this->eventName,
            'hu' => 'A jegyeid - ' . $this->eventName,
            'en' => 'Your tickets - ' . $this->eventName,
        ];
        return new Envelope(
            subject: $subjects[$this->locale] ?? $subjects['ro'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.leisure.tickets',
            with: [
                'order'           => $this->order,
                'tickets'         => $this->tickets,
                'issuer'          => $this->issuer,
                'issuerSecondary' => $this->issuerSecondary,
                'eventName'       => $this->eventName,
                'visitDate'       => $this->visitDate,
                't'               => $this->makeTranslator($this->locale),
                'locale'          => $this->locale,
            ],
        );
    }

    /**
     * Mini-dicționar i18n inline. Evităm dependența de un sistem de
     * translation Laravel (lang/ files) — toate textele relevante sunt
     * aici, ușor de auditat și extins.
     */
    private function makeTranslator(string $locale): \Closure
    {
        $dict = [
            'ro' => [
                'greeting'       => 'Bună,',
                'thanks'         => 'Mulțumim pentru achiziție! Biletele tale sunt pregătite pentru vizita la',
                'visit_date'     => 'Data vizitei',
                'tickets_h'      => 'Bilete',
                'code'           => 'Cod',
                'show_at_entry'  => 'Arată acest cod la intrare. Personalul îl scanează cu telefonul.',
                'order_number'   => 'Comandă',
                'issued_by'      => 'Emis de',
                'cui'            => 'CUI',
                'reg_com'        => 'Reg. Com.',
                'footer'         => 'Ticketing prin AmBilet.ro · ambilet.ro',
                'questions'      => 'Întrebări? Răspunde la acest email.',
            ],
            'hu' => [
                'greeting'       => 'Szia,',
                'thanks'         => 'Köszönjük a vásárlást! A jegyeid készen állnak a látogatásodra:',
                'visit_date'     => 'A látogatás dátuma',
                'tickets_h'      => 'Jegyek',
                'code'           => 'Kód',
                'show_at_entry'  => 'Mutasd be ezt a kódot a bejáratnál. A személyzet telefonnal beolvassa.',
                'order_number'   => 'Rendelés',
                'issued_by'      => 'Kibocsátó',
                'cui'            => 'Adószám',
                'reg_com'        => 'Cégjegyzékszám',
                'footer'         => 'Jegyértékesítés az AmBilet.ro által · ambilet.ro',
                'questions'      => 'Kérdés? Válaszolj erre az e-mailre.',
            ],
            'en' => [
                'greeting'       => 'Hi,',
                'thanks'         => 'Thanks for your purchase! Your tickets are ready for your visit to',
                'visit_date'     => 'Visit date',
                'tickets_h'      => 'Tickets',
                'code'           => 'Code',
                'show_at_entry'  => 'Show this code at the entrance. Staff scan it with a phone.',
                'order_number'   => 'Order',
                'issued_by'      => 'Issued by',
                'cui'            => 'Tax ID',
                'reg_com'        => 'Trade Reg.',
                'footer'         => 'Ticketing via AmBilet.ro · ambilet.ro',
                'questions'      => 'Questions? Reply to this email.',
            ],
        ];
        $map = $dict[$locale] ?? $dict['ro'];
        return fn (string $key) => $map[$key] ?? ($dict['ro'][$key] ?? $key);
    }
}

<?php

namespace App\Services\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Emailurile din spatele metodei „Via email" din POS-ul leisure
 * (LeisureController::posSale cu payment_method = 'invoice'):
 *
 *   - payment : comanda are de plată → mesaj + link de plată
 *   - paid    : plata a fost confirmată → biletele
 *   - free    : comanda are valoare 0 → biletele, direct
 *
 * Limba = $order->locale (alegerea operatorului din POS: ro / hu / en), cu
 * fallback pe 'ro'. Trimiterea merge prin transportul marketplace-ului
 * (BaseController::sendViaMarketplace), deci apare și în email logs.
 */
class LeisurePosEmail
{
    public const LOCALES = ['ro', 'hu', 'en'];
    public const TIMEZONE = 'Europe/Bucharest';
    public const LINK_HOURS = 48;
    public const TOKEN_META_KEY = 'pay_link_token';

    public static function isPosEmailOrder(Order $order): bool
    {
        return ($order->source ?? null) === 'pos'
            && (($order->meta['payment_method'] ?? null) === 'invoice');
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    /**
     * Linkul e valabil LINK_HOURS ore, dar nu mai târziu de sfârșitul zilei de
     * vizită (ora României). Minimum o oră, ca o comandă făcută seara târziu
     * pentru aceeași zi să poată fi totuși plătită.
     */
    public static function linkExpiry(string $visitDate): Carbon
    {
        $expiry = Carbon::now()->addHours(self::LINK_HOURS);
        try {
            $endOfVisitDay = Carbon::parse($visitDate, self::TIMEZONE)->endOfDay();
            if ($endOfVisitDay->lt($expiry)) {
                $expiry = $endOfVisitDay;
            }
        } catch (\Throwable $e) {
            // data vizitei nu se poate interpreta → rămâne fereastra standard
        }
        $minimum = Carbon::now()->addHour();

        return ($expiry->lt($minimum) ? $minimum : $expiry)->utc();
    }

    public static function locale(Order $order): string
    {
        return in_array($order->locale, self::LOCALES, true) ? $order->locale : 'ro';
    }

    public static function paymentUrl(Order $order): ?string
    {
        $token = $order->meta[self::TOKEN_META_KEY] ?? null;
        if (!$token || !$order->order_number) {
            return null;
        }

        return self::siteUrl($order) . '/plata/' . rawurlencode($order->order_number) . '?t=' . rawurlencode($token);
    }

    /**
     * Mesajul cu linkul de plată. Aruncă excepție dacă trimiterea eșuează.
     */
    public function sendPaymentLink(Order $order): void
    {
        $this->send($order, 'payment');
    }

    /**
     * Mesajul cu biletele: „plata confirmată" pentru comenzile plătite prin
     * link, „bilete gratuite" pentru cele cu valoare 0.
     */
    public function sendTickets(Order $order): void
    {
        $this->send($order, ((float) $order->total) > 0 ? 'paid' : 'free');
    }

    protected function send(Order $order, string $kind): void
    {
        $to = trim((string) $order->customer_email);
        if ($to === '' || str_contains($to, 'pos@')) {
            // pos@... e adresa implicită a vânzărilor de la casă, nu un client real.
            return;
        }

        $marketplace = $order->marketplaceClient;
        if (!$marketplace) {
            return;
        }

        $order->loadMissing(['items.ticketType', 'tickets.ticketType', 'event', 'marketplaceOrganizer']);

        $locale = self::locale($order);
        $t = self::texts($locale);
        $organizer = $order->marketplaceOrganizer;
        $venue = $this->venueName($order, $locale);
        $name = trim((string) $order->customer_name);
        if ($name === '' || str_starts_with($name, 'POS —')) {
            // 'POS — vânzare on-site' = numele implicit când operatorul nu completează unul
            $name = '';
        }

        $visitDateRaw = $order->meta['visit_date'] ?? null;
        $visitDate = $visitDateRaw ? self::formatDate(Carbon::parse($visitDateRaw), $locale) : '';
        $total = self::formatMoney((float) $order->total, $order->currency ?? 'RON', $locale);
        $payUrl = $kind === 'payment' ? self::paymentUrl($order) : null;

        if ($kind === 'payment' && !$payUrl) {
            throw new \RuntimeException('Order has no payment link token');
        }

        $introKey = ['payment' => 'payment_intro', 'paid' => 'paid_intro', 'free' => 'free_intro'][$kind];
        $closingKey = ['payment' => 'closing_welcome', 'paid' => 'closing_enjoy', 'free' => 'closing_welcome'][$kind];

        $html = view('mail.leisure.pos-email', [
            'kind' => $kind,
            'locale' => $locale,
            't' => $t,
            'venue' => $venue,
            'greeting' => $name !== '' ? str_replace(':name', $name, $t['greeting_named']) : $t['greeting'],
            'intro' => str_replace(':date', $visitDate, $t[$introKey]),
            'closing' => $t[$closingKey],
            'visitDate' => $visitDate,
            'orderNumber' => $order->order_number ?? ('#' . $order->id),
            'lines' => $this->summaryLines($order, $locale),
            'commission' => ((float) ($order->meta['commission_total'] ?? 0)) > 0
                ? self::formatMoney((float) $order->meta['commission_total'], $order->currency ?? 'RON', $locale)
                : null,
            'total' => $total,
            'payUrl' => $payUrl,
            'payButton' => str_replace(':total', $total, $t['pay_button']),
            'validity' => $order->expires_at
                ? str_replace(':expires', self::formatDateTime($order->expires_at, $locale), $t['payment_validity'])
                : null,
            'tickets' => $kind === 'payment' ? [] : $this->ticketCards($order, $locale),
            'issuer' => $organizer ? $organizer->getIssuerData('primary') : [],
        ])->render();

        $subjectKey = $kind === 'payment' ? 'subject_payment' : 'subject_tickets';
        $subject = str_replace(':venue', $venue, $t[$subjectKey]);

        $logExtra = [
            'order_id' => $order->id,
            'marketplace_organizer_id' => $order->marketplace_organizer_id,
            'template_slug' => $kind === 'payment' ? 'pos_payment_link' : 'pos_tickets',
        ];
        // Răspunsurile clientului ajung la organizator, nu la adresa platformei.
        if ($organizer && !empty($organizer->email)) {
            $logExtra['reply_to_email'] = $organizer->email;
            $logExtra['reply_to_name'] = $venue;
        }

        BaseController::sendViaMarketplace($marketplace, $to, $name !== '' ? $name : $to, $subject, $html, $logExtra);

        $meta = is_array($order->meta) ? $order->meta : [];
        $meta['pos_email'] = array_merge(
            is_array($meta['pos_email'] ?? null) ? $meta['pos_email'] : [],
            [$kind . '_sent_at' => now()->toIso8601String()]
        );
        $order->meta = $meta;
        $order->saveQuietly();
    }

    protected function venueName(Order $order, string $locale): string
    {
        $title = $order->event?->title;
        $name = self::pickLocale($title, $locale);

        return $name !== '' ? $name : ($order->marketplaceOrganizer?->name ?? 'AmBilet');
    }

    /**
     * Liniile din rezumatul comenzii: produs (+ variantă), cantitate, valoare.
     */
    protected function summaryLines(Order $order, string $locale): array
    {
        $lines = [];
        foreach ($order->items as $item) {
            $meta = is_array($item->meta) ? $item->meta : [];
            $name = self::pickLocale($item->ticketType?->name, $locale) ?: (string) $item->name;
            if (!empty($meta['variant']['label'])) {
                $name .= ' — ' . $meta['variant']['label'];
            }
            $addons = [];
            foreach ((array) ($meta['addons'] ?? []) as $addon) {
                if (!empty($addon['label']) && !empty($addon['total_qty'])) {
                    $addons[] = $addon['label'] . ' × ' . (int) $addon['total_qty'];
                }
            }
            $lines[] = [
                'name' => $name,
                'qty' => (int) $item->quantity,
                'total' => self::formatMoney((float) $item->total, $order->currency ?? 'RON', $locale),
                'addons' => $addons,
            ];
        }

        return $lines;
    }

    /**
     * Biletele afișate clientului. Biletul „umbrelă" al unui pachet există doar
     * pentru raportare — clientul vede componentele scanabile.
     */
    protected function ticketCards(Order $order, string $locale): array
    {
        $organizer = $order->marketplaceOrganizer;
        $issuerPrimary = $organizer ? $organizer->getIssuerData('primary') : [];
        $issuerSecondary = ($organizer && $organizer->has_secondary_issuer)
            ? $organizer->getIssuerData('secondary')
            : null;
        $siteUrl = self::siteUrl($order);

        $cards = [];
        foreach ($order->tickets as $ticket) {
            $meta = is_array($ticket->meta) ? $ticket->meta : [];
            if (!empty($meta['is_package_umbrella']) || $ticket->status === 'cancelled') {
                continue;
            }
            $name = !empty($meta['label_override'])
                ? (string) $meta['label_override']
                : self::pickLocale($ticket->ticketType?->name, $locale);
            if (empty($meta['label_override']) && !empty($meta['variant']['label'])) {
                $name .= ' — ' . $meta['variant']['label'];
            }
            $issuer = (($meta['issuing_company'] ?? 'primary') === 'secondary' && $issuerSecondary)
                ? $issuerSecondary
                : $issuerPrimary;

            $cards[] = [
                'name' => $name,
                'code' => (string) $ticket->code,
                'issuer' => $issuer['name'] ?? '',
                // Același conținut ca pe biletele tipărite la POS (pos-printer.js).
                'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                    'size' => '240x240',
                    'data' => $siteUrl . '/v/' . $ticket->code,
                    'margin' => '2',
                    'format' => 'png',
                ]),
            ];
        }

        return $cards;
    }

    protected static function siteUrl(Order $order): string
    {
        $domain = rtrim((string) ($order->marketplaceClient?->domain ?? ''), '/');
        if ($domain === '') {
            return 'https://ambilet.ro';
        }

        return str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
    }

    protected static function pickLocale(mixed $value, string $locale): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return $value;
            }
            $value = $decoded;
        }
        if (!is_array($value)) {
            return '';
        }
        foreach ([$locale, 'ro', 'en'] as $key) {
            if (!empty($value[$key]) && is_string($value[$key])) {
                return $value[$key];
            }
        }
        $first = reset($value);

        return is_string($first) ? $first : '';
    }

    protected static function formatMoney(float $amount, string $currency, string $locale): string
    {
        $isRon = strtoupper($currency) === 'RON';

        return match ($locale) {
            'hu' => number_format($amount, 2, ',', ' ') . ' ' . ($isRon ? 'lej' : $currency),
            'en' => number_format($amount, 2, '.', ',') . ' ' . $currency,
            default => number_format($amount, 2, ',', '.') . ' ' . ($isRon ? 'lei' : $currency),
        };
    }

    protected static function formatDate(Carbon $date, string $locale): string
    {
        return match ($locale) {
            'hu' => $date->format('Y. m. d.'),
            'en' => $date->format('j F Y'),
            default => $date->format('d.m.Y'),
        };
    }

    protected static function formatDateTime(Carbon $date, string $locale): string
    {
        $local = $date->copy()->timezone(self::TIMEZONE);

        return match ($locale) {
            'hu' => $local->format('Y. m. d. H:i'),
            'en' => $local->format('j F Y, H:i'),
            default => $local->format('d.m.Y, H:i'),
        };
    }

    /**
     * Textele emailurilor, pe limbă. Adresare formală.
     */
    public static function texts(string $locale): array
    {
        $dict = [
            'ro' => [
                'subject_payment' => 'Finalizați plata biletelor – :venue',
                'subject_tickets' => 'Biletele dumneavoastră pentru :venue',
                'header_payment' => 'Comanda dumneavoastră așteaptă plata',
                'header_tickets' => 'Biletele dumneavoastră',
                'greeting_named' => 'Bună ziua, :name!',
                'greeting' => 'Bună ziua!',
                'payment_intro' => 'Vă mulțumim că ne-ați ales! Am pregătit comanda dumneavoastră pentru vizita din :date. Mai este nevoie doar de plată.',
                'pay_button' => 'Plătiți acum – :total',
                'payment_note' => 'Plata se face online, cu cardul, în siguranță. Imediat după confirmare vă trimitem biletele pe această adresă de email.',
                'payment_validity' => 'Linkul este valabil până la :expires. Dacă nu dumneavoastră ați cerut această comandă, puteți ignora acest mesaj.',
                'link_fallback' => 'Dacă butonul nu funcționează, copiați acest link în browser:',
                'paid_intro' => 'Plata a fost confirmată. Vă mulțumim! Mai jos găsiți biletele pentru vizita din :date.',
                'free_intro' => 'V-am pregătit biletele pentru vizita din :date. Sunt deja valabile, nu trebuie să plătiți nimic.',
                'show_qr' => 'Arătați codul QR la intrare, direct de pe telefon sau tipărit.',
                'closing_welcome' => 'Vă așteptăm cu drag!',
                'closing_enjoy' => 'Vă dorim o vizită plăcută!',
                'visit_date' => 'Data vizitei',
                'order_number' => 'Comandă',
                'summary' => 'Rezumatul comenzii',
                'commission' => 'Comision ticketing',
                'total_due' => 'Total de plată',
                'total_paid' => 'Total plătit',
                'tickets_h' => 'Bilete',
                'code' => 'Cod',
                'issued_by' => 'Emis de',
                'cui' => 'CUI',
                'reg_com' => 'Reg. Com.',
                'footer' => 'Ticketing prin AmBilet.ro · ambilet.ro',
                'questions' => 'Aveți întrebări? Răspundeți la acest email.',
            ],
            // Data în maghiară se termină deja cu punct („2026. 10. 04."), deci
            // :date nu mai e urmat de punct în propoziții.
            'hu' => [
                'subject_payment' => 'Fejezze be jegyei kifizetését – :venue',
                'subject_tickets' => 'Az Ön jegyei – :venue',
                'header_payment' => 'Rendelése fizetésre vár',
                'header_tickets' => 'Az Ön jegyei',
                'greeting_named' => 'Tisztelt :name!',
                'greeting' => 'Tisztelt Vásárlónk!',
                'payment_intro' => 'Köszönjük, hogy minket választott! Előkészítettük rendelését. A látogatás dátuma: :date Már csak a fizetés van hátra.',
                'pay_button' => 'Fizetés most – :total',
                'payment_note' => 'A fizetés online, bankkártyával, biztonságosan történik. A visszaigazolás után azonnal elküldjük jegyeit erre az e-mail-címre.',
                'payment_validity' => 'A link eddig érvényes: :expires. Ha nem Ön kérte ezt a rendelést, kérjük, hagyja figyelmen kívül ezt az üzenetet.',
                'link_fallback' => 'Ha a gomb nem működik, másolja be ezt a linket a böngészőjébe:',
                'paid_intro' => 'Fizetése megérkezett. Köszönjük! Alább találja jegyeit. A látogatás dátuma: :date',
                'free_intro' => 'Elkészítettük jegyeit. A látogatás dátuma: :date A jegyek már érvényesek, nincs semmi fizetnivalója.',
                'show_qr' => 'Kérjük, mutassa fel a QR-kódot a bejáratnál, telefonról vagy kinyomtatva.',
                'closing_welcome' => 'Szeretettel várjuk!',
                'closing_enjoy' => 'Kellemes időtöltést kívánunk!',
                'visit_date' => 'A látogatás dátuma',
                'order_number' => 'Rendelés',
                'summary' => 'Rendelés összesítő',
                'commission' => 'Kezelési díj',
                'total_due' => 'Fizetendő összeg',
                'total_paid' => 'Kifizetett összeg',
                'tickets_h' => 'Jegyek',
                'code' => 'Kód',
                'issued_by' => 'Kibocsátó',
                'cui' => 'Adószám',
                'reg_com' => 'Cégjegyzékszám',
                'footer' => 'Jegyértékesítés az AmBilet.ro által · ambilet.ro',
                'questions' => 'Kérdése van? Válaszoljon erre az e-mailre.',
            ],
            'en' => [
                'subject_payment' => 'Complete your payment – :venue',
                'subject_tickets' => 'Your tickets for :venue',
                'header_payment' => 'Your order is awaiting payment',
                'header_tickets' => 'Your tickets',
                'greeting_named' => 'Dear :name,',
                'greeting' => 'Hello,',
                'payment_intro' => 'Thank you for choosing us! Your order for your visit on :date is ready. All that is left is the payment.',
                'pay_button' => 'Pay now – :total',
                'payment_note' => 'Payment is made securely online, by card. As soon as it is confirmed, we will send your tickets to this email address.',
                'payment_validity' => 'This link is valid until :expires. If you did not request this order, you can safely ignore this message.',
                'link_fallback' => 'If the button does not work, copy this link into your browser:',
                'paid_intro' => 'Your payment has been confirmed. Thank you! Your tickets for your visit on :date are below.',
                'free_intro' => 'Your tickets for your visit on :date are ready. They are already valid, so there is nothing to pay.',
                'show_qr' => 'Please show the QR code at the entrance, on your phone or printed.',
                'closing_welcome' => 'We look forward to welcoming you!',
                'closing_enjoy' => 'Enjoy your visit!',
                'visit_date' => 'Visit date',
                'order_number' => 'Order',
                'summary' => 'Order summary',
                'commission' => 'Ticketing fee',
                'total_due' => 'Total to pay',
                'total_paid' => 'Total paid',
                'tickets_h' => 'Tickets',
                'code' => 'Code',
                'issued_by' => 'Issued by',
                'cui' => 'Tax ID',
                'reg_com' => 'Trade Reg.',
                'footer' => 'Ticketing via AmBilet.ro · ambilet.ro',
                'questions' => 'Any questions? Reply to this email.',
            ],
        ];

        return $dict[$locale] ?? $dict['ro'];
    }
}

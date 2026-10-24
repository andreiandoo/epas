<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completează numele și descrierile microserviciilor în română și engleză, pe
 * înțelesul organizatorilor (le vede la /inregistrare și în panou). Câmpurile
 * goale („”, null, „<p></p>”) se completează; ce e în OVERRIDES se înlocuiește
 * (română lăsată în engleză, fără diacritice sau descrieri greșite).
 */
return new class extends Migration
{
    private const TEXTS = [
        'ticket-customizer' => [
            'short_description' => ['ro' => 'Îți desenezi singur cum arată biletul', 'en' => 'Design how your tickets look'],
            'description' => ['ro' => '<p>Editor vizual pentru designul biletelor: adaugi logo, imagini, texte, cod QR sau cod de bare și vezi pe loc cum arată biletul tipărit sau în PDF.</p>'],
        ],
        'tracking-pixels-manager' => [
            'name' => ['ro' => 'Pixeli de urmărire și analiză'],
            'short_description' => ['ro' => 'Google Analytics, Meta Pixel și TikTok Pixel pe site-ul tău', 'en' => 'Google Analytics, Meta Pixel and TikTok Pixel on your site'],
            'description' => ['ro' => '<p>Legi site-ul de bilete de Google Analytics 4, Google Tag Manager, Meta Pixel și TikTok Pixel, ca să vezi de unde vin cumpărătorii și cât aduc reclamele. Acordul vizitatorilor pentru cookie-uri e cerut conform GDPR.</p>'],
        ],
        'efactura-ro' => [
            'short_description' => ['ro' => 'Facturile ajung automat în SPV, la ANAF', 'en' => 'Invoices sent to ANAF SPV automatically'],
            'description' => ['ro' => '<p>Trimite automat facturile în sistemul e-Factura al ANAF (SPV) și îți arată dacă au fost acceptate sau respinse.</p>'],
        ],
        'whatsapp-notifications' => [
            'short_description' => ['ro' => 'Confirmări de comandă și remindere pe WhatsApp', 'en' => 'Order confirmations and reminders on WhatsApp'],
            'description' => ['ro' => '<p>Trimite pe WhatsApp confirmarea comenzii, remindere înainte de eveniment (cu 7, 3 și o zi înainte) și campanii promoționale, doar celor care au acceptat să primească mesaje.</p>'],
        ],
        'affiliate-tracking' => [
            'name' => ['ro' => 'Afiliați și comisioane'],
            'short_description' => ['ro' => 'Răsplătești partenerii care îți aduc cumpărători', 'en' => 'Reward partners who bring you buyers'],
            'description' => ['ro' => '<p>Dai fiecărui partener (bloggeri, influenceri, site-uri) un link al lui. Vezi câte vânzări a adus fiecare, cu atribuire la ultimul click și cookie de 90 de zile, iar comisionul lor se calculează automat.</p>'],
        ],
        'accounting-connectors' => [
            'short_description' => ['ro' => 'Facturile se emit automat în programul tău de facturare', 'en' => 'Invoices issued automatically in your invoicing software'],
            'description' => [
                'ro' => '<p>Leagă vânzările de programul de facturare pe care îl folosești deja: SmartBill, Oblio, FGO sau Keez. Facturile se emit acolo automat, fără să le copiezi de mână.</p>',
                'en' => '<p>Connects your sales to the invoicing software you already use: SmartBill, Oblio, FGO or Keez. Invoices are issued there automatically, with no manual copying.</p>',
            ],
        ],
        'invitations' => [
            'name' => ['ro' => 'Invitații'],
            'short_description' => ['ro' => 'Bilete gratuite pentru invitați, presă și VIP', 'en' => 'Free tickets for guests, press and VIPs'],
            'description' => ['ro' => '<p>Generezi invitații în număr mare sau dintr-un fișier CSV, le trimiți pe email ca PDF și vezi cine le-a descărcat și cine a intrat la eveniment.</p>'],
        ],
        'ticket-insurance' => [
            'name' => ['ro' => 'Asigurare bilet'],
            'short_description' => ['ro' => 'Cumpărătorul își poate asigura biletul la plată', 'en' => 'Buyers can insure their ticket at checkout'],
            'description' => ['ro' => '<p>La plată, cumpărătorul poate adăuga o asigurare pentru bilet. Prețul asigurării se calculează pe loc, iar polițele se gestionează automat.</p>'],
        ],
        'gamification' => [
            'short_description' => ['ro' => 'Program de puncte și recompense pentru clienți'],
            'description' => ['ro' => '<p>Răsplătește-ți clienții cu puncte pentru cumpărături, recomandări, zile de naștere și altele. Include niveluri, folosirea punctelor la plată și urmărirea recomandărilor.</p>'],
        ],
        'shop' => [
            'short_description' => ['ro' => 'Vinde produse și merchandise alături de bilete'],
            'description' => ['ro' => '<p>Vinde tricouri, produse și extra-uri alături de bilete. Include catalog de produse, coș de cumpărături, stocuri și plată în același checkout cu biletele.</p>'],
        ],
        'zoom-integration' => [
            'name' => ['ro' => 'Integrare Zoom', 'en' => 'Zoom Integration'],
            'short_description' => ['ro' => 'Întâlniri și webinarii Zoom pentru evenimentele online', 'en' => 'Zoom meetings and webinars for online events'],
            'description' => [
                'ro' => '<p>Creează automat întâlniri și webinarii Zoom pentru evenimentele online, sincronizează participanții și urmărește prezența și înregistrările.</p>',
                'en' => '<p>Create and manage Zoom meetings and webinars for virtual events. Automatic meeting creation, registrant sync, attendance tracking and recording management.</p>',
            ],
        ],
        'live-chat' => [
            'short_description' => ['ro' => 'Chat în timp real cu vizitatorii site-ului', 'en' => 'Real-time chat with your site visitors'],
            'description' => [
                'ro' => '<p>Vizitatorii site-ului îți pot scrie pe loc, dintr-o fereastră de chat, iar tu sau echipa ta răspundeți din panou.</p>',
                'en' => '<p>Visitors can message you instantly from a chat window on your site, and you or your team reply from the panel.</p>',
            ],
        ],
    ];

    // slug => câmpuri înlocuite chiar dacă au deja o valoare
    private const OVERRIDES = [
        'tracking-pixels-manager' => ['name.ro'],
        'affiliate-tracking' => ['name.ro'],
        'accounting-connectors' => ['description.en'],
        'invitations' => ['name.ro'],
        'ticket-insurance' => ['name.ro'],
        'gamification' => ['short_description.ro', 'description.ro'],
        'shop' => ['short_description.ro', 'description.ro'],
        'zoom-integration' => ['name.ro', 'name.en', 'description.en'],
        'live-chat' => ['short_description.ro', 'short_description.en'],
    ];

    public function up(): void
    {
        foreach (self::TEXTS as $slug => $fields) {
            $row = DB::table('microservices')->where('slug', $slug)->first();
            if (! $row) {
                continue;
            }

            $update = [];
            foreach ($fields as $field => $values) {
                $current = $this->decode($row->{$field} ?? null);
                $changed = false;
                foreach ($values as $locale => $text) {
                    $override = in_array("{$field}.{$locale}", self::OVERRIDES[$slug] ?? [], true);
                    if ($override || $this->isEmpty($current[$locale] ?? null)) {
                        $current[$locale] = $text;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $update[$field] = json_encode($current, JSON_UNESCAPED_UNICODE);
                }
            }

            if ($update) {
                DB::table('microservices')->where('slug', $slug)->update($update + ['updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Doar completări de text; nu se revine
    }

    /** Valoarea din coloană ca [locale => text]; un text simplu vechi e considerat engleză. */
    private function decode(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['en' => is_string($decoded) ? $decoded : (string) $value];
    }

    private function isEmpty(mixed $text): bool
    {
        return ! is_string($text) || trim(strip_tags($text)) === '';
    }
};

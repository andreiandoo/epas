<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ambilet: noua politica de returnare si rambursare in articolele din
 * Centrul de ajutor (bilete nereturnabile - OUG 34/2014 art. 16 lit. l),
 * rambursare doar la anulare, taxa de procesare nerambursabila).
 *
 * Suprascrie doar campul de continut (ro + en) al articolelor de mai jos;
 * restul localizarilor si statisticile raman neatinse.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clientId = DB::table('marketplace_clients')->where('domain', 'like', '%ambilet.ro%')->value('id') ?? 1;

        $policy = '<p>Biletele achiziționate pentru eveniment sunt nereturnabile, în conformitate cu prevederile OUG nr. 34/2014, art. 16 lit. l), privind excepțiile de la dreptul de retragere pentru serviciile referitoare la activități de agrement, spectacole și concerte, atunci când contractul prevede o dată sau o perioadă specifică de executare.</p>
<p>În cazul în care cumpărătorul renunță, din orice motiv, la participarea la eveniment, contravaloarea biletului nu poate fi rambursată.</p>
<p>Fac excepție situațiile în care evenimentul este anulat de organizator. În acest caz, rambursarea sumelor achitate se va realiza conform condițiilor comunicate de organizator și cu respectarea legislației aplicabile.</p>
<p>Taxa de procesare/ticketing nu este rambursabilă, inclusiv în situația anulării evenimentului de către organizator.</p>
<p>Prin achiziționarea biletului, cumpărătorul declară că a luat cunoștință de prezenta politică de returnare și rambursare și că este de acord cu prevederile acesteia.</p>';

        $policyEn = '<p>Tickets purchased for an event are non-returnable, in accordance with Government Emergency Ordinance no. 34/2014, art. 16 letter l), regarding the exceptions to the right of withdrawal for leisure, show and concert services when the contract provides for a specific date or period of performance.</p>
<p>If the buyer decides, for any reason, not to attend the event, the ticket price cannot be refunded.</p>
<p>The exception is when the event is cancelled by the organizer. In that case, the amounts paid will be refunded under the conditions communicated by the organizer and in compliance with applicable law.</p>
<p>The processing/ticketing fee is non-refundable, including when the event is cancelled by the organizer.</p>
<p>By purchasing the ticket, the buyer confirms having read this return and refund policy and agrees to its terms.</p>';

        $articles = [
            'politica-rambursare' => [
                'ro' => '<h2>Politica de returnare și rambursare</h2>
' . $policy . '
<h3>Ce se întâmplă dacă evenimentul este anulat?</h3>
<p>Te anunțăm pe email când evenimentul este anulat. Rambursarea contravalorii biletelor se face conform condițiilor comunicate de organizator, de regulă pe aceeași metodă de plată folosită la achiziție. Taxa de procesare/ticketing nu se rambursează.</p>
<h3>Nu mai poți ajunge la eveniment?</h3>
<p>Biletul nu poate fi returnat și nici rambursat. Dacă evenimentul permite, îl poți transfera unei alte persoane din secțiunea „Biletele mele” a contului tău.</p>',
                'en' => '<h2>Return and refund policy</h2>
' . $policyEn,
            ],
            'rambursare-comision' => [
                'ro' => '<p>Nu. Taxa de procesare/ticketing nu este rambursabilă, inclusiv în situația anulării evenimentului de către organizator.</p>
<ul>
<li><strong>Renunțare la participare</strong>: biletele sunt nereturnabile, nu se rambursează nici biletul, nici taxa de procesare</li>
<li><strong>Eveniment anulat de organizator</strong>: se rambursează contravaloarea biletelor, conform condițiilor comunicate de organizator; taxa de procesare se reține</li>
</ul>',
                'en' => '<p>No. The processing/ticketing fee is non-refundable, including when the event is cancelled by the organizer. If you decide not to attend, neither the ticket nor the fee is refunded.</p>',
            ],
            'durata-rambursare' => [
                'ro' => '<p>Rambursările se fac doar pentru evenimentele anulate de organizator, conform condițiilor comunicate de acesta:</p>
<ul>
<li><strong>Procesare</strong>: de regulă 5-14 zile lucrătoare de la anunțarea anulării</li>
<li><strong>Returnare în cont</strong>: depinde de banca emitentă a cardului</li>
</ul>
<p>Suma (fără taxa de procesare/ticketing, care nu este rambursabilă) va fi returnată pe același card/metodă de plată folosită la achiziție. Dacă cardul a expirat sau nu mai este valid, te vom contacta pentru detalii alternative.</p>',
                'en' => '<p>Refunds are issued only for events cancelled by the organizer, usually within 5-14 business days, to the original payment method. The processing/ticketing fee is non-refundable.</p>',
            ],
            'anulare-amanare-eveniment' => [
                'ro' => '<p>Pentru a anula sau amâna un eveniment:</p>
<ol>
<li>Accesează panoul de administrare și selectează evenimentul</li>
<li>Folosește opțiunea "Amână eveniment" sau "Anulează eveniment"</li>
<li>Completează motivul și noua dată (pentru amânare)</li>
<li>Confirmă acțiunea</li>
</ol>
<p><strong>Important:</strong></p>
<ul>
<li>La anulare, toți participanții sunt notificați automat, iar rambursarea sumelor achitate se face conform condițiilor comunicate de organizator și cu respectarea legislației aplicabile</li>
<li>Taxa de procesare/ticketing nu se rambursează participanților, nici în caz de anulare</li>
</ul>
<p>Te rugăm să ne contactezi înainte pentru a coordona procesul, mai ales pentru evenimente mari.</p>',
                'en' => '<p>You can cancel or postpone events from the admin panel. All attendees are notified automatically. On cancellation, refunds follow the conditions communicated by the organizer; the processing/ticketing fee is non-refundable.</p>',
            ],
        ];

        foreach ($articles as $slug => $content) {
            $row = DB::table('kb_articles')
                ->where('marketplace_client_id', $clientId)
                ->where('slug', $slug)
                ->first(['id', 'content']);
            if (!$row) {
                continue;
            }

            $current = json_decode($row->content ?? '', true) ?: [];
            DB::table('kb_articles')->where('id', $row->id)->update([
                'content' => json_encode(array_merge($current, $content), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        // Titlul articolului principal
        $row = DB::table('kb_articles')->where('marketplace_client_id', $clientId)->where('slug', 'politica-rambursare')->first(['id', 'title']);
        if ($row) {
            $title = json_decode($row->title ?? '', true) ?: [];
            $title['ro'] = 'Politica de returnare și rambursare AmBilet';
            $title['en'] = 'AmBilet Return and Refund Policy';
            DB::table('kb_articles')->where('id', $row->id)->update(['title' => json_encode($title, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // Continut editorial, nu se revine
    }
};

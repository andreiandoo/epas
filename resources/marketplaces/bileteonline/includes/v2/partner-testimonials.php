<?php
/**
 * bilete.online v2: what partners say, shown on /parteneri. Edit here, nowhere else.
 *
 * - video: one film (YouTube). The page shows its poster and loads the player only when someone presses play.
 * - quotes: short testimonials. The one marked 'pull' => true is the large quote before the finale; the rest form the wall.
 *
 * 'demo' => true marks a stand-in used while the real material is prepared. Stand-ins are shown ONLY in preview
 * (/parteneri?preview=1), with a "Demo" tag, never to visitors: a made-up testimonial on a live sales page would mislead
 * operators. To publish, replace the text (or the YouTube id) with the real one and set 'demo' => false.
 */

function v2_partner_testimonials(): array
{
    return [
        'video' => [
            'demo' => true,
            'youtube' => 'aqz-KE-bpKQ', // stand-in (Blender open film); the Sf. Ana film goes here once it's edited
            'duration' => '2:30',
            'title' => 'Sf. Ana: bilete online, acces rapid la intrare',
            'quote' => 'Am trecut de la cozi la intrare la oameni care vin cu biletul în telefon. Vedem în fiecare dimineață câți vin și pe ce interval, iar la final de zi știm exact cât s-a vândut online și cât la ghișeu.',
            'name' => 'Nume Prenume',
            'role' => 'Manager',
            'place' => 'Sf. Ana',
            'results' => ['Acces fără cozi', 'Online + ghișeu în același raport'],
        ],
        'quotes' => [
            [
                'demo' => true,
                'quote' => 'Camerele au sloturi la fiecare oră și înainte ne scriau oamenii pe Instagram ca să rezerve. Acum aleg singuri ora, plătesc și primesc biletul. Noi doar îi primim.',
                'name' => 'Andreea M.', 'role' => 'Fondatoare', 'venue' => 'Escape room', 'city' => 'Cluj-Napoca',
                'result' => 'Rezervări fără mesaje',
            ],
            [
                'demo' => true,
                'quote' => 'Ghișeul și vânzările online sunt în același raport. La închiderea casei nu mai numărăm de două ori și nu mai facem tabele separate pentru contabilitate.',
                'name' => 'Radu T.', 'role' => 'Administrator', 'venue' => 'Muzeu', 'city' => 'Sibiu',
                'result' => 'Închidere de casă în 5 minute',
            ],
            [
                'demo' => true,
                'quote' => 'Comisionul îl plătește cumpărătorul, deci prețul nostru a rămas întreg. Asta a fost decizia pentru noi, restul a venit ca bonus.',
                'name' => 'Ioana P.', 'role' => 'Manager', 'venue' => 'Parc de aventură', 'city' => 'Brașov',
                'result' => 'Prețul întreg, la fiecare bilet',
            ],
            [
                'demo' => true,
                'quote' => 'Scanăm cu telefonul chiar și unde semnalul e slab, iar totul se sincronizează când revine internetul. Pentru un traseu în natură, asta contează enorm.',
                'name' => 'Mihai D.', 'role' => 'Coordonator', 'venue' => 'Rezervație naturală', 'city' => 'Harghita',
                'result' => 'Scanare offline',
            ],
            [
                'demo' => true,
                'quote' => 'Atelierele au locuri puține și copii de vârste diferite. Am pus tipuri de bilete pe vârste, iar părinții văd imediat ce e potrivit pentru ei.',
                'name' => 'Elena S.', 'role' => 'Fondatoare', 'venue' => 'Ateliere creative', 'city' => 'București',
                'result' => 'Bilete pe vârste',
            ],
            [
                'demo' => true,
                'quote' => 'Reclamele ne costau tot mai mult și nu știam ce aduce vânzări. Cu tracking-ul conectat vedem ce campanie vinde și am tăiat ce nu funcționa.',
                'name' => 'Vlad C.', 'role' => 'Marketing', 'venue' => 'Tururi ghidate', 'city' => 'Timișoara',
                'result' => 'Reclame care vând',
            ],
            [
                'demo' => true,
                'pull' => true,
                'quote' => 'Ne-am făcut contul într-o după-amiază și a doua zi vindeam deja. Nu ne-a trebuit nimic în plus față de ce aveam: un telefon pentru scanare și imprimanta de la casă.',
                'name' => 'Cristina N.', 'role' => 'Proprietar', 'venue' => 'Parc de distracții', 'city' => 'Constanța',
                'result' => 'Live a doua zi',
            ],
        ],
    ];
}

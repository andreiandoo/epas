<?php
/** Clean-URL routes for teatru (mirrors the original .htaccess RO aliases). */
return [
    'exact' => [
        '/'            => 'index',
        '/repertoriu'  => 'repertoire',
        '/program'     => 'schedule',
        '/abonamente'  => 'subscriptions',
        '/trupa'       => 'troupe',
        '/despre'      => 'about',
        '/cos'         => 'cart',
        '/autentificare' => 'login',
        '/cont'          => 'cont-index',
        '/cont/bilete'   => 'cont-bilete',
    ],
    'capture' => [
        'spectacol' => 'show',   // /spectacol/{slug} → show.php?slug=
        'artist'    => 'artist',
    ],
];

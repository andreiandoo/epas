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
    ],
    'capture' => [
        'spectacol' => 'show',   // /spectacol/{slug} → show.php?slug=
        'artist'    => 'artist',
    ],
];

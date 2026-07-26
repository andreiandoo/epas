<?php
/** Clean-URL routes. Add pages here as you create them under pages/. */
return [
    'exact' => [
        '/'           => 'index',
        '/repertoriu' => 'repertoire',
        '/program'    => 'schedule',
        '/abonamente' => 'subscriptions',
        '/despre'     => 'about',
        '/cos'        => 'cart',
    ],
    'capture' => [
        'spectacol' => 'show',
        'artist'    => 'artist',
    ],
];

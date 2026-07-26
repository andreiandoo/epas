<?php
/** Clean-URL routes. */
return [
    'exact' => [
        '/'           => 'index',
        '/evenimente' => 'events',
        '/categorii'  => 'categories',
        '/locatii'    => 'venues',
        '/artisti'    => 'artists',
    ],
    'capture' => [
        'bilete' => 'show',
        'artist' => 'artist',
        'venue'  => 'venue',
    ],
];

<?php
/** Clean-URL routes for ambilet marketplace. */
return [
    'exact' => [
        '/'            => 'index',
        '/evenimente'  => 'events',
        '/categorii'   => 'categories',
        '/locatii'     => 'venues',
        '/artisti'     => 'artists',
    ],
    'capture' => [
        'bilete' => 'show',      // /bilete/{slug} → show.php?slug=
        'artist' => 'artist',
        'venue'  => 'venue',
    ],
];

<?php

/*
| Limbile panoului marketplace (/marketplace).
| enabled = apar în selectorul din bara de sus și se pot alege.
| Traducerile stau în resources/lang/{locale}.json (cheia = textul din cod).
| Limbi planificate: bg, pl, cs, de, fr, es, it etc. — se adaugă aici după ce {locale}.json e complet.
*/

return [
    'enabled' => ['ro', 'en', 'hu'],

    // Cât e pornit, textele din panou fără traducere se notează în storage/logs/i18n-missing-{locale}.log
    // (o dată pe zi per text). Pornește-l temporar cu I18N_COLLECT_MISSING=true, apoi oprește-l.
    'collect_missing' => (bool) env('I18N_COLLECT_MISSING', false),

    'names' => [
        'ro' => 'Română',
        'en' => 'English',
        'hu' => 'Magyar',
        'bg' => 'Български',
        'pl' => 'Polski',
        'cs' => 'Čeština',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
    ],
];

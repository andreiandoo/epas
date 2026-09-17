<?php
/**
 * bilete.online v2: the personalised operator landing copy (?tip=<type>&loc=<name>), shared by /devino-partener and
 * /parteneri. v2_partner_profiles($accent) gives the 12 activity profiles, with the commission accent (HTML of the
 * page) inside each subtitle; V2_PARTNER_ALIASES maps the ?tip values in use onto a profile. The scripts only ever
 * copy this server copy into the hero, never text from the address (except the ?loc name, set as text).
 */

function v2_partner_profiles(string $accent): array
{
    return [
        'escape' => ['label' => 'escape room', 'h1a' => 'Vinzi bilete', 'h1b' => 'la camerele tale de escape.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Sloturi de 60 de minute, capacitate pe cameră, rezervări în detaliu și o aplicație cu scanare offline. Comisionul de ' . $accent . ' e plătit de jucător — tu îți păstrezi prețul stabilit.'],
        'muzeu' => ['label' => 'muzeu', 'h1a' => 'Vinzi bilete', 'h1b' => 'la muzeul tău.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Bilete de acces pe zile și intervale orare, ghidaj și pachete pentru grupuri școlare, plus emitere automată de documente fiscale. Comisionul de ' . $accent . ' e plătit de vizitator — tu îți păstrezi prețul stabilit.'],
        'parc-distractii' => ['label' => 'parc de distracții', 'h1a' => 'Vinzi bilete', 'h1b' => 'la parcul tău.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Bilete de acces, abonamente, add-on-uri pentru atracții și rentals — toate într-un singur sistem, online și la fața locului. Comisionul de ' . $accent . ' e plătit de vizitator — tu îți păstrezi prețul stabilit.'],
        'parc-aventura' => ['label' => 'parc de aventură', 'h1a' => 'Vinzi bilete', 'h1b' => 'la traseele tale de aventură.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Trasee pe niveluri de dificultate, sloturi pe capacitate, închiriere de echipament (rental) și scanare offline pe teren. Comisionul de ' . $accent . ' e plătit de aventurier — tu îți păstrezi prețul stabilit.'],
        'natura' => ['label' => 'experiență în natură', 'h1a' => 'Vinzi bilete', 'h1b' => 'la experiențele tale în natură.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Tururi ghidate, trasee și activități outdoor cu sloturi pe zile și ore, plus scanare offline acolo unde nu prinde semnal. Comisionul de ' . $accent . ' e plătit de participant — tu îți păstrezi prețul stabilit.'],
        'acvarii-zoo' => ['label' => 'grădină zoologică / acvariu', 'h1a' => 'Vinzi bilete', 'h1b' => 'la grădina ta zoo / acvariu.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Bilete de acces pe zile și intervale, experiențe cu animale și pachete de familie, online și la casă. Comisionul de ' . $accent . ' e plătit de vizitator — tu îți păstrezi prețul stabilit.'],
        'ateliere' => ['label' => 'atelier creativ', 'h1a' => 'Vinzi locuri', 'h1b' => 'la atelierele tale creative.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Sesiuni cu locuri limitate, pachete de materiale și rezervare pe sloturi orare, fără supravânzare. Comisionul de ' . $accent . ' e plătit de participant — tu îți păstrezi prețul stabilit.'],
        'tururi' => ['label' => 'tur turistic', 'h1a' => 'Vinzi bilete', 'h1b' => 'la tururile tale ghidate.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'City walks și tururi cu plecări pe ore, capacitate per plecare și bilete pe telefon. Comisionul de ' . $accent . ' e plătit de turist — tu îți păstrezi prețul stabilit.'],
        'educatie' => ['label' => 'program educațional', 'h1a' => 'Vinzi locuri', 'h1b' => 'la programele tale educaționale.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Activități STEM și lecții interactive cu rezervare pe clase și grupuri, plus documente fiscale generate automat. Comisionul de ' . $accent . ' e plătit de participant — tu îți păstrezi prețul stabilit.'],
        'familie' => ['label' => 'activitate pentru familie', 'h1a' => 'Vinzi bilete', 'h1b' => 'la activitățile tale pentru familii.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Pachete de familie, prețuri pe categorii de vârstă și rezervare pe sloturi, online și la fața locului. Comisionul de ' . $accent . ' e plătit de client — tu îți păstrezi prețul stabilit.'],
        'corporate' => ['label' => 'experiență corporate', 'h1a' => 'Vinzi locuri', 'h1b' => 'la experiențele tale pentru echipe.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Pachete de grup pentru team-building și evenimente private, cu facturare fiscală automată. Comisionul de ' . $accent . ' e plătit de client — tu îți păstrezi prețul stabilit.'],
        'cultura' => ['label' => 'instituție culturală', 'h1a' => 'Vinzi bilete', 'h1b' => 'la evenimentele tale culturale.', 'h1c' => 'Prețul tău rămâne al tău.', 'sub' => 'Bilete cu locuri sau acces general, pe date și intervale, cu emitere fiscală automată. Comisionul de ' . $accent . ' e plătit de spectator — tu îți păstrezi prețul stabilit.'],
    ];
}

const V2_PARTNER_ALIASES = [
    'escape' => 'escape', 'escape-room' => 'escape', 'escape-rooms' => 'escape',
    'muzeu' => 'muzeu', 'muzee' => 'muzeu', 'muzee-expozitii' => 'muzeu', 'museum' => 'muzeu',
    'parc-distractii' => 'parc-distractii', 'parc-distracții' => 'parc-distractii', 'distractii' => 'parc-distractii', 'parcuri-de-distractii' => 'parc-distractii',
    'parc-aventura' => 'parc-aventura', 'aventura' => 'parc-aventura', 'parcuri-de-aventura' => 'parc-aventura',
    'natura' => 'natura', 'natura-outdoor' => 'natura', 'outdoor' => 'natura',
    'acvarii-zoo' => 'acvarii-zoo', 'zoo' => 'acvarii-zoo', 'acvariu' => 'acvarii-zoo',
    'ateliere' => 'ateliere', 'atelier' => 'ateliere', 'ateliere-experiente-creative' => 'ateliere',
    'tururi' => 'tururi', 'tur' => 'tururi', 'tururi-experiente-turistice' => 'tururi',
    'educatie' => 'educatie', 'educatie-invatare-experientiala' => 'educatie', 'stem' => 'educatie',
    'familie' => 'familie', 'familie-copii' => 'familie', 'copii' => 'familie',
    'corporate' => 'corporate', 'corporate-grupuri' => 'corporate',
    'cultura' => 'cultura', 'cultura-arta' => 'cultura', 'arta' => 'cultura',
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Split-screen login screens
    |--------------------------------------------------------------------------
    |
    | Copy and colours for the branded login pages rendered by
    | App\Filament\Auth\TixelloLogin. Every panel falls back to `default`,
    | so a panel that is not listed under `panels` still renders correctly.
    |
    | The submit button colour is NOT configured here: it comes from the
    | panel's own `->colors(['primary' => ...])`, so it always matches the
    | interface the user lands in after signing in.
    |
    */

    'default' => [
        // Small uppercase label above the headline, on the dark column.
        'eyebrow' => 'Tixello',

        // Large headline on the dark column.
        'headline' => 'Bine ai revenit.',

        // Supporting paragraph under the headline.
        'subcopy' => 'Autentifică-te pentru a continua.',

        // Heading and subheading above the form, on the light column.
        'greeting' => 'Bine ai revenit',
        'greeting_sub' => 'Autentifică-te pentru a continua.',

        // Browser tab title.
        'title' => 'Autentificare',

        // Key from the `accents` list below.
        'accent' => 'indigo',
    ],

    'panels' => [

        'admin' => [
            'eyebrow' => 'Tixello Platform',
            'headline' => 'Controlul complet asupra platformei.',
            'subcopy' => 'Evenimente, organizatori, vânzări și rapoarte, într-un singur loc.',
            'greeting' => 'Bine ai revenit',
            'greeting_sub' => 'Autentifică-te în panoul de administrare.',
            'title' => 'Autentificare · Administrare',
            'accent' => 'amber',
        ],

        'marketplace' => [
            'eyebrow' => 'Marketplace',
            'headline' => 'Marketplace-ul tău, la un click distanță.',
            'subcopy' => 'Gestionează organizatorii, evenimentele și comisioanele.',
            'greeting' => 'Bine ai revenit',
            'greeting_sub' => 'Autentifică-te în panoul de marketplace.',
            'title' => 'Autentificare · Marketplace',
            'accent' => 'emerald',
        ],

        'tenant' => [
            'eyebrow' => 'Cont organizator',
            'headline' => 'Evenimentele tale, sub control.',
            'subcopy' => 'Creează, publică și vinde. De rest ne ocupăm noi.',
            'greeting' => 'Bine ai revenit',
            'greeting_sub' => 'Autentifică-te în contul tău de organizator.',
            'title' => 'Autentificare · Organizator',
            'accent' => 'indigo',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Accent palettes
    |--------------------------------------------------------------------------
    |
    | Each palette drives the gradient of the dark brand column. `bg1` is the
    | top of the gradient, `bg3` the bottom, `glow` the soft radial light
    | behind the headline, and `eyebrow` the colour of the small label.
    |
    */

    'accents' => [

        'amber' => [
            'bg1' => '#17120a',
            'bg2' => '#2a1d08',
            'bg3' => '#5a3c0b',
            'glow' => '#f59e0b',
            'eyebrow' => '#fcd34d',
        ],

        'emerald' => [
            'bg1' => '#0a1310',
            'bg2' => '#082820',
            'bg3' => '#0a5240',
            'glow' => '#10b981',
            'eyebrow' => '#6ee7b7',
        ],

        'indigo' => [
            'bg1' => '#0d0c18',
            'bg2' => '#191635',
            'bg3' => '#332c78',
            'glow' => '#6366f1',
            'eyebrow' => '#a5b4fc',
        ],

    ],

];

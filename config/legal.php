<?php

/*
| Termenii și condițiile (/termeni-si-conditii) și politica de confidențialitate
| (/politica-confidentialitate). Datele firmei vin din Setări (Setting::current()).
| Versiunea e cea salvată la înscriere (tenants.terms_version), deci se schimbă
| doar odată cu textul.
*/
return [
    'version' => env('LEGAL_VERSION', '2026-10-26'),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '26 octombrie 2026'),

    // Unde scrie cineva pentru datele personale
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', 'contact@tixello.com'),
    'support_email' => env('LEGAL_SUPPORT_EMAIL', 'contact@tixello.com'),

    // Găzduirea aplicației și a bazei de date
    'hosting_provider' => env('LEGAL_HOSTING_PROVIDER', 'furnizorul nostru de servere'),
    'hosting_location' => env('LEGAL_HOSTING_LOCATION', 'Uniunea Europeană'),

    // Copii de siguranță ale bazei de date (php artisan db:backup, zilnic)
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAILY', 7),

    /*
    | Persoanele împuternicite (furnizorii prin care trec date). „always” = folosiți
    | pentru orice cont; „optional” = doar dacă clientul pornește funcția respectivă.
    */
    'subprocessors' => [
        'always' => [
            ['name' => 'Furnizorul de găzduire (vezi mai sus)', 'purpose' => 'Serverele pe care rulează aplicația și baza de date.', 'location' => 'Uniunea Europeană'],
            ['name' => 'Cloudflare, Inc.', 'purpose' => 'DNS, rețea de livrare a conținutului, protecție împotriva atacurilor, certificate de securitate.', 'location' => 'SUA, cu servere în UE; clauze contractuale standard și EU-US Data Privacy Framework'],
            ['name' => 'Sendinblue SAS (Brevo)', 'purpose' => 'Trimiterea emailurilor: coduri de confirmare, bilete, facturi, notificări.', 'location' => 'Franța (UE)'],
        ],
        'optional' => [
            ['name' => 'Stripe Payments Europe Ltd.', 'purpose' => 'Plata cu cardul a abonamentelor din panou; procesator de plăți pentru bilete, dacă îl alegi.', 'location' => 'Irlanda (UE), cu transferuri în SUA pe bază de clauze standard'],
            ['name' => 'Netopia Payments, EuPlătesc, PayU', 'purpose' => 'Procesatori de plăți pentru bilete, dacă îi alegi. Ei încasează plata și au contract direct cu tine.', 'location' => 'România / UE'],
            ['name' => 'Agenția Națională de Administrare Fiscală (ANAF)', 'purpose' => 'Verificarea firmei după CUI; trimiterea facturilor în e-Factura, dacă pornești funcția.', 'location' => 'România'],
            ['name' => 'SmartBill, Oblio, FGO, Keez', 'purpose' => 'Emiterea facturilor în programul tău de facturare, dacă pornești conectorul.', 'location' => 'România'],
            ['name' => 'Meta Platforms Ireland Ltd.', 'purpose' => 'Facebook Conversions API și Meta Pixel, dacă le pornești: date despre vânzări, pentru măsurarea reclamelor.', 'location' => 'Irlanda (UE) și SUA; EU-US Data Privacy Framework'],
            ['name' => 'Google Ireland Ltd.', 'purpose' => 'Google Analytics, Tag Manager, Workspace, Sheets și hărți, dacă le pornești.', 'location' => 'Irlanda (UE) și SUA; EU-US Data Privacy Framework'],
            ['name' => 'TikTok Technology Ltd.', 'purpose' => 'TikTok Pixel, dacă îl pornești.', 'location' => 'Irlanda (UE), cu transferuri în afara SEE pe bază de clauze standard'],
            ['name' => 'Twilio Inc. și furnizori WhatsApp Business', 'purpose' => 'Mesaje SMS și WhatsApp, dacă le pornești.', 'location' => 'SUA / UE; clauze contractuale standard'],
            ['name' => 'Zoom, Slack', 'purpose' => 'Integrările cu aceste servicii, dacă le pornești.', 'location' => 'SUA; EU-US Data Privacy Framework'],
            ['name' => 'BunnyWay d.o.o. (Bunny.net)', 'purpose' => 'Găzduirea și redarea materialelor video, dacă folosești funcțiile video.', 'location' => 'Slovenia (UE)'],
        ],
    ],

    // Autoritatea de supraveghere
    'authority' => [
        'name' => 'Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (ANSPDCP)',
        'address' => 'B-dul G-ral Gheorghe Magheru nr. 28-30, sector 1, București',
        'website' => 'https://www.dataprotection.ro',
    ],
];

<?php

return [
    /*
    | Cine primește emailul „Tenant nou de aprobat”. Listă separată prin virgulă;
    | goală = adresa din Setări (settings.email).
    */
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIGNUP_ADMIN_EMAILS', ''))))),

    // Versiunea termenilor + politicii de confidențialitate acceptate la înscriere
    'terms_version' => env('SIGNUP_TERMS_VERSION', '2026-10'),
    'terms_url' => '/termeni-si-conditii',
    'privacy_url' => '/politica-confidentialitate',

    // Codul de verificare a emailului
    'code_ttl_minutes' => 30,
    'code_max_attempts' => 5,
    'code_resend_seconds' => 60,
];

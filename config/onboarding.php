<?php

return [
    /*
    | Cine primește emailul „Tenant nou de aprobat”. Listă separată prin virgulă;
    | goală = adresa din Setări (settings.email).
    */
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIGNUP_ADMIN_EMAILS', ''))))),

    // Expeditorul emailurilor de înscriere (trebuie verificat în Brevo) și unde ajung răspunsurile
    'mail_from' => env('SIGNUP_MAIL_FROM', 'no-reply@tixello.com'),
    'mail_from_name' => env('SIGNUP_MAIL_FROM_NAME', 'Tixello'),
    'mail_reply_to' => env('SIGNUP_MAIL_REPLY_TO', 'contact@tixello.com'),

    // Site-ul făcut 100% special (nu din modelele demo): plată unică, fără punct în plus la comision
    'custom_site_price' => (float) env('SIGNUP_CUSTOM_SITE_PRICE', 500),
    'custom_site_currency' => env('SIGNUP_CUSTOM_SITE_CURRENCY', 'EUR'),

    // Versiunea termenilor + politicii de confidențialitate acceptate la înscriere
    'terms_version' => env('SIGNUP_TERMS_VERSION', env('LEGAL_VERSION', '2026-10-27')),
    'terms_url' => '/termeni-si-conditii',
    'privacy_url' => '/politica-confidentialitate',

    // Codul de verificare a emailului
    'code_ttl_minutes' => 30,
    'code_max_attempts' => 5,
    'code_resend_seconds' => 60,
];

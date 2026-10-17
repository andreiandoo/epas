<?php

/*
|--------------------------------------------------------------------------
| Tenant sites (subdomenii + template-uri găzduite de noi)
|--------------------------------------------------------------------------
|
| Site-urile tenanților fără domeniu sau fără site rulează pe VPS, ca un
| singur site Ploi („tenant-sites”) care preia toate subdomeniile
| domeniilor de mai jos (wildcard DNS în Cloudflare). Codul site-ului este
| în resources/tenant-sites și ajunge pe server prin branch-ul `tenant-sites`.
|
*/

return [

    // Domeniile noastre pe care se pot genera subdomenii pentru tenanți.
    // `active` = wildcard-ul DNS și site-ul Ploi sunt gata: doar acestea apar
    // în acțiunea „Generează subdomeniu”.
    'base_domains' => [
        'tics.ro' => ['active' => (bool) env('TENANT_SITES_TICS_ACTIVE', true)],
        'bilete-teatru.ro' => ['active' => (bool) env('TENANT_SITES_BILETE_TEATRU_ACTIVE', false)],
        'cautbilet.ro' => ['active' => (bool) env('TENANT_SITES_CAUTBILET_ACTIVE', false)],
        'teatru-copii.ro' => ['active' => (bool) env('TENANT_SITES_TEATRU_COPII_ACTIVE', false)],
    ],

    // Subdomenii pe care nu le poate primi niciun tenant (pe lângă lista din
    // CloudflareService::getReservedSubdomains()).
    'reserved' => [
        'tics', 'tixello', 'ambilet', 'bilete', 'stage', 'sites', 'site', 'm', 'mobile',
        'radar', 'pay', 'plata', 'checkout', 'cont', 'account', 'webmail', 'smtp', 'ns1', 'ns2',
    ],

    // Demo-urile live ale template-urilor: demo-{slug}.{demo_base_domain}, plus
    // galeria demo.{demo_base_domain}. Tenantul demo primește checkout demo.
    'demo_base_domain' => env('TENANT_SITES_DEMO_DOMAIN', 'tics.ro'),

    // De unde se servesc fișierele template-urilor (public/tpl/{slug}/ pe serverul site-urilor).
    'assets_base' => env('TENANT_SITES_ASSETS_BASE', 'https://sites.tics.ro'),

    // Unde stau template-urile (același folder ajunge și pe serverul site-urilor).
    'templates_path' => resource_path('tenant-sites/templates'),

    // Template-ul propus pentru un site nou.
    'default_template' => 'teatru',

    // Checkout-ul demo (plată simulată) e permis doar tenanților cu un domeniu
    // activ sub aceste domenii (site-urile de prezentare) sau cu
    // settings.demo_checkout = true. Vezi App\Support\DemoCheckout.
    'demo_checkout_domains' => array_filter(explode(',', (string) env('DEMO_CHECKOUT_DOMAINS', 'tixello.ro'))),

    // demo:reset (curățenia de noapte a demo-urilor) nu atinge acești tenanți.
    'demo_reset_exclude' => array_map('intval', array_filter(explode(',', (string) env('TENANT_SITES_DEMO_RESET_EXCLUDE', '60')))),

    // Discul și folderul pentru imaginile urcate din panoul de personalizare.
    'disk' => 'public',
    'upload_directory' => 'tenant-sites',
];

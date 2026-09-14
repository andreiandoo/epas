<?php
/**
 * bilete.online — homepage v2 <head>.
 *
 * Same SEO, Google consent mode, deferred tracking and GetYourGuide analytics as
 * includes/head.php, but it loads only the v2 stylesheet and the self-hosted Satoshi
 * font: no Tailwind bundle, Alpine or Google Fonts on this page.
 */
$hv2Title = $pageTitleRaw ?? SITE_NAME;
$hv2Desc = mb_substr(trim($pageDescription ?? SITE_TAGLINE), 0, 160);
$hv2Canonical = $canonicalUrl ?? SITE_URL . '/';
$hv2Robots = !empty($noindex) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1';
$hv2Og = $ogImage ?? SITE_URL . '/assets/home-v2/img/hero-1440.webp';
$hv2HeroSet = hv2_asset('img/hero-900.webp') . ' 900w, ' . hv2_asset('img/hero-1440.webp') . ' 1440w, ' . hv2_asset('img/hero-1920.webp') . ' 1920w';
?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= hv2_e($hv2Title) ?></title>
<meta name="description" content="<?= hv2_e($hv2Desc) ?>">
<link rel="canonical" href="<?= hv2_e($hv2Canonical) ?>">
<meta name="robots" content="<?= $hv2Robots ?>">
<meta name="theme-color" content="#0E4837">
<meta name="format-detection" content="telephone=no">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= hv2_e(SITE_NAME) ?>">
<meta property="og:title" content="<?= hv2_e($hv2Title) ?>">
<meta property="og:description" content="<?= hv2_e($hv2Desc) ?>">
<meta property="og:url" content="<?= hv2_e($hv2Canonical) ?>">
<meta property="og:locale" content="ro_RO">
<meta property="og:image" content="<?= hv2_e($hv2Og) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="<?= hv2_e(CORE_URL) ?>" crossorigin>
<?php /* the font URL must match the one in home.css exactly, so it is preloaded without a version query */ ?>
<link rel="preload" href="/assets/home-v2/fonts/Satoshi-Variable.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" as="image" media="(min-width: 1024px)" href="<?= hv2_asset('img/hero-1920.webp') ?>" imagesrcset="<?= $hv2HeroSet ?>" imagesizes="100vw" fetchpriority="high">
<link rel="stylesheet" href="<?= hv2_asset('home.css') ?>">
<script>document.documentElement.classList.add('js')</script>

<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
(function(){
    var c=null;
    try{c=JSON.parse(localStorage.getItem('bileteonline_cookie_consent'))}catch(e){}
    var m=c&&c.marketing,a=c&&c.analytics,f=c&&c.functional;
    gtag('consent','default',{
        'ad_storage':m?'granted':'denied',
        'ad_user_data':m?'granted':'denied',
        'ad_personalization':m?'granted':'denied',
        'analytics_storage':a?'granted':'denied',
        'functionality_storage':f?'granted':'denied',
        'personalization_storage':f?'granted':'denied',
        'security_storage':'granted',
        'wait_for_update':c?0:500
    });
    gtag('set','ads_data_redaction',true);
    gtag('set','url_passthrough',true);
})();
</script>
<?php
if (!isset($trackingHeadScripts)) {
    require_once BILETEONLINE_ROOT . '/includes/tracking.php';
}
if (!empty($trackingHeadScripts)): ?>
<script>
(function(){
    var h=<?= json_encode($trackingHeadScripts) ?>;
    var done=false;
    function go(){
        if(done)return;done=true;
        var d=document.createElement('div');d.innerHTML=h;
        d.querySelectorAll('script').forEach(function(s){
            var n=document.createElement('script');
            if(s.src){n.src=s.src;n.async=true}else{n.textContent=s.textContent}
            document.head.appendChild(n);
        });
    }
    ['scroll','click','touchstart','mousemove','keydown'].forEach(function(e){
        window.addEventListener(e,go,{once:true,passive:true});
    });
    if('requestIdleCallback' in window){requestIdleCallback(function(){setTimeout(go,7000)});}
    else{setTimeout(go,10000);}
})();
</script>
<?php endif; ?>
<script>
setTimeout(function(){
    if(!window.location.search)return;
    var p=new URLSearchParams(window.location.search),del=[];
    p.forEach(function(v,k){if(/^(_gl|_ga|_up|_gac|utm_)/.test(k))del.push(k)});
    if(del.length){
        del.forEach(function(k){p.delete(k)});
        var s=p.toString();
        history.replaceState(null,'',location.pathname+(s?'?'+s:'')+location.hash);
    }
},15000);
</script>
<?php
$hv2Ld = [
    [
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL,
        'contactPoint' => ['@type' => 'ContactPoint', 'email' => SUPPORT_EMAIL, 'contactType' => 'customer support', 'areaServed' => 'RO', 'availableLanguage' => ['Romanian']],
    ],
    [
        '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL, 'inLanguage' => 'ro-RO',
        'publisher' => ['@type' => 'Organization', 'name' => 'Tixello', 'url' => 'https://tixello.ro/'],
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SITE_URL . '/cauta?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
    ],
    [
        '@context' => 'https://schema.org', '@type' => 'FAQPage',
        'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
        }, HV2_FAQ),
    ],
];
foreach ($hv2Ld as $ld) {
    echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
}
?>
<script async defer src="https://widget.getyourguide.com/dist/pa.umd.production.min.js" data-gyg-partner-id="HF2XYCH"></script>
</head>

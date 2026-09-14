<?php
/**
 * bilete.online v2 <head>.
 *
 * Same SEO, Google consent mode, deferred tracking and GetYourGuide analytics as includes/head.php,
 * but it loads the v2 stylesheets and the self-hosted Satoshi font (no Tailwind, Alpine or Google Fonts).
 *
 * Page variables (all optional):
 *   $pageTitle        title segment, gets " · bilete.online"
 *   $pageTitleRaw     full title, used as is
 *   $pageDescription  meta description (cut to 160 characters)
 *   $canonicalUrl     defaults to SITE_URL + path
 *   $noindex          true for noindex, follow
 *   $ogImage          share image URL
 *   $structuredData   extra JSON-LD blocks (PHP arrays)
 *   $v2Styles         page stylesheets under assets/v2/css, e.g. ['home.css']
 *   $v2HeadExtra      raw HTML before </head> (preloads, noscript styles)
 */
$v2Title = $pageTitleRaw ?? (!empty($pageTitle) ? $pageTitle . ' · ' . SITE_NAME : SITE_NAME);
$v2Desc = mb_substr(trim($pageDescription ?? SITE_TAGLINE), 0, 160);
$v2Canonical = $canonicalUrl ?? SITE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$v2Robots = !empty($noindex) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1';
$v2Og = $ogImage ?? SITE_URL . '/assets/v2/img/hero-1440.webp';
?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= v2_e($v2Title) ?></title>
<meta name="description" content="<?= v2_e($v2Desc) ?>">
<link rel="canonical" href="<?= v2_e($v2Canonical) ?>">
<meta name="robots" content="<?= $v2Robots ?>">
<meta name="theme-color" content="#0E4837">
<meta name="format-detection" content="telephone=no">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= v2_e(SITE_NAME) ?>">
<meta property="og:title" content="<?= v2_e($v2Title) ?>">
<meta property="og:description" content="<?= v2_e($v2Desc) ?>">
<meta property="og:url" content="<?= v2_e($v2Canonical) ?>">
<meta property="og:locale" content="ro_RO">
<meta property="og:image" content="<?= v2_e($v2Og) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="<?= v2_e(CORE_URL) ?>" crossorigin>
<?php /* the font URL must match the one in base.css exactly, so it is preloaded without a version query */ ?>
<link rel="preload" href="/assets/v2/fonts/Satoshi-Variable.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= v2_asset('css/base.css') ?>">
<?php foreach (($v2Styles ?? []) as $v2Css): ?>
<link rel="stylesheet" href="<?= v2_asset('css/' . $v2Css) ?>">
<?php endforeach; ?>
<?= $v2HeadExtra ?? '' ?>
<script>document.documentElement.classList.add('js')</script>

<?php /* Google consent mode: same snippet as includes/head.php; keep both in sync with consentVersion in cookie-consent.php and base.js */ ?>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
(function(){
    function read(){
        try{
            var s=JSON.parse(localStorage.getItem('bo_cookie_consent_v1'));
            if(s&&s.version==='2026-05-26'&&s.consent)return s.consent;
        }catch(e){}
        return null;
    }
    function state(c){
        var m=!!(c&&c.marketing),a=!!(c&&c.analytics),p=!!(c&&c.personalization);
        return {
            'ad_storage':m?'granted':'denied',
            'ad_user_data':m?'granted':'denied',
            'ad_personalization':m?'granted':'denied',
            'analytics_storage':a?'granted':'denied',
            'functionality_storage':p?'granted':'denied',
            'personalization_storage':p?'granted':'denied',
            'security_storage':'granted'
        };
    }
    var c=read(),d=state(c);
    d.wait_for_update=c?0:500;
    gtag('consent','default',d);
    gtag('set','ads_data_redaction',true);
    gtag('set','url_passthrough',true);
    window.addEventListener('bo-cookie-consent-updated',function(e){
        gtag('consent','update',state(e.detail&&e.detail.consent));
    });
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
$v2Ld = array_merge([
    [
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL,
        'contactPoint' => ['@type' => 'ContactPoint', 'email' => SUPPORT_EMAIL, 'contactType' => 'customer support', 'areaServed' => 'RO', 'availableLanguage' => ['Romanian']],
    ],
    [
        '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL, 'inLanguage' => 'ro-RO',
        'publisher' => ['@type' => 'Organization', 'name' => 'Tixello', 'url' => 'https://tixello.ro/'],
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SITE_URL . '/cauta?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
    ],
], $structuredData ?? []);
foreach ($v2Ld as $ld) {
    echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
}
?>
<script async defer src="https://widget.getyourguide.com/dist/pa.umd.production.min.js" data-gyg-partner-id="HF2XYCH"></script>
</head>

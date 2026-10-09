<?php
/**
 * viaqui.com v2 <head>.
 *
 * Same SEO, Google consent mode, deferred tracking and GetYourGuide analytics as includes/head.php,
 * but it loads the v2 stylesheets and the self-hosted Geist font (no Tailwind, Alpine or Google Fonts).
 *
 * Page variables (all optional):
 *   $pageTitle        title segment, gets " · viaqui.com"
 *   $pageTitleRaw     full title, used as is
 *   $pageDescription  meta description (cut to 160 characters)
 *   $canonicalUrl     defaults to SITE_URL + path
 *   $noindex          true for noindex, follow
 *   $ogImage          share image URL
 *   $structuredData   extra JSON-LD blocks (PHP arrays)
 *   $v2Styles         page stylesheets under assets/v2/css, e.g. ['home.css']
 *   $v2InlineCss      true puts the stylesheets inside the page, so the first paint does not wait for them
 *                     (for landing pages; the page grows by the size of the stylesheets on every view)
 *   $v2HeadExtra      raw HTML before </head> (preloads, noscript styles)
 */
$v2Title = $pageTitleRaw ?? (!empty($pageTitle) ? $pageTitle . ' · ' . SITE_NAME : SITE_NAME);
$v2Desc = mb_substr(trim($pageDescription ?? v2_t('Your way in.')), 0, 160);      // the tagline (SITE_TAGLINE), in the visitor's language
$v2Canonical = $canonicalUrl ?? SITE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$v2Robots = !empty($noindex) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1';
$v2Og = $ogImage ?? SITE_URL . '/assets/v2/img/hero-1440.webp';
if (is_string($v2Og) && strncmp($v2Og, '/', 1) === 0) {
    $v2Og = SITE_URL . $v2Og;      // a photo from our own thumbnail cache: share previews need the full address
}
v2_i18n_boot();      // a language other than the default: internal links get its prefix on the way out
?><!DOCTYPE html>
<html lang="<?= v2_e(v2_locale()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= v2_e($v2Title) ?></title>
<meta name="description" content="<?= v2_e($v2Desc) ?>">
<link rel="canonical" href="<?= v2_e($v2Canonical) ?>">
<?= v2_hreflang($v2Canonical) ?><?= v2_i18n_script() ?>
<meta name="robots" content="<?= $v2Robots ?>">
<meta name="theme-color" content="#0F4D3A">
<meta name="format-detection" content="telephone=no">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= v2_e(SITE_NAME) ?>">
<meta property="og:title" content="<?= v2_e($v2Title) ?>">
<meta property="og:description" content="<?= v2_e($v2Desc) ?>">
<meta property="og:url" content="<?= v2_e($v2Canonical) ?>">
<meta property="og:locale" content="en_GB">
<meta property="og:image" content="<?= v2_e($v2Og) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="manifest" href="/site.webmanifest">
<?php /* photos of guides and listings come from core, most of them below the fold: the name is resolved early, the
   connection opens when the first one is needed */ ?>
<link rel="dns-prefetch" href="<?= v2_e(CORE_URL) ?>">
<?php /* the font URL must match the one in base.css exactly, so it is preloaded without a version query */ ?>
<link rel="preload" href="/assets/v2/fonts/Geist-latin.woff2" as="font" type="font/woff2" crossorigin>
<?php $v2Inline = !empty($v2InlineCss) ? v2_inline_css(array_merge(['base.css'], $v2Styles ?? [])) : null; ?>
<?php if ($v2Inline !== null): ?>
<style><?= $v2Inline ?></style>
<?php else: ?>
<link rel="stylesheet" href="<?= v2_asset('css/base.css') ?>">
<?php foreach (($v2Styles ?? []) as $v2Css): ?>
<link rel="stylesheet" href="<?= v2_asset('css/' . $v2Css) ?>">
<?php endforeach; ?>
<?php endif; ?>
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
<?php if (!empty($trackingEager)): /* thank-you page: the purchase conversion needs the pixels now */ ?>
    go();
<?php endif; ?>
    if('requestIdleCallback' in window){requestIdleCallback(function(){setTimeout(go,7000)});}
    else{setTimeout(go,10000);}
})();
</script>
<?php endif; ?>
<?php if (!empty($trackingEager) && !empty($trackingConversions['google_ads']) && is_string($trackingConversions['google_ads'])): ?>
<script>window.BO_ADS=<?= json_encode(['google_ads' => $trackingConversions['google_ads']], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
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
        'contactPoint' => ['@type' => 'ContactPoint', 'email' => SUPPORT_EMAIL, 'contactType' => 'customer support', 'areaServed' => 'Europe', 'availableLanguage' => ['English']],
    ],
    [
        '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL, 'inLanguage' => 'en',
        'publisher' => ['@type' => 'Organization', 'name' => 'Tixello', 'url' => 'https://tixello.com/'],
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SITE_URL . '/search?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
    ],
], $structuredData ?? []);
foreach ($v2Ld as $ld) {
    echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
}
?>
<?php if (empty($ckEmbed)): /* not inside the checkout embedded on an operator's site */ ?>
<?php /* Partner scripts (GetYourGuide widget, Travelpayouts): only after the visitor allows marketing cookies, at
   once when they do. The key and version are the cookie banner's (base.js, cookie-consent.php). */ ?>
<script>
(function () {
    var done = false;
    function allowed() {
        try {
            var s = JSON.parse(localStorage.getItem('bo_cookie_consent_v1'));
            return !!(s && s.version === '2026-05-26' && s.consent && s.consent.marketing);
        } catch (e) { return false; }
    }
    function load() {
        if (done || !allowed()) return;
        done = true;
        var g = document.createElement('script');
        g.async = true;
        g.src = 'https://widget.getyourguide.com/dist/pa.umd.production.min.js';
        g.setAttribute('data-gyg-partner-id', 'HF2XYCH');
        document.head.appendChild(g);
        var t = document.createElement('script');
        t.async = true;
        t.setAttribute('data-cmp-ab', '2');
        t.src = 'https://emrldtp.cc/NTgyNDkw.js?t=582490';
        document.head.appendChild(t);
    }
    load();
    window.addEventListener('bo-cookie-consent-updated', load);
})();
</script>
<?php endif; ?>
</head>

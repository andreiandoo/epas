<?php
/**
 * Partners: /parteneri (v2, direction "product theatre"). The single sales page for operators: it gathers what
 * /devino-partener, /pentru-locatii and /vinde-bilete say (those pages stay online). It is built in confirmed steps;
 * this file holds the hero and the ecosystem numbers, and stays noindex until the page is complete.
 *
 * Hero: personalised like /devino-partener (?tip=<type>&loc=<name>, shared copy in includes/v2/partner-profiles.php),
 * two ways in (self-service signup carrying tip/loc, or a demo request), and a stage where the operator's tools play:
 * the dashboard, the ticket-office receipt and the scanning phone. The screens are HTML mock-ups with sample figures,
 * marked as such. partners.js runs the stage, the funnel pings (leads.track, same as /devino-partener) and, on large
 * screens with motion allowed, the scroll scenes (GSAP + ScrollTrigger + Lenis, loaded only there).
 */

$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/partner-profiles.php';

$ptAccent = '<strong class="pt-accent">doar 2%*</strong>';
$ptProfiles = v2_partner_profiles($ptAccent);

// Real results of the Tixello ecosystem bilete.online runs on (confirmed by the owner).
$ptStats = [[4294, 'Evenimente & activități', ''], [96341, 'Clienți în bază', ''], [301310, 'Bilete vândute', ''], [4409557, 'Vânzări generate', ' €']];

// A small QR-like pattern for the mock-ups: finder squares plus a fixed pseudo-random fill (not a real code).
$ptQr = static function (string $seed, int $n = 21): string {
    $cells = '';
    $finder = static fn (int $x, int $y): bool => ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($finder($x, $y)) {
                $fx = $x >= $n - 7 ? $x - ($n - 7) : $x;
                $fy = $y >= $n - 7 ? $y - ($n - 7) : $y;
                $on = ($fx === 0 || $fx === 6 || $fy === 0 || $fy === 6) || ($fx >= 2 && $fx <= 4 && $fy >= 2 && $fy <= 4);
            } else {
                $on = (crc32($seed . ':' . $x . ':' . $y) & 3) === 0;
            }
            if ($on) {
                $cells .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1"/>';
            }
        }
    }
    return '<svg viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges" aria-hidden="true" focusable="false">' . $cells . '</svg>';
};

$pageTitleRaw = 'Parteneri ' . SITE_NAME . ' — vinde bilete la activitățile tale, comision 2%* plătit de client';
$pageDescription = 'Tot ce primește o locație pe bilete.online: booking pe sloturi, panou de operator, ghișeu cu bon, aplicație de scanare, analytics și tracking, deconturi și documente fiscale. Comision 2%* plătit de cumpărător, fără abonament.';
$canonicalUrl = SITE_URL . '/parteneri';
$noindex = true; // until every section is in place
$ogImage = SITE_URL . '/assets/v2/img/hero-1440.webp';

$v2Styles = ['partners.css'];
$v2Scripts = ['partners.js'];
$v2HeaderOverlay = true;
$v2ClientData = [
    'profiles' => $ptProfiles,
    'aliases' => V2_PARTNER_ALIASES,
    'libs' => [v2_asset('vendor/gsap-3.15.0.min.js'), v2_asset('vendor/ScrollTrigger-3.15.0.min.js'), v2_asset('vendor/lenis-1.3.26.min.js')],
];
// head.php strips utm_* from the address bar after load; the funnel pings read them from here
$v2HeadExtra = '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO: the stage ===================== -->
  <section class="pt-hero" id="pt-hero" aria-labelledby="pt-h">
    <div class="pt-bg" aria-hidden="true">
      <svg class="deco-arches" viewBox="0 0 400 400" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    </div>
    <div class="pt-in">
      <div class="pt-copy" id="pt-copy">
        <p class="pt-hello pt-rv" id="pt-hello" style="--d:0ms" hidden></p>
        <p class="pt-chip pt-rv" style="--d:40ms"><span class="pt-live" aria-hidden="true"></span><span id="pt-chip-t">Ticketing &amp; booking pentru activități</span></p>
        <h1 class="pt-h" id="pt-h">
          <span class="pt-line" style="--d:120ms"><span class="pt-line-in" id="pt-h1a">Vinzi bilete</span></span>
          <span class="pt-line is-soft" style="--d:220ms"><span class="pt-line-in" id="pt-h1b">la activitățile tale.</span></span>
          <span class="pt-line is-mark" style="--d:320ms"><span class="pt-line-in"><span id="pt-h1c">Prețul tău rămâne al tău.</span></span></span>
        </h1>
        <p class="pt-lead pt-rv" id="pt-sub" style="--d:460ms">Booking pe sloturi orare și calendar, panou de operator, ghișeu cu bon, aplicație de scanare offline, analytics și tracking care îți reduce costul reclamelor. Comisionul de <?= $ptAccent ?> e plătit de cumpărător — tu îți păstrezi prețul stabilit.</p>
        <div class="pt-cta pt-rv" style="--d:560ms">
          <a class="btn btn-light pt-go" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_hero_signup"><span id="pt-cta-t">Începe gratuit</span><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/pentru-locatii#demo" data-track-cta="parteneri_hero_demo"><?= v2_ic('calendar-blank') ?>Cere un demo</a>
        </div>
        <ul class="pt-ticks pt-rv" style="--d:660ms">
          <li><?= v2_ic('check') ?>0 lei cost de pornire</li>
          <li><?= v2_ic('check') ?>Fără abonament</li>
          <li><?= v2_ic('check') ?>Onboarding în ≈5 minute</li>
          <li><?= v2_ic('check') ?>Live în maximum o zi</li>
        </ul>
      </div>

      <div class="pt-stage" id="pt-stage" aria-hidden="true">
        <div class="pt-scene" id="pt-scene">
          <!-- dashboard -->
          <div class="pt-dash pt-part" style="--d:380ms">
            <div class="pt-win"><i></i><i></i><i></i><span>bilete.online · Panou operator</span></div>
            <div class="pt-dash-body">
              <div class="pt-dash-nav"><b class="is-on"></b><b></b><b></b><b></b><b></b></div>
              <div class="pt-dash-main">
                <div class="pt-kpis">
                  <div><small>Vânzări azi</small><b><span id="pt-kpi-sales">12.480</span> lei</b><em><?= v2_ic('trend-up') ?>18%</em></div>
                  <div><small>Bilete emise</small><b id="pt-kpi-tickets">286</b><em><?= v2_ic('trend-up') ?>42</em></div>
                  <div><small>Ocupare sloturi</small><b>84%</b><em class="is-soft">azi</em></div>
                </div>
                <div class="pt-chart">
                  <svg viewBox="0 0 300 92" preserveAspectRatio="none" focusable="false">
                    <defs><linearGradient id="pt-area-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2BB673" stop-opacity=".35"/><stop offset="1" stop-color="#2BB673" stop-opacity="0"/></linearGradient></defs>
                    <path class="pt-area" d="M0 78 C 30 70, 45 60, 70 62 S 115 40, 140 46 S 185 22, 210 30 S 255 12, 300 8 L300 92 L0 92 Z"/>
                    <path class="pt-curve" pathLength="1" d="M0 78 C 30 70, 45 60, 70 62 S 115 40, 140 46 S 185 22, 210 30 S 255 12, 300 8"/>
                  </svg>
                  <span class="pt-chart-tag">Ultimele 7 zile</span>
                </div>
                <ul class="pt-orders" id="pt-orders">
                  <li><i class="is-green"></i><b>Escape room · Camera 2</b><small>4 bilete · 18:00</small><em>180 lei</em></li>
                  <li><i class="is-yellow"></i><b>Muzeu · Acces general</b><small>2 bilete · 11:30</small><em>60 lei</em></li>
                  <li><i class="is-red"></i><b>Tur ghidat · Centrul vechi</b><small>6 bilete · 16:00</small><em>210 lei</em></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- ticket office -->
          <div class="pt-pos pt-part" style="--d:620ms">
            <div class="pt-printer"><span>Ghișeu 1</span><i></i></div>
            <div class="pt-feed"><div class="pt-receipt" id="pt-receipt">
              <p class="pt-r-brand">bilete.online</p>
              <p class="pt-r-meta">Bon nr. 0147 · 14:32</p>
              <ul><li><span>2 × Adult</span><b>90,00</b></li><li><span>1 × Copil</span><b>25,00</b></li></ul>
              <p class="pt-r-total"><span>Total</span><b>115,00 lei</b></p>
              <p class="pt-r-pay">Card · aprobat</p>
              <span class="pt-r-qr"><?= $ptQr('receipt', 21) ?></span>
            </div></div>
          </div>

          <!-- scanning phone -->
          <div class="pt-phone pt-part" style="--d:820ms">
            <div class="pt-phone-in">
              <p class="pt-ph-h">Scanare · Intrarea 1</p>
              <div class="pt-view"><span class="pt-qr"><?= $ptQr('ticket', 21) ?></span><i class="pt-scanline"></i><i class="pt-corners"></i></div>
              <p class="pt-result" id="pt-result"><?= v2_ic('check-circle') ?><span id="pt-result-t">Bilet valid</span></p>
              <p class="pt-count"><b id="pt-in">128</b> / 150 au intrat</p>
            </div>
          </div>

          <!-- live events -->
          <p class="pt-toast is-a" id="pt-toast-a"><span class="pt-t-ic"><?= v2_ic('ticket') ?></span><span><b>Rezervare nouă</b><small>Slot 18:00 · 4 persoane</small></span></p>
          <p class="pt-toast is-b" id="pt-toast-b"><span class="pt-t-ic is-yellow"><?= v2_ic('coins') ?></span><span><b>Decont cerut</b><small>4.320 lei · în procesare</small></span></p>
          <span class="pt-mock">Machetă ilustrativă</span>
        </div>
      </div>
    </div>
    <a class="pt-cue" href="#cifre"><span>Derulează</span><i aria-hidden="true"></i></a>
    <svg class="pt-hero-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== NUMBERS ===================== -->
  <section class="pt-stats" id="cifre" aria-labelledby="pt-stats-h">
    <div class="wrap">
      <p class="pt-stats-cap" id="pt-stats-h">Rezultate reale în ecosistemul Tixello — pe care e construit bilete.online</p>
      <ul class="pt-stats-grid">
        <?php foreach ($ptStats as [$statValue, $statLabel, $statSuffix]): ?>
        <li><b><span class="sr"><?= number_format($statValue, 0, ',', '.') . v2_e($statSuffix) ?></span><span aria-hidden="true"><span data-count="<?= $statValue ?>"><?= number_format($statValue, 0, ',', '.') ?></span><?= v2_e($statSuffix) ?></span></b><span class="pt-stat-l"><?= v2_e($statLabel) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>

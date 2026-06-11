<?php
/**
 * bilete.online — /devino-partener
 *
 * Sales / partner-acquisition landing page. Uses the site's standard
 * header + footer (matches the rest of the public pages) and the site
 * theme palette (paper / ink / vermilion / ochre / forest) — NOT a
 * standalone design with its own theme.
 *
 * Two structural pieces are data-driven so the page reflects the live
 * marketplace state instead of a frozen pitch:
 *   - the marquee band of category names at the top
 *   - the "Categoriile disponibile pe bilete.online" grid
 * Both pull from /api/marketplace-client/event-categories.
 *
 * Personalization via ?tip=<key>&loc=<name> in the URL flows through to
 * /inregistrare-locatie so a personalized campaign URL also pre-fills
 * the signup form.
 */

$pageCacheTTL = 900; // 15 min
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// ─── Categories from the public API ───────────────────────────────
$categoriesResp   = api_cached('categories_full_tree', fn () => api_get('/event-categories'), 900);
$rawCategories    = $categoriesResp['data']['categories'] ?? [];
if (!is_array($rawCategories)) $rawCategories = [];
$parentCategories = array_values(array_filter($rawCategories, fn ($c) => empty($c['parent_id'])));
usort($parentCategories, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

// Translatable strings come back as either string or {ro,en,...} array.
$peel = static function ($value): string {
    if (is_string($value)) return $value;
    if (is_array($value))  return (string) ($value['ro'] ?? $value['en'] ?? reset($value) ?? '');
    return '';
};

// ─── SEO ──────────────────────────────────────────────────────────
$pageTitleRaw    = 'Vinde bilete la activități pe ' . SITE_NAME . ' — comision 2%* plătit de client';
$pageDescription = 'Platforma de ticketing pentru activități: booking cu sloturi și calendar, analytics avansat, tracking 100% cu Facebook CAPI, deconturi periodice, app mobilă cu scanare offline. Comision 2%* plătit de cumpărător. Construit pe Tixello.';
$canonicalUrl    = SITE_URL . '/devino-partener';
$ogImage         = SITE_URL . '/assets/images/og-default.jpg';
$currentPage     = 'devino-partener';
$cssBundle       = 'listing';

$structuredData = [[
    '@context'    => 'https://schema.org',
    '@type'       => 'Service',
    'name'        => 'bilete.online — Ticketing & booking pentru activități',
    'description' => 'Platformă de ticketing pentru locații și organizatori de activități. Comision 2% plătit de cumpărător. Booking cu sloturi orare, analytics avansat, scanare offline, deconturi periodice.',
    'provider'    => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL],
    'areaServed'  => ['@type' => 'Country', 'name' => 'Romania'],
    'offers'      => [
        '@type'         => 'Offer',
        'priceCurrency' => 'RON',
        'price'         => '0',
        'description'   => '0 lei cost de pornire. Comision 2%* plătit de cumpărător la fiecare bilet vândut.',
    ],
]];

// Page-specific styling: replicate the design's "ticket card" treatment
// (notches, marquee, reveal) in a small inline block so we don't have
// to extend the global styles for one page. Alpine intersect plugin is
// loaded NON-deferred — `defer` would queue it AFTER alpine-3 (which
// itself is deferred + loaded earlier in head.php), causing the warnings
// the user reported ("you can't use [x-intersect] without first
// installing the plugin"). A regular blocking <script> ensures the
// plugin is available before Alpine starts processing directives.
$extraHead = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.x.x/dist/cdn.min.js"></script>
<style>
.partner-section { position: relative; }
.ticket-notch { position: relative; }
.ticket-notch::before, .ticket-notch::after { content:""; position:absolute; top:50%; width:22px; height:22px; border-radius:50%; background:var(--paper, #F4EFE3); transform:translateY(-50%); z-index:2; }
.ticket-notch::before { left:-11px; } .ticket-notch::after { right:-11px; }
.marquee-track { animation: bo-scroll 32s linear infinite; }
@keyframes bo-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@keyframes bo-fill { 0%{width:10%} 50%{width:90%} 100%{width:10%} }
.reveal { opacity:0; transform:translateY(24px); transition: opacity .7s cubic-bezier(.2,.7,.2,1), transform .7s cubic-bezier(.2,.7,.2,1); }
.reveal.in { opacity:1; transform:none; }
.underline-sketch { background-image: linear-gradient(transparent 60%, rgba(232,69,39,.30) 60%); background-repeat: no-repeat; }
.shadow-hard { box-shadow: 6px 6px 0 0 #1B1714; }
.shadow-hard-sm { box-shadow: 3px 3px 0 0 #1B1714; }
.hover-shadow-hard:hover { box-shadow: 6px 6px 0 0 #1B1714; }
.partner-card { transition: transform .2s ease, box-shadow .2s ease; }
.partner-card:hover { transform: translate(-2px, -2px); box-shadow: 8px 8px 0 0 #1B1714; }
[x-cloak] { display: none !important; }
@media (prefers-reduced-motion: reduce) {
  .marquee-track, .reveal { animation: none !important; opacity: 1 !important; transform: none !important; }
}
</style>
HTML;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<main id="top" class="bg-paper">

<!-- HERO -->
<section x-data class="relative pt-12 pb-20 sm:pt-16 sm:pb-24 overflow-hidden partner-section">
  <div class="absolute top-24 -right-20 w-72 h-72 rounded-full bg-vermilion/10 blur-3xl pointer-events-none"></div>
  <div class="absolute -left-24 bottom-0 w-80 h-80 rounded-full bg-forest/10 blur-3xl pointer-events-none"></div>
  <div class="absolute inset-0 opacity-[0.04] pointer-events-none" style="background-image:linear-gradient(#1B1714 1px,transparent 1px),linear-gradient(90deg,#1B1714 1px,transparent 1px);background-size:40px 40px;"></div>

  <div class="relative max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-12 gap-12 items-center">
    <div class="lg:col-span-7">
      <template x-if="$store.perso.loc">
        <div class="inline-flex items-center gap-2 bg-ink text-paper text-sm font-semibold px-4 py-2 rounded-full mb-4 shadow-hard-sm">
          <span class="text-vermilion">👋</span>
          <span>Salut, <strong x-text="$store.perso.loc"></strong>! Iată ce putem face împreună.</span>
        </div>
      </template>

      <span class="inline-flex items-center gap-2 bg-forest text-paper text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-full -rotate-1 mb-6">
        <span class="w-2 h-2 rounded-full bg-vermilion animate-pulse"></span>
        <span x-text="$store.perso.hasProfile ? ('Ticketing & booking pentru ' + $store.perso.profile.label) : 'Ticketing & booking pentru activități'">Ticketing &amp; booking pentru activități</span>
      </span>

      <h1 class="font-display font-bold leading-[0.95] tracking-tight text-5xl sm:text-6xl lg:text-7xl text-ink">
        <span x-text="$store.perso.p('h1a','Vinzi bilete')">Vinzi bilete</span><br>
        <span class="italic font-semibold" x-text="$store.perso.p('h1b','la activitățile tale.')">la activitățile tale.</span><br>
        <span class="underline-sketch" x-text="$store.perso.p('h1c','Prețul tău rămâne al tău.')">Prețul tău rămâne al tău.</span>
      </h1>
      <p class="mt-7 text-lg sm:text-xl max-w-xl text-ink-soft leading-relaxed" x-show="!$store.perso.hasProfile">
        Booking pe sloturi orare și calendar, analytics avansat, tracking 100% care îți reduce costul reclamelor, și o aplicație mobilă cu scanare offline. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de cumpărător — tu îți păstrezi prețul stabilit.
      </p>
      <template x-if="$store.perso.hasProfile">
        <p class="mt-7 text-lg sm:text-xl max-w-xl text-ink-soft leading-relaxed" x-html="$store.perso.profile.sub"></p>
      </template>
      <div class="mt-9 flex flex-col sm:flex-row gap-4">
        <a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="hero_primary" class="group inline-flex items-center justify-center gap-2 bg-vermilion text-paper font-bold text-lg px-8 py-4 rounded-full shadow-hard hover:shadow-none hover:translate-x-[6px] hover:translate-y-[6px] transition-all">
          <span x-text="$store.perso.hasProfile ? ('Pune ' + $store.perso.profile.label + ' online') : 'Pune-ți activitățile la vânzare'">Pune-ți activitățile la vânzare</span>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="group-hover:translate-x-1 transition"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a href="#cum" data-track-cta="hero_secondary" class="inline-flex items-center justify-center gap-2 border-2 border-ink font-bold text-lg px-8 py-4 rounded-full hover:bg-ink hover:text-paper transition">Vezi cum funcționează</a>
      </div>
      <div class="mt-8 flex flex-wrap items-center gap-x-7 gap-y-3 text-sm font-semibold text-ink-soft">
        <span class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E4A3D" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> Fără costuri de pornire</span>
        <span class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E4A3D" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> Activități nelimitate</span>
        <span class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E4A3D" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> Onboarding în 5 minute</span>
      </div>
    </div>

    <!-- Ticket visual -->
    <div class="lg:col-span-5 relative">
      <div class="relative max-w-sm mx-auto rotate-[2deg] hover:rotate-0 transition-transform duration-500">
        <div class="bg-paper-2 border-2 border-ink rounded-2xl shadow-hard overflow-hidden">
          <div class="bg-ink text-paper px-6 py-4 flex items-center justify-between">
            <span class="font-display font-bold text-lg">BOOKING</span>
            <span class="font-mono text-xs tracking-widest">No. 00 962</span>
          </div>
          <div class="px-6 py-6 ticket-notch border-b-2 border-dashed border-ink/40">
            <p class="font-mono text-xs uppercase tracking-widest text-ink/50">Activitatea ta</p>
            <p class="font-display font-bold text-2xl mt-1 leading-tight">Slot orar.<br>Zi din calendar.</p>
            <div class="mt-5 grid grid-cols-3 gap-3 text-center">
              <div><p class="font-display font-bold text-2xl text-vermilion">2%*</p><p class="text-[10px] uppercase tracking-wide text-ink/60 leading-tight mt-1">plătit de client</p></div>
              <div><p class="font-display font-bold text-2xl text-forest">−60%</p><p class="text-[10px] uppercase tracking-wide text-ink/60 leading-tight mt-1">cost reclame</p></div>
              <div><p class="font-display font-bold text-2xl text-ochre">∞</p><p class="text-[10px] uppercase tracking-wide text-ink/60 leading-tight mt-1">activități</p></div>
            </div>
          </div>
          <div class="px-6 py-5 flex items-center justify-between">
            <div class="font-mono text-[10px] leading-tight text-ink/60"><p>POWERED BY TIXELLO</p><p>301.310 BILETE VÂNDUTE</p></div>
            <div class="flex items-end gap-[2px] h-9">
              <?php for ($i = 0; $i < 11; $i++): ?>
                <span class="w-[2px] bg-ink" style="height:<?= [100,75,100,90,65,100,100,80,90,100,65][$i] ?>%"></span>
              <?php endfor; ?>
            </div>
          </div>
        </div>
        <span class="absolute -top-4 -left-4 bg-vermilion text-paper font-display font-bold text-sm px-3 py-1.5 rounded-full -rotate-[8deg] shadow-hard-sm border-2 border-ink">REZERVAT</span>
      </div>
    </div>
  </div>
</section>

<!-- MARQUEE: live categories -->
<div class="bg-ink text-paper py-3 border-y-2 border-ink overflow-hidden">
  <div class="flex marquee-track whitespace-nowrap font-display font-bold text-lg">
    <?php
        $marqueeNames = array_values(array_filter(array_map(fn ($c) => $peel($c['name'] ?? ''), $parentCategories)));
        if (count($marqueeNames) > 0 && count($marqueeNames) < 6) {
            $marqueeNames = array_merge($marqueeNames, $marqueeNames);
        }
    ?>
    <?php for ($pass = 0; $pass < 2; $pass++): ?>
    <div class="flex items-center gap-10 pr-10"<?= $pass === 1 ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($marqueeNames as $name): ?>
            <span>★ <?= htmlspecialchars($name, ENT_QUOTES) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- STATS BAND -->
<section class="py-14 bg-paper-2 border-b-2 border-ink reveal">
  <div class="max-w-7xl mx-auto px-5 sm:px-8">
    <p class="text-center font-mono text-sm uppercase tracking-widest text-ink/60 mb-8">Rezultate reale în ecosistemul Tixello — pe care e construit bilete.online</p>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
      <div><p class="font-display font-bold text-4xl sm:text-5xl text-vermilion" x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=110;if(c>=4294){c=4294;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</p><p class="mt-2 font-semibold text-ink-soft">Evenimente &amp; activități</p></div>
      <div><p class="font-display font-bold text-4xl sm:text-5xl text-forest" x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=2500;if(c>=96341){c=96341;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</p><p class="mt-2 font-semibold text-ink-soft">Clienți în bază</p></div>
      <div><p class="font-display font-bold text-4xl sm:text-5xl text-ochre" x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=7800;if(c>=301310){c=301310;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</p><p class="mt-2 font-semibold text-ink-soft">Bilete vândute</p></div>
      <div><p class="font-display font-bold text-4xl sm:text-5xl text-vermilion"><span x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=115000;if(c>=4409557){c=4409557;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</span> €</p><p class="mt-2 font-semibold text-ink-soft">Vânzări generate</p></div>
    </div>
  </div>
</section>

<!-- PROBLEM -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl">
    <p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Realitatea de azi</p>
    <h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Vinzi activități, dar instrumentele te trag înapoi.</h2>
    <p class="mt-6 text-lg text-ink-soft leading-relaxed">Comisioane mari scăzute din marja ta. Booking rigid care nu suportă sloturi sau zile. Tracking ciuntit de ad blockere și iOS, care îți umflă costul reclamelor. Și zero ajutor real ca să găsești clienți noi.</p>
  </div>
  <div class="mt-12 grid sm:grid-cols-3 gap-6">
    <?php
      $problems = [
        ['💸','Comisioane din marja ta','Plătești tu, la fiecare bilet. La volum, e o gaură reală în buget.'],
        ['📅','Booking inflexibil','Activitățile au sloturi, zile, capacități. Majoritatea platformelor nu le suportă.'],
        ['📉','Tracking pierdut','Ad blockerele și iOS blochează datele de conversie — plătești mai mult pe reclame.'],
      ];
      foreach ($problems as [$icon, $title, $body]):
    ?>
    <div class="partner-card bg-paper-2 border-2 border-ink rounded-2xl p-6 shadow-hard-sm">
      <div class="text-4xl mb-3"><?= $icon ?></div>
      <h3 class="font-display font-bold text-xl"><?= htmlspecialchars($title, ENT_QUOTES) ?></h3>
      <p class="mt-2 text-ink-soft"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="problem_solve" class="inline-flex items-center gap-2 bg-ink text-paper font-bold px-7 py-3.5 rounded-full shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Rezolvă-le pe toate cu bilete.online →</a></div>
</section>

<!-- BOOKING (animated demo) -->
<section id="booking" class="py-20 sm:py-24 bg-forest text-paper relative overflow-hidden reveal">
  <div class="absolute inset-0 opacity-[0.06]" style="background-image:radial-gradient(#F4EFE3 1.5px,transparent 1.5px);background-size:22px 22px;"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Booking gândit pentru activități</p>
      <h2 class="font-display font-bold text-4xl sm:text-5xl leading-[0.97]">Sloturi orare. Zile pe calendar. Rezervare în detaliu.</h2>
      <p class="mt-6 text-lg text-paper/85 leading-relaxed max-w-lg">bilete.online nu vinde doar „un bilet". Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile — exact cum funcționează un escape room, un tur ghidat sau un atelier. Tu controlezi capacitatea fiecărui slot.</p>
      <ul class="mt-7 space-y-3">
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Selecție de zile disponibile pe calendar</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Sloturi orare cu capacitate configurabilă</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Booking detaliat: participanți, opțiuni, add-on-uri</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Pachete de grup și prețuri pe categorie de vârstă</li>
      </ul>
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="booking_slots" class="mt-8 inline-flex items-center gap-2 bg-vermilion text-paper font-bold px-7 py-3.5 rounded-full shadow-hard-sm hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau booking pe sloturi →</a>
    </div>
    <div x-data="bookingFlow()" x-intersect.once="start()" class="bg-paper-2 text-ink rounded-2xl border-2 border-paper-2 shadow-hard p-6 -rotate-[1.5deg] min-h-[420px] flex flex-col">
      <div class="flex items-center justify-between mb-1">
        <p class="font-display font-bold text-lg" x-text="steps[step].title">Alege ora</p>
        <span class="font-mono text-[10px] uppercase tracking-widest text-ink/40" x-text="(step+1)+'/'+steps.length"></span>
      </div>
      <div class="h-1.5 bg-paper-2 border border-ink/10 rounded-full overflow-hidden mb-5">
        <div class="h-full bg-vermilion rounded-full transition-all duration-500" :style="`width:${((step+1)/steps.length)*100}%`"></div>
      </div>
      <div class="relative flex-1">
        <div x-show="step===0" x-transition.opacity.duration.400ms class="absolute inset-0">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40 mb-2">Sloturi — 19 oct</p>
          <div class="grid grid-cols-3 gap-2 font-mono text-xs">
            <template x-for="(s,i) in slots" :key="i">
              <span class="border-2 rounded-full py-3 text-center transition-all duration-300" :class="s.gone ? 'border-ink/20 text-ink/30 line-through' : (chosenSlot===i ? 'border-vermilion bg-vermilion text-paper font-bold scale-105 shadow-hard-sm' : 'border-ink')" x-text="s.t"></span>
            </template>
          </div>
        </div>
        <div x-show="step===1" x-transition.opacity.duration.400ms class="absolute inset-0 space-y-2.5">
          <template x-for="(t,i) in tickets" :key="i">
            <div class="flex items-center justify-between border-2 rounded-2xl px-4 py-3 transition-all duration-300" :class="chosenTicket===i ? 'border-forest bg-forest/5 scale-[1.02]' : 'border-ink/15'">
              <div><p class="font-display font-bold text-sm" x-text="t.name"></p><p class="text-[11px] text-ink/50" x-text="t.note"></p></div>
              <div class="flex items-center gap-3"><span class="font-mono text-sm font-bold" x-text="t.price"></span><span class="w-5 h-5 rounded-full border-2 grid place-items-center transition-all" :class="chosenTicket===i ? 'border-forest bg-forest text-paper' : 'border-ink/30'"><span x-show="chosenTicket===i" class="text-[10px]">✓</span></span></div>
            </div>
          </template>
        </div>
        <div x-show="step===2" x-transition.opacity.duration.400ms class="absolute inset-0 space-y-2.5">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40">Adaugă extra &amp; rentals</p>
          <template x-for="(e,i) in extras" :key="i">
            <div class="flex items-center justify-between border-2 rounded-2xl px-4 py-3 transition-all duration-300" :class="chosenExtras.includes(i) ? 'border-vermilion bg-vermilion/5 scale-[1.02]' : 'border-ink/15'">
              <div class="flex items-center gap-2.5"><span class="text-lg" x-text="e.icon"></span><p class="font-display font-bold text-sm" x-text="e.name"></p></div>
              <div class="flex items-center gap-3"><span class="font-mono text-sm font-bold" x-text="e.price"></span><span class="w-6 h-6 rounded-full border-2 grid place-items-center font-bold transition-all" :class="chosenExtras.includes(i) ? 'border-vermilion bg-vermilion text-paper' : 'border-ink/30 text-ink/30'" x-text="chosenExtras.includes(i) ? '✓' : '+'"></span></div>
            </div>
          </template>
        </div>
        <div x-show="step===3" x-transition.opacity.duration.400ms class="absolute inset-0 space-y-3">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40">Personalizează biletul</p>
          <div>
            <label class="text-[11px] font-semibold text-ink/60">Nume pe bilet</label>
            <div class="mt-1 border-2 border-ink/20 rounded-full px-4 py-2.5 font-mono text-sm bg-paper"><span x-text="typed"></span><span class="inline-block w-[2px] h-4 bg-ink align-middle ml-[1px]" :class="caret ? 'opacity-100' : 'opacity-0'"></span></div>
          </div>
          <div>
            <label class="text-[11px] font-semibold text-ink/60">Mesaj cadou (opțional)</label>
            <div class="mt-1 border-2 border-ink/20 rounded-2xl px-4 py-2.5 text-xs bg-paper text-ink/70 italic" x-text="giftMsg"></div>
          </div>
          <div class="flex items-center gap-2 text-xs text-forest font-semibold"><span class="w-4 h-4 rounded-full bg-forest text-paper grid place-items-center text-[9px]">✓</span> Trimite biletul pe email &amp; WhatsApp</div>
        </div>
        <div x-show="step===4" x-transition.opacity.duration.400ms class="absolute inset-0">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40 mb-2">Sumar comandă</p>
          <div class="border-2 border-ink/15 rounded-2xl divide-y divide-ink/10 font-mono text-xs">
            <div class="flex justify-between px-3 py-2"><span class="text-ink/60" x-text="tickets[1] ? tickets[1].name : 'Bilet'">Bilet</span><span x-text="tickets[1] ? tickets[1].price : ''"></span></div>
            <template x-for="(e,i) in [extras[0], extras[1]]" :key="i">
              <div class="flex justify-between px-3 py-2" x-show="e"><span class="text-ink/60" x-text="e ? e.name : ''"></span><span x-text="e ? e.price : ''"></span></div>
            </template>
            <div class="flex justify-between px-3 py-2 font-bold text-sm"><span>Total estimat</span><span x-text="orderTotal()">—</span></div>
          </div>
          <div class="mt-3 grid grid-cols-5 gap-1.5 text-[9px] font-bold text-center">
            <span class="border-2 border-ink rounded-full py-1.5 bg-ink text-paper">Stripe</span>
            <span class="border border-ink/20 rounded-full py-1.5">Apple</span>
            <span class="border border-ink/20 rounded-full py-1.5">G Pay</span>
            <span class="border border-ink/20 rounded-full py-1.5">Revolut</span>
            <span class="border border-ink/20 rounded-full py-1.5">RoPay</span>
          </div>
          <div class="mt-4">
            <div class="h-2 bg-paper border border-ink/10 rounded-full overflow-hidden"><div class="h-full bg-forest rounded-full transition-all duration-200" :style="`width:${payProgress}%`"></div></div>
            <p class="mt-2 text-center text-xs font-semibold text-ink/60" x-text="payProgress<100 ? 'Se procesează plata…' : 'Plată confirmată'"></p>
          </div>
        </div>
        <div x-show="step===5" x-transition.opacity.duration.400ms class="absolute inset-0 flex flex-col items-center justify-center text-center">
          <div class="w-14 h-14 rounded-full bg-forest text-paper grid place-items-center text-2xl mb-3" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-50" x-transition:enter-end="opacity-100 scale-100">✓</div>
          <p class="font-display font-bold text-xl">Comandă confirmată!</p>
          <p class="text-sm text-ink/60 mt-1">Bilet #BO-19024 · 14:00</p>
          <div class="mt-4 w-full space-y-2">
            <template x-for="(m,i) in messages" :key="i">
              <div x-show="msgShown>i" x-transition:enter="transition ease-out duration-400" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="flex items-center gap-2.5 bg-paper border border-ink/10 rounded-full px-3 py-2 text-left text-xs"><span class="text-base" x-text="m.icon"></span><span x-text="m.text"></span></div>
            </template>
          </div>
        </div>
      </div>
      <p class="mt-4 pt-3 border-t border-ink/10 text-center font-mono text-[10px] uppercase tracking-widest text-ink/40">Demo booking bilete.online</p>
    </div>
  </div>
</section>

<!-- CORE BENEFITS -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Tot ce-ți trebuie</p><h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">O platformă completă pentru vânzarea de activități.</h2></div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
      $benefits = [
        ['♾️','Activități nelimitate','Adaugi oricâte activități, de orice tip și în orice formă. Fără limită, fără costuri de pornire.'],
        ['⚙️','Gestiune avansată','Capacități, sloturi, variante de preț, disponibilitate, add-on-uri — controlezi fiecare detaliu al fiecărei activități.'],
        ['🏷️','Coduri de reducere','Creezi coduri promoționale și campanii de discount, cu reguli proprii, ca să-ți crești vânzările când vrei.'],
        ['👨‍👩‍👧‍👦','Pachete de grup','Vinzi pachete pentru grupuri, familii, clase sau echipe corporate, cu prețuri și capacități dedicate.'],
        ['🎯','Sistem de recomandare','Motorul propriu expune activitățile tale celor mai potriviți cumpărători din baza de peste 96.000 de clienți.'],
        ['🔒','Bilete sigure','Validare QR, verificare și protecție anti-fraudă — tehnologie testată în producție pe Tixello.'],
      ];
      foreach ($benefits as [$icon, $title, $body]):
    ?>
    <div class="partner-card bg-ink text-paper rounded-2xl p-7 shadow-hard border-2 border-ink">
      <div class="text-3xl mb-3"><?= $icon ?></div>
      <h3 class="font-display font-bold text-2xl"><?= htmlspecialchars($title, ENT_QUOTES) ?></h3>
      <p class="mt-3 text-paper/80 leading-relaxed"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <?php
      $smallFeatures = [
        ['📄','Pagină dedicată per activitate','Pagină SEO-friendly, link de partajat și schema markup pentru Google.'],
        ['⏳','Liste de așteptare','Slot plin? Clientul se înscrie pe waitlist și e anunțat dacă se eliberează locuri.'],
        ['🚪','Check-in & control acces','Validare QR cu prevenirea dublei intrări, ideal la sloturi cu capacitate fixă.'],
        ['📍','Multi-locație','Gestionezi mai multe locații sau puncte de lucru dintr-un singur cont.'],
        ['👥','Roluri & echipă','Adaugi colegi cu permisiuni (casier, scanare, manager), fără acces total.'],
        ['🎨','Branding propriu','Logo, culori și aspect pe paginile tale, ca să arate ca brandul tău.'],
        ['⭐','Recenzii & rating','Clienții lasă recenzii care cresc conversia pentru următorii cumpărători.'],
        ['📊','Export & rapoarte','Exporți comenzi, participanți și încasări pentru raportare și contabilitate.'],
      ];
      foreach ($smallFeatures as [$icon, $title, $body]):
    ?>
    <div class="partner-card bg-paper-2 border-2 border-ink rounded-2xl p-5 shadow-hard-sm">
      <div class="text-2xl mb-2"><?= $icon ?></div>
      <h4 class="font-display font-bold text-base leading-tight"><?= htmlspecialchars($title, ENT_QUOTES) ?></h4>
      <p class="mt-1.5 text-xs text-ink/65 leading-relaxed"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ANALYTICS + TRACKING -->
<section id="analytics" class="py-20 sm:py-24 bg-ink text-paper relative overflow-hidden reveal">
  <div class="absolute -right-32 top-10 w-96 h-96 rounded-full bg-vermilion/10 blur-3xl"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
    <div class="max-w-3xl"><p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Date care îți cresc vânzările</p><h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Analytics avansat + tracking 100%. <span class="text-vermilion">Reclame până la 60% mai ieftine.</span></h2></div>
    <div class="mt-12 grid lg:grid-cols-2 gap-10 items-start">
      <div>
        <h3 class="font-display font-bold text-2xl mb-4">De ce contează analytics-ul</h3>
        <ul class="space-y-3 text-paper/85">
          <li class="flex items-start gap-3"><span class="text-vermilion mt-1">→</span> Vezi exact ce activitate, slot și zi se vând cel mai bine</li>
          <li class="flex items-start gap-3"><span class="text-vermilion mt-1">→</span> Înțelegi de unde vin cumpărătorii și ce canal aduce profit</li>
          <li class="flex items-start gap-3"><span class="text-vermilion mt-1">→</span> Optimizezi prețurile și capacitatea pe baza cererii reale</li>
          <li class="flex items-start gap-3"><span class="text-vermilion mt-1">→</span> Urmărești conversia din vizită în vânzare, în timp real</li>
          <li class="flex items-start gap-3"><span class="text-vermilion mt-1">→</span> Identifici sloturile goale și le umpli cu promoții țintite</li>
          <li class="flex items-start gap-3"><span class="text-vermilion mt-1">→</span> Iei decizii pe date, nu pe presupuneri</li>
        </ul>
      </div>
      <div class="bg-paper/5 border border-paper/15 rounded-2xl p-7 backdrop-blur">
        <h3 class="font-display font-bold text-2xl mb-4">Tracking complet, fără pierderi</h3>
        <p class="text-paper/85 leading-relaxed">bilete.online se integrează cu <strong class="text-paper">toți pixelii de tracking</strong> și cu <strong class="text-paper">Facebook CAPI</strong>. Trimite <strong class="text-vermilion">100% din evenimentele de conversie</strong> server-side — deci nu te mai blochează ad blockerele și nici update-ul iOS care taie majoritatea trackingului.</p>
        <div class="mt-6 grid grid-cols-2 gap-3 text-center font-display font-bold">
          <div class="bg-ink/40 border border-paper/10 rounded-2xl py-4"><p class="text-3xl text-vermilion">100%</p><p class="text-xs text-paper/70 mt-1 font-sans font-semibold uppercase tracking-wide">evenimente urmărite</p></div>
          <div class="bg-ink/40 border border-paper/10 rounded-2xl py-4"><p class="text-3xl text-vermilion">−60%</p><p class="text-xs text-paper/70 mt-1 font-sans font-semibold uppercase tracking-wide">cost reclame</p></div>
        </div>
        <p class="mt-5 text-sm text-paper/70">Funcționează cu reclame pe <strong class="text-paper">Facebook, Instagram, TikTok și Google</strong>. Date corecte = algoritmi mai eficienți = cost pe vânzare mai mic.</p>
      </div>
    </div>
    <div class="mt-10"><a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="analytics_ads" class="inline-flex items-center gap-2 bg-vermilion text-paper font-bold px-7 py-3.5 rounded-full hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau reclame mai ieftine →</a></div>
  </div>
</section>

<!-- PAYMENTS -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Plăți pentru orice client</p>
      <h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Toate metodele de plată, la îndemâna cumpărătorului.</h2>
      <p class="mt-6 text-lg text-ink-soft leading-relaxed">Cu cât plata e mai simplă, cu atât vinzi mai mult. bilete.online acceptă cele mai folosite metode — clientul plătește în două atingeri, fără fricțiune.</p>
      <p class="mt-6 text-ink-soft">bilete.online încasează plata de la client și îți face <strong>deconturi periodice</strong> — sau la cerere, ori de câte ori vrei să-ți fie decontați banii.</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
      <?php foreach (['Stripe','Apple Pay','Google Pay','Revolut','RoPay','Netopia','Carduri culturale','Card bancar'] as $pm): ?>
      <div class="bg-paper-2 border-2 border-ink rounded-2xl p-5 shadow-hard-sm grid place-items-center font-display font-bold text-lg h-24 text-center leading-tight"><?= htmlspecialchars($pm) ?></div>
      <?php endforeach; ?>
      <div class="bg-ink text-paper rounded-2xl p-5 shadow-hard-sm grid place-items-center font-display font-bold text-center text-sm h-24 leading-tight">și altele<br>în curând</div>
    </div>
  </div>
</section>

<!-- THE 2% -->
<section id="bani" class="py-20 sm:py-24 bg-forest text-paper relative overflow-hidden reveal">
  <div class="absolute inset-0 opacity-[0.06]" style="background-image:radial-gradient(#F4EFE3 1.5px,transparent 1.5px);background-size:22px 22px;"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Diferența care schimbă tot</p>
      <h2 class="font-display font-bold text-4xl sm:text-6xl leading-[0.95]">Comision <span class="text-vermilion">2%*</span>.<br>Plătit de cumpărător.</h2>
      <p class="mt-7 text-lg text-paper/85 leading-relaxed max-w-lg">Comisionul de 2%* este adăugat transparent în prețul final și achitat de client. Tu îți stabilești prețul și îl primești <strong class="text-vermilion">integral</strong> la decont — fără să scazi nimic din marja ta.</p>
      <ul class="mt-7 space-y-3">
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Tu setezi prețul — tu primești prețul stabilit</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Clientul vede clar cei 2%* — onest, fără surprize</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-vermilion">✓</span> Zero costuri lunare, zero taxe de pornire</li>
      </ul>
      <p class="mt-6 text-sm text-paper/70 border-l-2 border-vermilion pl-4"><strong>* </strong>Comisionul de 2% se aplică pentru vânzarea exclusivă prin bilete.online. bilete.online încasează plata de la client și îți face deconturi periodice sau la cerere.</p>
    </div>
    <div class="bg-paper-2 text-ink rounded-2xl border-2 border-paper-2 shadow-hard p-7 -rotate-[1.5deg]">
      <p class="font-display font-bold text-xl mb-5 text-center">Bilet de 100 lei — ce primești?</p>
      <div class="space-y-4 font-mono text-sm">
        <div class="border-2 border-dashed border-ink/30 rounded-2xl p-4">
          <div class="flex justify-between font-bold uppercase text-xs tracking-wider mb-1"><span>Platformă clasică</span><span class="text-vermilion">−9,50 lei</span></div>
          <div class="text-[11px] text-ink/55 mb-2 leading-snug">comision 8% + cost tranzacționare card 1–2%, ambele scăzute din banii tăi</div>
          <div class="h-3 bg-paper border border-ink/10 rounded-full overflow-hidden"><div class="h-full bg-vermilion" style="width:90.5%"></div></div>
          <p class="mt-2 text-right">Primești: <strong class="text-vermilion">~90,50 lei</strong></p>
        </div>
        <div class="border-2 border-ink rounded-2xl p-4 bg-forest/5">
          <div class="flex justify-between font-bold uppercase text-xs tracking-wider mb-2"><span>bilete.online (2%* pe client)</span><span class="text-forest">100%</span></div>
          <div class="h-3 bg-paper border border-ink/10 rounded-full overflow-hidden"><div class="h-full bg-forest" style="width:100%"></div></div>
          <p class="mt-2 text-right">Primești: <strong class="text-forest text-base">100 lei</strong></p>
          <p class="text-[11px] text-ink/60 mt-1 text-right leading-snug">Comisionul și costul cardului sunt incluse în prețul plătit de client. Tu primești prețul tău, întreg.</p>
        </div>
      </div>
      <p class="mt-5 text-center font-display font-bold text-lg">La volum, diferența devine uriașă.</p>
    </div>
  </div>
</section>

<!-- MOBILE APP + LOCAL SALES -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Online și la fața locului</p><h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Aplicație mobilă + casă de marcat pentru vânzări locale.</h2></div>
  <div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-paper-2 border-2 border-ink rounded-2xl p-8 shadow-hard-sm">
      <div class="text-4xl mb-3">📱</div>
      <h3 class="font-display font-bold text-2xl">Aplicația mobilă — Android &amp; iOS</h3>
      <p class="mt-3 text-ink-soft leading-relaxed">Scanezi bilete rapid la intrare, <strong>inclusiv offline</strong> cu sincronizare ulterioară. În același timp vezi <strong>live vânzările și traficul</strong>, oriunde te-ai afla.</p>
      <ul class="mt-4 space-y-2 text-ink-soft">
        <li class="flex items-start gap-2"><span class="text-vermilion">›</span> Scanare QR cu validare anti-fraudă</li>
        <li class="flex items-start gap-2"><span class="text-vermilion">›</span> Funcționează fără internet stabil</li>
        <li class="flex items-start gap-2"><span class="text-vermilion">›</span> Vânzări și trafic în timp real</li>
      </ul>
    </div>
    <div class="bg-ink text-paper rounded-2xl p-8 shadow-hard-sm border-2 border-ink">
      <div class="text-4xl mb-3">🏪</div>
      <h3 class="font-display font-bold text-2xl">Panou de vânzări locale</h3>
      <p class="mt-3 text-paper/85 leading-relaxed">Pe lângă dashboard-ul de comenzi online, ai un <strong class="text-vermilion">panou de gestiune a vânzărilor la fața locului</strong>. Vinzi și emiți bilete direct la casă — acces, servicii suplimentare sau închirieri (rentals).</p>
      <ul class="mt-4 space-y-2 text-paper/85">
        <li class="flex items-start gap-2"><span class="text-vermilion">›</span> Vânzare bilete de acces la ghișeu</li>
        <li class="flex items-start gap-2"><span class="text-vermilion">›</span> Servicii suplimentare &amp; rentals</li>
        <li class="flex items-start gap-2"><span class="text-vermilion">›</span> Online + local, în același sistem</li>
      </ul>
    </div>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="local_sales" class="inline-flex items-center gap-2 bg-vermilion text-paper font-bold px-7 py-3.5 rounded-full shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau să vând online și local →</a></div>
</section>

<!-- FISCAL / ANAF -->
<section class="py-20 sm:py-24 bg-paper-2 border-y-2 border-ink reveal">
  <div class="max-w-7xl mx-auto px-5 sm:px-8">
    <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Fiscal, fără bătăi de cap</p><h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Contabilitatea și ANAF, rezolvate automat.</h2></div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php
        $fiscal = [
          ['📄','Documente ANAF','Generare automată a documentelor necesare pentru ANAF.'],
          ['🧾','Facturi fiscale','Emiți facturi fiscale către clienți direct din platformă.'],
          ['🔗','Contabilitate RO','Integrare cu sisteme de contabilitate din România.'],
          ['💼','Deconturi clare','Deconturi periodice sau la cerere, cu evidență transparentă.'],
        ];
        foreach ($fiscal as [$icon, $title, $body]):
      ?>
      <div class="partner-card bg-paper border-2 border-ink rounded-2xl p-6 shadow-hard-sm"><div class="text-3xl mb-2"><?= $icon ?></div><h3 class="font-display font-bold text-lg"><?= htmlspecialchars($title) ?></h3><p class="mt-2 text-ink-soft text-sm"><?= htmlspecialchars($body) ?></p></div>
      <?php endforeach; ?>
    </div>
    <div x-data="anafFlow()" x-intersect.once="start()" class="mt-8 bg-ink text-paper rounded-2xl border-2 border-ink shadow-hard p-6 sm:p-8 overflow-hidden">
      <p class="font-mono text-xs uppercase tracking-widest text-vermilion mb-6">O vânzare → documente generate automat, în secunde</p>
      <div class="grid lg:grid-cols-[auto_1fr] gap-8 items-center">
        <div class="relative">
          <div class="bg-paper/5 border border-paper/15 rounded-2xl p-5 w-full lg:w-64">
            <div class="flex items-center justify-between mb-3"><span class="font-mono text-[11px] uppercase tracking-widest text-paper/50">Comandă nouă</span><span class="w-2.5 h-2.5 rounded-full bg-vermilion animate-pulse"></span></div>
            <p class="font-display font-bold text-lg">Bilet acces + rental</p>
            <p class="font-mono text-sm text-paper/60 mt-1">#BO-19024 · 180 lei</p>
            <div class="mt-3 flex items-center gap-2 text-xs text-forest font-semibold"><span class="w-4 h-4 rounded-full bg-forest text-paper grid place-items-center text-[9px]">✓</span> Plată confirmată</div>
          </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
          <template x-for="(doc,i) in docs" :key="i">
            <div class="relative border-2 rounded-2xl p-4 transition-all duration-500" :class="done > i ? 'border-vermilion bg-paper/5 opacity-100 translate-y-0' : 'border-paper/15 opacity-30 translate-y-2'">
              <div class="flex items-center justify-between mb-2"><span class="text-2xl" x-text="doc.icon"></span><span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold transition-all" :class="done > i ? 'bg-forest text-paper' : 'bg-paper/10 text-paper/40'" x-text="done > i ? '✓' : (done===i ? '…' : '')"></span></div>
              <p class="font-display font-bold text-sm leading-tight" x-text="doc.name"></p>
              <p class="font-mono text-[10px] text-paper/50 mt-1" x-text="doc.meta"></p>
              <div class="mt-3 h-1 bg-paper/10 rounded-full overflow-hidden" x-show="done===i" x-transition><div class="h-full bg-vermilion rounded-full" style="width:60%;animation:bo-fill 1s ease-in-out infinite"></div></div>
            </div>
          </template>
        </div>
      </div>
      <div class="mt-6 pt-5 border-t border-paper/10 flex flex-wrap items-center justify-between gap-3">
        <p class="text-paper/70 text-sm">Zero introducere manuală. Documentele sunt gata în <strong class="text-vermilion" x-text="elapsed + 's'">0s</strong> de la fiecare vânzare.</p>
        <a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="anaf" class="inline-flex items-center gap-2 bg-vermilion text-paper font-bold px-6 py-3 rounded-full hover:translate-x-[3px] hover:translate-y-[3px] transition-all text-sm">Vreau fiscalitatea pe pilot automat →</a>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="cum" class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">De la cont la prima vânzare</p><h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Patru pași. Sub o zi. Zero costuri de pornire.</h2></div>
  <div class="grid md:grid-cols-4 gap-6">
    <?php
      $steps = [
        ['1','📝','Îți faci contul','Te înregistrezi în câteva minute. Fără taxe de pornire, fără abonament, fără card la înscriere.','≈ 5 minute'],
        ['2','➕','Adaugi activitățile','Oricâte, de orice tip. Setezi sloturi orare, zile, capacități, variante de preț și pachete de grup.','activități nelimitate'],
        ['3','🚀','Mergi live','Publici și ești în piață, cu pagini gata de partajat și tracking conectat. Intri direct în baza de 96.000+ clienți.','go-live în max 1 zi'],
        ['4','💰','Vinzi & încasezi','Online și local, în același sistem. bilete.online încasează de la client și îți face deconturi periodice sau la cerere.','prețul tău, întreg'],
      ];
      foreach ($steps as [$n, $icon, $title, $body, $tag]):
    ?>
    <div class="relative bg-paper-2 border-2 border-ink rounded-2xl p-6 shadow-hard-sm partner-card">
      <span class="absolute -top-4 -left-3 w-10 h-10 grid place-items-center bg-vermilion text-paper font-display font-bold rounded-full -rotate-[6deg] border-2 border-ink shadow-hard-sm"><?= $n ?></span>
      <div class="text-2xl mb-2 mt-2"><?= $icon ?></div>
      <h3 class="font-display font-bold text-xl"><?= htmlspecialchars($title) ?></h3>
      <p class="mt-2 text-ink-soft text-sm leading-relaxed"><?= htmlspecialchars($body) ?></p>
      <p class="mt-3 font-mono text-[11px] uppercase tracking-wide text-vermilion"><?= htmlspecialchars($tag) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="how_it_works" class="inline-flex items-center gap-2 bg-ink text-paper font-bold px-7 py-3.5 rounded-full shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Începe acum, gratuit →</a></div>
</section>

<!-- TIXELLO ENGINE -->
<section id="tehnologie" class="py-20 sm:py-24 bg-paper-2 border-y-2 border-ink reveal">
  <div class="max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <span class="inline-block font-mono text-xs uppercase tracking-widest bg-ink text-paper px-3 py-1.5 rounded-full mb-5">Powered by Tixello</span>
      <h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Infrastructură matură, testată la scară.</h2>
      <p class="mt-6 text-lg text-ink-soft leading-relaxed">bilete.online rulează pe Tixello — sistemul de ticketing care a procesat deja peste 4,4 milioane EUR în vânzări și peste 301.000 de bilete. Primești tehnologie de producție, fără s-o construiești sau s-o întreții.</p>
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="tixello" class="mt-7 inline-flex items-center gap-2 bg-vermilion text-paper font-bold px-7 py-3.5 rounded-full shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Devino partener →</a>
    </div>
    <div class="bg-ink text-paper rounded-2xl p-8 shadow-hard border-2 border-ink">
      <p class="font-mono text-xs uppercase tracking-widest text-vermilion mb-5">În cifre</p>
      <div class="space-y-4 font-mono text-sm">
        <div class="flex justify-between border-b border-paper/15 pb-3"><span class="text-paper/60">Evenimente &amp; activități</span><span class="font-bold">4.294</span></div>
        <div class="flex justify-between border-b border-paper/15 pb-3"><span class="text-paper/60">Clienți în bază</span><span class="font-bold">96.341</span></div>
        <div class="flex justify-between border-b border-paper/15 pb-3"><span class="text-paper/60">Bilete vândute</span><span class="font-bold">301.310</span></div>
        <div class="flex justify-between border-b border-paper/15 pb-3"><span class="text-paper/60">Vânzări generate</span><span class="font-bold">4.409.557 €</span></div>
        <div class="flex justify-between"><span class="text-paper/60">Scanare offline</span><span class="font-bold">Da, cu sync</span></div>
      </div>
    </div>
  </div>
</section>

<!-- CATEGORII (din DB) -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-2xl mx-auto text-center mb-12"><p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4">Pentru ce tip de activitate</p><h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight">Categoriile disponibile pe bilete.online</h2></div>
  <?php if (empty($parentCategories)): ?>
    <p class="text-center text-ink/60 italic">Categoriile se încarcă în curând. Reîncarcă pagina în câteva minute.</p>
  <?php else: ?>
  <div id="grid-categorii" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($parentCategories as $cat):
        $name = $peel($cat['name'] ?? '');
        $slug = $cat['slug'] ?? '';
        $desc = $peel($cat['description'] ?? '');
        $img  = $cat['image'] ?? null;
        if ($name === '') continue;
    ?>
    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="partner-card block bg-paper-2 border-2 border-ink rounded-2xl overflow-hidden shadow-hard-sm" data-cat-slug="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
      <?php if ($img): ?>
        <img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES) ?>" loading="lazy" class="w-full h-40 object-cover border-b-2 border-ink bg-paper">
      <?php else: ?>
        <div class="w-full h-40 bg-paper border-b-2 border-ink grid place-items-center text-5xl"><?= htmlspecialchars($cat['icon_emoji'] ?? '🎫', ENT_QUOTES) ?></div>
      <?php endif; ?>
      <div class="p-5">
        <h3 class="font-display font-bold text-lg"><?= htmlspecialchars($name, ENT_QUOTES) ?></h3>
        <?php if ($desc !== ''): ?>
          <p class="mt-1.5 text-sm text-ink-soft leading-relaxed"><?= htmlspecialchars($desc, ENT_QUOTES) ?></p>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- FAQ -->
<section class="py-20 sm:py-24 bg-paper-2 border-t-2 border-ink reveal">
  <div class="max-w-3xl mx-auto px-5 sm:px-8" x-data="{open:0}">
    <p class="font-mono text-sm uppercase tracking-widest text-vermilion mb-4 text-center">Întrebări frecvente</p>
    <h2 class="font-display font-bold text-4xl sm:text-5xl leading-tight text-center mb-12">Ce vrei să știi înainte să începi</h2>
    <template x-for="(f, i) in [
      {q:'Cât e comisionul și cine îl plătește?', a:'Comisionul este de 2%* și este adăugat în prețul final, plătit de cumpărător. Tu îți stabilești prețul și îl primești integral la decont. *Cei 2% se aplică pentru vânzarea exclusivă prin bilete.online.'},
      {q:'Cum și când primesc banii?', a:'bilete.online încasează plata de la client și îți face deconturi periodice — sau la cerere, ori de câte ori vrei să-ți fie decontați banii.'},
      {q:'Pot vinde activități cu sloturi și pe zile?', a:'Da. Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile. Tu controlezi capacitatea fiecărui slot și faci booking în detaliu.'},
      {q:'Ce metode de plată sunt acceptate?', a:'Stripe, Apple Pay, Google Pay, Revolut și RoPay — plus card bancar. Multiple metode, toate la îndemâna clientului.'},
      {q:'Cum îmi reduce costul reclamelor?', a:'Platforma se integrează cu toți pixelii de tracking și cu Facebook CAPI, trimițând 100% din evenimentele de conversie fără să fie blocate de ad blockere sau de iOS. Rezultatul: costul reclamelor pe Facebook, Instagram, TikTok și Google scade cu până la 60%.'},
      {q:'Pot vinde și la fața locului?', a:'Da. Pe lângă dashboard-ul online, ai un panou de vânzări locale — vinzi și emiți bilete la casă: acces, servicii suplimentare și închirieri.'},
      {q:'Mă ajută cu partea fiscală?', a:'Da. Generare automată de documente ANAF, emitere facturi fiscale către clienți și integrare cu sisteme de contabilitate din România.'},
      {q:'Cât durează să încep?', a:'Onboarding în aproximativ 5 minute, fără costuri de pornire. Mergi live în maxim o zi, în funcție de câte activități adaugi.'}
    ]" :key="i">
      <div class="border-b-2 border-ink/15">
        <button @click="open === i ? open = null : open = i" class="w-full flex items-center justify-between py-5 text-left gap-4">
          <span class="font-display font-bold text-xl" x-text="f.q"></span>
          <span class="shrink-0 w-8 h-8 grid place-items-center border-2 border-ink rounded-full transition-transform" :class="open===i && 'rotate-45 bg-ink text-paper'">+</span>
        </button>
        <div x-show="open===i" x-collapse><p class="pb-5 text-ink-soft leading-relaxed text-lg" x-text="f.a"></p></div>
      </div>
    </template>
  </div>
</section>

<!-- FINAL CTA -->
<section id="contact" class="py-24 sm:py-32 bg-vermilion text-paper relative overflow-hidden">
  <div class="absolute inset-0 opacity-10" style="background-image:repeating-linear-gradient(45deg,#F4EFE3 0 2px,transparent 2px 18px)"></div>
  <div class="relative max-w-3xl mx-auto px-5 sm:px-8 text-center reveal">
    <span class="inline-block font-mono text-xs uppercase tracking-widest bg-paper text-ink px-3 py-1.5 rounded-full mb-6 -rotate-2">Devino partener</span>
    <h2 class="font-display font-bold text-4xl sm:text-6xl leading-[0.95]">Pune-ți activitățile la vânzare<br>și păstrează prețul tău întreg.</h2>
    <p class="mt-6 text-lg text-paper/90 max-w-xl mx-auto leading-relaxed">Fără costuri de pornire. Activități nelimitate. Onboarding în 5 minute, go-live azi. Comision 2%* plătit de client.</p>
    <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" data-track-cta="final_primary" class="inline-flex items-center justify-center gap-2 bg-ink text-paper font-bold text-lg px-8 py-4 rounded-full shadow-hard hover:shadow-none hover:translate-x-[6px] hover:translate-y-[6px] transition-all">
        Vreau să-mi vând activitățile →
      </a>
      <a href="mailto:contact@bilete.online?subject=Întrebare%20parteneriat%20bilete.online" data-track-cta="email_contact" class="inline-flex items-center justify-center gap-2 border-2 border-paper font-bold text-lg px-8 py-4 rounded-full hover:bg-paper hover:text-ink transition">
        Trimite-ne un email
      </a>
    </div>
    <p class="mt-6 text-paper/80 text-sm">Fără cost de pornire · Activități nelimitate · Anulezi oricând</p>
  </div>
</section>

</main>

<!-- Page-specific JS: perso store, booking flow, ANAF flow, tracking ping, reveal observer -->
<script>
  const PERSO_PROFILES = {
    'escape': { label:'escape room', h1a:'Vinzi bilete', h1b:'la camerele tale de escape.', h1c:'Prețul tău rămâne al tău.', sub:'Sloturi de 60 de minute, capacitate pe cameră, rezervări în detaliu și o aplicație cu scanare offline. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de jucător — tu îți păstrezi prețul stabilit.' },
    'muzeu': { label:'muzeu', h1a:'Vinzi bilete', h1b:'la muzeul tău.', h1c:'Prețul tău rămâne al tău.', sub:'Bilete de acces pe zile și intervale orare, ghidaj și pachete pentru grupuri școlare, plus emitere automată de documente fiscale. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de vizitator — tu îți păstrezi prețul stabilit.' },
    'parc-distractii': { label:'parc de distracții', h1a:'Vinzi bilete', h1b:'la parcul tău.', h1c:'Prețul tău rămâne al tău.', sub:'Bilete de acces, abonamente, add-on-uri pentru atracții și rentals — toate într-un singur sistem, online și la fața locului. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de vizitator — tu îți păstrezi prețul stabilit.' },
    'parc-aventura': { label:'parc de aventură', h1a:'Vinzi bilete', h1b:'la traseele tale de aventură.', h1c:'Prețul tău rămâne al tău.', sub:'Trasee pe niveluri de dificultate, sloturi pe capacitate, închiriere de echipament (rental) și scanare offline pe teren. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de aventurier — tu îți păstrezi prețul stabilit.' },
    'natura': { label:'experiență în natură', h1a:'Vinzi bilete', h1b:'la experiențele tale în natură.', h1c:'Prețul tău rămâne al tău.', sub:'Tururi ghidate, trasee și activități outdoor cu sloturi pe zile și ore, plus scanare offline acolo unde nu prinde semnal. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de participant — tu îți păstrezi prețul stabilit.' },
    'acvarii-zoo': { label:'grădină zoologică / acvariu', h1a:'Vinzi bilete', h1b:'la grădina ta zoo / acvariu.', h1c:'Prețul tău rămâne al tău.', sub:'Bilete de acces pe zile și intervale, experiențe cu animale și pachete de familie, online și la casă. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de vizitator — tu îți păstrezi prețul stabilit.' },
    'ateliere': { label:'atelier creativ', h1a:'Vinzi locuri', h1b:'la atelierele tale creative.', h1c:'Prețul tău rămâne al tău.', sub:'Sesiuni cu locuri limitate, pachete de materiale și rezervare pe sloturi orare, fără supravânzare. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de participant — tu îți păstrezi prețul stabilit.' },
    'tururi': { label:'tur turistic', h1a:'Vinzi bilete', h1b:'la tururile tale ghidate.', h1c:'Prețul tău rămâne al tău.', sub:'City walks și tururi cu plecări pe ore, capacitate per plecare și bilete pe telefon. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de turist — tu îți păstrezi prețul stabilit.' },
    'educatie': { label:'program educațional', h1a:'Vinzi locuri', h1b:'la programele tale educaționale.', h1c:'Prețul tău rămâne al tău.', sub:'Activități STEM și lecții interactive cu rezervare pe clase și grupuri, plus documente fiscale generate automat. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de participant — tu îți păstrezi prețul stabilit.' },
    'familie': { label:'activitate pentru familie', h1a:'Vinzi bilete', h1b:'la activitățile tale pentru familii.', h1c:'Prețul tău rămâne al tău.', sub:'Pachete de familie, prețuri pe categorii de vârstă și rezervare pe sloturi, online și la fața locului. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de client — tu îți păstrezi prețul stabilit.' },
    'corporate': { label:'experiență corporate', h1a:'Vinzi locuri', h1b:'la experiențele tale pentru echipe.', h1c:'Prețul tău rămâne al tău.', sub:'Pachete de grup pentru team-building și evenimente private, cu facturare fiscală automată. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de client — tu îți păstrezi prețul stabilit.' },
    'cultura': { label:'instituție culturală', h1a:'Vinzi bilete', h1b:'la evenimentele tale culturale.', h1c:'Prețul tău rămâne al tău.', sub:'Bilete cu locuri sau acces general, pe date și intervale, cu emitere fiscală automată. Comisionul de <strong class="text-vermilion">doar 2%*</strong> e plătit de spectator — tu îți păstrezi prețul stabilit.' }
  };
  const PERSO_ALIASES = {
    'escape':'escape','escape-room':'escape','escape-rooms':'escape',
    'muzeu':'muzeu','muzee':'muzeu','muzee-expozitii':'muzeu','museum':'muzeu',
    'parc-distractii':'parc-distractii','parc-distracții':'parc-distractii','distractii':'parc-distractii','parcuri-de-distractii':'parc-distractii',
    'parc-aventura':'parc-aventura','aventura':'parc-aventura','parcuri-de-aventura':'parc-aventura',
    'natura':'natura','natura-outdoor':'natura','outdoor':'natura',
    'acvarii-zoo':'acvarii-zoo','zoo':'acvarii-zoo','acvariu':'acvarii-zoo',
    'ateliere':'ateliere','atelier':'ateliere','ateliere-experiente-creative':'ateliere',
    'tururi':'tururi','tur':'tururi','tururi-experiente-turistice':'tururi',
    'educatie':'educatie','educatie-invatare-experientiala':'educatie','stem':'educatie',
    'familie':'familie','familie-copii':'familie','copii':'familie',
    'corporate':'corporate','corporate-grupuri':'corporate',
    'cultura':'cultura','cultura-arta':'cultura','arta':'cultura'
  };
  document.addEventListener('alpine:init', () => {
    Alpine.store('perso', {
      loc:'', typeKey:'', profile:null,
      boot(){
        const p = new URLSearchParams(window.location.search);
        const rawLoc = (p.get('loc')||'').trim();
        const rawTip = (p.get('tip')||'').trim().toLowerCase();
        const key = PERSO_ALIASES[rawTip] || '';
        this.loc = rawLoc; this.typeKey = key;
        this.profile = key ? PERSO_PROFILES[key] : null;
      },
      get hasProfile(){ return !!this.profile; },
      p(field, fallback){ return (this.profile && this.profile[field]) ? this.profile[field] : fallback; },
      signupHref(base){
        const qs = new URLSearchParams();
        if (this.typeKey) qs.set('tip', this.typeKey);
        if (this.loc)     qs.set('loc', this.loc);
        const s = qs.toString();
        return s ? `${base}?${s}` : base;
      }
    });
    Alpine.store('perso').boot();
  });

  function anafFlow(){
    return {
      started:false, done:0, elapsed:0,
      docs:[{icon:'🧾',name:'Factură fiscală',meta:'serie BO · client'},{icon:'📄',name:'Document ANAF',meta:'raportare automată'},{icon:'🔗',name:'Înregistrare contabilă',meta:'sync contabilitate RO'}],
      wait(ms){ return new Promise(r=>setTimeout(r,ms)); },
      start(){ if(this.started) return; this.started=true; this.run(); },
      async run(){ while(true){ this.done=0; this.elapsed=0; await this.wait(900); for(let i=0;i<this.docs.length;i++){ this.done=i; await this.wait(1100); this.elapsed=Math.min(3,this.elapsed+1); this.done=i+1; await this.wait(450);} await this.wait(2600);} }
    }
  }
  function bookingFlow(){
    return {
      step:0, started:false,
      steps:[{title:'Alege ora'},{title:'Tipul de bilet'},{title:'Extra & rentals'},{title:'Personalizează'},{title:'Plată'},{title:'Gata!'}],
      slots:[{t:'10:00',gone:false},{t:'12:00',gone:true},{t:'14:00',gone:false},{t:'16:00',gone:false},{t:'18:00',gone:false},{t:'20:00',gone:false}],
      chosenSlot:null,
      tickets:[{name:'Acces standard',note:'1 persoană',price:'80 lei'},{name:'Acces + experiență',note:'1 persoană',price:'120 lei'},{name:'Pachet familie',note:'2 adulți + 2 copii',price:'260 lei'}],
      chosenTicket:null,
      extras:[{icon:'🥾',name:'Echipament (rental)',price:'35 lei'},{icon:'📸',name:'Ghid foto',price:'25 lei'},{icon:'🍫',name:'Pachet gustare',price:'18 lei'}],
      chosenExtras:[], fullName:'Andrei Popescu', typed:'', caret:true, giftMsg:'', payProgress:0,
      messages:[{icon:'📧',text:'Biletul a fost trimis pe email'},{icon:'💬',text:'Confirmare trimisă pe WhatsApp'},{icon:'🎟️',text:'Bilet QR valabil — îl scanezi la intrare'},{icon:'⭐',text:'Recomandare: „Tur foto la apus" pentru tine'}],
      msgShown:0, _caretTimer:null,
      start(){ if(this.started) return; this.started=true; this.caretBlink(); this.run(); },
      caretBlink(){ this._caretTimer=setInterval(()=>{ this.caret=!this.caret; },500); },
      wait(ms){ return new Promise(r=>setTimeout(r,ms)); },
      _num(p){ const m=String(p||'').replace(/\./g,'').match(/\d+/); return m?parseInt(m[0],10):0; },
      orderTotal(){ let t=this._num(this.tickets[1] && this.tickets[1].price); if(this.extras[0]) t+=this._num(this.extras[0].price); if(this.extras[1]) t+=this._num(this.extras[1].price); return t ? (t.toLocaleString('ro-RO') + ' lei') : '—'; },
      async run(){ while(true){ await this.cycle(); await this.wait(2600); this.reset(); await this.wait(600); } },
      reset(){ this.step=0; this.chosenSlot=null; this.chosenTicket=null; this.chosenExtras=[]; this.typed=''; this.giftMsg=''; this.payProgress=0; this.msgShown=0; },
      async cycle(){
        this.step=0; await this.wait(900); this.chosenSlot=2; await this.wait(1100);
        this.step=1; await this.wait(900); this.chosenTicket=1; await this.wait(1200);
        this.step=2; await this.wait(800); this.chosenExtras=[0]; await this.wait(750); this.chosenExtras=[0,1]; await this.wait(1100);
        this.step=3; this.typed=''; await this.wait(500);
        for(const ch of this.fullName){ this.typed+=ch; await this.wait(70); }
        await this.wait(400); this.giftMsg='La mulți ani! Distracție plăcută 🎉'; await this.wait(1300);
        this.step=4; this.payProgress=0; await this.wait(700);
        while(this.payProgress<100){ this.payProgress=Math.min(100,this.payProgress+8); await this.wait(90); }
        await this.wait(800);
        this.step=5; this.msgShown=0; await this.wait(600);
        for(let i=0;i<this.messages.length;i++){ this.msgShown=i+1; await this.wait(650); }
      }
    }
  }
  const io = new IntersectionObserver((entries)=>{ entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target);} }); }, {threshold:0.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  /* Lead funnel ping — log a "landing" view (anonymous) so we can see the
     funnel in admin. Cookie holds a session token shared across landing +
     onboarding; the form submit promotes it to a real lead.  */
  (function pingLanding(){
    try {
      let sid = (document.cookie.match(/(?:^|;\s*)bo_lead_sid=([^;]+)/)||[])[1];
      if (!sid) {
        sid = (crypto?.randomUUID?.() || (Date.now().toString(36) + Math.random().toString(36).slice(2)));
        const yr = new Date(); yr.setFullYear(yr.getFullYear()+1);
        document.cookie = `bo_lead_sid=${sid}; expires=${yr.toUTCString()}; path=/; SameSite=Lax`;
      }
      const p = new URLSearchParams(window.location.search);
      const utm = {};
      ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'].forEach(k => { if (p.get(k)) utm[k] = p.get(k); });
      fetch('/api/proxy.php?action=leads.track', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          session_token: sid,
          event_type: 'page_view_landing',
          page_url: window.location.pathname + window.location.search,
          referrer: document.referrer || null,
          prefill_tip: p.get('tip') || null,
          prefill_loc: p.get('loc') || null,
          utm,
        }),
        keepalive: true,
      }).catch(() => {});
    } catch (_) {}
  })();

  /* CTA click tracking — every element with [data-track-cta] reports a
     cta_click event tied to the same bo_lead_sid session. We use the
     readable button text as cta_label so the admin timeline shows
     "Click pe „Vreau booking pe sloturi"" instead of just an ID. */
  document.addEventListener('click', function(ev){
    const el = ev.target.closest('[data-track-cta]');
    if (!el) return;
    try {
      let sid = (document.cookie.match(/(?:^|;\s*)bo_lead_sid=([^;]+)/)||[])[1];
      if (!sid) {
        sid = (crypto?.randomUUID?.() || (Date.now().toString(36) + Math.random().toString(36).slice(2)));
        const yr = new Date(); yr.setFullYear(yr.getFullYear()+1);
        document.cookie = `bo_lead_sid=${sid}; expires=${yr.toUTCString()}; path=/; SameSite=Lax`;
      }
      const ctaId = el.getAttribute('data-track-cta');
      const label = (el.innerText || el.textContent || '').replace(/\s+/g,' ').trim().slice(0, 200);
      const p = new URLSearchParams(window.location.search);
      fetch('/api/proxy.php?action=leads.track', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          session_token: sid,
          event_type: 'cta_click',
          page_url: window.location.pathname + window.location.search,
          referrer: document.referrer || null,
          prefill_tip: p.get('tip') || null,
          prefill_loc: p.get('loc') || null,
          cta_id: ctaId,
          cta_label: label,
        }),
        keepalive: true,
      }).catch(() => {});
    } catch (_) {}
  }, true);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

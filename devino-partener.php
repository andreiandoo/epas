<?php
/**
 * bilete.online — /devino-partener
 *
 * Sales / partner-acquisition landing page sent to prospective venues
 * and activity organizers. Built from designs/ofertare.html with the
 * static "Categoriile disponibile pe bilete.online" grid replaced by
 * live data from the public categories API, so the page always reflects
 * what's actually live on the marketplace.
 *
 * Personalization via ?tip=<key>&loc=<name> in the URL (handled by the
 * design's Alpine `perso` store) — these query params also flow through
 * to the /inregistrare-locatie form linked from every CTA, so a lead that
 * lands from a personalized campaign URL also gets a pre-filled signup
 * form.
 *
 * Why custom <nav>/<footer> instead of the site's header.php/footer.php:
 * this is a focused conversion page targeting prospects (not buyers), so
 * the site's customer-facing chrome (cart, account, intent hubs) would
 * dilute it. head.php is still included for SEO meta, tracking, and the
 * cookie consent infrastructure.
 */

$pageCacheTTL = 900; // 15 min — sales content rarely changes
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// ─── Categories from the live marketplace API ──────────────────────
// Reuse the same cache key the /categorii page already uses so we don't
// double-fetch when both pages are warm. 15-min TTL matches.
$categoriesResp = api_cached('categories_full_tree', fn () => api_get('/event-categories'), 900);
$rawCategories  = $categoriesResp['data']['categories'] ?? [];
if (!is_array($rawCategories)) {
    $rawCategories = [];
}

// Top-level only — the design grid shows the headline 12, no children
// drilled in. Sort by the API's sort_order to keep visual stability.
$parentCategories = array_values(array_filter($rawCategories, fn ($c) => empty($c['parent_id'])));
usort($parentCategories, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

// Helper: peel a usable string out of a translatable name/description.
// The API returns these flat for the public locale, but be defensive in
// case a future change adds a JSON shape.
$peel = static function ($value): string {
    if (is_string($value)) return $value;
    if (is_array($value))  return (string) ($value['ro'] ?? $value['en'] ?? reset($value) ?? '');
    return '';
};

// ─── SEO ───────────────────────────────────────────────────────────
$pageTitleRaw    = 'Vinde bilete la activități pe ' . SITE_NAME . ' — comision 2%* plătit de client';
$pageDescription = 'Platforma de ticketing pentru activități: booking cu sloturi și calendar, analytics avansat, tracking 100% cu Facebook CAPI, deconturi periodice, app mobilă cu scanare offline. Comision 2%* plătit de cumpărător. Construit pe Tixello.';
$canonicalUrl    = SITE_URL . '/devino-partener';
$ogImage         = SITE_URL . '/assets/images/og-devino-partener.jpg';
$ogType          = 'website';

// Schema.org Service for the offering
$structuredData = [
    [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => 'bilete.online — Ticketing & booking pentru activități',
        'description' => 'Platformă de ticketing pentru locații și organizatori de activități. Comision 2% plătit de cumpărător. Booking cu sloturi orare, analytics avansat, scanare offline, deconturi periodice.',
        'provider'    => [
            '@type' => 'Organization',
            'name'  => SITE_NAME,
            'url'   => SITE_URL,
        ],
        'areaServed'  => ['@type' => 'Country', 'name' => 'Romania'],
        'offers'      => [
            '@type'         => 'Offer',
            'priceCurrency' => 'RON',
            'price'         => '0',
            'description'   => '0 lei cost de pornire. Comision 2%* plătit de cumpărător la fiecare bilet vândut.',
        ],
    ],
];

// Standalone page — no site shell needed. head.php closes </head> and
// opens <body>, then we write the rest including our own footer.
$extraHead = <<<HTML
<!-- Fonts for the partner page design (Fraunces / Archivo / JetBrains Mono) -->
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,400;1,9..144,600&family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

<!-- Tailwind CDN for the standalone design. The site's pre-built CSS
     uses a different theme; we don't want to mix them on this page. -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Alpine intersect plugin — site's head.php only ships collapse, the
     partner page needs `x-intersect.once` for the animated booking flow + ANAF demo. -->
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.x.x/dist/cdn.min.js"></script>

<script>
  tailwind.config = {
    theme: { extend: {
      fontFamily: { display:['Fraunces','serif'], sans:['Archivo','sans-serif'], mono:['JetBrains Mono','monospace'] },
      colors: { ink:'#1a1410', paper:'#f4ecdd', cream:'#fbf6ec', rust:'#c2410c', ember:'#e8590c', forest:'#1f3d2b', gold:'#b88a2e', ticket:'#d6cab0' },
      boxShadow: { 'hard':'6px 6px 0 0 #1a1410', 'hard-sm':'3px 3px 0 0 #1a1410' },
    }}
  }
</script>
<style>
  body { background-color:#f4ecdd; font-family:'Archivo',sans-serif; color:#1a1410; }
  .grain::before { content:""; position:fixed; inset:0; pointer-events:none; z-index:50; opacity:0.04;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }
  .ticket-notch { position:relative; }
  .ticket-notch::before, .ticket-notch::after { content:""; position:absolute; top:50%; width:22px; height:22px; border-radius:50%; background:#f4ecdd; transform:translateY(-50%); z-index:2; }
  .ticket-notch::before { left:-11px; } .ticket-notch::after { right:-11px; }
  .marquee { animation: scroll 32s linear infinite; }
  @keyframes scroll { from{transform:translateX(0)} to{transform:translateX(-50%)} }
  @keyframes fill { 0%{width:10%} 50%{width:90%} 100%{width:10%} }
  [x-cloak]{display:none!important;}
  .reveal{opacity:0; transform:translateY(24px); transition:opacity .7s cubic-bezier(.2,.7,.2,1), transform .7s cubic-bezier(.2,.7,.2,1);}
  .reveal.in{opacity:1; transform:none;}
  .underline-sketch{background-image:linear-gradient(transparent 60%, rgba(232,89,12,.35) 60%); background-repeat:no-repeat;}
  ::selection{background:#e8590c; color:#fbf6ec;}
</style>
HTML;

include __DIR__ . '/includes/head.php';
?>

<div class="grain font-sans text-ink antialiased overflow-x-hidden">

<!-- NAV -->
<header x-data="{ open:false, scrolled:false }" @scroll.window="scrolled = window.scrollY > 40"
        class="fixed top-0 inset-x-0 z-40 transition-all duration-300"
        :class="scrolled ? 'bg-cream/95 backdrop-blur border-b-2 border-ink shadow-sm' : 'bg-transparent'">
  <nav class="max-w-7xl mx-auto px-5 sm:px-8 flex items-center justify-between h-16">
    <a href="#top" class="flex items-center gap-2 font-display font-black text-xl tracking-tight">
      <span class="inline-grid place-items-center w-8 h-8 bg-ink text-cream rounded-sm font-mono text-sm rotate-[-6deg]">b.</span>
      bilete<span class="text-rust">.online</span>
    </a>
    <div class="hidden md:flex items-center gap-7 text-sm font-semibold">
      <a href="#cum" class="hover:text-rust transition">Cum funcționează</a>
      <a href="#booking" class="hover:text-rust transition">Booking</a>
      <a href="#analytics" class="hover:text-rust transition">Analytics</a>
      <a href="#bani" class="hover:text-rust transition">Comisionul</a>
      <a href="#tehnologie" class="hover:text-rust transition">Platforma</a>
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="bg-ink text-cream px-5 py-2.5 rounded-sm shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Devino partener</a>
    </div>
    <button @click="open=!open" class="md:hidden p-2" aria-label="Meniu">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path x-show="!open" d="M4 6h16M4 12h16M4 18h16"/><path x-cloak x-show="open" d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </nav>
  <div x-cloak x-show="open" x-transition class="md:hidden bg-cream border-b-2 border-ink px-5 py-4 space-y-3 font-semibold">
    <a @click="open=false" href="#cum" class="block">Cum funcționează</a>
    <a @click="open=false" href="#booking" class="block">Booking</a>
    <a @click="open=false" href="#analytics" class="block">Analytics</a>
    <a @click="open=false" href="#bani" class="block">Comisionul</a>
    <a @click="open=false" href="#tehnologie" class="block">Platforma</a>
    <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="block bg-ink text-cream text-center px-5 py-3 rounded-sm">Devino partener</a>
  </div>
</header>

<!-- HERO -->
<section id="top" x-data class="relative pt-32 pb-20 sm:pt-40 sm:pb-24 overflow-hidden">
  <div class="absolute top-24 -right-20 w-72 h-72 rounded-full bg-ember/10 blur-3xl"></div>
  <div class="absolute -left-24 bottom-0 w-80 h-80 rounded-full bg-forest/10 blur-3xl"></div>
  <div class="absolute inset-0 opacity-[0.04]" style="background-image:linear-gradient(#1a1410 1px,transparent 1px),linear-gradient(90deg,#1a1410 1px,transparent 1px);background-size:40px 40px;"></div>

  <div class="relative max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-12 gap-12 items-center">
    <div class="lg:col-span-7">
      <template x-if="$store.perso.loc">
        <div class="inline-flex items-center gap-2 bg-ink text-cream text-sm font-semibold px-4 py-2 rounded-sm mb-4 shadow-hard-sm">
          <span class="text-ember">👋</span>
          <span>Salut, <strong x-text="$store.perso.loc"></strong>! Iată ce putem face împreună.</span>
        </div>
      </template>

      <span class="inline-flex items-center gap-2 bg-forest text-cream text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-sm rotate-[-1.5deg] mb-6">
        <span class="w-2 h-2 rounded-full bg-ember animate-pulse"></span>
        <span x-text="$store.perso.hasProfile ? ('Ticketing &amp; booking pentru ' + $store.perso.profile.label) : 'Ticketing &amp; booking pentru activități'">Ticketing &amp; booking pentru activități</span>
      </span>

      <h1 class="font-display font-black leading-[0.92] tracking-tight text-5xl sm:text-6xl lg:text-7xl">
        <span x-text="$store.perso.p('h1a','Vinzi bilete')">Vinzi bilete</span><br>
        <span class="italic font-semibold" x-text="$store.perso.p('h1b','la activitățile tale.')">la activitățile tale.</span><br>
        <span class="underline-sketch" x-text="$store.perso.p('h1c','Prețul tău rămâne al tău.')">Prețul tău rămâne al tău.</span>
      </h1>
      <p class="mt-7 text-lg sm:text-xl max-w-xl text-ink/80 leading-relaxed" x-show="!$store.perso.hasProfile">
        Booking pe sloturi orare și calendar, analytics avansat, tracking 100% care îți reduce costul reclamelor, și o aplicație mobilă cu scanare offline. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de cumpărător — tu îți păstrezi prețul stabilit.
      </p>
      <template x-if="$store.perso.hasProfile">
        <p class="mt-7 text-lg sm:text-xl max-w-xl text-ink/80 leading-relaxed" x-html="$store.perso.profile.sub"></p>
      </template>
      <div class="mt-9 flex flex-col sm:flex-row gap-4">
        <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="group inline-flex items-center justify-center gap-2 bg-rust text-cream font-bold text-lg px-8 py-4 rounded-sm shadow-hard hover:shadow-none hover:translate-x-[6px] hover:translate-y-[6px] transition-all">
          <span x-text="$store.perso.hasProfile ? ('Pune ' + $store.perso.profile.label + ' online') : 'Pune-ți activitățile la vânzare'">Pune-ți activitățile la vânzare</span>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="group-hover:translate-x-1 transition"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a href="#cum" class="inline-flex items-center justify-center gap-2 border-2 border-ink font-bold text-lg px-8 py-4 rounded-sm hover:bg-ink hover:text-cream transition">Vezi cum funcționează</a>
      </div>
      <div class="mt-8 flex flex-wrap items-center gap-x-7 gap-y-3 text-sm font-semibold text-ink/70">
        <span class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1f3d2b" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> Fără costuri de pornire</span>
        <span class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1f3d2b" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> Activități nelimitate</span>
        <span class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1f3d2b" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> Onboarding în 5 minute</span>
      </div>
    </div>

    <!-- Ticket visual -->
    <div class="lg:col-span-5 relative">
      <div class="relative max-w-sm mx-auto rotate-[2deg] hover:rotate-0 transition-transform duration-500">
        <div class="bg-cream border-2 border-ink rounded-md shadow-hard overflow-hidden">
          <div class="bg-ink text-cream px-6 py-4 flex items-center justify-between">
            <span class="font-display font-black text-lg">BOOKING</span>
            <span class="font-mono text-xs tracking-widest">No. 00 962</span>
          </div>
          <div class="px-6 py-6 ticket-notch border-b-2 border-dashed border-ink/40">
            <p class="font-mono text-xs uppercase tracking-widest text-ink/50">Activitatea ta</p>
            <p class="font-display font-bold text-2xl mt-1 leading-tight">Slot orar.<br>Zi din calendar.</p>
            <div class="mt-5 grid grid-cols-3 gap-3 text-center">
              <div><p class="font-display font-black text-2xl text-rust">2%*</p><p class="text-[10px] uppercase tracking-wide text-ink/60 leading-tight mt-1">plătit de client</p></div>
              <div><p class="font-display font-black text-2xl text-forest">−60%</p><p class="text-[10px] uppercase tracking-wide text-ink/60 leading-tight mt-1">cost reclame</p></div>
              <div><p class="font-display font-black text-2xl text-gold">∞</p><p class="text-[10px] uppercase tracking-wide text-ink/60 leading-tight mt-1">activități</p></div>
            </div>
          </div>
          <div class="px-6 py-5 flex items-center justify-between">
            <div class="font-mono text-[10px] leading-tight text-ink/60"><p>POWERED BY TIXELLO</p><p>301.310 BILETE VÂNDUTE</p></div>
            <div class="flex items-end gap-[2px] h-9">
              <span class="w-[2px] h-full bg-ink"></span><span class="w-[3px] h-7 bg-ink"></span><span class="w-[1px] h-full bg-ink"></span><span class="w-[3px] h-8 bg-ink"></span><span class="w-[2px] h-6 bg-ink"></span><span class="w-[1px] h-full bg-ink"></span><span class="w-[3px] h-full bg-ink"></span><span class="w-[2px] h-7 bg-ink"></span><span class="w-[1px] h-8 bg-ink"></span><span class="w-[3px] h-full bg-ink"></span><span class="w-[2px] h-6 bg-ink"></span>
            </div>
          </div>
        </div>
        <span class="absolute -top-4 -left-4 bg-ember text-cream font-display font-black text-sm px-3 py-1.5 rounded-sm rotate-[-8deg] shadow-hard-sm border-2 border-ink">REZERVAT</span>
      </div>
    </div>
  </div>
</section>

<!-- MARQUEE: dynamic categories from API -->
<div class="bg-ink text-cream py-3 border-y-2 border-ink overflow-hidden">
  <div class="flex marquee whitespace-nowrap font-display font-bold text-lg">
    <?php
        $marqueeNames = array_values(array_filter(array_map(fn ($c) => $peel($c['name'] ?? ''), $parentCategories)));
        // If the API ever returns fewer than 6 categories, repeat them so the
        // marquee still feels alive. The visual aim is a non-empty band of
        // moving labels — empty is worse than slightly repetitive.
        if (count($marqueeNames) < 6 && count($marqueeNames) > 0) {
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
<section class="py-14 bg-cream border-b-2 border-ink reveal">
  <div class="max-w-7xl mx-auto px-5 sm:px-8">
    <p class="text-center font-mono text-sm uppercase tracking-widest text-ink/60 mb-8">Rezultate reale în ecosistemul Tixello — pe care e construit bilete.online</p>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
      <div><p class="font-display font-black text-4xl sm:text-5xl text-rust" x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=110;if(c>=4294){c=4294;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</p><p class="mt-2 font-semibold text-ink/70">Evenimente &amp; activități</p></div>
      <div><p class="font-display font-black text-4xl sm:text-5xl text-forest" x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=2500;if(c>=96341){c=96341;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</p><p class="mt-2 font-semibold text-ink/70">Clienți în bază</p></div>
      <div><p class="font-display font-black text-4xl sm:text-5xl text-gold" x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=7800;if(c>=301310){c=301310;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</p><p class="mt-2 font-semibold text-ink/70">Bilete vândute</p></div>
      <div><p class="font-display font-black text-4xl sm:text-5xl text-ember"><span x-data="{c:0}" x-intersect.once="(()=>{let t=setInterval(()=>{c+=115000;if(c>=4409557){c=4409557;clearInterval(t)}$el.textContent=c.toLocaleString('ro-RO')},18)})()">0</span> €</p><p class="mt-2 font-semibold text-ink/70">Vânzări generate</p></div>
    </div>
  </div>
</section>

<!-- PROBLEM -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl">
    <p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Realitatea de azi</p>
    <h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Vinzi activități, dar instrumentele te trag înapoi.</h2>
    <p class="mt-6 text-lg text-ink/75 leading-relaxed">Comisioane mari scăzute din marja ta. Booking rigid care nu suportă sloturi sau zile. Tracking ciuntit de ad blockere și iOS, care îți umflă costul reclamelor. Și zero ajutor real ca să găsești clienți noi.</p>
  </div>
  <div class="mt-12 grid sm:grid-cols-3 gap-6">
    <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-4xl mb-3">💸</div><h3 class="font-display font-bold text-xl">Comisioane din marja ta</h3><p class="mt-2 text-ink/70">Plătești tu, la fiecare bilet. La volum, e o gaură reală în buget.</p></div>
    <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-4xl mb-3">📅</div><h3 class="font-display font-bold text-xl">Booking inflexibil</h3><p class="mt-2 text-ink/70">Activitățile au sloturi, zile, capacități. Majoritatea platformelor nu le suportă.</p></div>
    <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-4xl mb-3">📉</div><h3 class="font-display font-bold text-xl">Tracking pierdut</h3><p class="mt-2 text-ink/70">Ad blockerele și iOS blochează datele de conversie — plătești mai mult pe reclame.</p></div>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center gap-2 bg-ink text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Rezolvă-le pe toate cu bilete.online →</a></div>
</section>

<!-- PE HÂRTIE → DIGITAL -->
<section class="py-20 sm:py-24 bg-ink text-cream relative overflow-hidden reveal">
  <div class="absolute inset-0 opacity-[0.05]" style="background-image:repeating-linear-gradient(0deg,#fbf6ec 0 1px,transparent 1px 28px),repeating-linear-gradient(90deg,#fbf6ec 0 1px,transparent 1px 28px)"></div>
  <div class="absolute -left-24 top-10 w-80 h-80 rounded-full bg-rust/15 blur-3xl"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
    <div class="max-w-3xl">
      <p class="font-mono text-sm uppercase tracking-widest text-ember mb-4">Încă vinzi „pe hârtie"?</p>
      <h2 class="font-display font-black text-4xl sm:text-6xl leading-[0.95]">Fără sistem digital, totul e <span class="italic text-ember">o nebuloasă</span>.</h2>
      <p class="mt-6 text-lg text-cream/85 leading-relaxed">Bilete scrise de mână, bani strânși într-un sertar, un caiet cu rezervări și multă „încredere". Nu știi câți oameni au intrat cu adevărat, câți bani ar fi trebuit să fie în casă, ce zile merg și ce zile pierd bani. Speri că totul e corect — dar nu ai cum să verifici.</p>
    </div>
    <div class="mt-12 grid lg:grid-cols-2 gap-6 items-stretch">
      <div class="bg-cream/[0.04] border border-cream/15 rounded-md p-7">
        <div class="flex items-center gap-3 mb-5"><span class="text-2xl grayscale">📒</span><h3 class="font-display font-bold text-2xl text-cream/90">Acum, fără sistem</h3></div>
        <ul class="space-y-4">
          <li class="flex items-start gap-3"><span class="mt-0.5 text-rust font-bold text-lg leading-none">✕</span><span><strong>Zero control:</strong> nu știi în timp real câte bilete s-au vândut sau câți oameni urmează să vină.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-rust font-bold text-lg leading-none">✕</span><span><strong>Încasări neverificabile:</strong> banii din sertar nu spun nimic despre câți ar fi trebuit să fie acolo.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-rust font-bold text-lg leading-none">✕</span><span><strong>Gestiune pe memorie:</strong> capacitatea, sloturile și rezervările trăiesc într-un caiet sau în capul tău.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-rust font-bold text-lg leading-none">✕</span><span><strong>Fraudă și scurgeri:</strong> bilete duplicate, intrări nenumărate, bani care „se pierd" pe drum.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-rust font-bold text-lg leading-none">✕</span><span><strong>Zero predictibilitate:</strong> nu poți planifica nimic — nu ai date despre trafic, sezon sau cele mai bune ore.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-rust font-bold text-lg leading-none">✕</span><span><strong>Doar speranță:</strong> la final de zi, doar speri că vânzările, traficul și încasările au fost reale.</span></li>
        </ul>
      </div>
      <div class="bg-cream text-ink rounded-md p-7 shadow-hard border-2 border-cream">
        <div class="flex items-center gap-3 mb-5"><span class="text-2xl">📲</span><h3 class="font-display font-bold text-2xl">Cu bilete.online</h3></div>
        <ul class="space-y-4">
          <li class="flex items-start gap-3"><span class="mt-0.5 text-forest font-bold text-lg leading-none">✓</span><span><strong>Control total, live:</strong> vezi în orice moment câte bilete s-au vândut și cine urmează să vină.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-forest font-bold text-lg leading-none">✓</span><span><strong>Încasări exacte:</strong> fiecare leu e înregistrat — online și la fața locului, în același sistem.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-forest font-bold text-lg leading-none">✓</span><span><strong>Gestiune automată:</strong> capacități, sloturi și disponibilitate sincronizate, fără supravânzare.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-forest font-bold text-lg leading-none">✓</span><span><strong>Bilete sigure:</strong> validare QR cu prevenirea dublei intrări, inclusiv offline.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-forest font-bold text-lg leading-none">✓</span><span><strong>Predictibilitate reală:</strong> analytics care îți arată trafic, sezon și cele mai bune ore.</span></li>
          <li class="flex items-start gap-3"><span class="mt-0.5 text-forest font-bold text-lg leading-none">✓</span><span><strong>Certitudine, nu speranță:</strong> deciziile tale se bazează pe date, nu pe presupuneri.</span></li>
        </ul>
      </div>
    </div>
    <div class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4 bg-cream/[0.04] border border-cream/15 rounded-md p-6">
      <p class="font-display font-bold text-xl sm:text-2xl text-center sm:text-left">Treci de la „sper că a fost bine" la „știu exact cum a fost".</p>
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="shrink-0 inline-flex items-center gap-2 bg-ember text-cream font-bold px-7 py-3.5 rounded-sm hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Digitalizează-ți vânzările →</a>
    </div>
  </div>
</section>

<!-- BOOKING (animated demo) -->
<section id="booking" class="py-20 sm:py-24 bg-forest text-cream relative overflow-hidden reveal">
  <div class="absolute inset-0 opacity-[0.06]" style="background-image:radial-gradient(#fbf6ec 1.5px,transparent 1.5px);background-size:22px 22px;"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <p class="font-mono text-sm uppercase tracking-widest text-ember mb-4">Booking gândit pentru activități</p>
      <h2 class="font-display font-black text-4xl sm:text-5xl leading-[0.97]">Sloturi orare. Zile pe calendar. Rezervare în detaliu.</h2>
      <p class="mt-6 text-lg text-cream/85 leading-relaxed max-w-lg">bilete.online nu vinde doar „un bilet". Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile — exact cum funcționează un escape room, un tur ghidat sau un atelier. Tu controlezi capacitatea fiecărui slot.</p>
      <ul class="mt-7 space-y-3">
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Selecție de zile disponibile pe calendar</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Sloturi orare cu capacitate configurabilă</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Booking detaliat: participanți, opțiuni, add-on-uri</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Pachete de grup și prețuri pe categorie de vârstă</li>
      </ul>
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="mt-8 inline-flex items-center gap-2 bg-ember text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm border-2 border-cream/0 hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau booking pe sloturi →</a>
    </div>
    <div x-data="bookingFlow()" x-intersect.once="start()" class="bg-cream text-ink rounded-md border-2 border-cream shadow-hard p-6 rotate-[-1.5deg] min-h-[420px] flex flex-col">
      <div class="flex items-center justify-between mb-1">
        <p class="font-display font-bold text-lg" x-text="steps[step].title">Alege ora</p>
        <span class="font-mono text-[10px] uppercase tracking-widest text-ink/40" x-text="(step+1)+'/'+steps.length"></span>
      </div>
      <div class="h-1.5 bg-ticket rounded-full overflow-hidden mb-5">
        <div class="h-full bg-ember rounded-full transition-all duration-500" :style="`width:${((step+1)/steps.length)*100}%`"></div>
      </div>
      <div class="relative flex-1">
        <div x-show="step===0" x-transition.opacity.duration.400ms class="absolute inset-0">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40 mb-2">Sloturi — 19 oct</p>
          <div class="grid grid-cols-3 gap-2 font-mono text-xs">
            <template x-for="(s,i) in slots" :key="i">
              <span class="border-2 rounded-sm py-3 text-center transition-all duration-300" :class="s.gone ? 'border-ink/20 text-ink/30 line-through' : (chosenSlot===i ? 'border-ember bg-ember text-cream font-bold scale-105 shadow-hard-sm' : 'border-ink')" x-text="s.t"></span>
            </template>
          </div>
        </div>
        <div x-show="step===1" x-transition.opacity.duration.400ms class="absolute inset-0 space-y-2.5">
          <template x-for="(t,i) in tickets" :key="i">
            <div class="flex items-center justify-between border-2 rounded-sm px-4 py-3 transition-all duration-300" :class="chosenTicket===i ? 'border-forest bg-forest/5 scale-[1.02]' : 'border-ink/15'">
              <div><p class="font-display font-bold text-sm" x-text="t.name"></p><p class="text-[11px] text-ink/50" x-text="t.note"></p></div>
              <div class="flex items-center gap-3"><span class="font-mono text-sm font-bold" x-text="t.price"></span><span class="w-5 h-5 rounded-full border-2 grid place-items-center transition-all" :class="chosenTicket===i ? 'border-forest bg-forest text-cream' : 'border-ink/30'"><span x-show="chosenTicket===i" class="text-[10px]">✓</span></span></div>
            </div>
          </template>
        </div>
        <div x-show="step===2" x-transition.opacity.duration.400ms class="absolute inset-0 space-y-2.5">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40">Adaugă extra &amp; rentals</p>
          <template x-for="(e,i) in extras" :key="i">
            <div class="flex items-center justify-between border-2 rounded-sm px-4 py-3 transition-all duration-300" :class="chosenExtras.includes(i) ? 'border-rust bg-rust/5 scale-[1.02]' : 'border-ink/15'">
              <div class="flex items-center gap-2.5"><span class="text-lg" x-text="e.icon"></span><p class="font-display font-bold text-sm" x-text="e.name"></p></div>
              <div class="flex items-center gap-3"><span class="font-mono text-sm font-bold" x-text="e.price"></span><span class="w-6 h-6 rounded-sm border-2 grid place-items-center font-bold transition-all" :class="chosenExtras.includes(i) ? 'border-rust bg-rust text-cream' : 'border-ink/30 text-ink/30'" x-text="chosenExtras.includes(i) ? '✓' : '+'"></span></div>
            </div>
          </template>
        </div>
        <div x-show="step===3" x-transition.opacity.duration.400ms class="absolute inset-0 space-y-3">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40">Personalizează biletul</p>
          <div>
            <label class="text-[11px] font-semibold text-ink/60">Nume pe bilet</label>
            <div class="mt-1 border-2 border-ink/20 rounded-sm px-3 py-2.5 font-mono text-sm bg-paper"><span x-text="typed"></span><span class="inline-block w-[2px] h-4 bg-ink align-middle ml-[1px]" :class="caret ? 'opacity-100' : 'opacity-0'"></span></div>
          </div>
          <div>
            <label class="text-[11px] font-semibold text-ink/60">Mesaj cadou (opțional)</label>
            <div class="mt-1 border-2 border-ink/20 rounded-sm px-3 py-2.5 text-xs bg-paper text-ink/70 italic" x-text="giftMsg"></div>
          </div>
          <div class="flex items-center gap-2 text-xs text-forest font-semibold"><span class="w-4 h-4 rounded-sm bg-forest text-cream grid place-items-center text-[9px]">✓</span> Trimite biletul pe email &amp; WhatsApp</div>
        </div>
        <div x-show="step===4" x-transition.opacity.duration.400ms class="absolute inset-0">
          <p class="font-mono text-[11px] uppercase tracking-widest text-ink/40 mb-2">Sumar comandă</p>
          <div class="border-2 border-ink/15 rounded-sm divide-y divide-ink/10 font-mono text-xs">
            <div class="flex justify-between px-3 py-2"><span class="text-ink/60" x-text="tickets[1] ? tickets[1].name : 'Bilet'">Bilet</span><span x-text="tickets[1] ? tickets[1].price : ''"></span></div>
            <template x-for="(e,i) in [extras[0], extras[1]]" :key="i">
              <div class="flex justify-between px-3 py-2" x-show="e"><span class="text-ink/60" x-text="e ? e.name : ''"></span><span x-text="e ? e.price : ''"></span></div>
            </template>
            <div class="flex justify-between px-3 py-2 font-bold text-sm"><span>Total estimat</span><span x-text="orderTotal()">—</span></div>
          </div>
          <div class="mt-3 grid grid-cols-5 gap-1.5 text-[9px] font-bold text-center">
            <span class="border-2 border-ink rounded-sm py-1.5 bg-ink text-cream">Stripe</span>
            <span class="border border-ink/20 rounded-sm py-1.5">Apple</span>
            <span class="border border-ink/20 rounded-sm py-1.5">G Pay</span>
            <span class="border border-ink/20 rounded-sm py-1.5">Revolut</span>
            <span class="border border-ink/20 rounded-sm py-1.5">RoPay</span>
          </div>
          <div class="mt-4">
            <div class="h-2 bg-ticket rounded-full overflow-hidden"><div class="h-full bg-forest rounded-full transition-all duration-200" :style="`width:${payProgress}%`"></div></div>
            <p class="mt-2 text-center text-xs font-semibold text-ink/60" x-text="payProgress<100 ? 'Se procesează plata…' : 'Plată confirmată'"></p>
          </div>
        </div>
        <div x-show="step===5" x-transition.opacity.duration.400ms class="absolute inset-0 flex flex-col items-center justify-center text-center">
          <div class="w-14 h-14 rounded-full bg-forest text-cream grid place-items-center text-2xl mb-3" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-50" x-transition:enter-end="opacity-100 scale-100">✓</div>
          <p class="font-display font-black text-xl">Comandă confirmată!</p>
          <p class="text-sm text-ink/60 mt-1">Bilet #BO-19024 · 14:00</p>
          <div class="mt-4 w-full space-y-2">
            <template x-for="(m,i) in messages" :key="i">
              <div x-show="msgShown>i" x-transition:enter="transition ease-out duration-400" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="flex items-center gap-2.5 bg-paper border border-ink/10 rounded-sm px-3 py-2 text-left text-xs"><span class="text-base" x-text="m.icon"></span><span x-text="m.text"></span></div>
            </template>
          </div>
        </div>
      </div>
      <p class="mt-4 pt-3 border-t border-ink/10 text-center font-mono text-[10px] uppercase tracking-widest text-ink/40">Demo booking bilete.online</p>
    </div>
  </div>
</section>

<!-- CORE BENEFITS GRID -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Tot ce-ți trebuie</p><h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">O platformă completă pentru vânzarea de activități.</h2></div>
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
    <div class="group bg-ink text-cream rounded-md p-7 shadow-hard hover:-translate-y-1 transition-all border-2 border-ink">
        <div class="text-3xl mb-3"><?= $icon ?></div>
        <h3 class="font-display font-bold text-2xl"><?= htmlspecialchars($title, ENT_QUOTES) ?></h3>
        <p class="mt-3 text-cream/80 leading-relaxed"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
    <?php
      $highlighted = [
        ['🔄','Disponibilitate în timp real','Stocul e sincronizat instant între vânzările online și cele de la fața locului. Nu supravinzi niciodată un slot, indiferent de unde vine comanda.'],
        ['🔔','Notificări automate','Confirmări și remindere trimise automat pe email, WhatsApp și SMS. Mai puține no-show-uri la activitățile cu sloturi orare.'],
        ['🎁','Bilete & vouchere cadou','Vinzi vouchere valorice și experiențe cadou, răscumpărabile online sau la fața locului. O sursă nouă de venit, mai ales în sezon.'],
      ];
      foreach ($highlighted as [$icon, $title, $body]):
    ?>
    <div class="group bg-ink text-cream rounded-md p-7 shadow-hard hover:-translate-y-1 transition-all border-2 border-ink">
        <div class="text-3xl mb-3"><?= $icon ?></div>
        <h3 class="font-display font-bold text-2xl"><?= htmlspecialchars($title, ENT_QUOTES) ?></h3>
        <p class="mt-3 text-cream/80 leading-relaxed"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
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
    <div class="bg-cream border-2 border-ink rounded-md p-5 shadow-hard-sm hover:-translate-y-0.5 transition-all">
        <div class="text-2xl mb-2"><?= $icon ?></div>
        <h4 class="font-display font-bold text-base leading-tight"><?= htmlspecialchars($title, ENT_QUOTES) ?></h4>
        <p class="mt-1.5 text-xs text-ink/65 leading-relaxed"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ANALYTICS + TRACKING -->
<section id="analytics" class="py-20 sm:py-24 bg-ink text-cream relative overflow-hidden reveal">
  <div class="absolute -right-32 top-10 w-96 h-96 rounded-full bg-ember/10 blur-3xl"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
    <div class="max-w-3xl"><p class="font-mono text-sm uppercase tracking-widest text-ember mb-4">Date care îți cresc vânzările</p><h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Analytics avansat + tracking 100%. <span class="text-ember">Reclame până la 60% mai ieftine.</span></h2></div>
    <div class="mt-12 grid lg:grid-cols-2 gap-10 items-start">
      <div>
        <h3 class="font-display font-bold text-2xl mb-4">De ce contează analytics-ul</h3>
        <ul class="space-y-3 text-cream/85">
          <li class="flex items-start gap-3"><span class="text-ember mt-1">→</span> Vezi exact ce activitate, slot și zi se vând cel mai bine</li>
          <li class="flex items-start gap-3"><span class="text-ember mt-1">→</span> Înțelegi de unde vin cumpărătorii și ce canal aduce profit</li>
          <li class="flex items-start gap-3"><span class="text-ember mt-1">→</span> Optimizezi prețurile și capacitatea pe baza cererii reale</li>
          <li class="flex items-start gap-3"><span class="text-ember mt-1">→</span> Urmărești conversia din vizită în vânzare, în timp real</li>
          <li class="flex items-start gap-3"><span class="text-ember mt-1">→</span> Identifici sloturile goale și le umpli cu promoții țintite</li>
          <li class="flex items-start gap-3"><span class="text-ember mt-1">→</span> Iei decizii pe date, nu pe presupuneri</li>
        </ul>
      </div>
      <div class="bg-cream/5 border border-cream/15 rounded-md p-7 backdrop-blur">
        <h3 class="font-display font-bold text-2xl mb-4">Tracking complet, fără pierderi</h3>
        <p class="text-cream/85 leading-relaxed">bilete.online se integrează cu <strong class="text-cream">toți pixelii de tracking</strong> și cu <strong class="text-cream">Facebook CAPI</strong>. Trimite <strong class="text-ember">100% din evenimentele de conversie</strong> server-side — deci nu te mai blochează ad blockerele și nici update-ul iOS care taie majoritatea trackingului.</p>
        <div class="mt-6 grid grid-cols-2 gap-3 text-center font-display font-bold">
          <div class="bg-ink/40 border border-cream/10 rounded-sm py-4"><p class="text-3xl text-ember">100%</p><p class="text-xs text-cream/70 mt-1 font-sans font-semibold uppercase tracking-wide">evenimente urmărite</p></div>
          <div class="bg-ink/40 border border-cream/10 rounded-sm py-4"><p class="text-3xl text-ember">−60%</p><p class="text-xs text-cream/70 mt-1 font-sans font-semibold uppercase tracking-wide">cost reclame</p></div>
        </div>
        <p class="mt-5 text-sm text-cream/70">Funcționează cu reclame pe <strong class="text-cream">Facebook, Instagram, TikTok și Google</strong>. Date corecte = algoritmi mai eficienți = cost pe vânzare mai mic.</p>
      </div>
    </div>
    <div class="mt-10"><a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center gap-2 bg-ember text-cream font-bold px-7 py-3.5 rounded-sm hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau reclame mai ieftine →</a></div>
  </div>
</section>

<!-- PAYMENTS -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Plăți pentru orice client</p>
      <h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Toate metodele de plată, la îndemâna cumpărătorului.</h2>
      <p class="mt-6 text-lg text-ink/75 leading-relaxed">Cu cât plata e mai simplă, cu atât vinzi mai mult. bilete.online acceptă cele mai folosite metode — clientul plătește în două atingeri, fără fricțiune.</p>
      <p class="mt-6 text-ink/70">bilete.online încasează plata de la client și îți face <strong>deconturi periodice</strong> — sau la cerere, ori de câte ori vrei să-ți fie decontați banii.</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
      <?php foreach (['Stripe','Apple Pay','Google Pay','Revolut','RoPay','Netopia','Carduri culturale','Card bancar'] as $pm): ?>
      <div class="bg-cream border-2 border-ink rounded-md p-5 shadow-hard-sm grid place-items-center font-display font-bold text-lg h-24 text-center leading-tight"><?= htmlspecialchars($pm) ?></div>
      <?php endforeach; ?>
      <div class="bg-ink text-cream rounded-md p-5 shadow-hard-sm grid place-items-center font-display font-bold text-center text-sm h-24 leading-tight">și altele<br>în curând</div>
    </div>
  </div>
</section>

<!-- THE 2% -->
<section id="bani" class="py-20 sm:py-24 bg-forest text-cream relative overflow-hidden reveal">
  <div class="absolute inset-0 opacity-[0.06]" style="background-image:radial-gradient(#fbf6ec 1.5px,transparent 1.5px);background-size:22px 22px;"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <p class="font-mono text-sm uppercase tracking-widest text-ember mb-4">Diferența care schimbă tot</p>
      <h2 class="font-display font-black text-4xl sm:text-6xl leading-[0.95]">Comision <span class="text-ember">2%*</span>.<br>Plătit de cumpărător.</h2>
      <p class="mt-7 text-lg text-cream/85 leading-relaxed max-w-lg">Comisionul de 2%* este adăugat transparent în prețul final și achitat de client. Tu îți stabilești prețul și îl primești <strong class="text-ember">integral</strong> la decont — fără să scazi nimic din marja ta.</p>
      <ul class="mt-7 space-y-3">
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Tu setezi prețul — tu primești prețul stabilit</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Clientul vede clar cei 2%* — onest, fără surprize</li>
        <li class="flex items-start gap-3"><span class="mt-1 text-ember">✓</span> Zero costuri lunare, zero taxe de pornire</li>
      </ul>
      <p class="mt-6 text-sm text-cream/70 border-l-2 border-ember pl-4"><strong>* </strong>Comisionul de 2% se aplică pentru vânzarea exclusivă prin bilete.online. bilete.online încasează plata de la client și îți face deconturi periodice sau la cerere.</p>
    </div>
    <div class="bg-cream text-ink rounded-md border-2 border-cream shadow-hard p-7 rotate-[-1.5deg]">
      <p class="font-display font-black text-xl mb-5 text-center">Bilet de 100 lei — ce primești?</p>
      <div class="space-y-4 font-mono text-sm">
        <div class="border-2 border-dashed border-ink/30 rounded p-4">
          <div class="flex justify-between font-bold uppercase text-xs tracking-wider mb-1"><span>Platformă clasică</span><span class="text-rust">−9,50 lei</span></div>
          <div class="text-[11px] text-ink/55 mb-2 leading-snug">comision 8% + cost tranzacționare card 1–2%, ambele scăzute din banii tăi</div>
          <div class="h-3 bg-ticket rounded-full overflow-hidden"><div class="h-full bg-rust" style="width:90.5%"></div></div>
          <p class="mt-2 text-right">Primești: <strong class="text-rust">~90,50 lei</strong></p>
        </div>
        <div class="border-2 border-ink rounded p-4 bg-forest/5">
          <div class="flex justify-between font-bold uppercase text-xs tracking-wider mb-2"><span>bilete.online (2%* pe client)</span><span class="text-forest">100%</span></div>
          <div class="h-3 bg-ticket rounded-full overflow-hidden"><div class="h-full bg-forest" style="width:100%"></div></div>
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
  <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Online și la fața locului</p><h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Aplicație mobilă + casă de marcat pentru vânzări locale.</h2></div>
  <div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-cream border-2 border-ink rounded-md p-8 shadow-hard-sm">
      <div class="text-4xl mb-3">📱</div>
      <h3 class="font-display font-bold text-2xl">Aplicația mobilă — Android &amp; iOS</h3>
      <p class="mt-3 text-ink/75 leading-relaxed">Scanezi bilete rapid la intrare, <strong>inclusiv offline</strong> cu sincronizare ulterioară. În același timp vezi <strong>live vânzările și traficul</strong>, oriunde te-ai afla.</p>
      <ul class="mt-4 space-y-2 text-ink/75">
        <li class="flex items-start gap-2"><span class="text-rust">›</span> Scanare QR cu validare anti-fraudă</li>
        <li class="flex items-start gap-2"><span class="text-rust">›</span> Funcționează fără internet stabil</li>
        <li class="flex items-start gap-2"><span class="text-rust">›</span> Vânzări și trafic în timp real</li>
      </ul>
    </div>
    <div class="bg-ink text-cream rounded-md p-8 shadow-hard-sm border-2 border-ink">
      <div class="text-4xl mb-3">🏪</div>
      <h3 class="font-display font-bold text-2xl">Panou de vânzări locale</h3>
      <p class="mt-3 text-cream/85 leading-relaxed">Pe lângă dashboard-ul de comenzi online, ai un <strong class="text-ember">panou de gestiune a vânzărilor la fața locului</strong>. Vinzi și emiți bilete direct la casă — acces, servicii suplimentare sau închirieri (rentals).</p>
      <ul class="mt-4 space-y-2 text-cream/85">
        <li class="flex items-start gap-2"><span class="text-ember">›</span> Vânzare bilete de acces la ghișeu</li>
        <li class="flex items-start gap-2"><span class="text-ember">›</span> Servicii suplimentare &amp; rentals</li>
        <li class="flex items-start gap-2"><span class="text-ember">›</span> Online + local, în același sistem</li>
      </ul>
    </div>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center gap-2 bg-rust text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau să vând online și local →</a></div>
</section>

<!-- FISCAL / ANAF -->
<section class="py-20 sm:py-24 bg-paper border-y-2 border-ink reveal">
  <div class="max-w-7xl mx-auto px-5 sm:px-8">
    <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Fiscal, fără bătăi de cap</p><h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Contabilitatea și ANAF, rezolvate automat.</h2></div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-3xl mb-2">📄</div><h3 class="font-display font-bold text-lg">Documente ANAF</h3><p class="mt-2 text-ink/70 text-sm">Generare automată a documentelor necesare pentru ANAF.</p></div>
      <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-3xl mb-2">🧾</div><h3 class="font-display font-bold text-lg">Facturi fiscale</h3><p class="mt-2 text-ink/70 text-sm">Emiți facturi fiscale către clienți direct din platformă.</p></div>
      <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-3xl mb-2">🔗</div><h3 class="font-display font-bold text-lg">Contabilitate RO</h3><p class="mt-2 text-ink/70 text-sm">Integrare cu sisteme de contabilitate din România.</p></div>
      <div class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm"><div class="text-3xl mb-2">💼</div><h3 class="font-display font-bold text-lg">Deconturi clare</h3><p class="mt-2 text-ink/70 text-sm">Deconturi periodice sau la cerere, cu evidență transparentă.</p></div>
    </div>
    <div x-data="anafFlow()" x-intersect.once="start()" class="mt-8 bg-ink text-cream rounded-md border-2 border-ink shadow-hard p-6 sm:p-8 overflow-hidden">
      <p class="font-mono text-xs uppercase tracking-widest text-ember mb-6">O vânzare → documente generate automat, în secunde</p>
      <div class="grid lg:grid-cols-[auto_1fr] gap-8 items-center">
        <div class="relative">
          <div class="bg-cream/5 border border-cream/15 rounded-md p-5 w-full lg:w-64">
            <div class="flex items-center justify-between mb-3"><span class="font-mono text-[11px] uppercase tracking-widest text-cream/50">Comandă nouă</span><span class="w-2.5 h-2.5 rounded-full bg-ember animate-pulse"></span></div>
            <p class="font-display font-bold text-lg">Bilet acces + rental</p>
            <p class="font-mono text-sm text-cream/60 mt-1">#BO-19024 · 180 lei</p>
            <div class="mt-3 flex items-center gap-2 text-xs text-forest font-semibold"><span class="w-4 h-4 rounded-full bg-forest text-cream grid place-items-center text-[9px]">✓</span> Plată confirmată</div>
          </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
          <template x-for="(doc,i) in docs" :key="i">
            <div class="relative border-2 rounded-md p-4 transition-all duration-500" :class="done > i ? 'border-ember bg-cream/5 opacity-100 translate-y-0' : 'border-cream/15 opacity-30 translate-y-2'">
              <div class="flex items-center justify-between mb-2"><span class="text-2xl" x-text="doc.icon"></span><span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold transition-all" :class="done > i ? 'bg-forest text-cream' : 'bg-cream/10 text-cream/40'" x-text="done > i ? '✓' : (done===i ? '…' : '')"></span></div>
              <p class="font-display font-bold text-sm leading-tight" x-text="doc.name"></p>
              <p class="font-mono text-[10px] text-cream/50 mt-1" x-text="doc.meta"></p>
              <div class="mt-3 h-1 bg-cream/10 rounded-full overflow-hidden" x-show="done===i" x-transition><div class="h-full bg-ember rounded-full animate-[fill_1s_ease-in-out_infinite]" style="width:60%"></div></div>
            </div>
          </template>
        </div>
      </div>
      <div class="mt-6 pt-5 border-t border-cream/10 flex flex-wrap items-center justify-between gap-3">
        <p class="text-cream/70 text-sm">Zero introducere manuală. Documentele sunt gata în <strong class="text-ember" x-text="elapsed + 's'">0s</strong> de la fiecare vânzare.</p>
        <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center gap-2 bg-ember text-cream font-bold px-6 py-3 rounded-sm hover:translate-x-[3px] hover:translate-y-[3px] transition-all text-sm">Vreau fiscalitatea pe pilot automat →</a>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="cum" class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-3xl mb-12"><p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">De la cont la prima vânzare</p><h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Patru pași. Sub o zi. Zero costuri de pornire.</h2></div>
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
    <div class="relative bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm">
      <span class="absolute -top-4 -left-3 w-10 h-10 grid place-items-center bg-rust text-cream font-display font-black rounded-sm rotate-[-6deg] border-2 border-ink shadow-hard-sm"><?= $n ?></span>
      <div class="text-2xl mb-2 mt-2"><?= $icon ?></div>
      <h3 class="font-display font-bold text-xl"><?= htmlspecialchars($title, ENT_QUOTES) ?></h3>
      <p class="mt-2 text-ink/75 text-sm leading-relaxed"><?= htmlspecialchars($body, ENT_QUOTES) ?></p>
      <p class="mt-3 font-mono text-[11px] uppercase tracking-wide text-rust"><?= htmlspecialchars($tag, ENT_QUOTES) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center gap-2 bg-ink text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Începe acum, gratuit →</a></div>
</section>

<!-- TIXELLO ENGINE -->
<section id="tehnologie" class="py-20 sm:py-24 bg-paper border-y-2 border-ink reveal">
  <div class="max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
    <div>
      <span class="inline-block font-mono text-xs uppercase tracking-widest bg-ink text-cream px-3 py-1.5 rounded-sm mb-5">Powered by Tixello</span>
      <h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Infrastructură matură, testată la scară.</h2>
      <p class="mt-6 text-lg text-ink/75 leading-relaxed">bilete.online rulează pe Tixello — sistemul de ticketing care a procesat deja peste 4,4 milioane EUR în vânzări și peste 301.000 de bilete. Primești tehnologie de producție, fără s-o construiești sau s-o întreții.</p>
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="mt-7 inline-flex items-center gap-2 bg-rust text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Devino partener →</a>
    </div>
    <div class="bg-ink text-cream rounded-md p-8 shadow-hard border-2 border-ink">
      <p class="font-mono text-xs uppercase tracking-widest text-ember mb-5">În cifre</p>
      <div class="space-y-4 font-mono text-sm">
        <div class="flex justify-between border-b border-cream/15 pb-3"><span class="text-cream/60">Evenimente &amp; activități</span><span class="font-bold">4.294</span></div>
        <div class="flex justify-between border-b border-cream/15 pb-3"><span class="text-cream/60">Clienți în bază</span><span class="font-bold">96.341</span></div>
        <div class="flex justify-between border-b border-cream/15 pb-3"><span class="text-cream/60">Bilete vândute</span><span class="font-bold">301.310</span></div>
        <div class="flex justify-between border-b border-cream/15 pb-3"><span class="text-cream/60">Vânzări generate</span><span class="font-bold">4.409.557 €</span></div>
        <div class="flex justify-between"><span class="text-cream/60">Scanare offline</span><span class="font-bold">Da, cu sync</span></div>
      </div>
    </div>
  </div>
</section>

<!-- CATEGORII — DINAMIC DIN API -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-2xl mx-auto text-center mb-12"><p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Pentru ce tip de activitate</p><h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Categoriile disponibile pe bilete.online</h2></div>
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
    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="block bg-cream border-2 border-ink rounded-md overflow-hidden shadow-hard-sm hover:-translate-y-1 hover:shadow-hard transition-all" data-cat-slug="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
      <?php if ($img): ?>
        <img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES) ?>" loading="lazy" class="w-full h-40 object-cover border-b-2 border-ink bg-ticket">
      <?php else: ?>
        <div class="w-full h-40 bg-ticket border-b-2 border-ink grid place-items-center text-5xl"><?= htmlspecialchars($cat['icon_emoji'] ?? '🎫', ENT_QUOTES) ?></div>
      <?php endif; ?>
      <div class="p-5">
        <h3 class="font-display font-bold text-lg"><?= htmlspecialchars($name, ENT_QUOTES) ?></h3>
        <?php if ($desc !== ''): ?>
        <p class="mt-1.5 text-sm text-ink/70 leading-relaxed"><?= htmlspecialchars($desc, ENT_QUOTES) ?></p>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- TESTIMONIALE -->
<section class="py-20 sm:py-24 max-w-7xl mx-auto px-5 sm:px-8 reveal">
  <div class="max-w-2xl mb-12">
    <p class="font-mono text-sm uppercase tracking-widest text-rust mb-4">Ce spun organizatorii</p>
    <h2 class="font-display font-black text-4xl sm:text-5xl leading-tight">Locații care vând deja prin platformă.</h2>
  </div>
  <div id="grid-testimoniale" class="grid md:grid-cols-3 gap-6">
    <figure data-cat="escape" class="bg-cream border-2 border-ink rounded-md p-7 shadow-hard-sm flex flex-col">
      <div class="flex gap-0.5 text-ember text-lg mb-4">★★★★★</div>
      <blockquote class="font-display text-lg leading-snug flex-1">„Înainte pierdeam din marjă la fiecare comision. Acum prețul afișat e exact ce încasez. Diferența pe un sezon ne-a plătit două angajări noi.”</blockquote>
      <figcaption class="mt-6 flex items-center gap-3 pt-5 border-t border-ink/10">
        <span class="w-11 h-11 rounded-full bg-forest text-cream grid place-items-center font-display font-bold">AR</span>
        <span><span class="block font-bold text-sm">Andrei Roșu</span><span class="block text-xs text-ink/60">Fondator, EscapeLab Cluj</span></span>
      </figcaption>
    </figure>
    <figure data-cat="parc-aventura" class="bg-ink text-cream rounded-md p-7 shadow-hard border-2 border-ink flex flex-col">
      <div class="flex gap-0.5 text-ember text-lg mb-4">★★★★★</div>
      <blockquote class="font-display text-lg leading-snug flex-1">„Booking-ul pe sloturi a fost decisiv pentru noi. Vizitatorii își aleg singuri ora, iar cozile de la casă aproape au dispărut. Scanarea merge și când pică netul.”</blockquote>
      <figcaption class="mt-6 flex items-center gap-3 pt-5 border-t border-cream/15">
        <span class="w-11 h-11 rounded-full bg-ember text-cream grid place-items-center font-display font-bold">MD</span>
        <span><span class="block font-bold text-sm">Maria Dincă</span><span class="block text-xs text-cream/60">Manager, Parcul de Aventură Brașov</span></span>
      </figcaption>
    </figure>
    <figure data-cat="ateliere" class="bg-cream border-2 border-ink rounded-md p-7 shadow-hard-sm flex flex-col">
      <div class="flex gap-0.5 text-ember text-lg mb-4">★★★★★</div>
      <blockquote class="font-display text-lg leading-snug flex-1">„Cu pixelii și CAPI conectate, costul pe vânzare la reclame ne-a scăzut vizibil. Vedem în sfârșit ce campanie aduce bilete, nu doar click-uri.”</blockquote>
      <figcaption class="mt-6 flex items-center gap-3 pt-5 border-t border-ink/10">
        <span class="w-11 h-11 rounded-full bg-rust text-cream grid place-items-center font-display font-bold">VT</span>
        <span><span class="block font-bold text-sm">Vlad Tudor</span><span class="block text-xs text-ink/60">Marketing, Atelierele Creative București</span></span>
      </figcaption>
    </figure>
  </div>
  <div class="grid sm:grid-cols-2 gap-6 mt-6">
    <figure class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm">
      <div class="flex gap-0.5 text-ember mb-3">★★★★★</div>
      <blockquote class="text-ink/85 leading-relaxed">„Am pus muzeul online într-o singură după-amiază. Facturile fiscale și documentele pentru ANAF se generează automat — contabila noastră a răsuflat ușurată.”</blockquote>
      <figcaption class="mt-4 flex items-center gap-3 pt-4 border-t border-ink/10">
        <span class="w-10 h-10 rounded-full bg-gold text-ink grid place-items-center font-display font-bold text-sm">IC</span>
        <span><span class="block font-bold text-sm">Ioana Crețu</span><span class="block text-xs text-ink/60">Director, Muzeul Etnografic Sibiu</span></span>
      </figcaption>
    </figure>
    <figure class="bg-cream border-2 border-ink rounded-md p-6 shadow-hard-sm">
      <div class="flex gap-0.5 text-ember mb-3">★★★★★</div>
      <blockquote class="text-ink/85 leading-relaxed">„Vindem și online, și la fața locului din același panou, plus rentals de echipament. În weekend-uri aglomerate, totul rămâne sincronizat și nu mai supravindem niciun loc.”</blockquote>
      <figcaption class="mt-4 flex items-center gap-3 pt-4 border-t border-ink/10">
        <span class="w-10 h-10 rounded-full bg-forest text-cream grid place-items-center font-display font-bold text-sm">RP</span>
        <span><span class="block font-bold text-sm">Radu Pop</span><span class="block text-xs text-ink/60">Administrator, Aqua Park Mureș</span></span>
      </figcaption>
    </figure>
  </div>
  <div class="mt-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-center">
    <div><span class="font-display font-black text-2xl text-ink">96.341</span><span class="block text-xs text-ink/60 uppercase tracking-wide">clienți în bază</span></div>
    <span class="hidden sm:block w-px h-8 bg-ink/15"></span>
    <div><span class="font-display font-black text-2xl text-ink">301.310</span><span class="block text-xs text-ink/60 uppercase tracking-wide">bilete vândute</span></div>
    <span class="hidden sm:block w-px h-8 bg-ink/15"></span>
    <div><span class="font-display font-black text-2xl text-ink">4.294</span><span class="block text-xs text-ink/60 uppercase tracking-wide">activități listate</span></div>
    <span class="hidden sm:block w-px h-8 bg-ink/15"></span>
    <div><span class="font-display font-black text-2xl text-ink">4,4M €</span><span class="block text-xs text-ink/60 uppercase tracking-wide">vânzări generate</span></div>
  </div>
  <div class="mt-10 text-center"><a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center gap-2 bg-rust text-cream font-bold px-7 py-3.5 rounded-sm shadow-hard-sm hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">Vreau și eu rezultatele astea →</a></div>
</section>

<!-- FAQ -->
<section class="py-20 sm:py-24 bg-paper border-t-2 border-ink reveal">
  <div class="max-w-3xl mx-auto px-5 sm:px-8" x-data="{open:0}">
    <p class="font-mono text-sm uppercase tracking-widest text-rust mb-4 text-center">Întrebări frecvente</p>
    <h2 class="font-display font-black text-4xl sm:text-5xl leading-tight text-center mb-12">Ce vrei să știi înainte să începi</h2>
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
          <span class="shrink-0 w-8 h-8 grid place-items-center border-2 border-ink rounded-sm transition-transform" :class="open===i && 'rotate-45 bg-ink text-cream'">+</span>
        </button>
        <div x-show="open===i" x-collapse><p class="pb-5 text-ink/75 leading-relaxed text-lg" x-text="f.a"></p></div>
      </div>
    </template>
  </div>
</section>

<!-- FINAL CTA -->
<section id="contact" class="py-24 sm:py-32 bg-rust text-cream relative overflow-hidden">
  <div class="absolute inset-0 opacity-10" style="background-image:repeating-linear-gradient(45deg,#fbf6ec 0 2px,transparent 2px 18px)"></div>
  <div class="relative max-w-3xl mx-auto px-5 sm:px-8 text-center reveal">
    <span class="inline-block font-mono text-xs uppercase tracking-widest bg-cream text-ink px-3 py-1.5 rounded-sm mb-6 rotate-[-2deg]">Devino partener</span>
    <h2 class="font-display font-black text-4xl sm:text-6xl leading-[0.95]">Pune-ți activitățile la vânzare<br>și păstrează prețul tău întreg.</h2>
    <p class="mt-6 text-lg text-cream/90 max-w-xl mx-auto leading-relaxed">Fără costuri de pornire. Activități nelimitate. Onboarding în 5 minute, go-live azi. Comision 2%* plătit de client.</p>
    <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
      <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="inline-flex items-center justify-center gap-2 bg-ink text-cream font-bold text-lg px-8 py-4 rounded-sm shadow-hard hover:shadow-none hover:translate-x-[6px] hover:translate-y-[6px] transition-all">
        Vreau să-mi vând activitățile →
      </a>
      <a href="mailto:contact@bilete.online?subject=Întrebare%20parteneriat%20bilete.online" class="inline-flex items-center justify-center gap-2 border-2 border-cream font-bold text-lg px-8 py-4 rounded-sm hover:bg-cream hover:text-ink transition">
        Trimite-ne un email
      </a>
    </div>
    <p class="mt-6 text-cream/70 text-sm">Fără cost de pornire · Activități nelimitate · Anulezi oricând</p>
  </div>
</section>

<!-- FOOTER -->
<footer class="bg-ink text-cream py-12">
  <div class="max-w-7xl mx-auto px-5 sm:px-8 flex flex-col sm:flex-row justify-between items-center gap-6">
    <div class="flex items-center gap-2 font-display font-black text-xl"><span class="inline-grid place-items-center w-8 h-8 bg-cream text-ink rounded-sm font-mono text-sm rotate-[-6deg]">b.</span>bilete<span class="text-ember">.online</span></div>
    <p class="text-sm text-cream/60 text-center">Construit pe <strong class="text-cream">Tixello</strong> · Ticketing &amp; booking pentru activități<br><span class="text-cream/40">* Comisionul de 2% se aplică pentru vânzarea exclusivă prin bilete.online.<br>În cazul vânzării de bilete prin mai multe platforme (sau neexclusiv), comisionul este de 4%: 2% inclus în prețul biletului, 2% adăugat peste prețul biletului.</span></p>
    <a :href="$store.perso.signupHref('/inregistrare-locatie')" class="text-sm font-semibold border-2 border-cream/40 rounded-sm px-5 py-2.5 hover:bg-cream hover:text-ink transition">Devino partener</a>
  </div>
</footer>

</div>

<script>
  /* Personalizare PE TIP DE LOCAȚIE — preserves the URL query string
     through the Devino partener CTA so the /inregistrare-locatie form
     can pre-fill from the same `tip` + `loc` params. */
  const PERSO_PROFILES = {
    'escape': { label:'escape room', h1a:'Vinzi bilete', h1b:'la camerele tale de escape.', h1c:'Prețul tău rămâne al tău.',
      sub:'Sloturi de 60 de minute, capacitate pe cameră, rezervări în detaliu și o aplicație cu scanare offline. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de jucător — tu îți păstrezi prețul stabilit.' },
    'muzeu': { label:'muzeu', h1a:'Vinzi bilete', h1b:'la muzeul tău.', h1c:'Prețul tău rămâne al tău.',
      sub:'Bilete de acces pe zile și intervale orare, ghidaj și pachete pentru grupuri școlare, plus emitere automată de documente fiscale. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de vizitator — tu îți păstrezi prețul stabilit.' },
    'parc-distractii': { label:'parc de distracții', h1a:'Vinzi bilete', h1b:'la parcul tău.', h1c:'Prețul tău rămâne al tău.',
      sub:'Bilete de acces, abonamente, add-on-uri pentru atracții și rentals — toate într-un singur sistem, online și la fața locului. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de vizitator — tu îți păstrezi prețul stabilit.' },
    'parc-aventura': { label:'parc de aventură', h1a:'Vinzi bilete', h1b:'la traseele tale de aventură.', h1c:'Prețul tău rămâne al tău.',
      sub:'Trasee pe niveluri de dificultate, sloturi pe capacitate, închiriere de echipament (rental) și scanare offline pe teren. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de aventurier — tu îți păstrezi prețul stabilit.' },
    'natura': { label:'experiență în natură', h1a:'Vinzi bilete', h1b:'la experiențele tale în natură.', h1c:'Prețul tău rămâne al tău.',
      sub:'Tururi ghidate, trasee și activități outdoor cu sloturi pe zile și ore, plus scanare offline acolo unde nu prinde semnal. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de participant — tu îți păstrezi prețul stabilit.' },
    'acvarii-zoo': { label:'grădină zoologică / acvariu', h1a:'Vinzi bilete', h1b:'la grădina ta zoo / acvariu.', h1c:'Prețul tău rămâne al tău.',
      sub:'Bilete de acces pe zile și intervale, experiențe cu animale și pachete de familie, online și la casă. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de vizitator — tu îți păstrezi prețul stabilit.' },
    'ateliere': { label:'atelier creativ', h1a:'Vinzi locuri', h1b:'la atelierele tale creative.', h1c:'Prețul tău rămâne al tău.',
      sub:'Sesiuni cu locuri limitate, pachete de materiale și rezervare pe sloturi orare, fără supravânzare. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de participant — tu îți păstrezi prețul stabilit.' },
    'tururi': { label:'tur turistic', h1a:'Vinzi bilete', h1b:'la tururile tale ghidate.', h1c:'Prețul tău rămâne al tău.',
      sub:'City walks și tururi cu plecări pe ore, capacitate per plecare și bilete pe telefon. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de turist — tu îți păstrezi prețul stabilit.' },
    'educatie': { label:'program educațional', h1a:'Vinzi locuri', h1b:'la programele tale educaționale.', h1c:'Prețul tău rămâne al tău.',
      sub:'Activități STEM și lecții interactive cu rezervare pe clase și grupuri, plus documente fiscale generate automat. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de participant — tu îți păstrezi prețul stabilit.' },
    'familie': { label:'activitate pentru familie', h1a:'Vinzi bilete', h1b:'la activitățile tale pentru familii.', h1c:'Prețul tău rămâne al tău.',
      sub:'Pachete de familie, prețuri pe categorii de vârstă și rezervare pe sloturi, online și la fața locului. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de client — tu îți păstrezi prețul stabilit.' },
    'corporate': { label:'experiență corporate', h1a:'Vinzi locuri', h1b:'la experiențele tale pentru echipe.', h1c:'Prețul tău rămâne al tău.',
      sub:'Pachete de grup pentru team-building și evenimente private, cu facturare fiscală automată. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de client — tu îți păstrezi prețul stabilit.' },
    'cultura': { label:'instituție culturală', h1a:'Vinzi bilete', h1b:'la evenimentele tale culturale.', h1c:'Prețul tău rămâne al tău.',
      sub:'Bilete cu locuri sau acces general, pe date și intervale, cu emitere fiscală automată. Comisionul de <strong class="text-rust">doar 2%*</strong> e plătit de spectator — tu îți păstrezi prețul stabilit.' }
  };

  const PERSO_ALIASES = {
    'escape':'escape','escape-room':'escape','escape-rooms':'escape',
    'muzeu':'muzeu','muzee':'muzeu','museum':'muzeu','muzee-expozitii':'muzeu',
    'parc-distractii':'parc-distractii','parc-distracții':'parc-distractii','distractii':'parc-distractii','parcuri-de-distractii':'parc-distractii',
    'parc-aventura':'parc-aventura','aventura':'parc-aventura','aventură':'parc-aventura','parcuri-de-aventura':'parc-aventura',
    'natura':'natura','natură':'natura','outdoor':'natura',
    'acvarii-zoo':'acvarii-zoo','zoo':'acvarii-zoo','acvariu':'acvarii-zoo',
    'ateliere':'ateliere','atelier':'ateliere','workshop':'ateliere','creativ':'ateliere',
    'tururi':'tururi','tur':'tururi','tours':'tururi','tururi-turistice':'tururi',
    'educatie':'educatie','educație':'educatie','educational':'educatie','stem':'educatie',
    'familie':'familie','copii':'familie','family':'familie',
    'corporate':'corporate','grupuri':'corporate','team-building':'corporate',
    'cultura':'cultura','cultură':'cultura','arta':'cultura','artă':'cultura','culture':'cultura'
  };

  document.addEventListener('alpine:init', () => {
    Alpine.store('perso', {
      loc:'', typeKey:'', profile:null,
      boot(){
        const p = new URLSearchParams(window.location.search);
        const rawLoc = (p.get('loc')||'').trim();
        const rawTip = (p.get('tip')||'').trim().toLowerCase();
        const key = PERSO_ALIASES[rawTip] || '';
        this.loc = rawLoc;
        this.typeKey = key;
        this.profile = key ? PERSO_PROFILES[key] : null;
      },
      get hasProfile(){ return !!this.profile; },
      p(field, fallback){ return (this.profile && this.profile[field]) ? this.profile[field] : fallback; },
      // Build an /inregistrare-locatie href that forwards the same `tip` +
      // `loc` query params so the signup form can pre-fill.
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

  /* Animated ANAF / auto-document pipeline */
  function anafFlow(){
    return {
      started:false, done:0, elapsed:0,
      docs:[
        {icon:'🧾', name:'Factură fiscală', meta:'serie BO · client'},
        {icon:'📄', name:'Document ANAF', meta:'raportare automată'},
        {icon:'🔗', name:'Înregistrare contabilă', meta:'sync contabilitate RO'}
      ],
      wait(ms){ return new Promise(r=>setTimeout(r,ms)); },
      start(){ if(this.started) return; this.started=true; this.run(); },
      async run(){
        while(true){
          this.done=0; this.elapsed=0; await this.wait(900);
          for(let i=0;i<this.docs.length;i++){
            this.done=i; await this.wait(1100);
            this.elapsed = Math.min(3, this.elapsed + 1);
            this.done=i+1; await this.wait(450);
          }
          await this.wait(2600);
        }
      }
    }
  }

  /* Animated booking flow component */
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
      orderTotal(){ let t = this._num(this.tickets[1] && this.tickets[1].price); if(this.extras[0]) t += this._num(this.extras[0].price); if(this.extras[1]) t += this._num(this.extras[1].price); return t ? (t.toLocaleString('ro-RO') + ' lei') : '—'; },
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

  /* scroll reveal */
  const io = new IntersectionObserver((entries)=>{ entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target);} }); }, {threshold:0.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  /* Promote the matching category card + testimonial to the top when ?tip= is set */
  (function(){
    const p = new URLSearchParams(window.location.search);
    const tip = (p.get('tip')||'').trim().toLowerCase();
    if(!tip) return;
    const norm = {
      'escape':'escape-rooms','escape-room':'escape-rooms','escape-rooms':'escape-rooms',
      'muzeu':'muzee-expozitii','muzee':'muzee-expozitii',
      'parc-distractii':'parcuri-de-distractii','distractii':'parcuri-de-distractii',
      'parc-aventura':'parcuri-de-aventura','aventura':'parcuri-de-aventura','aventură':'parcuri-de-aventura',
      'acvarii-zoo':'acvarii-zoo','zoo':'acvarii-zoo','acvariu':'acvarii-zoo',
      'ateliere':'ateliere-experiente-creative','atelier':'ateliere-experiente-creative',
      'tururi':'tururi-experiente-turistice','tur':'tururi-experiente-turistice',
      'educatie':'educatie-invatare-experientiala','educație':'educatie-invatare-experientiala',
      'familie':'familie-copii','copii':'familie-copii',
      'corporate':'corporate-grupuri','grupuri':'corporate-grupuri',
      'cultura':'cultura-arta','cultură':'cultura-arta'
    };
    const TKEY = { 'escape':'escape','escape-room':'escape','muzeu':'muzeu','muzee':'muzeu','parc-distractii':'parc-distractii','parc-aventura':'parc-aventura','aventura':'parc-aventura','acvarii-zoo':'acvarii-zoo','zoo':'acvarii-zoo','ateliere':'ateliere','atelier':'ateliere','tururi':'tururi','tur':'tururi','educatie':'educatie','familie':'familie','corporate':'corporate','cultura':'cultura' };
    const targetSlug = norm[tip];
    function run(){
      const grid = document.getElementById('grid-categorii');
      if (grid && targetSlug) {
        const card = grid.querySelector(`[data-cat-slug="${targetSlug}"]`);
        if (card) {
          card.classList.add('!border-ember','ring-2','ring-ember');
          if (!card.querySelector('[data-perso-badge]')) {
            const wrap = card.querySelector('.p-5') || card;
            const badge = document.createElement('span');
            badge.setAttribute('data-perso-badge','');
            badge.className = 'inline-block mb-2 bg-ember text-cream text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded-sm';
            badge.textContent = 'Pentru tine';
            wrap.insertBefore(badge, wrap.firstChild);
          }
          grid.insertBefore(card, grid.firstChild);
        }
      }
      const tgrid = document.getElementById('grid-testimoniale');
      const tk = TKEY[tip];
      if (tgrid && tk) {
        const fig = tgrid.querySelector(`figure[data-cat="${tk}"]`);
        if (fig) tgrid.insertBefore(fig, tgrid.firstChild);
      }
    }
    if (document.readyState !== 'loading') run();
    else document.addEventListener('DOMContentLoaded', run);
  })();
</script>

<?php include __DIR__ . '/includes/cookie-consent.php'; ?>
</body>
</html>

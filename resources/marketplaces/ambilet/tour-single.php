<?php
/**
 * Tour Single Page - Ambilet Marketplace
 * Visual based on resources/marketplaces/ambilet/designs/ambilet-tour-v2.html.
 * Data loaded async via AmbiletAPI.get('/tours/{slug}').
 */
$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';

$tourSlug = $_GET['slug'] ?? '';
if (empty($tourSlug)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/turnee/([a-z0-9-]+)#i', $uri, $matches)) {
        $tourSlug = $matches[1];
    }
}

$pageTitle = "Turneu — {$siteName}";
$pageDescription = "Descoperă turneul și concertele incluse pe {$siteName}.";
$bodyClass = 'antialiased bg-slate-50 text-slate-900 page-tour-single';
$cssBundle = 'single';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    body.page-tour-single { background: #f8fafc; }
    .card-shadow { box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.04); }
    .card-shadow-hover:hover { box-shadow: 0 4px 12px rgba(16,24,40,.08), 0 2px 6px rgba(16,24,40,.04); }
    .date-card { transition: all .2s ease; }
    .date-card:hover { border-color: var(--color-primary, #C8102E); transform: translateY(-2px); }
    .date-card:hover .buy-btn { background: var(--color-primary, #C8102E); color: #fff; }
    @keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: .4; } }
    .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
    @keyframes ping-slow { 0% { transform: scale(1); opacity: 1; } 75%, 100% { transform: scale(2.5); opacity: 0; } }
    .ping-slow { animation: ping-slow 2s cubic-bezier(0, 0, 0.2, 1) infinite; }
    @keyframes progress-fill { from { width: 0; } to { width: var(--progress); } }
    .progress-bar { animation: progress-fill 1.2s ease-out forwards; }
    [data-hidden] { display: none !important; }
</style>

<!-- HERO with poster -->
<section class="px-4 pt-4 mx-auto max-w-7xl md:px-6 mt-17 mobile:mt-16">
  <div class="overflow-hidden bg-gradient-to-br from-slate-900 via-slate-950 to-slate-950 rounded-2xl card-shadow">
    <div class="grid items-stretch grid-cols-1 md:grid-cols-12">
      <!-- POSTER (left) -->
      <div class="md:col-span-5 relative aspect-[3/4] md:aspect-auto md:min-h-[480px]">
        <div id="tourPosterBg" class="absolute inset-0 overflow-hidden bg-gradient-to-br from-red-900 via-red-950 to-black">
          <img id="tourPosterImg" src="" alt="" class="absolute inset-0 object-cover w-full h-full opacity-90" data-hidden>
          <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/30"></div>
        </div>
        <div class="absolute top-3 right-3">
          <div class="bg-primary text-white text-[9px] font-bold uppercase tracking-widest px-2 py-1 rounded">Turneu</div>
        </div>
      </div>

      <!-- INFO (right) -->
      <div class="relative flex flex-col justify-between p-6 text-white md:col-span-7 md:p-10">
        <div class="flex items-start justify-between gap-3 mb-6">
          <a id="tourArtistLink" href="#" class="flex items-center gap-2 px-3 py-2 text-white transition rounded-lg bg-white/10 hover:bg-white/15 backdrop-blur-sm" data-hidden>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            <span id="tourArtistLinkText" class="text-sm font-medium">Artist</span>
          </a>

          <div class="relative" id="shareWrap">
            <button id="shareBtn" type="button" class="flex items-center justify-center w-10 h-10 text-white transition rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-sm" title="Distribuie">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>
            </button>
            <div id="shareMenu" class="absolute right-0 z-20 overflow-hidden bg-white border shadow-xl top-12 w-60 rounded-xl border-slate-200 text-slate-900" data-hidden>
              <button id="shareCopyBtn" type="button" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition text-left">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                </div>
                <div id="shareCopyLabel" class="text-sm font-medium">Copiază link</div>
              </button>
              <a id="shareFb" href="#" target="_blank" rel="noopener" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-lg">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </div>
                <div class="text-sm font-medium">Facebook</div>
              </a>
              <a id="shareWa" href="#" target="_blank" rel="noopener" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                </div>
                <div class="text-sm font-medium">WhatsApp</div>
              </a>
            </div>
          </div>
        </div>

        <div class="flex flex-col justify-center flex-1">
          <div class="flex flex-wrap items-center gap-2 mb-3" id="tourBadges"></div>
          <h1 id="tourName" class="mb-3 text-3xl font-black tracking-tight md:text-5xl">
            <span class="inline-block h-12 rounded w-80 bg-white/20 animate-pulse"></span>
          </h1>
          <div class="flex items-center gap-2 mb-4" id="tourArtistChip" data-hidden>
            <a id="tourArtistChipLink" href="#" class="flex items-center gap-2 group">
              <div id="tourArtistChipAvatar" class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white transition rounded-full bg-gradient-to-br from-slate-700 to-slate-900 ring-2 ring-white/20 group-hover:ring-primary"></div>
              <span id="tourArtistChipName" class="font-semibold transition group-hover:text-primary"></span>
            </a>
          </div>
          <p id="tourShortDescription" class="max-w-xl text-sm leading-relaxed text-white/80 md:text-base" data-hidden></p>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-6 mt-6 border-t border-white/10">
          <a href="#dates" class="flex items-center gap-2 px-5 text-sm font-bold text-white transition rounded-lg bg-primary hover:bg-primary-dark h-11">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
            Vezi datele turneului
          </a>
          <a id="tourPosterDownload" href="#" download class="flex items-center gap-2 px-5 text-sm font-semibold text-white transition rounded-lg bg-white/10 hover:bg-white/20 backdrop-blur h-11" data-hidden>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
            Descarcă poster
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- META STRIP -->
<section class="px-4 mx-auto mt-4 max-w-7xl md:px-6">
  <div class="grid grid-cols-2 gap-4 p-4 bg-white rounded-2xl card-shadow md:p-5 md:grid-cols-4 md:gap-0 md:divide-x divide-slate-200">
    <div class="md:px-5 first:pl-0">
      <div class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1 flex items-center gap-1.5">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
        Perioadă
      </div>
      <div class="text-sm font-bold text-slate-900" id="metaPeriod">—</div>
      <div class="text-xs text-slate-500 mt-0.5" id="metaPeriodDays"></div>
    </div>
    <div class="md:px-5">
      <div class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1 flex items-center gap-1.5">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 7-8 13-8 13s-8-6-8-13a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
        Orașe
      </div>
      <div class="text-sm font-bold text-slate-900" id="metaCities">—</div>
      <div class="text-xs text-slate-500 mt-0.5" id="metaCitiesList"></div>
    </div>
    <div class="md:px-5">
      <div class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1 flex items-center gap-1.5">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Preț
      </div>
      <div class="text-sm font-bold text-slate-900" id="metaPrice">—</div>
      <div class="text-xs text-slate-500 mt-0.5" id="metaPriceNote"></div>
    </div>
    <div class="md:px-5 last:pr-0">
      <div class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1 flex items-center gap-1.5">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Durată concert
      </div>
      <div class="text-sm font-bold text-slate-900" id="metaDuration">—</div>
      <div class="text-xs text-slate-500 mt-0.5">Setlist principal</div>
    </div>
  </div>
</section>

<!-- MAIN GRID -->
<main class="px-4 py-8 mx-auto max-w-7xl md:px-6">
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <div class="space-y-6 lg:col-span-2">

      <!-- DATES -->
      <section id="dates" class="overflow-hidden bg-white rounded-2xl card-shadow scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 pb-4 border-b border-slate-100">
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><path d="M20 10c0 7-8 13-8 13s-8-6-8-13a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div>
              <h2 class="text-xl font-bold">Date turneu</h2>
              <p class="text-sm text-slate-500">Toate concertele din turneu</p>
            </div>
          </div>
        </div>

        <!-- Progress -->
        <div class="px-6 pt-5 pb-4">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold tracking-wider uppercase text-slate-600">Progres turneu</span>
              <span id="progressLabel" class="text-xs text-slate-400"></span>
            </div>
            <span id="progressTimeline" class="text-xs font-bold text-primary"></span>
          </div>
          <div class="relative h-2 overflow-hidden rounded-full bg-slate-100">
            <div id="progressBar" class="h-full rounded-full progress-bar bg-gradient-to-r from-primary to-primary-dark" style="--progress: 0%"></div>
          </div>
          <div class="flex items-center justify-between mt-2 text-[11px] text-slate-500 font-medium">
            <span id="progressStart"></span>
            <span id="progressEnd"></span>
          </div>
        </div>

        <!-- LIST -->
        <div id="datesList" class="border-t divide-y divide-slate-100 border-slate-100">
          <div class="p-6 animate-pulse">
            <div class="h-16 mb-3 bg-slate-100 rounded-xl"></div>
            <div class="h-16 bg-slate-100 rounded-xl"></div>
          </div>
        </div>
        <div id="datesEmpty" class="p-6 text-sm text-center border-t text-slate-500 border-slate-100" data-hidden>
          Nu sunt evenimente publicate momentan pentru acest turneu.
        </div>
      </section>

      <!-- ABOUT THE TOUR -->
      <section id="aboutSection" class="p-6 bg-white rounded-2xl card-shadow" data-hidden>
        <div class="flex items-center gap-3 mb-5">
          <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
          </div>
          <h2 class="text-xl font-bold">Despre turneu</h2>
        </div>
        <div id="tourDescription" class="mb-6 prose prose-slate max-w-none text-slate-700"></div>

        <!-- Setlist -->
        <div id="setlistCard" class="p-5 text-white bg-slate-900 rounded-xl" data-hidden>
          <div class="flex items-center gap-2 mb-3">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            <h3 class="text-sm font-bold tracking-wider uppercase">Setlist</h3>
            <span id="setlistDuration" class="text-[10px] bg-white/10 text-white/70 px-2 py-0.5 rounded ml-auto" data-hidden></span>
          </div>
          <div id="setlistGrid" class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1.5 text-sm"></div>
        </div>
      </section>

      <!-- ARTISTS -->
      <section id="artistsSection" class="p-6 bg-white rounded-2xl card-shadow" data-hidden>
        <div class="flex items-center gap-3 mb-5">
          <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-bold">Artiști participanți</h2>
            <p class="text-sm text-slate-500">Headliner + invitați pe fiecare dată</p>
          </div>
        </div>

        <div id="headlinerCard" data-hidden></div>
        <div id="guestsWrap" data-hidden>
          <div class="mt-5 mb-3 text-xs font-semibold tracking-wider uppercase text-slate-500">Invitați speciali</div>
          <div id="guestsGrid" class="grid grid-cols-2 gap-3 md:grid-cols-4"></div>
        </div>
      </section>

      <!-- FAQ -->
      <section id="faqSection" class="p-6 bg-white rounded-2xl card-shadow" data-hidden>
        <div class="flex items-center gap-3 mb-5">
          <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
          </div>
          <h2 class="text-xl font-bold">Întrebări frecvente</h2>
        </div>
        <div id="faqList" class="space-y-2"></div>
      </section>

    </div>

    <!-- SIDEBAR -->
    <aside class="space-y-6 lg:sticky lg:top-20 lg:self-start">
      <!-- Next date CTA -->
      <div id="nextDateCard" class="relative p-5 overflow-hidden text-white bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl" data-hidden>
        <div class="absolute w-32 h-32 rounded-full -top-6 -right-6 bg-primary/30 blur-2xl"></div>
        <div class="relative">
          <div class="text-[10px] uppercase tracking-widest text-white/60 font-semibold mb-1 flex items-center gap-1.5">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Următorul concert
          </div>
          <div id="nextDateCity" class="mb-1 text-2xl font-bold"></div>
          <div id="nextDateMeta" class="mb-3 text-sm text-white/80"></div>
          <div id="nextDateCountdown" class="inline-flex items-center gap-2 bg-white/10 backdrop-blur px-3 py-1.5 rounded-full text-xs font-semibold mb-4">
            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full pulse-dot"></span>
            <span id="nextDateCountdownText">Începe în curând</span>
          </div>
          <a id="nextDateCta" href="#" class="block py-3 text-sm font-bold text-center transition rounded-lg bg-primary hover:bg-primary-dark">
            Cumpără bilet →
          </a>
          <div class="text-[10px] text-white/50 text-center mt-2">Plată securizată</div>
        </div>
      </div>

      <!-- Booking info -->
      <div id="infoCard" class="overflow-hidden bg-white rounded-2xl card-shadow">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-slate-100">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11H3V9Z"/><path d="M8 2v4"/><path d="M16 2v4"/><path d="M3 13h18"/></svg>
          <h3 class="font-bold">Informații turneu</h3>
        </div>
        <dl id="infoList" class="divide-y divide-slate-100"></dl>
      </div>

      <!-- Follow artist -->
      <a id="followArtist" href="#" class="block p-5 transition bg-white border border-transparent rounded-2xl card-shadow hover:border-primary hover:shadow-lg group" data-hidden>
        <div class="flex items-center gap-3 mb-3">
          <div id="followArtistAvatar" class="flex items-center justify-center w-12 h-12 text-sm font-bold text-white transition rounded-full bg-gradient-to-br from-slate-700 to-slate-900 ring-2 ring-slate-200 group-hover:ring-primary"></div>
          <div class="flex-1 min-w-0">
            <span id="followArtistName" class="font-bold truncate text-slate-900"></span>
          </div>
        </div>
        <div class="flex items-center justify-center w-full h-10 gap-2 px-4 text-sm font-bold text-white transition rounded-lg bg-primary hover:bg-primary-dark">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          Vezi pagina artistului
        </div>
      </a>
    </aside>
  </div>
</main>

<!-- Sticky mobile CTA -->
<div id="mobileStickyCta" class="fixed bottom-0 left-0 right-0 z-30 flex items-center gap-3 p-3 bg-white border-t shadow-lg lg:hidden border-slate-200" data-hidden>
  <div class="flex-1 min-w-0">
    <div id="mobileStickyMeta" class="text-xs text-slate-500"></div>
    <div id="mobileStickyTitle" class="text-sm font-semibold truncate"></div>
  </div>
  <a id="mobileStickyCtaLink" href="#dates" class="px-5 py-3 text-sm font-semibold text-white rounded-lg bg-primary whitespace-nowrap">Bilete →</a>
</div>

<button onclick="window.scrollTo({top:0, behavior:'smooth'})" class="fixed z-30 items-center justify-center hidden text-white transition rounded-full shadow-lg lg:flex bottom-6 right-6 w-11 h-11 bg-primary hover:bg-primary-dark">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m18 15-6-6-6 6"/></svg>
</button>

<?php
require_once __DIR__ . '/includes/footer.php';
echo '<script>window.TOUR_SLUG = ' . json_encode($tourSlug) . ';</script>';

$scriptsExtra = <<<'JS'
<script>
(function () {
    const slug = window.TOUR_SLUG;
    if (!slug) return;

    const $ = (id) => document.getElementById(id);
    const esc = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    const show = (el, isVisible) => { if (!el) return; if (isVisible) el.removeAttribute('data-hidden'); else el.setAttribute('data-hidden',''); };
    const initials = (name) => (name || '?').split(/\s+/).filter(Boolean).map(w => w[0] || '').slice(0, 2).join('').toUpperCase() || '?';
    const ROMONTHS = ['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Noi','Dec'];
    const RODAYS = ['Dum','Lun','Mar','Mie','Joi','Vin','Sâm'];

    function fmtDateLong(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' });
    }
    function fmtTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
    }
    function fmtNumber(n) { return Number(n || 0).toLocaleString('ro-RO'); }
    function fmtCapacity(n) { return Number(n) < 0 ? '∞' : fmtNumber(n); }
    function daysBetween(aIso, bIso) {
        if (!aIso || !bIso) return null;
        const a = new Date(aIso), b = new Date(bIso);
        return Math.round((b - a) / (1000 * 60 * 60 * 24));
    }
    function shortPeriod(start, end) {
        if (!start) return '—';
        const s = new Date(start), e = end ? new Date(end) : null;
        if (!e || s.toDateString() === e.toDateString()) {
            return s.getDate() + ' ' + ROMONTHS[s.getMonth()] + ' ' + s.getFullYear();
        }
        if (s.getFullYear() === e.getFullYear() && s.getMonth() === e.getMonth()) {
            return s.getDate() + ' — ' + e.getDate() + ' ' + ROMONTHS[s.getMonth()] + ' ' + s.getFullYear();
        }
        if (s.getFullYear() === e.getFullYear()) {
            return s.getDate() + ' ' + ROMONTHS[s.getMonth()] + ' — ' + e.getDate() + ' ' + ROMONTHS[e.getMonth()] + ' ' + s.getFullYear();
        }
        return s.getDate() + ' ' + ROMONTHS[s.getMonth()] + ' ' + s.getFullYear() + ' — ' + e.getDate() + ' ' + ROMONTHS[e.getMonth()] + ' ' + e.getFullYear();
    }

    document.addEventListener('DOMContentLoaded', load);

    async function load() {
        try {
            const res = await AmbiletAPI.get('/tours/' + encodeURIComponent(slug));
            if (!res || !res.success || !res.data) {
                showNotFound();
                return;
            }
            render(res.data);
        } catch (e) {
            console.error('Tour load failed', e);
            showNotFound();
        }
    }

    function showNotFound() {
        document.body.innerHTML = '<div class="flex items-center justify-center min-h-screen bg-slate-50"><div class="text-center"><h1 class="mb-2 text-2xl font-bold text-slate-900">Turneu negăsit</h1><p class="mb-6 text-slate-500">Linkul accesat nu mai e valabil.</p><a href="/" class="px-4 py-2 font-semibold text-white rounded-lg bg-primary">Înapoi la pagina principală</a></div></div>';
    }

    function render(data) {
        const tour = data.tour || {};
        const artist = data.artist || null;
        const organizer = data.organizer || null;
        const aggregates = data.aggregates || {};
        const events = data.events || [];

        const periodStart = tour.period?.start || null;
        const periodEnd = tour.period?.end || null;

        document.title = (tour.name || 'Turneu') + (window.AMBILET?.siteName ? ' — ' + window.AMBILET.siteName : '');

        // Hero — poster
        if (tour.poster_url) {
            const img = $('tourPosterImg');
            img.src = tour.poster_url;
            img.alt = tour.name || '';
            show(img, true);
        } else if (tour.cover_url) {
            // fallback to cover image as the hero left panel
            const img = $('tourPosterImg');
            img.src = tour.cover_url;
            img.alt = tour.name || '';
            show(img, true);
        }
        if (tour.poster_url) {
            const dl = $('tourPosterDownload');
            dl.href = tour.poster_url;
            show(dl, true);
        }

        // Title
        $('tourName').textContent = tour.name || '';

        // Badges
        const badges = [];
        if (tour.type === 'turneu') {
            badges.push('<span class="inline-flex items-center gap-1.5 bg-primary text-white text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Turneu</span>');
        } else if (tour.type === 'serie_evenimente') {
            badges.push('<span class="inline-flex items-center gap-1.5 bg-white/20 text-white text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full">Serie evenimente</span>');
        }
        const status = (tour.status || '').toLowerCase();
        if (status === 'on_sale' || status === 'in_progress' || status === 'announced') {
            const label = status === 'in_progress' ? 'În desfășurare' : (status === 'on_sale' ? 'Vânzare activă' : 'Anunțat');
            badges.push('<span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-widest text-white/70 font-medium bg-white/10 backdrop-blur px-2.5 py-1 rounded-full border border-white/10"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full pulse-dot"></span>' + label + '</span>');
        }
        if (tour.age_min) {
            badges.push('<span class="inline-flex items-center gap-1 bg-amber-500/90 text-white text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full">' + esc(tour.age_min) + '</span>');
        }
        $('tourBadges').innerHTML = badges.join('');

        // Artist link in toolbar + chip
        if (artist?.name) {
            const link = $('tourArtistLink');
            link.href = '/artist/' + (artist.slug || '');
            $('tourArtistLinkText').textContent = artist.name;
            show(link, true);

            const chip = $('tourArtistChip');
            const chipLink = $('tourArtistChipLink');
            chipLink.href = '/artist/' + (artist.slug || '');
            $('tourArtistChipName').textContent = artist.name;
            const av = $('tourArtistChipAvatar');
            if (artist.image) {
                av.style.backgroundImage = 'url(' + artist.image + ')';
                av.style.backgroundSize = 'cover';
                av.style.backgroundPosition = 'center';
                av.textContent = '';
            } else {
                av.textContent = initials(artist.name);
            }
            show(chip, true);
        }

        // Short description
        if (tour.short_description) {
            $('tourShortDescription').textContent = tour.short_description;
            show($('tourShortDescription'), true);
        }

        // Share menu
        const shareUrl = window.location.href;
        const shareTitle = tour.name || 'Turneu';
        $('shareFb').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
        $('shareWa').href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(shareTitle + ' ' + shareUrl);
        $('shareBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            const m = $('shareMenu');
            m.hasAttribute('data-hidden') ? show(m, true) : show(m, false);
        });
        document.addEventListener('click', (e) => {
            const wrap = $('shareWrap');
            if (wrap && !wrap.contains(e.target)) show($('shareMenu'), false);
        });
        $('shareCopyBtn').addEventListener('click', () => {
            const finish = () => {
                $('shareCopyLabel').textContent = 'Link copiat!';
                setTimeout(() => { $('shareCopyLabel').textContent = 'Copiază link'; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareUrl).then(finish).catch(() => fallbackCopy(shareUrl, finish));
            } else {
                fallbackCopy(shareUrl, finish);
            }
        });
        function fallbackCopy(text, cb) {
            const ta = document.createElement('textarea'); ta.value = text;
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); cb(); } catch (e) {}
            document.body.removeChild(ta);
        }

        // META STRIP
        $('metaPeriod').textContent = shortPeriod(periodStart, periodEnd);
        if (periodStart && periodEnd) {
            const days = daysBetween(periodStart, periodEnd);
            if (days != null) $('metaPeriodDays').textContent = days === 0 ? '1 zi' : (days + 1) + ' zile';
        }

        const cities = aggregates.cities || [];
        $('metaCities').textContent = cities.length === 0 ? '—' : (cities.length + (cities.length === 1 ? ' oraș' : ' orașe'));
        $('metaCitiesList').textContent = cities.slice(0, 4).join(' · ');

        $('metaCapacity').textContent = (aggregates.total_capacity ?? 0) > 0 || aggregates.total_capacity === -1
            ? fmtCapacity(aggregates.total_capacity)
            : '—';
        if ((aggregates.total_sold ?? 0) > 0) {
            $('metaCapacityNote').textContent = fmtNumber(aggregates.total_sold) + ' vândute';
        }

        // Min price across events
        let minPrice = Infinity;
        events.forEach(e => {
            (e.ticket_types || []).forEach(t => {
                const p = Number(t.price);
                if (p > 0 && p < minPrice) minPrice = p;
            });
        });
        if (isFinite(minPrice)) {
            $('metaPrice').innerHTML = 'de la <span class="text-primary">' + minPrice.toFixed(0) + ' lei</span>';
        } else {
            $('metaPrice').textContent = '—';
        }

        // Setlist duration in meta
        if (tour.setlist_duration_minutes) {
            $('metaDuration').textContent = '~' + tour.setlist_duration_minutes + ' min';
        } else {
            $('metaDuration').textContent = '—';
        }

        // PROGRESS
        const totalEvents = events.length;
        const now = Date.now();
        const completedEvents = events.filter(e => e.starts_at && new Date(e.starts_at).getTime() < now).length;
        const upcomingEvents = events.filter(e => !e.starts_at || new Date(e.starts_at).getTime() >= now);
        $('progressLabel').textContent = '· ' + completedEvents + ' din ' + totalEvents + (totalEvents === 1 ? ' concert realizat' : ' concerte realizate');

        const nextEvent = upcomingEvents[0] || null;
        const tourHasStarted = completedEvents > 0;
        if (nextEvent) {
            const days = Math.ceil((new Date(nextEvent.starts_at).getTime() - now) / (1000 * 60 * 60 * 24));
            if (tourHasStarted) {
                $('progressTimeline').textContent = days <= 0
                    ? 'Următorul concert: astăzi'
                    : 'Următorul concert în ' + days + (days === 1 ? ' zi' : ' zile');
            } else {
                $('progressTimeline').textContent = days <= 0
                    ? 'Începe astăzi'
                    : 'Începe în ' + days + (days === 1 ? ' zi' : ' zile');
            }
        } else if (completedEvents === totalEvents && totalEvents > 0) {
            $('progressTimeline').textContent = 'Turneu finalizat';
        }

        let progressPct = 0;
        if (totalEvents > 0) progressPct = Math.round((completedEvents / totalEvents) * 100);
        $('progressBar').style.setProperty('--progress', progressPct + '%');

        if (periodStart) $('progressStart').textContent = new Date(periodStart).toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' });
        if (periodEnd) $('progressEnd').textContent = new Date(periodEnd).toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' });

        // DATES LIST — upcoming first; past events behind a collapsed toggle
        const list = $('datesList');
        if (events.length === 0) {
            list.innerHTML = '';
            show($('datesEmpty'), true);
        } else {
            const pastEvents = events.filter(e => e.starts_at && new Date(e.starts_at).getTime() < now);
            // Stop number assigned by global event order (oldest first) so an event's
            // "Stop N" matches its place in the original tour list, regardless of
            // whether it's past or upcoming.
            const stopNumberById = new Map();
            events.forEach((e, idx) => stopNumberById.set(e.id, idx + 1));

            const upcomingHtml = upcomingEvents.map(e => buildDateRow(e, stopNumberById.get(e.id), events.length, now, /*isPast*/ false)).join('');
            const pastHtml = pastEvents.map(e => buildDateRow(e, stopNumberById.get(e.id), events.length, now, /*isPast*/ true)).join('');

            const togglePastHtml = pastEvents.length > 0
                ? `
                    <button id="togglePastEvents" type="button" class="w-full flex items-center justify-between gap-3 px-6 py-3 text-sm font-semibold transition border-t bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-100">
                        <span class="flex items-center gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Evenimente încheiate · ${pastEvents.length}
                        </span>
                        <svg id="togglePastEventsChevron" class="transition-transform" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div id="pastEventsList" data-hidden>${pastHtml}</div>
                `
                : '';

            list.innerHTML = upcomingHtml + togglePastHtml;

            const toggle = $('togglePastEvents');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    const panel = $('pastEventsList');
                    const chev = $('togglePastEventsChevron');
                    const opening = panel.hasAttribute('data-hidden');
                    show(panel, opening);
                    chev.style.transform = opening ? 'rotate(180deg)' : 'rotate(0deg)';
                });
            }
        }

        // ABOUT (description + setlist)
        let hasAbout = false;
        if (tour.description) {
            $('tourDescription').innerHTML = tour.description;
            hasAbout = true;
        }
        if (Array.isArray(tour.setlist) && tour.setlist.length > 0) {
            const sorted = tour.setlist.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
            $('setlistGrid').innerHTML = sorted.map((s, i) => `
                <div class="flex items-center gap-2"><span class="font-mono text-xs text-primary">${String(i + 1).padStart(2, '0')}</span><span>${esc(s.title || '')}</span></div>
            `).join('');
            if (tour.setlist_duration_minutes) {
                $('setlistDuration').textContent = '~' + tour.setlist_duration_minutes + ' min';
                show($('setlistDuration'), true);
            }
            show($('setlistCard'), true);
            hasAbout = true;
        }
        show($('aboutSection'), hasAbout);

        // ARTISTS
        const artistsList = aggregates.artists || [];
        const headlinerId = artist?.id;
        const headlinerData = headlinerId ? artistsList.find(a => a.id === headlinerId) || artist : artist;
        const guestsList = artistsList.filter(a => !headlinerId || a.id !== headlinerId);

        if (headlinerData?.name) {
            const av = headlinerData.image ? `<div class="flex items-center justify-center flex-none w-16 h-16 bg-center bg-cover rounded-full ring-2 ring-primary" style="background-image:url(${esc(headlinerData.image)})"></div>` : `<div class="flex items-center justify-center flex-none w-16 h-16 font-bold text-white rounded-full bg-gradient-to-br from-slate-700 to-slate-900 ring-2 ring-primary">${esc(initials(headlinerData.name))}</div>`;
            $('headlinerCard').innerHTML = `
                <a href="/artist/${esc(headlinerData.slug || '')}" class="flex items-center gap-4 p-4 mb-3 transition border border-primary/20 bg-gradient-to-r from-primary/5 to-transparent rounded-xl group hover:border-primary">
                    ${av}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-primary bg-white px-2 py-0.5 rounded">Headliner</span>
                        </div>
                        <h3 class="font-bold text-slate-900">${esc(headlinerData.name)}</h3>
                    </div>
                    <svg class="transition text-slate-400 group-hover:text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            `;
            show($('headlinerCard'), true);
        }
        if (guestsList.length > 0) {
            $('guestsGrid').innerHTML = guestsList.map(g => {
                const av = g.image
                    ? `<div class="mb-2 bg-center bg-cover rounded-full aspect-square ring-2 ring-transparent group-hover:ring-primary" style="background-image:url(${esc(g.image)})"></div>`
                    : `<div class="flex items-center justify-center mb-2 text-xl font-bold text-white transition rounded-full aspect-square bg-gradient-to-br from-slate-700 to-slate-900 ring-2 ring-transparent group-hover:ring-primary">${esc(initials(g.name))}</div>`;
                return `
                    <a href="/artist/${esc(g.slug || '')}" class="p-3 text-center transition group rounded-xl hover:bg-slate-50">
                        ${av}
                        <h4 class="text-sm font-semibold truncate transition text-slate-900 group-hover:text-primary">${esc(g.name)}</h4>
                    </a>
                `;
            }).join('');
            show($('guestsWrap'), true);
        }
        show($('artistsSection'), !!headlinerData?.name || guestsList.length > 0);

        // FAQ
        if (Array.isArray(tour.faq) && tour.faq.length > 0) {
            $('faqList').innerHTML = tour.faq.map((item, i) => `
                <details class="overflow-hidden border border-slate-200 rounded-xl group">
                    <summary class="flex items-center justify-between w-full gap-3 p-4 text-left cursor-pointer hover:bg-slate-50">
                        <span class="text-sm font-semibold text-slate-900">${esc(item.question || '')}</span>
                        <svg class="flex-none transition text-slate-400 group-open:rotate-180" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="px-4 pt-3 pb-4 text-sm leading-relaxed whitespace-pre-line border-t text-slate-600 border-slate-100">${esc(item.answer || '')}</div>
                </details>
            `).join('');
            show($('faqSection'), true);
        }

        // SIDEBAR — Next date CTA
        if (nextEvent) {
            $('nextDateCity').textContent = nextEvent.venue?.city || nextEvent.venue?.name || nextEvent.name || '';
            const dateLabel = fmtDateLong(nextEvent.starts_at || nextEvent.event_date);
            const timeLabel = fmtTime(nextEvent.starts_at);
            const venueLabel = nextEvent.venue?.name || '';
            $('nextDateMeta').textContent = [dateLabel, timeLabel, venueLabel].filter(Boolean).join(' · ');
            const days = Math.ceil((new Date(nextEvent.starts_at).getTime() - now) / (1000 * 60 * 60 * 24));
            $('nextDateCountdownText').textContent = days <= 0 ? 'Începe astăzi' : 'Începe în ' + days + (days === 1 ? ' zi' : ' zile');
            const minP = Math.min.apply(null, (nextEvent.ticket_types || []).map(t => Number(t.price) || 0).filter(p => p > 0));
            $('nextDateCta').textContent = isFinite(minP) ? 'Cumpără bilet · de la ' + minP.toFixed(0) + ' lei →' : 'Vezi bilete →';
            $('nextDateCta').href = '/bilete/' + (nextEvent.slug || '');
            show($('nextDateCard'), true);
        }

        // SIDEBAR — Info
        const infoRows = [];
        if (organizer?.name) infoRows.push(['Organizator', organizer.name]);
        if (periodStart) infoRows.push(['Început turneu', fmtDateLong(periodStart)]);
        if (tour.age_min) infoRows.push(['Vârstă minimă', tour.age_min]);
        if (tour.setlist_duration_minutes) infoRows.push(['Durată concert', '~' + tour.setlist_duration_minutes + ' min']);
        infoRows.push(['Evenimente', fmtNumber(aggregates.total_events ?? 0)]);
        $('infoList').innerHTML = infoRows.map(([k, v]) => `
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <dt class="text-xs font-medium tracking-wide uppercase text-slate-500">${esc(k)}</dt>
                <dd class="text-sm font-medium text-right text-slate-900">${esc(v)}</dd>
            </div>
        `).join('');

        // SIDEBAR — Follow artist
        if (artist?.name) {
            const fa = $('followArtist');
            fa.href = '/artist/' + (artist.slug || '');
            $('followArtistName').textContent = artist.name;
            const av = $('followArtistAvatar');
            if (artist.image) {
                av.style.backgroundImage = 'url(' + artist.image + ')';
                av.style.backgroundSize = 'cover';
                av.style.backgroundPosition = 'center';
                av.textContent = '';
            } else {
                av.textContent = initials(artist.name);
            }
            show(fa, true);
        }

        // Mobile sticky
        if (events.length > 0) {
            $('mobileStickyMeta').textContent = events.length + (events.length === 1 ? ' concert' : ' concerte') + ' · începe ' + (periodStart ? new Date(periodStart).toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' }) : '');
            $('mobileStickyTitle').textContent = tour.name || '';
            if (nextEvent?.slug) $('mobileStickyCtaLink').href = '/bilete/' + nextEvent.slug;
            show($('mobileStickyCta'), true);
        }
    }

    function buildDateRow(e, stopNumber, total, now, isPast) {
        // stopNumber = 1-based position in the original tour list (oldest first)
        const isFirst = stopNumber === 1;
        const isFinal = stopNumber === total && total > 1;
        const dateObj = e.starts_at ? new Date(e.starts_at) : (e.event_date ? new Date(e.event_date) : null);
        const day = dateObj ? String(dateObj.getDate()).padStart(2, '0') : '—';
        const monthShort = dateObj ? ROMONTHS[dateObj.getMonth()].slice(0, 3) : '';
        const dayShort = dateObj ? RODAYS[dateObj.getDay()] : '';
        const time = fmtTime(e.starts_at);

        const ticketTypes = (e.ticket_types || []).filter(t => Number(t.price) > 0);
        const minPrice = ticketTypes.length ? Math.min.apply(null, ticketTypes.map(t => Number(t.price))) : 0;

        // Prefer the API-supplied available_capacity (shared pool remaining or total)
        // — same number admin's "Capacitate generală" badge shows.
        const availableCap = (e.available_capacity != null) ? Number(e.available_capacity) : null;
        const generalQuota = (e.general_quota != null) ? Number(e.general_quota) : null;

        let badge;
        if (isPast) {
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-medium">Încheiat</span>';
        } else if (availableCap === 0) {
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-rose-50 text-rose-700 px-2 py-0.5 rounded font-medium"><span class="w-1 h-1 rounded-full bg-rose-600"></span>Sold out</span>';
        } else if (availableCap !== null && availableCap > 0 && availableCap < 30) {
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-medium"><span class="w-1 h-1 rounded-full bg-amber-600"></span>Ultimele ' + availableCap + ' locuri</span>';
        } else {
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-medium"><span class="w-1 h-1 rounded-full bg-emerald-600"></span>Disponibil</span>';
        }

        const stopLabel = isFirst
            ? 'Stop 1 · Deschiderea turneului'
            : (isFinal ? 'Stop ' + stopNumber + ' · Finalul turneului 🎉' : 'Stop ' + stopNumber);
        const numberCircle = isPast
            ? '<div class="flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-full bg-slate-400">' + stopNumber + '</div>'
            : (isFirst
                ? '<div class="relative"><div class="flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-full bg-primary">' + stopNumber + '</div><span class="absolute inset-0 rounded-full bg-primary ping-slow"></span></div>'
                : '<div class="flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-full bg-slate-900">' + stopNumber + '</div>');

        const venueLine = e.venue ? esc(e.venue.city || '') + (e.venue.name ? ' · ' + esc(e.venue.name) : '') : esc(e.name || '');
        const bgClass = isFinal && !isPast ? ' bg-gradient-to-r from-rose-50/40 to-transparent' : '';
        const finalDot = isFinal && !isPast ? '<span class="absolute w-3 h-3 border-2 border-white rounded-full -top-1 -right-1 bg-amber-400" title="Finalul turneului"></span>' : '';

        // Capacity meta line: for active events show available, for past events show total quota
        const capMetaLine = (() => {
            if (isPast) {
                if (generalQuota && generalQuota > 0) return fmtNumber(generalQuota) + ' locuri';
                return '';
            }
            if (availableCap === null || availableCap < 0) return ''; // unlimited or unknown
            return fmtNumber(availableCap) + ' locuri disponibile';
        })();

        // Past events: no price column, no clickable link to the event
        const pastClasses = isPast ? ' opacity-70' : '';
        const wrapperOpen = isPast
            ? `<div class="grid items-center grid-cols-12 gap-4 p-4 border-l-4 border-transparent md:p-5${bgClass}${pastClasses}">`
            : `<a href="/bilete/${esc(e.slug || '')}" class="grid items-center grid-cols-12 gap-4 p-4 border-l-4 border-transparent date-card md:p-5${bgClass}">`;
        const wrapperClose = isPast ? '</div>' : '</a>';

        const priceColumn = isPast
            ? '' // past events: hide price + CTA per spec
            : `
                <div class="flex items-center justify-between col-span-12 gap-4 pt-3 border-t md:col-span-4 md:justify-end md:border-0 border-slate-100 md:pt-0">
                    ${minPrice > 0
                        ? '<div class="text-left md:text-right"><div class="text-[11px] text-slate-500">de la</div><div class="text-lg font-bold text-slate-900">' + minPrice.toFixed(0) + ' lei</div></div>'
                        : '<div class="text-left md:text-right"><div class="text-lg font-bold text-slate-900">Gratis</div></div>'}
                    <div class="buy-btn px-4 py-2.5 bg-slate-100 text-slate-900 rounded-lg text-sm font-semibold transition">Cumpără bilet</div>
                </div>
            `;

        const infoColSpan = isPast ? 'col-span-10' : 'col-span-10 md:col-span-6';

        return `
            ${wrapperOpen}
                <div class="flex items-center col-span-2 gap-3">
                    <div class="relative">${numberCircle}${finalDot}</div>
                    <div class="flex-none hidden overflow-hidden text-center bg-white border rounded-lg w-14 border-slate-200 md:block">
                        <div class="text-[9px] font-bold uppercase tracking-wider bg-primary text-white py-0.5">${esc(monthShort)}</div>
                        <div class="py-1 text-xl font-bold">${esc(day)}</div>
                        <div class="text-[9px] font-medium pb-1 text-slate-500">${esc(dayShort)}</div>
                    </div>
                </div>
                <div class="min-w-0 ${infoColSpan}">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-xs font-semibold tracking-wider uppercase text-primary">${esc(stopLabel)}</span>
                        ${badge}
                    </div>
                    <h3 class="mb-1 font-bold text-slate-900">${venueLine}</h3>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        ${time ? '<span class="flex items-center gap-1"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' + esc(time) + '</span>' : ''}
                        ${capMetaLine ? '<span class="flex items-center gap-1"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><circle cx="12" cy="12" r="10"/></svg>' + capMetaLine + '</span>' : ''}
                        ${e.name ? '<span class="flex items-center gap-1 text-slate-500"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>' + esc(e.name) + '</span>' : ''}
                    </div>
                </div>
                ${priceColumn}
            ${wrapperClose}
        `;
    }
})();
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';

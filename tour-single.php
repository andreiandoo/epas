<?php
/**
 * Tour Single Page - Ambilet Marketplace
 * Individual tour landing page with linked events.
 */
$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';

// Get tour slug from URL (rewrite passes ?slug=...)
$tourSlug = $_GET['slug'] ?? '';
if (empty($tourSlug)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/turnee/([a-z0-9-]+)#i', $uri, $matches)) {
        $tourSlug = $matches[1];
    }
}

$pageTitle = "Turneu — {$siteName}";
$pageDescription = "Descoperă turneul și concertele incluse pe {$siteName}.";
$bodyClass = 'page-tour-single';
$cssBundle = 'single';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="relative h-[560px] overflow-hidden mt-17 mobile:mt-0" id="tourHero">
    <div class="absolute inset-0 bg-gray-200 skeleton-hero animate-pulse"></div>
    <img id="tourHeroImage" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-black/30 to-black/80"></div>
    <div class="relative z-10 flex flex-col justify-end h-full px-6 pb-12 mx-auto max-w-7xl">
        <div id="tourBadges" class="flex flex-wrap gap-2 mb-3"></div>
        <h1 id="tourName" class="text-[56px] font-extrabold text-white mb-3 leading-tight mobile:text-3xl">
            <span class="inline-block rounded h-14 w-96 bg-white/20 animate-pulse"></span>
        </h1>
        <p id="tourArtist" class="text-lg text-white/80 mb-4"></p>
        <p id="tourShortDescription" class="text-base text-white/90 max-w-3xl mb-5 hidden"></p>
        <div id="tourMeta" class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/80"></div>
    </div>
</section>

<!-- BODY -->
<div class="max-w-7xl mx-auto px-4 lg:px-8 py-10">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">

            <!-- DESCRIPTION -->
            <section id="tourDescriptionSection" class="hidden bg-white rounded-2xl border border-border p-6">
                <h2 class="text-xl font-bold text-secondary mb-3">Despre turneu</h2>
                <div id="tourDescription" class="prose prose-sm max-w-none text-secondary"></div>
            </section>

            <!-- EVENTS LIST -->
            <section id="tourEventsSection" class="bg-white rounded-2xl border border-border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-secondary">Evenimentele turneului</h2>
                    <span id="tourEventsCount" class="text-sm text-muted"></span>
                </div>
                <div id="tourEventsList" class="space-y-3">
                    <!-- skeleton -->
                    <div class="animate-pulse h-24 bg-gray-100 rounded-xl"></div>
                    <div class="animate-pulse h-24 bg-gray-100 rounded-xl"></div>
                </div>
                <div id="tourEventsEmpty" class="hidden text-sm text-muted text-center py-6">
                    Nu sunt evenimente publicate momentan pentru acest turneu.
                </div>
            </section>

            <!-- SETLIST -->
            <section id="tourSetlistSection" class="hidden bg-white rounded-2xl border border-border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-secondary">Setlist</h2>
                    <span id="tourSetlistDuration" class="text-sm text-muted"></span>
                </div>
                <ol id="tourSetlist" class="list-decimal list-inside space-y-1 text-sm text-secondary"></ol>
            </section>

            <!-- FAQ -->
            <section id="tourFaqSection" class="hidden bg-white rounded-2xl border border-border p-6">
                <h2 class="text-xl font-bold text-secondary mb-4">Întrebări frecvente</h2>
                <div id="tourFaq" class="space-y-3"></div>
            </section>

        </div>

        <aside class="lg:col-span-1 space-y-6">
            <!-- POSTER -->
            <div id="tourPosterCard" class="bg-white rounded-2xl border border-border overflow-hidden hidden">
                <img id="tourPoster" src="" alt="" class="w-full h-auto object-cover">
            </div>

            <!-- AGGREGATES -->
            <div id="tourAggregatesCard" class="bg-white rounded-2xl border border-border p-5 hidden">
                <h3 class="font-bold text-secondary mb-3">Sumar turneu</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Evenimente</dt><dd id="aggEvents" class="font-semibold"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Capacitate totală</dt><dd id="aggCapacity" class="font-semibold"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Bilete vândute</dt><dd id="aggSold" class="font-semibold text-emerald-700"></dd></div>
                </dl>

                <div id="aggCitiesWrap" class="mt-4 hidden">
                    <div class="text-xs uppercase text-muted mb-1">Orașe</div>
                    <div id="aggCities" class="flex flex-wrap gap-1"></div>
                </div>

                <div id="aggArtistsWrap" class="mt-4 hidden">
                    <div class="text-xs uppercase text-muted mb-1">Artiști</div>
                    <div id="aggArtists" class="flex flex-wrap gap-1"></div>
                </div>
            </div>

            <!-- ORGANIZER -->
            <div id="tourOrganizerCard" class="bg-white rounded-2xl border border-border p-5 hidden">
                <h3 class="font-bold text-secondary mb-3">Organizator</h3>
                <div class="flex items-center gap-3">
                    <img id="tourOrganizerLogo" src="" alt="" class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                    <div id="tourOrganizerName" class="font-semibold text-secondary"></div>
                </div>
            </div>
        </aside>
    </div>
</div>

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
    const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' }) : '';
    const fmtNumber = (n) => Number(n || 0).toLocaleString('ro-RO');
    const fmtCapacity = (n) => Number(n) < 0 ? '∞' : fmtNumber(n);

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
        document.body.innerHTML = '<div class="min-h-screen flex items-center justify-center bg-slate-50"><div class="text-center"><h1 class="text-2xl font-bold text-secondary mb-2">Turneu negăsit</h1><p class="text-muted mb-6">Linkul pe care l-ai accesat nu mai e valabil.</p><a href="/" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold">Înapoi la pagina principală</a></div></div>';
    }

    function render(data) {
        const tour = data.tour;
        const artist = data.artist;
        const organizer = data.organizer;
        const aggregates = data.aggregates || {};
        const events = data.events || [];

        document.title = (tour.name || 'Turneu') + (window.SITE_NAME ? ' — ' + window.SITE_NAME : '');

        // HERO
        if (tour.cover_url) {
            const img = $('tourHeroImage');
            img.src = tour.cover_url;
            img.alt = tour.name || '';
            img.classList.remove('hidden');
            document.querySelector('#tourHero .skeleton-hero')?.classList.add('hidden');
        }
        $('tourName').textContent = tour.name || '';
        if (artist?.name) {
            $('tourArtist').textContent = artist.name;
        }
        if (tour.short_description) {
            $('tourShortDescription').textContent = tour.short_description;
            $('tourShortDescription').classList.remove('hidden');
        }

        // BADGES
        const badges = [];
        if (tour.type === 'turneu') badges.push('<span class="px-2.5 py-0.5 rounded-full bg-primary/90 text-white text-xs font-semibold">Turneu</span>');
        else if (tour.type === 'serie_evenimente') badges.push('<span class="px-2.5 py-0.5 rounded-full bg-white/20 text-white text-xs font-semibold">Serie evenimente</span>');
        if (tour.age_min) badges.push('<span class="px-2.5 py-0.5 rounded-full bg-amber-500/90 text-white text-xs font-semibold">' + esc(tour.age_min) + '</span>');
        $('tourBadges').innerHTML = badges.join(' ');

        // META
        const meta = [];
        if (tour.period?.start || tour.period?.end) {
            const start = tour.period.start ? fmtDate(tour.period.start) : '';
            const end = tour.period.end ? fmtDate(tour.period.end) : '';
            meta.push('<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' + (start === end || !end ? start : start + ' → ' + end) + '</span>');
        }
        if (Array.isArray(aggregates.cities) && aggregates.cities.length > 0) {
            meta.push('<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + aggregates.cities.length + ' orașe</span>');
        }
        meta.push('<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>' + (aggregates.total_events || 0) + ' evenimente</span>');
        $('tourMeta').innerHTML = meta.join('');

        // POSTER
        if (tour.poster_url) {
            $('tourPoster').src = tour.poster_url;
            $('tourPoster').alt = tour.name || '';
            $('tourPosterCard').classList.remove('hidden');
        }

        // DESCRIPTION
        if (tour.description) {
            $('tourDescription').innerHTML = tour.description; // RichEditor output is sanitized server-side
            $('tourDescriptionSection').classList.remove('hidden');
        }

        // SETLIST
        if (Array.isArray(tour.setlist) && tour.setlist.length > 0) {
            const sortedList = tour.setlist.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
            $('tourSetlist').innerHTML = sortedList.map(s => '<li>' + esc(s.title || '') + '</li>').join('');
            if (tour.setlist_duration_minutes) {
                $('tourSetlistDuration').textContent = '~' + tour.setlist_duration_minutes + ' minute';
            }
            $('tourSetlistSection').classList.remove('hidden');
        }

        // FAQ
        if (Array.isArray(tour.faq) && tour.faq.length > 0) {
            $('tourFaq').innerHTML = tour.faq.map(item => `
                <details class="rounded-xl border border-border p-4 bg-slate-50/50">
                    <summary class="font-semibold text-secondary cursor-pointer">${esc(item.question || '')}</summary>
                    <p class="mt-2 text-sm text-muted whitespace-pre-line">${esc(item.answer || '')}</p>
                </details>
            `).join('');
            $('tourFaqSection').classList.remove('hidden');
        }

        // EVENTS LIST
        const list = $('tourEventsList');
        const countEl = $('tourEventsCount');
        if (events.length === 0) {
            list.innerHTML = '';
            $('tourEventsEmpty').classList.remove('hidden');
            countEl.textContent = '';
        } else {
            countEl.textContent = events.length + (events.length === 1 ? ' eveniment' : ' evenimente');
            list.innerHTML = events.map(e => {
                const minPrice = e.ticket_types && e.ticket_types.length
                    ? Math.min.apply(null, e.ticket_types.map(t => Number(t.price) || 0).filter(p => p > 0))
                    : null;
                const priceLabel = (minPrice && isFinite(minPrice)) ? 'de la ' + minPrice.toFixed(2) + ' lei' : 'Gratis';
                const venueLine = e.venue ? esc(e.venue.name || '') + (e.venue.city ? ' · ' + esc(e.venue.city) : '') : '';
                const img = e.image
                    ? '<img src="' + e.image + '" alt="" class="w-24 h-24 object-cover rounded-lg flex-shrink-0">'
                    : '<div class="w-24 h-24 rounded-lg bg-slate-200 flex-shrink-0"></div>';
                return `
                    <a href="/bilete/${esc(e.slug || '')}" class="flex items-center gap-4 p-3 rounded-xl border border-border hover:border-primary/40 hover:bg-slate-50/60 transition">
                        ${img}
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-muted uppercase tracking-wide">${fmtDate(e.starts_at || e.event_date)}</p>
                            <h3 class="font-bold text-secondary mb-0.5 truncate">${esc(e.name || '')}</h3>
                            <p class="text-sm text-muted truncate">${venueLine}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="inline-block px-3 py-1.5 rounded-lg bg-primary text-white text-sm font-semibold">${priceLabel}</span>
                        </div>
                    </a>
                `;
            }).join('');
        }

        // AGGREGATES
        if ((aggregates.total_events ?? 0) > 0) {
            $('aggEvents').textContent = fmtNumber(aggregates.total_events);
            $('aggCapacity').textContent = fmtCapacity(aggregates.total_capacity);
            $('aggSold').textContent = fmtNumber(aggregates.total_sold);
            if (Array.isArray(aggregates.cities) && aggregates.cities.length > 0) {
                $('aggCities').innerHTML = aggregates.cities.map(c => '<span class="px-2 py-0.5 rounded text-xs bg-blue-50 text-blue-700">' + esc(c) + '</span>').join('');
                $('aggCitiesWrap').classList.remove('hidden');
            }
            if (Array.isArray(aggregates.artists) && aggregates.artists.length > 0) {
                $('aggArtists').innerHTML = aggregates.artists.map(a => '<span class="px-2 py-0.5 rounded text-xs bg-purple-50 text-purple-700">' + esc(a.name || '') + '</span>').join('');
                $('aggArtistsWrap').classList.remove('hidden');
            }
            $('tourAggregatesCard').classList.remove('hidden');
        }

        // ORGANIZER
        if (organizer) {
            $('tourOrganizerName').textContent = organizer.name || '';
            if (organizer.logo) {
                $('tourOrganizerLogo').src = organizer.logo;
            }
            $('tourOrganizerCard').classList.remove('hidden');
        }
    }
})();
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';

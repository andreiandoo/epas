<?php
/**
 * Venue Owner — /venue/evenimente
 * Premium list of hosted events. Posters, stats, hover-lift.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Evenimente găzduite';
$venuePageTitle  = 'Evenimente găzduite';
$bodyClass       = 'min-h-screen flex bg-slate-50';
$currentPage     = 'venue_evenimente';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8 space-y-6">
            <!-- Hero -->
            <section class="relative overflow-hidden rounded-3xl shadow-xl" style="background:linear-gradient(135deg, #7c3aed 0%, #ec4899 100%);">
                <div class="absolute top-0 right-0 w-96 h-96 rounded-full opacity-20" style="background:radial-gradient(circle, #f9a8d4 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                <div class="relative p-8 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-2xl lg:text-3xl font-black text-white">Evenimente găzduite</h2>
                        <p class="mt-1 text-sm text-white/70">Toate evenimentele care se desfășoară la locațiile tale</p>
                    </div>
                    <div id="hero-stats" class="flex items-center gap-6 text-white">
                        <div class="text-right">
                            <p id="hero-total" class="text-4xl font-black">—</p>
                            <p class="text-xs uppercase tracking-wider text-white/60">Total</p>
                        </div>
                        <div class="w-px h-12 bg-white/20"></div>
                        <div class="text-right">
                            <p id="hero-upcoming" class="text-4xl font-black">—</p>
                            <p class="text-xs uppercase tracking-wider text-white/60">Viitoare</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Filter chips -->
            <div class="flex flex-wrap gap-2">
                <button data-filter="all" class="venue-filter-chip active">Toate</button>
                <button data-filter="upcoming" class="venue-filter-chip">Viitoare</button>
                <button data-filter="past" class="venue-filter-chip">Trecute</button>
                <button data-filter="cancelled" class="venue-filter-chip">Anulate</button>
                <button data-filter="postponed" class="venue-filter-chip">Amânate</button>
            </div>

            <!-- Events list -->
            <div id="events-list" class="space-y-4">
                <!-- Skeleton loader -->
                <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="flex gap-4 p-4 bg-white border rounded-2xl border-slate-200 animate-pulse">
                    <div class="w-24 h-24 rounded-xl bg-slate-200 flex-shrink-0"></div>
                    <div class="flex-1 space-y-3">
                        <div class="h-4 w-3/4 bg-slate-200 rounded"></div>
                        <div class="h-3 w-1/2 bg-slate-100 rounded"></div>
                        <div class="h-3 w-1/3 bg-slate-100 rounded"></div>
                    </div>
                    <div class="hidden md:block w-24 space-y-2">
                        <div class="h-6 bg-slate-200 rounded"></div>
                        <div class="h-2 bg-slate-100 rounded"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </main>
    </div>

<style>
    .venue-filter-chip { background:#fff; border:1px solid #e2e8f0; color:#475569; padding:.5rem 1rem; font-size:.875rem; font-weight:600; border-radius:9999px; transition:all .15s; cursor:pointer; }
    .venue-filter-chip:hover { background:#f1f5f9; }
    .venue-filter-chip.active { background:linear-gradient(135deg, #7c3aed, #ec4899); border-color:transparent; color:#fff; box-shadow:0 4px 12px rgba(124,58,237,0.3); }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    const list = document.getElementById('events-list');
    let allEvents = [];
    let activeFilter = 'all';

    function fmtDate(d) {
        if (!d) return '—';
        try {
            const dt = new Date(d);
            return dt.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) { return d; }
    }

    function fmtInt(n) { return Number(n || 0).toLocaleString('ro-RO'); }

    function eventDate(ev) { return ev.start_date || null; }

    function statusOf(ev) {
        if (ev.is_cancelled) return 'cancelled';
        if (ev.is_postponed) return 'postponed';
        const d = eventDate(ev);
        if (!d) return 'unknown';
        return d >= new Date().toISOString().slice(0, 10) ? 'upcoming' : 'ended';
    }

    function statusBadge(status) {
        const map = {
            upcoming:  { text: 'Viitor',   class: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
            ended:     { text: 'Trecut',   class: 'bg-slate-100 text-slate-500 border-slate-200' },
            cancelled: { text: 'Anulat',   class: 'bg-red-50 text-red-600 border-red-200' },
            postponed: { text: 'Amânat',   class: 'bg-amber-50 text-amber-700 border-amber-200' },
            unknown:   { text: 'Necunoscut', class: 'bg-slate-50 text-slate-500 border-slate-200' },
        };
        const m = map[status] || map.unknown;
        return `<span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider border rounded-full ${m.class}">${m.text}</span>`;
    }

    function dayMonth(d) {
        if (!d) return { day: '?', month: '', year: '' };
        try {
            const dt = new Date(d);
            return {
                day: dt.getDate(),
                month: dt.toLocaleDateString('ro-RO', { month: 'short' }),
                year: dt.getFullYear(),
                weekday: dt.toLocaleDateString('ro-RO', { weekday: 'short' }),
            };
        } catch (e) { return { day: '?', month: '', year: '' }; }
    }

    function resolvePoster(ev) {
        let url = ev.poster_url || ev.image || ev.featured_image || ev.homepage_featured_image || null;
        if (!url) return null;
        if (/^https?:\/\//i.test(url)) return url;
        const trimmed = url.replace(/^\/+/, '');
        return trimmed.startsWith('storage/')
            ? 'https://core.tixello.com/' + trimmed
            : 'https://core.tixello.com/storage/' + trimmed;
    }

    function render() {
        const sortedEvents = allEvents.slice().sort((a, b) => {
            const da = eventDate(a) || '';
            const db = eventDate(b) || '';
            return db.localeCompare(da);
        });

        const filtered = sortedEvents.filter(ev => {
            const st = statusOf(ev);
            if (activeFilter === 'upcoming')  return st === 'upcoming';
            if (activeFilter === 'past')      return st === 'ended';
            if (activeFilter === 'cancelled') return st === 'cancelled';
            if (activeFilter === 'postponed') return st === 'postponed';
            return true;
        });

        if (filtered.length === 0) {
            list.innerHTML = `
                <div class="p-16 text-center bg-white border rounded-2xl border-slate-200 shadow-sm">
                    <div class="inline-flex items-center justify-center w-20 h-20 mb-4 rounded-3xl" style="background:linear-gradient(135deg, rgba(124,58,237,0.1), rgba(236,72,153,0.1)); color:#7c3aed;">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="text-slate-500 text-lg font-semibold">Niciun eveniment pentru acest filtru</p>
                </div>
            `;
            return;
        }

        list.innerHTML = filtered.map(ev => {
            const title = ev.title || ev.name || '—';
            const st = statusOf(ev);
            const dm = dayMonth(eventDate(ev));
            const posterUrl = resolvePoster(ev);
            const organizerName = (ev.marketplace_organizer && ev.marketplace_organizer.name)
                || (ev.tenant && (ev.tenant.public_name || ev.tenant.name))
                || '—';
            const venueLabel = [ev.venue_name, ev.venue_city].filter(Boolean).join(' · ');
            const stats = ev.stats || {};
            const sold = stats.tickets_sold ?? ev.tickets_sold ?? 0;
            const cap = stats.stock_total ?? ev.capacity ?? 0;
            const pct = cap > 0 ? Math.min(100, Math.round(sold / cap * 100)) : 0;
            const ci = stats.checked_in_count ?? 0;
            const ciPct = sold > 0 ? Math.round(ci / sold * 100) : 0;
            const fillColor = pct >= 75 ? '#10b981' : pct >= 40 ? '#f59e0b' : '#94a3b8';

            // Compact poster: 18×18 (72px), just the image or a
            // date tile. Smaller than the previous 28-32 sizes and
            // without the gradient overlay, so the whole row stays
            // short even on desktop.
            const posterHtml = posterUrl
                ? `<div class="w-18 h-18 rounded-lg overflow-hidden flex-shrink-0" style="width:72px;height:72px;">
                       <img src="${posterUrl}" alt="" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\\'w-full h-full flex flex-col items-center justify-center text-white\\' style=\\'background:linear-gradient(135deg, #7c3aed, #ec4899);\\'><span class=\\'text-[9px] uppercase\\'>' + '${dm.month}' + '</span><span class=\\'text-xl font-black leading-none\\'>' + '${dm.day}' + '</span></div>';">
                   </div>`
                : `<div class="flex-shrink-0 rounded-lg flex flex-col items-center justify-center text-white" style="width:72px;height:72px;background:linear-gradient(135deg, #7c3aed, #ec4899);">
                       <p class="text-[9px] uppercase leading-none opacity-90">${dm.weekday || ''}</p>
                       <p class="text-xl font-black leading-none mt-0.5">${dm.day}</p>
                       <p class="text-[9px] uppercase font-semibold mt-0.5">${dm.month}</p>
                   </div>`;

            return `
                <a href="/venue/eveniment/${ev.id}" class="group block px-4 py-3 bg-white border rounded-xl border-slate-200 shadow-sm hover:shadow-md hover:border-purple-200 transition-all">
                    <div class="flex items-center gap-3 md:gap-4">
                        ${posterHtml}

                        <!-- Main info: title + venue + organizer -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                <h3 class="text-sm md:text-base font-bold text-slate-900 truncate group-hover:text-purple-700 transition-colors">${title}</h3>
                                ${statusBadge(st)}
                            </div>
                            <div class="flex items-center gap-3 text-xs text-slate-500 flex-wrap">
                                <span class="flex items-center gap-1 whitespace-nowrap">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    ${fmtDate(eventDate(ev))}
                                </span>
                                <span class="flex items-center gap-1 truncate">
                                    <svg class="w-3 h-3 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="truncate">${venueLabel || '—'}</span>
                                </span>
                                <span class="flex items-center gap-1 truncate hidden md:flex">
                                    <svg class="w-3 h-3 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span class="truncate">${organizerName}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Bilete: numărul + bar de fill -->
                        <div class="hidden sm:block flex-shrink-0 text-right" style="min-width:120px;">
                            <div class="flex items-baseline justify-end gap-1">
                                <span class="text-lg font-black text-slate-900">${fmtInt(sold)}</span>
                                ${cap > 0 ? `<span class="text-[11px] text-slate-400 font-mono">/${fmtInt(cap)}</span>` : ''}
                            </div>
                            <p class="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">Bilete${ciPct > 0 ? ` · ${ciPct}% CI` : ''}</p>
                            ${cap > 0 ? `
                                <div class="mt-1 h-1 bg-slate-100 rounded-full overflow-hidden ml-auto" style="width:100px;">
                                    <div class="h-full rounded-full transition-all" style="width:${pct}%; background:${fillColor};"></div>
                                </div>
                            ` : ''}
                        </div>

                        <!-- Chevron CTA -->
                        <div class="flex-shrink-0 flex items-center justify-center text-purple-600 group-hover:translate-x-0.5 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </a>
            `;
        }).join('');
    }

    // Filter chip clicks
    document.querySelectorAll('.venue-filter-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.venue-filter-chip').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.getAttribute('data-filter');
            render();
        });
    });

    // Load
    try {
        const params = new URLSearchParams(window.location.search);
        const venueId = params.get('venue_id');
        const filters = venueId ? { venue_id: venueId } : { scope: 'all' };

        const res = await AmbiletVenueAPI.events(filters);
        if (res && res.success && res.data && Array.isArray(res.data.events)) {
            allEvents = res.data.events;
        } else if (res && Array.isArray(res.data)) {
            allEvents = res.data;
        } else {
            allEvents = [];
        }

        const upcomingCount = allEvents.filter(e => statusOf(e) === 'upcoming').length;
        document.getElementById('hero-total').textContent = fmtInt(allEvents.length);
        document.getElementById('hero-upcoming').textContent = fmtInt(upcomingCount);

        render();
    } catch (e) {
        console.error('events load failed', e);
        list.innerHTML = '<div class="p-6 text-center text-sm text-red-400 bg-white border rounded-2xl border-slate-200">Eroare la încărcare.</div>';
    }
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

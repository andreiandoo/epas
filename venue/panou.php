<?php
/**
 * Venue Owner Dashboard — /venue/panou
 * Live KPIs + top venue + upcoming events.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Panou Locație';
$venuePageTitle  = 'Panou';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_panou';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Welcome Banner -->
            <div class="relative p-6 overflow-hidden text-white rounded-2xl" style="background:linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
                <div class="relative">
                    <h1 class="mb-2 text-2xl font-bold md:text-3xl">Bun venit! 🏛️</h1>
                    <p class="text-white/80" id="welcome-line">Gestionează evenimentele găzduite la locațiile tale.</p>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-2 gap-4 mt-6 lg:grid-cols-4">
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center justify-center w-9 h-9 rounded-lg" style="background:rgba(59,130,246,0.1); color:#3b82f6;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                    </div>
                    <p id="kpi-venues" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Locații</p>
                </div>
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center justify-center w-9 h-9 rounded-lg" style="background:rgba(16,185,129,0.1); color:#10b981;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </div>
                    <p id="kpi-events" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Evenimente totale</p>
                </div>
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center justify-center w-9 h-9 rounded-lg" style="background:rgba(245,158,11,0.1); color:#f59e0b;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                    </div>
                    <p id="kpi-tickets" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Bilete vândute</p>
                </div>
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center justify-center w-9 h-9 rounded-lg" style="background:rgba(239,68,68,0.1); color:#ef4444;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <p id="kpi-upcoming" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">În următoarele 30 zile</p>
                </div>
            </div>

            <!-- Upcoming events + venue quick stats -->
            <div class="grid gap-6 mt-8 lg:grid-cols-3">
                <!-- Upcoming -->
                <div class="p-6 bg-white border rounded-2xl border-slate-200 lg:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-slate-900">Evenimente ce urmează</h2>
                        <a href="/venue/evenimente" class="text-sm font-medium" style="color:#3b82f6;">Vezi toate →</a>
                    </div>
                    <div id="upcoming-list" class="space-y-3">
                        <div class="p-4 text-center text-sm text-slate-400">Se încarcă…</div>
                    </div>
                </div>

                <!-- Venues quick -->
                <div class="p-6 bg-white border rounded-2xl border-slate-200">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-slate-900">Locațiile tale</h2>
                        <a href="/venue/locatii" class="text-sm font-medium" style="color:#3b82f6;">Toate →</a>
                    </div>
                    <div id="venues-quick" class="space-y-3">
                        <div class="p-4 text-center text-sm text-slate-400">Se încarcă…</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<script>
(async function () {
    // Wait for AmbiletVenueAPI to be available (loaded from api.js).
    if (typeof AmbiletVenueAPI === 'undefined') return;

    function fmtDate(d) {
        if (!d) return '—';
        try {
            const dt = new Date(d);
            return dt.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) { return d; }
    }

    function fmtInt(n) {
        return Number(n || 0).toLocaleString('ro-RO');
    }

    // ── Venues + KPIs (top 4 numbers) ────────────────────────
    try {
        const vres = await AmbiletVenueAPI.venues();
        if (vres && vres.success && vres.data && Array.isArray(vres.data.venues)) {
            const venues = vres.data.venues;
            const totalEvents = venues.reduce((s, v) => s + (v.total_events || 0), 0);
            const totalSold = venues.reduce((s, v) => s + (v.total_sold || 0), 0);
            const totalUpcoming = venues.reduce((s, v) => s + (v.upcoming_events || 0), 0);

            document.getElementById('kpi-venues').textContent = fmtInt(venues.length);
            document.getElementById('kpi-events').textContent = fmtInt(totalEvents);
            document.getElementById('kpi-tickets').textContent = fmtInt(totalSold);
            document.getElementById('kpi-upcoming').textContent = fmtInt(totalUpcoming);

            // Venue quick-stats sidebar
            const vhtml = venues.slice(0, 5).map(v => `
                <a href="/venue/analiza?venue_id=${v.id}" class="block p-3 transition-colors border rounded-xl border-slate-100 hover:border-blue-200 hover:bg-blue-50/40">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm text-slate-900 truncate">${v.name}</p>
                            <p class="text-xs text-slate-500 mt-0.5">${v.city || ''}</p>
                        </div>
                        <div class="text-right text-xs">
                            <p class="font-bold text-slate-900">${fmtInt(v.upcoming_events)}</p>
                            <p class="text-slate-500">viitoare</p>
                        </div>
                    </div>
                </a>
            `).join('') || '<p class="text-sm text-slate-400 text-center py-4">Niciun venue</p>';
            document.getElementById('venues-quick').innerHTML = vhtml;

            // Welcome line
            document.getElementById('welcome-line').textContent =
                `Ai ${venues.length} ${venues.length === 1 ? 'locație' : 'locații'} cu ${totalUpcoming} ${totalUpcoming === 1 ? 'eveniment' : 'evenimente'} programate.`;
        }
    } catch (e) {
        console.error('venues failed', e);
    }

    // ── Upcoming events (from /venue-owner/analytics/upcoming) ─────
    try {
        const ures = await AmbiletVenueAPI.analytics('upcoming');
        const list = document.getElementById('upcoming-list');
        if (ures && ures.success && ures.data && Array.isArray(ures.data.upcoming) && ures.data.upcoming.length) {
            list.innerHTML = ures.data.upcoming.slice(0, 8).map(ev => `
                <div class="flex items-center gap-4 p-3 border rounded-xl border-slate-100 hover:border-blue-200 transition-colors">
                    <div class="flex flex-col items-center justify-center w-14 h-14 rounded-lg text-white flex-shrink-0" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">
                        <span class="text-xs font-medium">${fmtDate(ev.event_date).split(' ')[1] || ''}</span>
                        <span class="text-lg font-bold leading-none">${fmtDate(ev.event_date).split(' ')[0] || '?'}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-slate-900 truncate">${(ev.title && (ev.title.ro || ev.title.en)) || ev.name || '—'}</p>
                        <p class="text-xs text-slate-500 mt-0.5">${ev.venue_name || ''} ${ev.city ? '· ' + ev.city : ''}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold text-slate-900">${fmtInt(ev.tickets_sold)}/${fmtInt(ev.capacity)}</p>
                        <p class="text-xs text-slate-500">bilete</p>
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<div class="p-6 text-center text-sm text-slate-400">Niciun eveniment viitor</div>';
        }
    } catch (e) {
        console.error('upcoming failed', e);
        document.getElementById('upcoming-list').innerHTML = '<div class="p-6 text-center text-sm text-red-400">Eroare la încărcare</div>';
    }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

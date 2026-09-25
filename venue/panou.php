<?php
/**
 * Venue Owner Dashboard — /venue/panou
 * Premium redesign with hero gradient, animated stat cards, glass-morph
 * accents, and rich upcoming/venues panels.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Panou Locație';
$venuePageTitle  = 'Panou';
$bodyClass       = 'min-h-screen flex bg-slate-50';
$currentPage     = 'venue_panou';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8 space-y-6">
            <!-- Hero -->
            <section class="relative overflow-hidden rounded-3xl shadow-xl" style="background:linear-gradient(135deg, #0f172a 0%, #1e40af 50%, #3b82f6 100%);">
                <!-- Decorative blobs -->
                <div class="absolute top-0 right-0 w-96 h-96 rounded-full opacity-20" style="background:radial-gradient(circle, #60a5fa 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                <div class="absolute bottom-0 left-1/2 w-80 h-80 rounded-full opacity-15" style="background:radial-gradient(circle, #a855f7 0%, transparent 70%); transform:translate(-50%, 50%);"></div>
                <div class="absolute inset-0 opacity-10" style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'40\' height=\'40\' viewBox=\'0 0 40 40\'><path fill=\'%23ffffff\' d=\'M0 0h40v1H0zM0 39h40v1H0zM0 0h1v40H0zM39 0h1v40h-1z\'/></svg>');"></div>

                <div class="relative p-8 lg:p-10">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="max-w-2xl">
                            <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-semibold text-white rounded-full backdrop-blur" style="background:rgba(255,255,255,0.12);">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Panou live
                            </div>
                            <h1 class="text-3xl lg:text-4xl font-extrabold text-white leading-tight">Bun venit înapoi</h1>
                            <p id="hero-tagline" class="mt-3 text-base text-white/70 max-w-lg">Gestionează evenimentele găzduite la locațiile tale și urmărește performanța în timp real.</p>
                        </div>
                        <div id="hero-highlight" class="text-right">
                            <p class="text-xs uppercase tracking-wider text-white/60 mb-1">Venit total până acum</p>
                            <p id="hero-revenue" class="text-3xl lg:text-4xl font-black text-white">—</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Android app -->
            <section class="relative overflow-hidden rounded-2xl shadow-xl" style="background:linear-gradient(135deg, #052e16 0%, #166534 55%, #16a34a 100%);">
                <div class="absolute top-0 right-0 w-80 h-80 rounded-full opacity-20" style="background:radial-gradient(circle, #86efac 0%, transparent 70%); transform:translate(30%, -40%);"></div>
                <div class="relative p-5 lg:p-8">
                    <div class="flex items-center justify-between gap-6 flex-wrap">
                        <div class="flex items-start gap-4" style="max-width:680px;">
                            <div class="flex items-center justify-center w-11 h-11 rounded-xl" style="background:rgba(255,255,255,0.15); flex-shrink:0;">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider" style="color:#bbf7d0;">Aplicația AmBilet · Android și iPhone</p>
                                <h2 class="mt-1 font-extrabold text-white" style="font-size:22px; line-height:1.25;">Scanează biletele și vinde la intrare direct din telefon</h2>
                                <ol class="mt-3 text-sm" style="color:rgba(255,255,255,0.88); padding-left:18px; list-style:decimal; line-height:1.7;">
                                    <li>Deschide <strong class="text-white">ambilet.ro/android</strong> de pe telefonul Android și descarcă aplicația (fișier APK).</li>
                                    <li>Deschide fișierul descărcat. Dacă telefonul îți cere, permite instalarea din această sursă, apoi apasă Instalează.</li>
                                    <li>Intră în aplicație cu același email și aceeași parolă ca în acest panou.</li>
                                </ol>
                                <p class="mt-3 text-sm" style="color:rgba(255,255,255,0.88); line-height:1.6;"><strong class="text-white">Ai iPhone?</strong> Deschide <strong class="text-white">Aplicația Scan</strong> din Safari, cu contul în care ești logat acum, apoi atinge Distribuie → „Adaugă pe ecranul de start”. Scanezi și vinzi la intrare la fel ca din aplicația de Android.</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-start gap-2">
                            <a href="/android" class="inline-flex items-center gap-2 font-bold rounded-xl shadow-lg transition-all hover:-translate-y-0.5" style="background:#ffffff; color:#14532d; padding:14px 22px; font-size:15px;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Descarcă aplicația (APK)
                            </a>
                            <a href="/venue/scan/evenimente" class="inline-flex items-center gap-2 font-bold rounded-xl transition-all hover:-translate-y-0.5" style="background:rgba(255,255,255,0.15); color:#ffffff; border:1px solid rgba(255,255,255,0.45); padding:14px 22px; font-size:15px;">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M16.37 12.6c-.02-2.2 1.8-3.26 1.88-3.31-1.02-1.5-2.62-1.7-3.19-1.72-1.36-.14-2.65.8-3.34.8-.69 0-1.75-.78-2.88-.76-1.48.02-2.85.86-3.61 2.19-1.54 2.67-.39 6.62 1.11 8.79.73 1.06 1.6 2.25 2.74 2.21 1.1-.04 1.52-.71 2.85-.71 1.33 0 1.7.71 2.87.69 1.18-.02 1.93-1.08 2.66-2.14.84-1.23 1.18-2.42 1.2-2.48-.03-.01-2.3-.88-2.32-3.5zM14.18 6.13c.6-.73 1.01-1.75.9-2.76-.87.04-1.92.58-2.54 1.31-.56.64-1.05 1.67-.92 2.66.97.08 1.96-.49 2.56-1.21z"/></svg>
                                Aplicația Scan (iPhone)
                            </a>
                            <p class="text-xs" style="color:rgba(255,255,255,0.75);">APK pentru Android · Aplicația Scan din browser pentru iPhone</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- KPI cards -->
            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="group relative overflow-hidden p-5 bg-white border rounded-2xl border-slate-200 shadow-sm hover:shadow-lg transition-all hover:-translate-y-0.5">
                    <div class="absolute top-0 right-0 w-32 h-32 opacity-5 group-hover:opacity-10 transition-opacity" style="background:radial-gradient(circle, #3b82f6 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-11 h-11 rounded-xl" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                        </div>
                        <p id="kpi-venues" class="text-3xl font-black text-slate-900">—</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Locații</p>
                    </div>
                </div>

                <div class="group relative overflow-hidden p-5 bg-white border rounded-2xl border-slate-200 shadow-sm hover:shadow-lg transition-all hover:-translate-y-0.5">
                    <div class="absolute top-0 right-0 w-32 h-32 opacity-5 group-hover:opacity-10 transition-opacity" style="background:radial-gradient(circle, #10b981 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-11 h-11 rounded-xl" style="background:linear-gradient(135deg, #10b981, #047857);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <p id="kpi-events" class="text-3xl font-black text-slate-900">—</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Evenimente totale</p>
                    </div>
                </div>

                <div class="group relative overflow-hidden p-5 bg-white border rounded-2xl border-slate-200 shadow-sm hover:shadow-lg transition-all hover:-translate-y-0.5">
                    <div class="absolute top-0 right-0 w-32 h-32 opacity-5 group-hover:opacity-10 transition-opacity" style="background:radial-gradient(circle, #f59e0b 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-11 h-11 rounded-xl" style="background:linear-gradient(135deg, #f59e0b, #d97706);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                        </div>
                        <p id="kpi-tickets" class="text-3xl font-black text-slate-900">—</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Bilete vândute</p>
                    </div>
                </div>

                <div class="group relative overflow-hidden p-5 bg-white border rounded-2xl border-slate-200 shadow-sm hover:shadow-lg transition-all hover:-translate-y-0.5">
                    <div class="absolute top-0 right-0 w-32 h-32 opacity-5 group-hover:opacity-10 transition-opacity" style="background:radial-gradient(circle, #ec4899 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center justify-center w-11 h-11 rounded-xl" style="background:linear-gradient(135deg, #ec4899, #be185d);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <span class="text-xs font-bold text-pink-600 px-2 py-0.5 rounded-full" style="background:rgba(236,72,153,0.1);">NEXT 30d</span>
                        </div>
                        <p id="kpi-upcoming" class="text-3xl font-black text-slate-900">—</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">În curând</p>
                    </div>
                </div>
            </section>

            <!-- Upcoming + Venues quick stats -->
            <section class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 p-6 bg-white border rounded-2xl border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg text-white" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </span>
                                Evenimente ce urmează
                            </h2>
                            <p class="text-xs text-slate-500 mt-1">Următoarele evenimente găzduite la locațiile tale</p>
                        </div>
                        <a href="/venue/evenimente" class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors flex items-center gap-1">
                            Vezi toate
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                    <div id="upcoming-list" class="space-y-3">
                        <div class="p-4 text-center text-sm text-slate-400">Se încarcă…</div>
                    </div>
                </div>

                <div class="p-6 bg-white border rounded-2xl border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg text-white" style="background:linear-gradient(135deg, #10b981, #047857);">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </span>
                                Locații
                            </h2>
                            <p class="text-xs text-slate-500 mt-1">Statistici rapide</p>
                        </div>
                        <a href="/venue/locatii" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                            Toate →
                        </a>
                    </div>
                    <div id="venues-quick" class="space-y-2">
                        <div class="p-4 text-center text-sm text-slate-400">Se încarcă…</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    function fmtDate(d) {
        if (!d) return '—';
        try {
            const dt = new Date(d);
            return dt.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) { return d; }
    }
    function dayMonth(d) {
        if (!d) return { day: '?', month: '' };
        try {
            const dt = new Date(d);
            return { day: dt.getDate(), month: dt.toLocaleDateString('ro-RO', { month: 'short' }) };
        } catch (e) { return { day: '?', month: '' }; }
    }
    function fmtInt(n) { return Number(n || 0).toLocaleString('ro-RO'); }
    function fmtMoney(n) { return Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }

    // ── Venues + KPIs ─────────────────────────────────────────
    let totalUpcoming = 0;
    let venueList = [];
    try {
        const vres = await AmbiletVenueAPI.venues();
        if (vres && vres.success && vres.data && Array.isArray(vres.data.venues)) {
            venueList = vres.data.venues;
            const totalEvents = venueList.reduce((s, v) => s + (v.total_events || 0), 0);
            const totalSold = venueList.reduce((s, v) => s + (v.total_sold || 0), 0);
            totalUpcoming = venueList.reduce((s, v) => s + (v.upcoming_events || 0), 0);

            document.getElementById('kpi-venues').textContent = fmtInt(venueList.length);
            document.getElementById('kpi-events').textContent = fmtInt(totalEvents);
            document.getElementById('kpi-tickets').textContent = fmtInt(totalSold);
            document.getElementById('kpi-upcoming').textContent = fmtInt(totalUpcoming);

            // Venues quick — richer card style
            document.getElementById('venues-quick').innerHTML = venueList.slice(0, 5).map(v => `
                <a href="/venue/analiza?venue_id=${v.id}" class="block p-4 transition-all border rounded-xl border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/30 hover:shadow-sm group">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm text-slate-900 truncate group-hover:text-emerald-700">${v.name}</p>
                            <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                ${v.city || ''}
                            </p>
                        </div>
                        <div class="flex-shrink-0 flex items-center gap-3 text-xs">
                            <div class="text-center">
                                <p class="font-black text-slate-900">${fmtInt(v.upcoming_events)}</p>
                                <p class="text-slate-400 text-[10px]">viitoare</p>
                            </div>
                            <div class="text-center">
                                <p class="font-black text-emerald-600">${fmtInt(v.total_sold)}</p>
                                <p class="text-slate-400 text-[10px]">bilete</p>
                            </div>
                        </div>
                    </div>
                </a>
            `).join('') || '<p class="text-sm text-slate-400 text-center py-4">Niciun venue</p>';

            document.getElementById('hero-tagline').textContent =
                `Ai ${venueList.length} ${venueList.length === 1 ? 'locație' : 'locații'} · ${totalUpcoming} ${totalUpcoming === 1 ? 'eveniment' : 'evenimente'} programate · ${fmtInt(totalSold)} bilete emise.`;
        }
    } catch (e) { console.error('venues failed', e); }

    // ── Hero: revenue so far, over ALL events (past included) ──
    // Written every time — it used to be set only when there were upcoming
    // events, and summed only those, so "până acum" stayed at "—".
    try {
        const all = await AmbiletVenueAPI.events({ scope: 'all' });
        const evs = (all && all.data && Array.isArray(all.data.events)) ? all.data.events : [];
        const totalRevenue = evs.reduce((sum, e) => sum + Number((e.stats && e.stats.revenue) ?? e.revenue ?? 0), 0);
        document.getElementById('hero-revenue').textContent = fmtMoney(totalRevenue) + ' RON';
    } catch (e) {
        console.error('hero revenue failed', e);
        document.getElementById('hero-revenue').textContent = '0 RON';
    }

    // ── Upcoming events (rich cards) ──────────────────────────
    try {
        const events = await AmbiletVenueAPI.events({ scope: 'upcoming' });
        const list = document.getElementById('upcoming-list');
        const items = (events && events.data && Array.isArray(events.data.events)) ? events.data.events : [];
        if (items.length) {
            list.innerHTML = items.slice(0, 6).map(ev => {
                const title = ev.title || ev.name || '—';
                const dm = dayMonth(ev.start_date);
                const venue = ev.venue_name || (ev.venue && ev.venue.name) || '';
                const organizer = (ev.marketplace_organizer && ev.marketplace_organizer.name)
                    || (ev.tenant && (ev.tenant.public_name || ev.tenant.name))
                    || '—';
                const stats = ev.stats || {};
                const sold = stats.tickets_sold ?? ev.tickets_sold ?? 0;
                const cap = stats.stock_total ?? ev.capacity ?? 0;
                const pct = cap > 0 ? Math.min(100, Math.round(sold / cap * 100)) : 0;

                return `
                    <a href="#" class="block p-4 border rounded-xl border-slate-100 hover:border-blue-200 hover:bg-blue-50/30 hover:shadow-sm transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col items-center justify-center w-16 h-16 rounded-xl text-white flex-shrink-0 shadow-md" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">
                                <span class="text-[10px] font-medium uppercase tracking-wider text-white/80">${dm.month}</span>
                                <span class="text-xl font-black leading-none">${dm.day}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-slate-900 truncate group-hover:text-blue-700">${title}</p>
                                <p class="text-xs text-slate-500 mt-0.5">📍 ${venue} · 🎪 ${organizer}</p>
                                ${cap > 0 ? `
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full" style="width:${pct}%; background:linear-gradient(90deg, #3b82f6, #1e40af);"></div>
                                        </div>
                                        <span class="text-[10px] text-slate-500 font-mono">${sold}/${cap}</span>
                                    </div>
                                ` : `<p class="text-[10px] text-slate-400 mt-1">${fmtInt(sold)} bilete emise</p>`}
                            </div>
                        </div>
                    </a>
                `;
            }).join('');
        } else {
            list.innerHTML = '<div class="p-8 text-center text-sm text-slate-400"><div class="inline-flex items-center justify-center w-14 h-14 mb-3 rounded-full bg-slate-100"><svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div><p>Niciun eveniment programat</p></div>';
        }
    } catch (e) {
        console.error('upcoming failed', e);
        document.getElementById('upcoming-list').innerHTML = '<div class="p-6 text-center text-sm text-red-400">Eroare la încărcare</div>';
    }
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

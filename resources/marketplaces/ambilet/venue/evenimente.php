<?php
/**
 * Venue Owner — /venue/evenimente
 * Events happening at the tenant's partnered venues (own + hosted).
 * Uses /venue-owner/events which already exists and is the same
 * endpoint the AmBilet mobile app consumes for its VenueEventsScreen.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Evenimente găzduite';
$venuePageTitle  = 'Evenimente găzduite';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_evenimente';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Evenimente găzduite</h2>
                    <p class="mt-1 text-sm text-slate-500">Toate evenimentele care se desfășoară la locațiile tale.</p>
                </div>
            </div>

            <!-- Filter chips -->
            <div class="flex flex-wrap gap-2 mb-4">
                <button data-filter="all" class="venue-filter-chip active px-4 py-2 text-sm font-semibold rounded-full transition-colors">Toate</button>
                <button data-filter="upcoming" class="venue-filter-chip px-4 py-2 text-sm font-semibold rounded-full transition-colors">Viitoare</button>
                <button data-filter="past" class="venue-filter-chip px-4 py-2 text-sm font-semibold rounded-full transition-colors">Trecute</button>
            </div>

            <!-- Events list -->
            <div id="events-list" class="space-y-3">
                <div class="p-6 text-center text-sm text-slate-400 bg-white border rounded-2xl border-slate-200">
                    Se încarcă evenimentele…
                </div>
            </div>
        </main>
    </div>

<style>
    .venue-filter-chip { background:#fff; border:1px solid #e2e8f0; color:#475569; }
    .venue-filter-chip:hover { background:#f1f5f9; }
    .venue-filter-chip.active { background:#3b82f6; border-color:#3b82f6; color:#fff; }
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

    function eventDate(ev) {
        // Backend returns start_date (not event_date) as an ISO Y-m-d string.
        return ev.start_date || null;
    }

    function statusOf(ev) {
        // Backend returns a lifecycle status (published / draft / postponed /
        // cancelled) via derivedStatus. We layer time on top: an event that
        // is `published` and past its start_date is "ended", otherwise
        // "upcoming". Cancelled/postponed win over the time bucket so the
        // badges match what the customer sees.
        if (ev.is_cancelled) return 'cancelled';
        if (ev.is_postponed) return 'postponed';
        const d = eventDate(ev);
        if (!d) return 'unknown';
        return d >= new Date().toISOString().slice(0, 10) ? 'upcoming' : 'ended';
    }

    function statusBadge(status) {
        const map = {
            'upcoming':  { text: 'Viitor',    class: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
            'ended':     { text: 'Trecut',    class: 'bg-slate-50 text-slate-500 border-slate-200' },
            'cancelled': { text: 'Anulat',    class: 'bg-red-50 text-red-600 border-red-200' },
            'postponed': { text: 'Amânat',    class: 'bg-amber-50 text-amber-700 border-amber-200' },
            'unknown':   { text: 'Necunoscut', class: 'bg-slate-50 text-slate-500 border-slate-200' },
        };
        const m = map[status] || map.unknown;
        return `<span class="px-2 py-1 text-xs font-semibold border rounded-full ${m.class}">${m.text}</span>`;
    }

    // Extract day + short month from an ISO Y-m-d string. Manual instead of
    // relying on fmtDate.split(' ') because ro-RO locales can insert commas
    // or reorder tokens, which broke the "?" placeholder in day slot.
    function dayMonth(d) {
        if (!d) return { day: '?', month: '' };
        try {
            const dt = new Date(d);
            return {
                day: dt.getDate(),
                month: dt.toLocaleDateString('ro-RO', { month: 'short' }),
            };
        } catch (e) {
            return { day: '?', month: '' };
        }
    }

    function render() {
        const filtered = allEvents.filter(ev => {
            const st = statusOf(ev);
            if (activeFilter === 'upcoming') return st === 'upcoming';
            if (activeFilter === 'past')     return st === 'ended';
            return true;
        });

        if (filtered.length === 0) {
            list.innerHTML = '<div class="p-12 text-center text-sm text-slate-400 bg-white border rounded-2xl border-slate-200">Niciun eveniment pentru acest filtru.</div>';
            return;
        }

        list.innerHTML = filtered.map(ev => {
            const title = ev.title || ev.name || '—';
            const st = statusOf(ev);
            const dm = dayMonth(eventDate(ev));
            const posterUrl = ev.poster_url || ev.image || ev.featured_image || null;
            const organizerName = (ev.marketplace_organizer && ev.marketplace_organizer.name)
                || (ev.tenant && (ev.tenant.public_name || ev.tenant.name))
                || '—';
            const venueLabel = [ev.venue_name, ev.venue_city].filter(Boolean).join(' · ');
            const stats = ev.stats || {};
            const sold = stats.tickets_sold ?? ev.tickets_sold ?? 0;
            const cap  = stats.stock_total ?? ev.capacity ?? 0;

            const posterHtml = posterUrl
                ? `<img src="${posterUrl}" alt="" class="w-16 h-16 md:w-20 md:h-20 rounded-lg object-cover flex-shrink-0" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                   <div class="flex flex-col items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-lg text-white flex-shrink-0" style="display:none;background:linear-gradient(135deg, #3b82f6, #1e40af);"><span class="text-xs uppercase">${dm.month}</span><span class="text-lg font-bold leading-none">${dm.day}</span></div>`
                : `<div class="flex flex-col items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-lg text-white flex-shrink-0" style="background:linear-gradient(135deg, #3b82f6, #1e40af);"><span class="text-xs uppercase">${dm.month}</span><span class="text-lg font-bold leading-none">${dm.day}</span></div>`;

            return `
                <div class="flex flex-col gap-4 p-4 bg-white border rounded-xl border-slate-200 md:flex-row md:items-center">
                    ${posterHtml}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-slate-900 truncate">${title}</h3>
                            ${statusBadge(st)}
                        </div>
                        <p class="text-xs text-slate-500 mt-1">📅 ${fmtDate(eventDate(ev))}${venueLabel ? ' · 📍 ' + venueLabel : ''}</p>
                        <p class="text-xs text-slate-500 mt-0.5">Organizator: <span class="font-medium">${organizerName}</span></p>
                    </div>
                    <div class="text-right md:w-32">
                        <p class="text-lg font-bold text-slate-900">${fmtInt(sold)}${cap > 0 ? `<span class="text-xs text-slate-500 font-normal">/${fmtInt(cap)}</span>` : ''}</p>
                        <p class="text-xs text-slate-500">bilete emise</p>
                    </div>
                </div>
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
        // Optional ?venue_id= from URL to pre-scope.
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
        render();
    } catch (e) {
        console.error('events load failed', e);
        list.innerHTML = '<div class="p-6 text-center text-sm text-red-400 bg-white border rounded-2xl border-slate-200">Eroare la încărcare.</div>';
    }
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

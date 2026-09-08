<?php
/**
 * Venue Owner — /venue/locatii
 * Grid of venue cards with per-venue quick stats. Each card links
 * to /venue/analiza?venue_id={id} to open the detailed analytics.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Locațiile mele';
$venuePageTitle  = 'Locațiile mele';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_locatii';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Locațiile mele</h2>
                <p class="mt-1 text-sm text-slate-500">Locațiile partenere ale contului tău la marketplace-ul curent.</p>
            </div>

            <div id="venues-grid" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div class="p-6 text-center text-sm text-slate-400 bg-white border rounded-2xl border-slate-200 md:col-span-2 lg:col-span-3">
                    Se încarcă locațiile…
                </div>
            </div>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    const grid = document.getElementById('venues-grid');

    function fmtInt(n) { return Number(n || 0).toLocaleString('ro-RO'); }

    try {
        const res = await AmbiletVenueAPI.venues();
        if (!res || !res.success || !res.data || !Array.isArray(res.data.venues) || res.data.venues.length === 0) {
            grid.innerHTML = `
                <div class="p-12 text-center bg-white border rounded-2xl border-slate-200 md:col-span-2 lg:col-span-3">
                    <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-2xl" style="background:rgba(59,130,246,0.1); color:#3b82f6;">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900">Nicio locație parteneră</h3>
                    <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                        Locațiile tale nu sunt încă asociate cu acest marketplace. Contactează administratorul pentru configurare.
                    </p>
                </div>
            `;
            return;
        }

        grid.innerHTML = res.data.venues.map(v => `
            <div class="p-6 bg-white border rounded-2xl border-slate-200 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl text-white" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 21V10a1 1 0 011-1h4a1 1 0 011 1v11m0 0h4V6a1 1 0 011-1h4a1 1 0 011 1v15M4 21h16"/></svg>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full" style="background:rgba(16,185,129,0.1); color:#059669;">Activă</span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 truncate">${v.name}</h3>
                <p class="text-sm text-slate-500 mt-1">${v.city || ''} ${v.state ? '· ' + v.state : ''}</p>

                <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-slate-100">
                    <div>
                        <p class="text-xs text-slate-500">Capacitate</p>
                        <p class="text-base font-bold text-slate-900">${fmtInt(v.capacity)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Evenimente</p>
                        <p class="text-base font-bold text-slate-900">${fmtInt(v.total_events)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Viitoare</p>
                        <p class="text-base font-bold" style="color:#3b82f6;">${fmtInt(v.upcoming_events)}</p>
                    </div>
                </div>

                <div class="flex gap-2 mt-5">
                    <a href="/venue/analiza?venue_id=${v.id}"
                       class="flex-1 px-3 py-2 text-sm font-semibold text-center text-white rounded-lg transition-colors"
                       style="background:#3b82f6;">
                        Analiză
                    </a>
                    <a href="/venue/evenimente?venue_id=${v.id}"
                       class="flex-1 px-3 py-2 text-sm font-semibold text-center rounded-lg transition-colors border border-slate-200 text-slate-700 hover:bg-slate-50">
                        Evenimente
                    </a>
                </div>
            </div>
        `).join('');
    } catch (e) {
        console.error('venues load failed', e);
        grid.innerHTML = `<div class="p-6 text-center text-sm text-red-400 bg-white border rounded-2xl border-slate-200 md:col-span-2 lg:col-span-3">Eroare la încărcarea locațiilor.</div>`;
    }
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

<?php
/**
 * Venue Owner — /venue/locatii
 * Premium venue cards with rich stats + CTAs.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Locațiile mele';
$venuePageTitle  = 'Locațiile mele';
$bodyClass       = 'min-h-screen flex bg-slate-50';
$currentPage     = 'venue_locatii';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8 space-y-6">
            <!-- Header -->
            <section class="relative overflow-hidden rounded-3xl shadow-xl" style="background:linear-gradient(135deg, #047857 0%, #10b981 100%);">
                <div class="absolute top-0 right-0 w-80 h-80 rounded-full opacity-15" style="background:radial-gradient(circle, #6ee7b7 0%, transparent 70%); transform:translate(30%, -30%);"></div>
                <div class="relative p-8 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-2xl lg:text-3xl font-black text-white">Locațiile mele</h2>
                        <p class="mt-1 text-sm text-white/70">Locațiile partenere ale contului tău la marketplace-ul curent</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wider text-white/60">Total locații</p>
                        <p id="hero-count" class="text-4xl font-black text-white">—</p>
                    </div>
                </div>
            </section>

            <!-- Grid -->
            <div id="venues-grid" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="p-6 text-center text-sm text-slate-400 bg-white border rounded-2xl border-slate-200 md:col-span-2 xl:col-span-3">
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
            document.getElementById('hero-count').textContent = '0';
            grid.innerHTML = `
                <div class="p-12 text-center bg-white border rounded-2xl border-slate-200 md:col-span-2 xl:col-span-3 shadow-sm">
                    <div class="inline-flex items-center justify-center w-20 h-20 mb-4 rounded-3xl" style="background:linear-gradient(135deg, rgba(59,130,246,0.1), rgba(30,64,175,0.1)); color:#3b82f6;">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Nicio locație parteneră</h3>
                    <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">Locațiile tale nu sunt încă asociate cu acest marketplace. Contactează administratorul pentru configurare.</p>
                </div>
            `;
            return;
        }

        document.getElementById('hero-count').textContent = fmtInt(res.data.venues.length);

        grid.innerHTML = res.data.venues.map((v, idx) => {
            // Rotate accent gradients for visual variety across cards.
            const gradients = [
                'linear-gradient(135deg, #3b82f6, #1e40af)',
                'linear-gradient(135deg, #10b981, #047857)',
                'linear-gradient(135deg, #f59e0b, #d97706)',
                'linear-gradient(135deg, #ec4899, #be185d)',
                'linear-gradient(135deg, #8b5cf6, #6d28d9)',
                'linear-gradient(135deg, #06b6d4, #0e7490)',
            ];
            const g = gradients[idx % gradients.length];
            const fillRate = v.capacity > 0 ? Math.min(100, Math.round(v.total_sold / v.capacity * 100)) : 0;

            return `
                <div class="relative overflow-hidden p-6 bg-white border rounded-2xl border-slate-200 shadow-sm hover:shadow-xl transition-all hover:-translate-y-1 group">
                    <!-- Decorative accent -->
                    <div class="absolute top-0 right-0 w-40 h-40 opacity-5 group-hover:opacity-10 transition-opacity" style="background:${g}; transform:translate(30%, -30%); border-radius:50%;"></div>

                    <div class="relative">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl shadow-md text-white" style="background:${g};">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 21V10a1 1 0 011-1h4a1 1 0 011 1v11m0 0h4V6a1 1 0 011-1h4a1 1 0 011 1v15M4 21h16"/></svg>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full" style="background:rgba(16,185,129,0.1); color:#059669;">● Activă</span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 truncate">${v.name}</h3>
                        <p class="text-sm text-slate-500 mt-1 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            ${v.city || ''}${v.state && v.state !== v.city ? ' · ' + v.state : ''}
                        </p>

                        <!-- Stats grid -->
                        <div class="grid grid-cols-3 gap-3 mt-5 pt-5 border-t border-slate-100">
                            <div class="text-center">
                                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Capacitate</p>
                                <p class="text-lg font-black text-slate-900">${fmtInt(v.capacity)}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Total ev.</p>
                                <p class="text-lg font-black text-slate-900">${fmtInt(v.total_events)}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Viitoare</p>
                                <p class="text-lg font-black text-blue-600">${fmtInt(v.upcoming_events)}</p>
                            </div>
                        </div>

                        ${v.total_sold > 0 ? `
                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs uppercase tracking-wider text-slate-400">Bilete emise</p>
                                    <p class="text-sm font-bold text-slate-900">${fmtInt(v.total_sold)}</p>
                                </div>
                                ${v.capacity > 0 ? `
                                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" style="width:${fillRate}%; background:${g};"></div>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}

                        <div class="grid grid-cols-2 gap-2 mt-5">
                            <a href="/venue/analiza?venue_id=${v.id}"
                               class="flex items-center justify-center gap-1.5 px-3 py-2.5 text-sm font-semibold text-white rounded-lg transition-all hover:shadow-md" style="background:${g};">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10"/></svg>
                                Analiză
                            </a>
                            <a href="/venue/evenimente?venue_id=${v.id}"
                               class="flex items-center justify-center gap-1.5 px-3 py-2.5 text-sm font-semibold rounded-lg transition-all border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Evenimente
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (e) {
        console.error('venues load failed', e);
        grid.innerHTML = `<div class="p-6 text-center text-sm text-red-400 bg-white border rounded-2xl border-slate-200 md:col-span-2 xl:col-span-3">Eroare la încărcarea locațiilor.</div>`;
    }
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

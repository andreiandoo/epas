<?php
/**
 * Venue Owner — /venue/setari
 * Profile info (read-only) + password change stub. Real password
 * change requires an extra backend endpoint (Faza 5 wiring).
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Setări cont';
$venuePageTitle  = 'Setări';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_setari';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Setări cont</h2>
                <p class="mt-1 text-sm text-slate-500">Informațiile contului tău de venue owner.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Profile card -->
                <div class="p-6 bg-white border rounded-2xl border-slate-200">
                    <h3 class="mb-4 text-lg font-bold text-slate-900">Profil</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nume</dt>
                            <dd id="setari-name" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Email</dt>
                            <dd id="setari-email" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cont</dt>
                            <dd id="setari-tenant" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Locații asociate</dt>
                            <dd id="setari-venues" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                    </dl>
                </div>

                <!-- Info card -->
                <div class="p-6 border rounded-2xl" style="background:rgba(59,130,246,0.05); border-color:rgba(59,130,246,0.15);">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 flex-shrink-0 mt-1" style="color:#3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Modificări cont</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Pentru modificări legate de nume, email, resetare parolă sau adăugarea unei locații noi, contactează administratorul marketplace-ului.
                            </p>
                            <p class="mt-4 text-xs text-slate-500">
                                Autentificarea în aplicația AmBilet folosește aceleași credențiale ca și acest cont web.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<script>
(async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    try {
        const res = await AmbiletVenueAPI.me();
        if (!res || !res.success || !res.data) return;

        const u = res.data.venue_owner || res.data;
        const name  = u.name || u.first_name || u.email || '—';
        const email = u.email || '—';
        const tenant = u.tenant_name || u.tenant || '—';
        const venues = Array.isArray(u.venues) ? u.venues.length : (u.venue_count || 0);

        document.getElementById('setari-name').textContent = name;
        document.getElementById('setari-email').textContent = email;
        document.getElementById('setari-tenant').textContent = tenant;
        document.getElementById('setari-venues').textContent = venues + ' ' + (venues === 1 ? 'locație' : 'locații');
    } catch (e) {
        console.error('me load failed', e);
    }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

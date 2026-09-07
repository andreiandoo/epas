<?php
/**
 * Venue Owner — /venue/evenimente
 * Stub scaffold. Faza 4/5 wires data + UI.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Locație — Evenimente găzduite';
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
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Evenimente găzduite</h2>
                <p class="mt-1 text-sm text-slate-500">Se completează în etapele următoare.</p>
            </div>

            <div class="p-12 text-center bg-white border rounded-2xl border-slate-200">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-2xl" style="background:rgba(59,130,246,0.1); color:#3b82f6;">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900">În construcție</h3>
                <p class="mt-2 text-slate-500 max-w-md mx-auto">
                    Această pagină va fi disponibilă în curând.
                    Pentru scanare bilete și vânzare POS, folosește aplicația AmBilet mobilă.
                </p>
            </div>
        </main>
    </div>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

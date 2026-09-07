<?php
/**
 * Venue Owner Dashboard — /venue/panou
 * Stub scaffold. Real KPI + upcoming-events wiring lands in Faza 4.
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
                <div class="relative">
                    <h1 class="mb-2 text-2xl font-bold md:text-3xl">Bun venit! 🏛️</h1>
                    <p class="text-white/80">Gestionează evenimentele găzduite la locațiile tale.</p>
                </div>
            </div>

            <!-- KPI placeholders — populated by Faza 4 wiring -->
            <div class="grid grid-cols-2 gap-4 mt-8 lg:grid-cols-4">
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <p id="venue-kpi-events" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Evenimente găzduite</p>
                </div>
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <p id="venue-kpi-tickets" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Bilete emise</p>
                </div>
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <p id="venue-kpi-occupancy" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Ocupare medie</p>
                </div>
                <div class="p-5 bg-white border rounded-2xl border-slate-200">
                    <p id="venue-kpi-upcoming" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="mt-1 text-sm text-slate-500">Următoarele 30 zile</p>
                </div>
            </div>

            <div class="mt-8 p-6 text-center bg-white border rounded-2xl border-slate-200">
                <p class="text-slate-500">Datele complete se vor afișa aici după activarea completă.</p>
            </div>
        </main>
    </div>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

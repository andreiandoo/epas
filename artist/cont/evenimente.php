<?php
/**
 * Artist Account — Events list
 * Tabbed list (Upcoming | Past) of events the artist is associated with.
 * Backed by GET /api/marketplace-client/artist/events?filter=...
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Evenimente';
$bodyClass = 'min-h-screen bg-surface';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<div class="flex min-h-screen">
    <?php require __DIR__ . '/_partials/sidebar.php'; ?>
    <div class="flex flex-col flex-1 min-w-0">
        <?php require __DIR__ . '/_partials/header.php'; ?>

        <main class="flex-1 p-6 lg:p-10">
            <div class="max-w-5xl mx-auto">
                <h1 class="mb-2 text-3xl font-bold text-secondary">Evenimente</h1>
                <p class="mb-6 text-muted">Toate evenimentele la care ești asociat ca artist.</p>

                <!-- Tabs -->
                <div class="flex gap-1 p-1 mb-6 bg-white border rounded-xl border-border w-fit">
                    <button data-filter="upcoming" class="filter-tab px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-primary text-white">
                        Viitoare
                    </button>
                    <button data-filter="past" class="filter-tab px-4 py-2 text-sm font-medium rounded-lg transition-colors text-secondary hover:bg-surface">
                        Trecute
                    </button>
                </div>

                <!-- Events container -->
                <div id="events-container" class="space-y-3">
                    <div class="p-12 text-center bg-white border rounded-2xl border-border">
                        <p class="text-muted">Se încarcă evenimentele…</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div id="events-pagination" class="hidden mt-6 flex items-center justify-between"></div>
            </div>
        </main>
    </div>
</div>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-evenimente.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>

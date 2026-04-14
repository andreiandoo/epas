<?php
$pageCacheTTL = 1800; // 30 minutes (static page)
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Fetch real stats from API
$statsEvents = 0;
$statsTickets = 0;
$statsOrganizers = 0;

// Events: all published (current + archived)
$eventsResp = api_get('/marketplace-events', ['per_page' => 1]);
if ($eventsResp['success'] && isset($eventsResp['meta']['total'])) {
    $statsEvents = (int) $eventsResp['meta']['total'];
}
$archivedResp = api_get('/marketplace-events', ['per_page' => 1, 'status' => 'archived']);
if ($archivedResp['success'] && isset($archivedResp['meta']['total'])) {
    $statsEvents += (int) $archivedResp['meta']['total'];
}

// Organizers
$orgResp = api_get('/marketplace-events/organizers', ['per_page' => 1]);
if ($orgResp['success'] && isset($orgResp['meta']['total'])) {
    $statsOrganizers = (int) $orgResp['meta']['total'];
}

// Tickets sold via statistics endpoint
$statsResp = api_get('/statistics/dashboard', ['from_date' => '2020-01-01', 'to_date' => date('Y-m-d')]);
if ($statsResp['success'] && isset($statsResp['data']['summary']['total_tickets_sold'])) {
    $statsTickets = (int) $statsResp['data']['summary']['total_tickets_sold'];
}

// Fallback: if tickets or organizers still 0, use reasonable estimates from known data
if ($statsTickets === 0) {
    // Estimate from completed orders count (avg ~2 tickets/order)
    if ($statsResp['success'] && isset($statsResp['data']['summary']['completed_orders'])) {
        $statsTickets = (int) ($statsResp['data']['summary']['completed_orders'] * 2);
    }
}
// If API calls failed completely, use last known values
if ($statsEvents === 0) $statsEvents = 4100;
if ($statsTickets === 0) $statsTickets = 284000;
if ($statsOrganizers === 0) $statsOrganizers = 500;

// Format numbers for display
function formatStat(int $n): string {
    if ($n >= 1000000) return number_format($n / 1000000, 1, '.', '') . 'M+';
    if ($n >= 1000) return number_format($n / 1000, $n >= 10000 ? 0 : 1, '.', '') . 'K+';
    return (string) $n;
}

$pageTitle = 'Despre Noi';
$transparentHeader = false;
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero Section -->
    <section class="relative px-6 py-20 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-900 md:py-24 md:px-12">
        <div class="absolute -top-[300px] -right-[300px] w-[800px] h-[800px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="absolute -bottom-[200px] -left-[200px] w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(165,28,48,0.1)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="relative z-10 max-w-4xl mx-auto text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 mb-6 text-sm font-semibold border rounded-full bg-primary/20 border-primary/30 text-accent">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Echipa AmBilet
            </div>
            <h1 class="text-4xl md:text-[56px] font-extrabold text-white mb-5 leading-tight tracking-tight">Conectam oamenii cu experiente memorabile</h1>
            <p class="max-w-2xl mx-auto text-lg leading-relaxed md:text-xl text-white/90">Suntem o echipa pasionata de tehnologie si evenimente, dedicata sa transformam modul in care romanii descopera si participa la evenimente.</p>
        </div>
    </section>

    <main class="max-w-6xl px-6 py-16 mx-auto md:px-12 md:py-20">
        <!-- Story Section -->
        <section class="mb-24">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Povestea noastra</span>
            <div class="grid items-center gap-12 md:grid-cols-2 md:gap-16">
                <div>
                    <h2 class="mb-6 text-3xl font-extrabold leading-tight md:text-4xl text-slate-800">Am inceput cu o idee simpla</h2>
                    <p class="text-[17px] text-slate-500 leading-relaxed mb-5">In 2024, am observat o problema: organizatorii de evenimente din Romania se luptau cu sisteme de ticketing complicate, scumpe si neadaptate pietei locale.</p>
                    <p class="text-[17px] text-slate-500 leading-relaxed mb-5">Am construit AmBilet pentru a rezolva asta. O platforma moderna, intuitiva, care pune accent pe experienta utilizatorului - atat pentru organizatori, cat si pentru participanti.</p>
                    <p class="text-[17px] text-slate-500 leading-relaxed">Astazi, AmBilet este alegerea a sute de organizatori din Romania, de la festivaluri mari la evenimente de nisa.</p>
                </div>
                <div class="bg-gradient-to-br from-primary to-red-800 rounded-3xl p-12 md:p-16 flex items-center justify-center min-h-[400px] relative overflow-hidden">
                    <div class="absolute -top-[50px] -right-[50px] w-[200px] h-[200px] bg-white/10 rounded-full"></div>
                    <svg class="text-white w-28 h-28 md:w-32 md:h-32 opacity-90" viewBox="0 0 48 48" fill="none">
                        <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="currentColor"/>
                        <line x1="17" y1="15" x2="31" y2="15" stroke="#A51C30" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="15" y1="19" x2="33" y2="19" stroke="#A51C30" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                        <rect x="20" y="27" width="8" height="8" rx="1.5" fill="#A51C30"/>
                    </svg>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-6 lg:grid-cols-3 md:gap-8 mt-14">
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary"><?= formatStat($statsEvents) ?></div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Evenimente organizate</div>
                </div>
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary"><?= formatStat($statsTickets) ?></div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Bilete vandute</div>
                </div>
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary"><?= formatStat($statsOrganizers) ?></div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Organizatori activi</div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-[32px] p-12 md:p-20 text-center relative overflow-hidden">
            <div class="absolute -top-[150px] -right-[150px] w-[400px] h-[400px] bg-[radial-gradient(circle,rgba(165,28,48,0.2),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-[40px] font-extrabold text-white mb-4">Hai sa construim impreuna</h2>
                <p class="max-w-lg mx-auto mb-8 text-lg text-white/90">Vrei sa faci parte din echipa AmBilet sau sa colaborezi cu noi?</p>
                <div class="flex flex-col justify-center gap-4 sm:flex-row">
                    <a href="/cariere" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 md:px-9 md:py-[18px] rounded-xl text-base font-bold bg-gradient-to-br from-primary to-red-600 text-white hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(165,28,48,0.4)] transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Vezi cariere
                    </a>
                    <a href="/contact" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 md:px-9 md:py-[18px] rounded-xl text-base font-bold bg-white/10 border border-white/20 text-white hover:bg-white/15 transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Contacteaza-ne
                    </a>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>

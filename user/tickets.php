<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Biletele mele';
$currentPage = 'tickets';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .ticket-qr { transition: transform 0.2s ease; cursor: pointer; }
    .ticket-qr:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    @media print {
        .no-print { display: none !important; }
        .ticket-card { break-inside: avoid; page-break-inside: avoid; }
    }
    /* QR Modal styles */
    .qr-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 50; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .qr-modal-backdrop.active { opacity: 1; visibility: visible; }
    .qr-modal { background: white; border-radius: 1.5rem; padding: 2rem; max-width: 90vw; max-height: 90vh; transform: scale(0.9); transition: transform 0.3s ease; text-align: center; }
    .qr-modal-backdrop.active .qr-modal { transform: scale(1); }
    .qr-modal-qr { width: 280px; height: 280px; margin: 1rem auto; background: white; padding: 1rem; border-radius: 1rem; }
    .qr-modal-qr canvas { width: 100% !important; height: 100% !important; }
</style>
<!-- QRCode.js library for local QR generation (with fallback) -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js" onerror="window._qrLibFailed=true"></script>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Biletele mele</h1>
                <p class="mt-1 text-sm text-muted">Vizualizează și descarcă biletele tale</p>
            </div>
            <button onclick="window.print()" class="no-print flex items-center gap-2 px-4 py-2.5 bg-surface text-secondary rounded-xl text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Printeaza toate
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 no-print">
            <button onclick="UserTickets.showTab('upcoming')" class="px-4 py-2 text-sm font-medium tab-btn active rounded-xl" data-tab="upcoming">
                Viitoare (<span id="upcoming-count">0</span>)
            </button>
            <button onclick="UserTickets.showTab('past')" class="px-4 py-2 text-sm font-medium tab-btn rounded-xl text-muted bg-surface" data-tab="past">
                Trecute (<span id="past-count">0</span>)
            </button>
        </div>

        <!-- Upcoming Tickets -->
        <div id="tab-upcoming" class="space-y-6">
            <div class="py-8 text-center">
                <div class="w-8 h-8 mx-auto border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                <p class="mt-2 text-muted">Se încarcă biletele...</p>
            </div>
        </div>

        <!-- Past Tickets -->
        <div id="tab-past" class="hidden space-y-4"></div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden py-16 text-center">
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-surface rounded-2xl">
                <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <h3 class="mb-2 text-lg font-bold text-secondary">Nu ai bilete încă</h3>
            <p class="mb-6 text-muted">Descoperă evenimente interesante și achiziționează primul tău bilet!</p>
            <a href="/evenimente" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Descoperă evenimente
            </a>
        </div>

        <!-- QR Modal -->
        <div id="qr-modal-backdrop" class="qr-modal-backdrop no-print" onclick="UserTickets.hideQRModal(event)">
            <div class="qr-modal" onclick="event.stopPropagation()">
                <button onclick="UserTickets.hideQRModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <h3 id="qr-modal-title" class="text-lg font-bold text-secondary mb-1"></h3>
                <p id="qr-modal-attendee" class="text-sm text-muted mb-2"></p>
                <div id="qr-modal-qr" class="qr-modal-qr"></div>
                <p id="qr-modal-code" class="text-sm font-mono text-muted mt-2"></p>
                <p id="qr-modal-type" class="text-sm font-medium text-secondary mt-1"></p>
            </div>
        </div>
<?php
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php';
?>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/user-tickets.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>

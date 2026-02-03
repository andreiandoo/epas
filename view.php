<?php
/**
 * Public Share Link View
 * Displays real-time event statistics for a shared monitoring link.
 * No authentication required - the unique code acts as access control.
 */
require_once __DIR__ . '/includes/config.php';

// Validate share link code
$code = $_GET['code'] ?? '';
if (!$code || !preg_match('/^[A-Za-z0-9]{6,20}$/', $code)) {
    http_response_code(404);
    $pageTitle = 'Link invalid';
    $showError = true;
    $errorMessage = 'Link-ul accesat nu este valid.';
} else {
    $pageTitle = 'Monitorizare Evenimente';
    $showError = false;
}

$noIndex = true;
$bodyClass = 'bg-slate-50 min-h-screen';
$pageDescription = 'Monitorizare in timp real a vanzarilor de bilete.';
require_once __DIR__ . '/includes/head.php';
?>

    <!-- Minimal Header -->
    <header class="bg-white border-b border-border">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/images/logo.svg" alt="<?= SITE_NAME ?>" class="h-8" onerror="this.style.display='none'">
                <span class="font-bold text-secondary text-lg"><?= SITE_NAME ?></span>
            </a>
            <div class="flex items-center gap-3 text-sm text-muted">
                <span id="auto-refresh-indicator" class="hidden items-center gap-1.5">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span>Live</span>
                </span>
                <span id="last-updated" class="hidden"></span>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        <?php if ($showError): ?>
            <!-- Error State -->
            <div class="text-center py-20">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h1 class="text-2xl font-bold text-secondary mb-2"><?= htmlspecialchars($errorMessage) ?></h1>
                <p class="text-muted">Verifica link-ul primit sau contacteaza organizatorul.</p>
                <a href="/" class="btn btn-primary mt-6 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Inapoi la <?= SITE_NAME ?>
                </a>
            </div>
        <?php else: ?>
            <!-- Loading State -->
            <div id="loading-state" class="text-center py-20">
                <div class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-muted">Se incarca datele...</p>
            </div>

            <!-- Error State (JS) -->
            <div id="error-state" class="hidden text-center py-20">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h1 class="text-2xl font-bold text-secondary mb-2" id="error-title">Link indisponibil</h1>
                <p class="text-muted" id="error-desc">Acest link nu mai este activ sau a fost sters.</p>
            </div>

            <!-- Content -->
            <div id="content-state" class="hidden">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-secondary" id="share-title">Monitorizare Evenimente</h1>
                    <p class="text-sm text-muted mt-1" id="share-subtitle"></p>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" id="summary-cards"></div>

                <!-- Events Grid -->
                <div class="space-y-4" id="events-container"></div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="border-t border-border bg-white mt-12">
        <div class="max-w-6xl mx-auto px-4 py-4 text-center text-xs text-muted">
            Generat de <a href="<?= SITE_URL ?>" class="text-primary hover:underline"><?= SITE_NAME ?></a> &mdash; Informatii actualizate automat
        </div>
    </footer>

<?php if (!$showError): ?>
<script>
(function() {
    'use strict';

    const SHARE_CODE = <?= json_encode($code) ?>;
    const API_URL = '/api/proxy.php';
    const REFRESH_INTERVAL = 30000; // 30 seconds
    let refreshTimer = null;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function formatNumber(n) {
        return (n || 0).toLocaleString('ro-RO');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('ro-RO', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
        } catch(e) { return dateStr; }
    }

    function formatTime(timeStr) {
        if (!timeStr) return '';
        return timeStr.substring(0, 5);
    }

    function showError(title, desc) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('content-state').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
        document.getElementById('error-title').textContent = title || 'Link indisponibil';
        document.getElementById('error-desc').textContent = desc || 'Acest link nu mai este activ.';
    }

    function showContent() {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-state').classList.add('hidden');
        document.getElementById('content-state').classList.remove('hidden');
    }

    async function fetchShareData() {
        try {
            const response = await fetch(`${API_URL}?action=share-link.data&code=${encodeURIComponent(SHARE_CODE)}`);
            const data = await response.json();

            if (!response.ok || !data.success) {
                if (response.status === 410) {
                    showError('Link dezactivat', 'Organizatorul a dezactivat acest link de monitorizare.');
                } else if (response.status === 404) {
                    showError('Link inexistent', 'Acest link de monitorizare nu exista.');
                } else {
                    showError('Eroare', data.error || 'Nu s-au putut incarca datele.');
                }
                stopAutoRefresh();
                return;
            }

            renderData(data.data);
            showContent();
            updateLastUpdated();
            startAutoRefresh();
        } catch (error) {
            console.error('Fetch error:', error);
            showError('Eroare de retea', 'Verifica conexiunea la internet si reincearca.');
            stopAutoRefresh();
        }
    }

    function renderData(data) {
        // Title
        if (data.name) {
            document.getElementById('share-title').textContent = data.name;
        }
        document.getElementById('share-subtitle').textContent = (data.events || []).length + ' eveniment' + ((data.events || []).length !== 1 ? 'e' : '') + ' monitorizate';

        const events = data.events || [];

        // Calculate totals
        let totalTickets = 0, totalSold = 0, totalRevenue = 0, totalEvents = events.length;
        events.forEach(ev => {
            const evTotal = (ev.ticket_types || []).reduce((s, tt) => s + (tt.total || 0), 0) || ev.tickets_total || 0;
            const evSold = (ev.ticket_types || []).reduce((s, tt) => s + (tt.sold || 0), 0) || ev.tickets_sold || 0;
            totalTickets += evTotal;
            totalSold += evSold;
            (ev.ticket_types || []).forEach(tt => {
                totalRevenue += (tt.sold || 0) * (tt.price || 0);
            });
        });

        // Summary cards
        const summaryEl = document.getElementById('summary-cards');
        const pct = totalTickets > 0 ? Math.round((totalSold / totalTickets) * 100) : 0;
        summaryEl.innerHTML = `
            <div class="bg-white rounded-2xl border border-border p-5">
                <p class="text-sm text-muted mb-1">Evenimente</p>
                <p class="text-2xl font-bold text-secondary">${formatNumber(totalEvents)}</p>
            </div>
            <div class="bg-white rounded-2xl border border-border p-5">
                <p class="text-sm text-muted mb-1">Bilete vandute</p>
                <p class="text-2xl font-bold text-primary">${formatNumber(totalSold)}</p>
            </div>
            <div class="bg-white rounded-2xl border border-border p-5">
                <p class="text-sm text-muted mb-1">Bilete totale</p>
                <p class="text-2xl font-bold text-secondary">${formatNumber(totalTickets)}</p>
            </div>
            <div class="bg-white rounded-2xl border border-border p-5">
                <p class="text-sm text-muted mb-1">Grad ocupare</p>
                <p class="text-2xl font-bold ${pct >= 80 ? 'text-green-600' : pct >= 50 ? 'text-yellow-600' : 'text-secondary'}">${pct}%</p>
            </div>
        `;

        // Events
        const eventsEl = document.getElementById('events-container');
        if (!events.length) {
            eventsEl.innerHTML = '<div class="text-center py-12 text-muted">Nu sunt evenimente in acest link.</div>';
            return;
        }

        eventsEl.innerHTML = events.map(ev => {
            const evTotal = (ev.ticket_types || []).reduce((s, tt) => s + (tt.total || 0), 0) || ev.tickets_total || 0;
            const evSold = (ev.ticket_types || []).reduce((s, tt) => s + (tt.sold || 0), 0) || ev.tickets_sold || 0;
            const evAvailable = evTotal - evSold;
            const evPct = evTotal > 0 ? Math.round((evSold / evTotal) * 100) : 0;

            let ticketTypesHtml = '';
            if (ev.ticket_types && ev.ticket_types.length > 0) {
                ticketTypesHtml = `
                    <div class="mt-4 border-t border-border pt-4">
                        <h4 class="text-xs font-semibold text-muted uppercase tracking-wide mb-3">Categorii bilete</h4>
                        <div class="space-y-2">
                            ${ev.ticket_types.map(tt => {
                                const ttPct = tt.total > 0 ? Math.round((tt.sold / tt.total) * 100) : 0;
                                return `
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm font-medium text-secondary truncate">${escapeHtml(tt.name)}</span>
                                                <span class="text-sm text-muted">${formatNumber(tt.sold)} / ${formatNumber(tt.total)}</span>
                                            </div>
                                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-500 ${ttPct >= 90 ? 'bg-red-500' : ttPct >= 70 ? 'bg-yellow-500' : 'bg-primary'}" style="width: ${ttPct}%"></div>
                                            </div>
                                        </div>
                                        <span class="text-xs font-semibold w-10 text-right ${ttPct >= 90 ? 'text-red-600' : 'text-muted'}">${ttPct}%</span>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            }

            return `
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <div class="p-5 lg:p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-6">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-secondary mb-2">${escapeHtml(ev.title)}</h3>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted mb-3">
                                    ${ev.venue_name ? `<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>${escapeHtml(ev.venue_name)}${ev.city ? ', ' + escapeHtml(ev.city) : ''}</span>` : ''}
                                    ${ev.start_date ? `<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${formatDate(ev.start_date)}${ev.start_time ? ' ' + formatTime(ev.start_time) : ''}</span>` : ''}
                                </div>
                            </div>
                            <div class="flex items-center gap-4 lg:gap-6 mt-3 lg:mt-0 flex-shrink-0">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-primary">${formatNumber(evSold)}</p>
                                    <p class="text-xs text-muted">Vandute</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-secondary">${formatNumber(evTotal)}</p>
                                    <p class="text-xs text-muted">Total</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold ${evAvailable <= 0 ? 'text-red-600' : evAvailable < evTotal * 0.1 ? 'text-yellow-600' : 'text-green-600'}">${formatNumber(evAvailable)}</p>
                                    <p class="text-xs text-muted">Disponibile</p>
                                </div>
                            </div>
                        </div>
                        <!-- Overall progress bar -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs text-muted">Grad ocupare</span>
                                <span class="text-xs font-semibold ${evPct >= 90 ? 'text-red-600' : evPct >= 70 ? 'text-yellow-600' : 'text-primary'}">${evPct}%</span>
                            </div>
                            <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 ${evPct >= 90 ? 'bg-red-500' : evPct >= 70 ? 'bg-yellow-500' : 'bg-primary'}" style="width: ${evPct}%"></div>
                            </div>
                        </div>
                        ${ticketTypesHtml}
                    </div>
                </div>
            `;
        }).join('');
    }

    function updateLastUpdated() {
        const el = document.getElementById('last-updated');
        const now = new Date();
        el.textContent = 'Actualizat: ' + now.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
        el.classList.remove('hidden');
        document.getElementById('auto-refresh-indicator').classList.remove('hidden');
        document.getElementById('auto-refresh-indicator').classList.add('flex');
    }

    function startAutoRefresh() {
        if (refreshTimer) return;
        refreshTimer = setInterval(fetchShareData, REFRESH_INTERVAL);
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
        const indicator = document.getElementById('auto-refresh-indicator');
        if (indicator) {
            indicator.classList.add('hidden');
            indicator.classList.remove('flex');
        }
    }

    // Start
    fetchShareData();
})();
</script>
<?php endif; ?>

<?php
$skipJsComponents = true;
$scriptsExtra = '';
require_once __DIR__ . '/includes/scripts.php';
?>

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
$cssBundle = 'static';
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

            <!-- Password Prompt -->
            <div id="password-state" class="hidden text-center py-20">
                <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h1 class="text-2xl font-bold text-secondary mb-2">Link protejat</h1>
                <p class="text-muted mb-6">Introdu parola pentru a vizualiza datele.</p>
                <form onsubmit="window._submitSharePassword(event)" class="max-w-xs mx-auto">
                    <input type="password" id="share-password-input" class="w-full px-4 py-3 border border-border rounded-xl text-center text-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Parola" autocomplete="off" autofocus>
                    <p id="password-error" class="text-red-500 text-sm mt-2 hidden">Parola incorecta</p>
                    <button type="submit" id="password-submit-btn" class="w-full mt-4 px-6 py-3 bg-primary text-white rounded-xl font-medium hover:bg-primary/90 transition-colors">Acceseaza</button>
                </form>
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
    let sharePassword = null;
    let isFirstLoad = true;

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
        // Handle full datetime strings like "2026-01-31T18:30:00"
        if (timeStr.includes('T')) {
            const timePart = timeStr.split('T')[1];
            return timePart ? timePart.substring(0, 5) : '';
        }
        // Handle time-only strings like "18:30:00"
        return timeStr.substring(0, 5);
    }

    function showError(title, desc) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('content-state').classList.add('hidden');
        document.getElementById('password-state').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
        document.getElementById('error-title').textContent = title || 'Link indisponibil';
        document.getElementById('error-desc').textContent = desc || 'Acest link nu mai este activ.';
    }

    function showContent() {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-state').classList.add('hidden');
        document.getElementById('password-state').classList.add('hidden');
        document.getElementById('content-state').classList.remove('hidden');
    }

    function showPasswordPrompt() {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-state').classList.add('hidden');
        document.getElementById('content-state').classList.add('hidden');
        document.getElementById('password-state').classList.remove('hidden');
        document.getElementById('share-password-input').focus();
    }

    async function fetchShareData() {
        try {
            const headers = {};
            if (!isFirstLoad) {
                headers['X-Auto-Refresh'] = '1';
            }

            let response;
            if (sharePassword) {
                // Send password via POST
                headers['Content-Type'] = 'application/json';
                response = await fetch(`${API_URL}?action=share-link.data&code=${encodeURIComponent(SHARE_CODE)}`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ password: sharePassword })
                });
            } else {
                response = await fetch(`${API_URL}?action=share-link.data&code=${encodeURIComponent(SHARE_CODE)}`, {
                    headers: headers
                });
            }
            const data = await response.json();

            if (!response.ok || !data.success) {
                if (response.status === 401 && data.error === 'password_required') {
                    showPasswordPrompt();
                    return;
                } else if (response.status === 403 && data.error === 'invalid_password') {
                    document.getElementById('password-error').classList.remove('hidden');
                    document.getElementById('password-submit-btn').disabled = false;
                    document.getElementById('password-submit-btn').textContent = 'Acceseaza';
                    return;
                } else if (response.status === 429) {
                    if (data.error === 'too_many_attempts') {
                        document.getElementById('password-error').textContent = 'Prea multe incercari. Incearca mai tarziu.';
                        document.getElementById('password-error').classList.remove('hidden');
                        document.getElementById('password-submit-btn').disabled = false;
                        document.getElementById('password-submit-btn').textContent = 'Acceseaza';
                    }
                    return;
                } else if (response.status === 410) {
                    showError('Link dezactivat', 'Organizatorul a dezactivat acest link de monitorizare.');
                } else if (response.status === 404) {
                    showError('Link inexistent', 'Acest link de monitorizare nu exista.');
                } else {
                    showError('Eroare', data.error || 'Nu s-au putut incarca datele.');
                }
                stopAutoRefresh();
                return;
            }

            isFirstLoad = false;
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

    // Password submit handler (exposed globally for the form)
    window._submitSharePassword = function(e) {
        e.preventDefault();
        const pw = document.getElementById('share-password-input').value.trim();
        if (!pw) return;
        sharePassword = pw;
        document.getElementById('password-error').classList.add('hidden');
        document.getElementById('password-submit-btn').disabled = true;
        document.getElementById('password-submit-btn').textContent = 'Se verifica...';
        fetchShareData();
    };

    let showParticipants = false;
    let participantsData = {};
    let openAccordions = {};

    function toggleAccordion(evId) {
        const body = document.getElementById('accordion-body-' + evId);
        const icon = document.getElementById('accordion-icon-' + evId);
        if (!body) return;
        if (body.classList.contains('hidden')) {
            body.classList.remove('hidden');
            icon.classList.add('rotate-180');
            openAccordions[evId] = true;
        } else {
            body.classList.add('hidden');
            icon.classList.remove('rotate-180');
            delete openAccordions[evId];
        }
    }
    // Expose globally for onclick
    window._toggleAccordion = toggleAccordion;

    function switchTab(evId, tab) {
        const ticketsTab = document.getElementById('tab-tickets-' + evId);
        const participantsTab = document.getElementById('tab-participants-' + evId);
        const ticketsBtn = document.getElementById('tab-btn-tickets-' + evId);
        const participantsBtn = document.getElementById('tab-btn-participants-' + evId);
        if (!ticketsTab) return;

        if (tab === 'participants') {
            ticketsTab.classList.add('hidden');
            participantsTab && participantsTab.classList.remove('hidden');
            ticketsBtn.classList.remove('bg-primary', 'text-white');
            ticketsBtn.classList.add('text-muted', 'hover:bg-surface');
            participantsBtn.classList.add('bg-primary', 'text-white');
            participantsBtn.classList.remove('text-muted', 'hover:bg-surface');
        } else {
            ticketsTab.classList.remove('hidden');
            participantsTab && participantsTab.classList.add('hidden');
            ticketsBtn.classList.add('bg-primary', 'text-white');
            ticketsBtn.classList.remove('text-muted', 'hover:bg-surface');
            participantsBtn.classList.remove('bg-primary', 'text-white');
            participantsBtn.classList.add('text-muted', 'hover:bg-surface');
        }
    }
    window._switchTab = switchTab;

    function renderData(data) {
        document.getElementById('share-subtitle').textContent = (data.events || []).length + ' eveniment' + ((data.events || []).length !== 1 ? 'e' : '') + ' monitorizate';

        const events = data.events || [];
        showParticipants = !!data.show_participants;
        participantsData = data.participants || {};

        // Calculate totals
        let totalTickets = 0, totalSold = 0, totalEvents = events.length;
        events.forEach(ev => {
            const evTotal = (ev.ticket_types || []).reduce((s, tt) => s + (tt.total || 0), 0) || ev.tickets_total || 0;
            const evSold = (ev.ticket_types || []).reduce((s, tt) => s + (tt.sold || 0), 0) || ev.tickets_sold || 0;
            totalTickets += evTotal;
            totalSold += evSold;
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

        // Events as accordions
        const eventsEl = document.getElementById('events-container');
        if (!events.length) {
            eventsEl.innerHTML = '<div class="text-center py-12 text-muted">Nu sunt evenimente in acest link.</div>';
            return;
        }

        eventsEl.innerHTML = events.map(ev => {
            const evId = ev.id || 0;
            const evTotal = (ev.ticket_types || []).reduce((s, tt) => s + (tt.total || 0), 0) || ev.tickets_total || 0;
            const evSold = (ev.ticket_types || []).reduce((s, tt) => s + (tt.sold || 0), 0) || ev.tickets_sold || 0;
            const evPct = evTotal > 0 ? Math.round((evSold / evTotal) * 100) : 0;
            const isOpen = !!openAccordions[evId];

            // Ticket types tab content
            let ticketTypesHtml = '<p class="text-sm text-muted py-4">Nu sunt categorii de bilete.</p>';
            if (ev.ticket_types && ev.ticket_types.length > 0) {
                ticketTypesHtml = `<div class="space-y-2">${ev.ticket_types.map(tt => {
                    const ttPct = tt.total > 0 ? Math.round((tt.sold / tt.total) * 100) : 0;
                    return `<div class="flex items-center gap-3">
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
                    </div>`;
                }).join('')}</div>`;
            }

            // Participants tab content
            let participantsHtml = '';
            if (showParticipants) {
                const evParticipants = participantsData[evId] || participantsData[String(evId)] || [];
                if (evParticipants.length > 0) {
                    participantsHtml = `<div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-border text-left">
                                <th class="pb-2 font-medium text-muted">Nume</th>
                                <th class="pb-2 font-medium text-muted">Telefon</th>
                                <th class="pb-2 font-medium text-muted">Tip bilet</th>
                                ${evParticipants.some(p => p.seat_label) ? '<th class="pb-2 font-medium text-muted">Loc</th>' : ''}
                            </tr></thead>
                            <tbody>${evParticipants.map(p => `<tr class="border-b border-border/50">
                                <td class="py-2 text-secondary">${escapeHtml(p.name)}</td>
                                <td class="py-2 text-muted">${escapeHtml(p.phone)}</td>
                                <td class="py-2 text-muted">${escapeHtml(p.ticket_type)}</td>
                                ${evParticipants.some(pp => pp.seat_label) ? '<td class="py-2 text-muted">' + escapeHtml(p.seat_label || '-') + '</td>' : ''}
                            </tr>`).join('')}</tbody>
                        </table>
                    </div>`;
                } else {
                    participantsHtml = '<p class="text-sm text-muted py-4">Nu sunt participanti inregistrati.</p>';
                }
            }

            // Tabs (only show if participants enabled)
            let tabsHtml = '';
            if (showParticipants) {
                tabsHtml = `
                    <div class="flex gap-1 mb-4">
                        <button id="tab-btn-tickets-${evId}" onclick="_switchTab(${evId}, 'tickets')" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-white transition-colors">Tipuri Bilete</button>
                        <button id="tab-btn-participants-${evId}" onclick="_switchTab(${evId}, 'participants')" class="px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:bg-surface transition-colors">Participanti</button>
                    </div>
                    <div id="tab-tickets-${evId}">${ticketTypesHtml}</div>
                    <div id="tab-participants-${evId}" class="hidden">${participantsHtml}</div>
                `;
            } else {
                tabsHtml = ticketTypesHtml;
            }

            return `
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <!-- Accordion Header -->
                    <button onclick="_toggleAccordion(${evId})" class="w-full p-5 lg:p-6 text-left hover:bg-slate-50/50 transition-colors">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-6">
                            <div class="flex-1 min-w-0 flex items-start gap-3">
                                <svg id="accordion-icon-${evId}" class="w-5 h-5 text-muted flex-shrink-0 mt-0.5 transition-transform ${isOpen ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-bold text-secondary mb-1">${escapeHtml(ev.title)}</h3>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                                        ${ev.venue_name ? `<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>${escapeHtml(ev.venue_name)}${ev.city ? ', ' + escapeHtml(ev.city) : ''}</span>` : ''}
                                        ${ev.start_date ? `<span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${formatDate(ev.start_date)}${ev.start_time ? ' ' + formatTime(ev.start_time) : ''}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 lg:gap-6 mt-3 lg:mt-0 flex-shrink-0 pl-8 lg:pl-0">
                                <div class="text-center">
                                    <p class="text-xl font-bold text-primary">${formatNumber(evSold)}</p>
                                    <p class="text-xs text-muted">Vandute</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xl font-bold text-secondary">${formatNumber(evTotal)}</p>
                                    <p class="text-xs text-muted">Total</p>
                                </div>
                                <div class="min-w-[60px]">
                                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full ${evPct >= 90 ? 'bg-red-500' : evPct >= 70 ? 'bg-yellow-500' : 'bg-primary'}" style="width: ${evPct}%"></div>
                                    </div>
                                    <p class="text-xs font-semibold text-center mt-1 ${evPct >= 90 ? 'text-red-600' : 'text-muted'}">${evPct}%</p>
                                </div>
                            </div>
                        </div>
                    </button>
                    <!-- Accordion Body -->
                    <div id="accordion-body-${evId}" class="${isOpen ? '' : 'hidden'}">
                        <div class="px-5 lg:px-6 pb-5 lg:pb-6 pt-0 border-t border-border">
                            <div class="pt-4">
                                ${tabsHtml}
                            </div>
                        </div>
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

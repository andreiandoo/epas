<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $marketplaceName }} - Gate Scanner</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .flash-overlay {
            position: fixed; inset: 0; z-index: 50;
            pointer-events: none; opacity: 0;
            transition: opacity 0.15s ease-out;
        }
        .flash-overlay.active { opacity: 0.3; }
        #qr-reader { width: 100% !important; }
        #qr-reader video { border-radius: 12px !important; }
        #qr-reader__scan_region { min-height: 250px; }
        #qr-reader__dashboard { display: none !important; }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen">

<div id="flash-green" class="flash-overlay bg-green-500"></div>
<div id="flash-red" class="flash-overlay bg-red-500"></div>
<div id="flash-amber" class="flash-overlay bg-amber-500"></div>

<!-- LOGIN SCREEN -->
<div id="login-screen" class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">{{ $marketplaceName }}</h1>
            <p class="text-gray-400 mt-2">Gate Scanner</p>
        </div>
        <div class="bg-gray-900 rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Email</label>
                <input type="email" id="login-email" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500" placeholder="organizer@email.com">
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Parola</label>
                <input type="password" id="login-password" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500" placeholder="********">
            </div>
            <div id="login-error" class="text-red-400 text-sm hidden"></div>
            <button onclick="doLogin()" id="login-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">
                Autentificare
            </button>
        </div>
    </div>
</div>

<!-- SCANNER SCREEN -->
<div id="scanner-screen" class="hidden flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="font-semibold text-lg truncate">{{ $marketplaceName }}</div>
            <button onclick="doLogout()" class="text-gray-400 hover:text-white text-sm">Deconectare</button>
        </div>
        <div class="mt-2 flex gap-2">
            <select id="event-select" onchange="onEventChange()" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none">
                <option value="">Toate evenimentele</option>
            </select>
        </div>
    </header>

    <!-- Mode Toggle -->
    <div class="bg-gray-900 px-4 py-2 flex gap-2">
        <button onclick="setMode('rapid')" id="btn-rapid" class="flex-1 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white transition">
            Rapid
        </button>
        <button onclick="setMode('manual')" id="btn-manual" class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-400 transition">
            Manual
        </button>
    </div>

    <!-- Stats Bar -->
    <div class="bg-gray-900 border-b border-gray-800 px-4 py-3 grid grid-cols-3 gap-2 text-center">
        <div>
            <div class="text-xs text-gray-500 uppercase">Total</div>
            <div id="stat-total" class="text-xl font-bold">-</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase">Check-in</div>
            <div id="stat-checkin" class="text-xl font-bold text-green-400">-</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase">Ramase</div>
            <div id="stat-remaining" class="text-xl font-bold text-amber-400">-</div>
        </div>
    </div>

    <!-- Scanner Area -->
    <div class="flex-1 p-4 space-y-4 overflow-y-auto">
        <!-- Camera -->
        <div class="bg-gray-900 rounded-2xl p-4">
            <div id="qr-reader"></div>
            <div class="flex gap-2 mt-3">
                <button onclick="toggleScanner()" id="btn-camera" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg text-sm transition">
                    Porneste camera
                </button>
            </div>
        </div>

        <!-- Manual Entry -->
        <div class="bg-gray-900 rounded-2xl p-4">
            <div class="flex gap-2">
                <input type="text" id="manual-code" placeholder="Introdu codul biletului" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white uppercase tracking-widest text-center font-mono focus:outline-none focus:border-blue-500" maxlength="20">
                <button onclick="manualLookup()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                    Verifica
                </button>
            </div>
        </div>

        <!-- Result Area -->
        <div id="result-area" class="hidden">
            <div id="result-card" class="rounded-2xl p-6 text-center"></div>
            <!-- Manual mode confirm button -->
            <div id="confirm-area" class="hidden mt-3">
                <button onclick="confirmCheckin()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-xl text-lg transition">
                    Check-in
                </button>
            </div>
        </div>

        <!-- Recent Scans -->
        <div class="bg-gray-900 rounded-2xl p-4">
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Scanari recente</h3>
            <div id="recent-list" class="space-y-2">
                <p class="text-gray-600 text-sm text-center py-4">Nicio scanare</p>
            </div>
        </div>
    </div>
</div>

<script>
const API_BASE = '{{ $apiBaseUrl }}';
const API_KEY = '{{ $apiKey }}';
const MARKETPLACE_NAME = '{{ $marketplaceName }}';

let token = localStorage.getItem('gate_token');
let mode = 'rapid';
let scanner = null;
let scanning = false;
let lastScannedCode = null;
let lastScanTime = 0;
let pendingCode = null;
let recentScans = [];

// ============ INIT ============
if (token) {
    showScanner();
}

// ============ AUTH ============
async function doLogin() {
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;
    const errorEl = document.getElementById('login-error');
    const btn = document.getElementById('login-btn');

    if (!email || !password) {
        errorEl.textContent = 'Completati email si parola.';
        errorEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Se autentifica...';
    errorEl.classList.add('hidden');

    try {
        const res = await apiFetch('/organizer/login', 'POST', { email, password }, false);
        if (res.token || res.data?.token) {
            token = res.token || res.data.token;
            localStorage.setItem('gate_token', token);
            showScanner();
        } else {
            throw new Error(res.message || 'Autentificare esuata');
        }
    } catch (e) {
        errorEl.textContent = e.message || 'Eroare la autentificare';
        errorEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Autentificare';
    }
}

function doLogout() {
    token = null;
    localStorage.removeItem('gate_token');
    stopScanner();
    document.getElementById('scanner-screen').classList.add('hidden');
    document.getElementById('login-screen').classList.remove('hidden');
}

function showScanner() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('scanner-screen').classList.remove('hidden');
    loadEvents();
    loadStats();
}

// ============ EVENTS ============
async function loadEvents() {
    try {
        const res = await apiFetch('/organizer/events', 'GET');
        const events = res.data || res.events || res;
        const select = document.getElementById('event-select');
        if (Array.isArray(events)) {
            events.forEach(ev => {
                const opt = document.createElement('option');
                opt.value = ev.id;
                const title = typeof ev.title === 'object'
                    ? (ev.title.ro || ev.title.en || Object.values(ev.title)[0] || '')
                    : (ev.title || '');
                opt.textContent = title;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Failed to load events:', e);
    }
}

function onEventChange() {
    loadStats();
}

async function loadStats() {
    try {
        const eventId = document.getElementById('event-select').value;
        const url = eventId
            ? '/organizer/participants?event_id=' + eventId
            : '/organizer/participants';
        const res = await apiFetch(url, 'GET');
        const stats = res.stats || res.data?.stats || {};
        const total = stats.total_tickets ?? stats.total ?? '-';
        const checkedIn = stats.checked_in ?? stats.checked_in_count ?? '-';
        const remaining = (typeof total === 'number' && typeof checkedIn === 'number')
            ? total - checkedIn : '-';
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-checkin').textContent = checkedIn;
        document.getElementById('stat-remaining').textContent = remaining;
    } catch (e) {
        console.error('Failed to load stats:', e);
    }
}

// ============ MODE ============
function setMode(m) {
    mode = m;
    document.getElementById('btn-rapid').className = m === 'rapid'
        ? 'flex-1 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white transition'
        : 'flex-1 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-400 transition';
    document.getElementById('btn-manual').className = m === 'manual'
        ? 'flex-1 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white transition'
        : 'flex-1 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-400 transition';
    hideResult();
}

// ============ QR SCANNER ============
function toggleScanner() {
    if (scanning) {
        stopScanner();
    } else {
        startScanner();
    }
}

async function startScanner() {
    const btn = document.getElementById('btn-camera');
    try {
        if (!scanner) {
            scanner = new Html5Qrcode('qr-reader');
        }
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
            onScanSuccess,
            () => {}
        );
        scanning = true;
        btn.textContent = 'Opreste camera';
        btn.classList.replace('bg-gray-800', 'bg-red-600');
        btn.classList.replace('hover:bg-gray-700', 'hover:bg-red-700');
    } catch (e) {
        console.error('Camera error:', e);
        btn.textContent = 'Eroare camera';
    }
}

function stopScanner() {
    const btn = document.getElementById('btn-camera');
    if (scanner && scanning) {
        scanner.stop().catch(() => {});
        scanning = false;
    }
    btn.textContent = 'Porneste camera';
    btn.classList.replace('bg-red-600', 'bg-gray-800');
    btn.classList.replace('hover:bg-red-700', 'hover:bg-gray-700');
}

function onScanSuccess(decodedText) {
    // Extract code from URL or use as-is
    let code = decodedText.trim();
    if (code.includes('/t/')) {
        code = code.split('/t/').pop();
    }

    // Debounce: ignore same code within 3 seconds
    const now = Date.now();
    if (code === lastScannedCode && now - lastScanTime < 3000) return;
    lastScannedCode = code;
    lastScanTime = now;

    processCode(code);
}

// ============ MANUAL ENTRY ============
function manualLookup() {
    const input = document.getElementById('manual-code');
    let code = input.value.trim().toUpperCase();
    if (!code) return;

    // Extract code from URL if pasted
    if (code.includes('/T/')) {
        code = code.split('/T/').pop();
    }

    processCode(code);
    input.value = '';
}

// Handle Enter key on manual input
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('manual-code').addEventListener('keydown', e => {
        if (e.key === 'Enter') manualLookup();
    });
});

// ============ PROCESS ============
function processCode(code) {
    if (mode === 'rapid') {
        checkinTicket(code);
    } else {
        lookupTicket(code);
    }
}

async function lookupTicket(code) {
    try {
        const res = await apiFetch('/organizer/participants/checkin', 'POST', { ticket_code: code, dry_run: true }, true, true);
        // If we get here with the existing API, it means it was checked in.
        // But we want a lookup. Since the API doesn't have a dry_run,
        // we use the public status endpoint instead.
        showLookupResult(code, res);
    } catch (e) {
        // The check-in endpoint returns errors for already checked-in / cancelled tickets
        // Try the public status API for a read-only lookup
        try {
            const statusRes = await fetch('/api/public/ticket/' + encodeURIComponent(code));
            const data = await statusRes.json();
            showManualResult(code, data);
        } catch (e2) {
            showError('Eroare la verificare');
        }
    }
}

function showManualResult(code, data) {
    const area = document.getElementById('result-area');
    const card = document.getElementById('result-card');
    const confirm = document.getElementById('confirm-area');
    area.classList.remove('hidden');

    if (data.status === 'valid') {
        card.className = 'rounded-2xl p-6 text-center bg-green-900/50 border-2 border-green-500';
        card.innerHTML = `
            <div class="text-4xl mb-2">&#10003;</div>
            <div class="text-xl font-bold text-green-400 mb-4">Bilet Valid</div>
            <div class="space-y-1 text-sm text-gray-300">
                ${data.attendee_name ? '<div>' + escHtml(data.attendee_name) + '</div>' : ''}
                ${data.ticket_type ? '<div>' + escHtml(data.ticket_type) + '</div>' : ''}
                ${data.seat_label ? '<div>Loc: ' + escHtml(data.seat_label) + '</div>' : ''}
                ${data.event_title ? '<div class="text-gray-500 mt-2">' + escHtml(data.event_title) + '</div>' : ''}
            </div>
        `;
        pendingCode = code;
        confirm.classList.remove('hidden');
        vibrate([100]);
    } else if (data.status === 'used') {
        card.className = 'rounded-2xl p-6 text-center bg-amber-900/50 border-2 border-amber-500';
        card.innerHTML = `
            <div class="text-4xl mb-2">&#9888;</div>
            <div class="text-xl font-bold text-amber-400 mb-2">Deja folosit</div>
            <div class="text-sm text-gray-400">Check-in: ${data.checked_in_at ? new Date(data.checked_in_at).toLocaleString('ro-RO') : '-'}</div>
        `;
        confirm.classList.add('hidden');
        pendingCode = null;
        vibrate([100, 100, 100]);
        flash('amber');
    } else {
        card.className = 'rounded-2xl p-6 text-center bg-red-900/50 border-2 border-red-500';
        card.innerHTML = `
            <div class="text-4xl mb-2">&#10007;</div>
            <div class="text-xl font-bold text-red-400 mb-2">Invalid</div>
            <div class="text-sm text-gray-400">${escHtml(data.status === 'not_found' ? 'Bilet negasit' : (data.status || 'Necunoscut'))}</div>
        `;
        confirm.classList.add('hidden');
        pendingCode = null;
        vibrate([100, 100, 100]);
        flash('red');
    }
}

async function confirmCheckin() {
    if (!pendingCode) return;
    await checkinTicket(pendingCode);
    pendingCode = null;
    document.getElementById('confirm-area').classList.add('hidden');
}

async function checkinTicket(code) {
    try {
        const res = await apiFetch('/organizer/participants/checkin', 'POST', { ticket_code: code });
        showCheckinSuccess(code, res);
    } catch (e) {
        const msg = e.message || '';
        if (msg.includes('already checked in') || msg.includes('deja')) {
            showAlreadyUsed(code, msg);
        } else if (msg.includes('cancelled') || msg.includes('refunded')) {
            showInvalid(code, msg);
        } else {
            showInvalid(code, msg || 'Bilet negasit');
        }
    }
}

function showCheckinSuccess(code, res) {
    const area = document.getElementById('result-area');
    const card = document.getElementById('result-card');
    area.classList.remove('hidden');
    document.getElementById('confirm-area').classList.add('hidden');

    const ticket = res.data?.ticket || res.ticket || {};
    const customer = res.data?.customer || res.customer || {};

    card.className = 'rounded-2xl p-6 text-center bg-green-900/50 border-2 border-green-500';
    card.innerHTML = `
        <div class="text-5xl mb-3">&#10003;</div>
        <div class="text-2xl font-bold text-green-400 mb-1">Check-in realizat</div>
        <div class="space-y-1 text-sm text-gray-300 mt-4">
            ${customer.name ? '<div class="text-lg font-medium text-white">' + escHtml(customer.name) + '</div>' : ''}
            ${ticket.ticket_type ? '<div>' + escHtml(ticket.ticket_type) + '</div>' : ''}
            ${ticket.seat_label ? '<div>Loc: ' + escHtml(ticket.seat_label) + '</div>' : ''}
        </div>
    `;

    addRecentScan(code, 'success', customer.name || code);
    vibrate([100]);
    flash('green');
    loadStats();

    // Auto-hide after 3 seconds in rapid mode
    if (mode === 'rapid') {
        setTimeout(hideResult, 3000);
    }
}

function showAlreadyUsed(code, msg) {
    const area = document.getElementById('result-area');
    const card = document.getElementById('result-card');
    area.classList.remove('hidden');
    document.getElementById('confirm-area').classList.add('hidden');

    card.className = 'rounded-2xl p-6 text-center bg-amber-900/50 border-2 border-amber-500';
    card.innerHTML = `
        <div class="text-5xl mb-3">&#9888;</div>
        <div class="text-2xl font-bold text-amber-400 mb-2">Deja folosit</div>
        <div class="text-sm text-gray-400">${escHtml(msg)}</div>
    `;

    addRecentScan(code, 'used', code);
    vibrate([100, 100, 100]);
    flash('amber');

    if (mode === 'rapid') {
        setTimeout(hideResult, 3000);
    }
}

function showInvalid(code, msg) {
    const area = document.getElementById('result-area');
    const card = document.getElementById('result-card');
    area.classList.remove('hidden');
    document.getElementById('confirm-area').classList.add('hidden');

    card.className = 'rounded-2xl p-6 text-center bg-red-900/50 border-2 border-red-500';
    card.innerHTML = `
        <div class="text-5xl mb-3">&#10007;</div>
        <div class="text-2xl font-bold text-red-400 mb-2">Invalid</div>
        <div class="text-sm text-gray-400">${escHtml(msg)}</div>
    `;

    addRecentScan(code, 'invalid', code);
    vibrate([100, 100, 100]);
    flash('red');

    if (mode === 'rapid') {
        setTimeout(hideResult, 3000);
    }
}

function showError(msg) {
    const area = document.getElementById('result-area');
    const card = document.getElementById('result-card');
    area.classList.remove('hidden');
    document.getElementById('confirm-area').classList.add('hidden');

    card.className = 'rounded-2xl p-6 text-center bg-red-900/50 border-2 border-red-500';
    card.innerHTML = `
        <div class="text-5xl mb-3">&#10007;</div>
        <div class="text-xl font-bold text-red-400">${escHtml(msg)}</div>
    `;
}

function showLookupResult(code, res) {
    // If check-in succeeded in lookup (shouldn't happen with dry_run, but fallback)
    showCheckinSuccess(code, res);
}

function hideResult() {
    document.getElementById('result-area').classList.add('hidden');
    document.getElementById('confirm-area').classList.add('hidden');
    pendingCode = null;
}

// ============ RECENT SCANS ============
function addRecentScan(code, status, label) {
    recentScans.unshift({ code, status, label, time: new Date() });
    if (recentScans.length > 20) recentScans.pop();
    renderRecentScans();
}

function renderRecentScans() {
    const list = document.getElementById('recent-list');
    if (recentScans.length === 0) {
        list.innerHTML = '<p class="text-gray-600 text-sm text-center py-4">Nicio scanare</p>';
        return;
    }
    list.innerHTML = recentScans.map(s => {
        const colors = {
            success: 'bg-green-500',
            used: 'bg-amber-500',
            invalid: 'bg-red-500',
        };
        const dot = colors[s.status] || 'bg-gray-500';
        return `<div class="flex items-center gap-3 py-2 border-b border-gray-800 last:border-0">
            <div class="w-2.5 h-2.5 rounded-full ${dot} flex-shrink-0"></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm text-white truncate">${escHtml(s.label)}</div>
                <div class="text-xs text-gray-500 font-mono">${escHtml(s.code)}</div>
            </div>
            <div class="text-xs text-gray-600 flex-shrink-0">${s.time.toLocaleTimeString('ro-RO', {hour:'2-digit', minute:'2-digit', second:'2-digit'})}</div>
        </div>`;
    }).join('');
}

// ============ FEEDBACK ============
function vibrate(pattern) {
    if (navigator.vibrate) navigator.vibrate(pattern);
}

function flash(color) {
    const el = document.getElementById('flash-' + color);
    if (!el) return;
    el.classList.add('active');
    setTimeout(() => el.classList.remove('active'), 1500);
}

// ============ API ============
async function apiFetch(endpoint, method = 'GET', body = null, auth = true, silent = false) {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-API-Key': API_KEY,
    };
    if (auth && token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    const opts = { method, headers };
    if (body && method !== 'GET') {
        opts.body = JSON.stringify(body);
    }

    const url = endpoint.startsWith('http') ? endpoint : API_BASE + endpoint;
    const res = await fetch(url, opts);
    const data = await res.json();

    if (!res.ok) {
        // If 401 unauthorized, force logout
        if (res.status === 401) {
            doLogout();
        }
        const errMsg = data.message || data.error || 'Request failed';
        throw new Error(errMsg);
    }

    return data;
}

// ============ HELPERS ============
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
</body>
</html>

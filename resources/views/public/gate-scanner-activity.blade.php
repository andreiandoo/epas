<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $marketplaceName }} — Activități Gate</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .flash-overlay { position: fixed; inset: 0; z-index: 50; pointer-events: none; opacity: 0; transition: opacity 0.15s ease-out; }
        .flash-overlay.active { opacity: 0.32; }
        #qr-reader { width: 100% !important; }
        #qr-reader video { border-radius: 12px !important; }
        #qr-reader__scan_region { min-height: 230px; }
        #qr-reader__dashboard { display: none !important; }
        body { -webkit-font-smoothing: antialiased; }
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
            <p class="text-gray-400 mt-2 text-sm">Check-in pentru activități</p>
        </div>
        <div class="bg-gray-900 rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Email organizator</label>
                <input type="email" id="login-email" autocomplete="username" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500" placeholder="organizer@email.com">
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Parola</label>
                <input type="password" id="login-password" autocomplete="current-password" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500" placeholder="********">
            </div>
            <div id="login-error" class="text-red-400 text-sm hidden"></div>
            <button onclick="doLogin()" id="login-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">Autentificare</button>
        </div>
    </div>
</div>

<!-- SCANNER SCREEN -->
<div id="scanner-screen" class="hidden flex-col min-h-screen">
    <header class="bg-gray-900 border-b border-gray-800 px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="font-semibold text-lg truncate">{{ $marketplaceName }} · Activități</div>
            <button onclick="doLogout()" class="text-gray-400 hover:text-white text-sm">Deconectare</button>
        </div>
    </header>

    <div class="bg-gray-900 px-4 py-2 flex gap-2">
        <button onclick="setMode('rapid')" id="btn-rapid" class="flex-1 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white transition">Rapid (auto check-in)</button>
        <button onclick="setMode('manual')" id="btn-manual" class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-400 transition">Manual (confirmare)</button>
    </div>

    <main class="flex-1 p-4 space-y-4">
        <!-- Manual entry -->
        <div class="bg-gray-900 rounded-2xl p-4">
            <label class="block text-xs text-gray-500 uppercase mb-2 tracking-wider">Cod rezervare / bilet</label>
            <div class="flex gap-2">
                <input type="text"
                       id="code-input"
                       autofocus
                       autocomplete="off"
                       autocapitalize="characters"
                       inputmode="text"
                       onkeydown="if(event.key==='Enter'){submitCode();}"
                       class="flex-1 bg-gray-800 border-2 border-gray-700 rounded-lg px-4 py-3 text-white font-mono text-lg uppercase tracking-wider focus:outline-none focus:border-blue-500"
                       placeholder="ABC123XYZ">
                <button onclick="submitCode()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 rounded-lg transition">Validează</button>
            </div>
            <p class="mt-2 text-xs text-gray-500">Scanează QR cu Bluetooth (mod tastatură) sau tastează codul de pe bilet/confirmare.</p>
        </div>

        <!-- Camera scanner toggle -->
        <div class="bg-gray-900 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-gray-300">Scanner cameră</span>
                <button onclick="toggleCamera()" id="camera-toggle" class="text-xs font-medium px-3 py-1 rounded-lg bg-gray-800 text-gray-300 hover:bg-gray-700 transition">Pornește camera</button>
            </div>
            <div id="qr-reader" class="hidden rounded-xl overflow-hidden"></div>
        </div>

        <!-- Result panel -->
        <div id="result-panel" class="bg-gray-900 rounded-2xl p-4 hidden">
            <div id="result-content"></div>
            <div id="confirm-actions" class="mt-4 flex gap-2 hidden">
                <button onclick="confirmCheckin()" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg">Confirmă check-in</button>
                <button onclick="clearResult()" class="px-5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg">Anulează</button>
            </div>
        </div>

        <!-- Recent scans -->
        <div class="bg-gray-900 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-gray-300">Scanări recente</span>
                <button onclick="clearHistory()" class="text-xs text-gray-500 hover:text-gray-300">Curăță</button>
            </div>
            <div id="history" class="space-y-2 max-h-72 overflow-y-auto">
                <p class="text-xs text-gray-500 text-center py-4">Nicio scanare încă</p>
            </div>
        </div>
    </main>
</div>

<script>
const API_BASE   = '{{ $apiBaseUrl }}';
const API_KEY    = '{{ $apiKey }}';
let TOKEN        = localStorage.getItem('gate-activity-token');
let mode         = 'rapid';   // rapid | manual
let pendingCode  = null;      // in manual mode, the code awaiting confirmation
let cameraScanner = null;
let history = [];

// --- Utilities ----------------------------------------------------------

function flash(color) {
    const el = document.getElementById('flash-' + color);
    if (! el) return;
    el.classList.add('active');
    setTimeout(() => el.classList.remove('active'), 200);
    if (navigator.vibrate) navigator.vibrate(color === 'green' ? 60 : 120);
}

async function api(path, opts = {}) {
    opts.headers = Object.assign({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-API-KEY': API_KEY,
    }, opts.headers || {});
    if (TOKEN) opts.headers['Authorization'] = 'Bearer ' + TOKEN;
    const res = await fetch(API_BASE + path, opts);
    let data = null;
    try { data = await res.json(); } catch (e) {}
    return { ok: res.ok, status: res.status, data };
}

// --- Auth ---------------------------------------------------------------

async function doLogin() {
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;
    const err = document.getElementById('login-error');
    const btn = document.getElementById('login-btn');

    err.classList.add('hidden');
    if (! email || ! password) {
        err.textContent = 'Email și parolă obligatorii.';
        err.classList.remove('hidden');
        return;
    }
    btn.disabled = true;
    btn.textContent = 'Se conectează...';

    const r = await api('/organizer/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
    });
    btn.disabled = false;
    btn.textContent = 'Autentificare';

    if (r.ok && r.data?.data?.token) {
        TOKEN = r.data.data.token;
        localStorage.setItem('gate-activity-token', TOKEN);
        showScanner();
    } else {
        err.textContent = r.data?.message || 'Autentificare eșuată.';
        err.classList.remove('hidden');
    }
}

function doLogout() {
    TOKEN = null;
    localStorage.removeItem('gate-activity-token');
    history = [];
    renderHistory();
    document.getElementById('scanner-screen').classList.add('hidden');
    document.getElementById('scanner-screen').classList.remove('flex');
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('login-screen').classList.add('flex');
    if (cameraScanner) {
        try { cameraScanner.stop(); cameraScanner.clear(); } catch (e) {}
        cameraScanner = null;
    }
}

function showScanner() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('login-screen').classList.remove('flex');
    document.getElementById('scanner-screen').classList.remove('hidden');
    document.getElementById('scanner-screen').classList.add('flex');
    setTimeout(() => document.getElementById('code-input')?.focus(), 80);
}

// --- Mode ---------------------------------------------------------------

function setMode(m) {
    mode = m;
    pendingCode = null;
    clearResult();
    document.getElementById('btn-rapid').className =
        'flex-1 py-2 rounded-lg text-sm font-medium transition ' +
        (m === 'rapid' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400');
    document.getElementById('btn-manual').className =
        'flex-1 py-2 rounded-lg text-sm font-medium transition ' +
        (m === 'manual' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400');
}

// --- Scan flow ----------------------------------------------------------

async function submitCode() {
    const input = document.getElementById('code-input');
    const raw = (input.value || '').trim();
    if (! raw) return;
    input.value = '';
    input.focus();

    if (mode === 'manual') {
        // Preview first, confirm second.
        await lookupCode(raw);
        return;
    }
    // Rapid: hit the check-in endpoint directly.
    await performCheckin(raw);
}

async function lookupCode(code) {
    const r = await api('/organizer/activity-bookings/lookup/' + encodeURIComponent(code), { method: 'GET' });
    if (! r.ok) {
        showError(r.data?.message || 'Cod necunoscut');
        flash('red');
        addHistory({ status: 'error', code, message: r.data?.message || 'Cod necunoscut' });
        return;
    }
    pendingCode = code;
    showPreview(r.data?.data, true);
}

async function performCheckin(code) {
    const r = await api('/organizer/activity-bookings/check-in/' + encodeURIComponent(code), { method: 'POST' });
    if (r.ok) {
        flash('green');
        showSuccess(r.data?.data, 'Validat cu succes');
        addHistory({ status: 'ok', code, payload: r.data?.data });
    } else if (r.status === 400 && r.data?.message?.toLowerCase()?.includes('deja')) {
        flash('amber');
        showAlreadyChecked(r.data, 'Deja validat');
        addHistory({ status: 'already', code, payload: r.data });
    } else {
        flash('red');
        showError(r.data?.message || 'Validare eșuată');
        addHistory({ status: 'error', code, message: r.data?.message || 'Validare eșuată' });
    }
    pendingCode = null;
    document.getElementById('confirm-actions').classList.add('hidden');
}

async function confirmCheckin() {
    if (! pendingCode) return;
    await performCheckin(pendingCode);
}

function clearResult() {
    pendingCode = null;
    document.getElementById('result-panel').classList.add('hidden');
    document.getElementById('confirm-actions').classList.add('hidden');
    document.getElementById('result-content').innerHTML = '';
}

function showPreview(d, withConfirm) {
    if (! d) return;
    document.getElementById('result-panel').classList.remove('hidden');
    document.getElementById('result-content').innerHTML = buildResultHtml(d, 'preview');
    document.getElementById('confirm-actions').classList.toggle('hidden', ! withConfirm);
}

function showSuccess(d, label) {
    document.getElementById('result-panel').classList.remove('hidden');
    document.getElementById('result-content').innerHTML =
        '<div class="text-green-400 text-sm font-semibold mb-2">✓ ' + label + '</div>' +
        buildResultHtml(d, 'ok');
    document.getElementById('confirm-actions').classList.add('hidden');
}

function showAlreadyChecked(payload, label) {
    document.getElementById('result-panel').classList.remove('hidden');
    document.getElementById('result-content').innerHTML =
        '<div class="text-amber-400 text-sm font-semibold mb-2">⚠ ' + label + '</div>' +
        buildResultHtml(payload, 'already');
    document.getElementById('confirm-actions').classList.add('hidden');
}

function showError(msg) {
    document.getElementById('result-panel').classList.remove('hidden');
    document.getElementById('result-content').innerHTML =
        '<div class="text-red-400 font-semibold">✗ ' + msg + '</div>';
    document.getElementById('confirm-actions').classList.add('hidden');
}

function buildResultHtml(d, kind) {
    if (! d) return '';
    const b = d.booking || {};
    const a = d.activity || {};
    const c = d.customer || {};
    const t = d.ticket || null;

    const rows = [];
    if (a.title)                rows.push(['Activitate', a.title]);
    if (b.booking_date)         rows.push(['Data', b.booking_date]);
    if (b.slot_start_time)      rows.push(['Slot', b.slot_start_time + (b.slot_end_time ? ' – ' + b.slot_end_time : '')]);
    if (b.participants_count)   rows.push(['Locuri', b.participants_count]);
    if (b.confirmation_code)    rows.push(['Cod rezervare', b.confirmation_code]);
    if (c.name)                 rows.push(['Client', c.name]);
    if (c.email)                rows.push(['Email', c.email]);
    if (t?.code)                rows.push(['Cod bilet', t.code]);
    if (t?.attendee_name)       rows.push(['Beneficiar', t.attendee_name]);

    return '<div class="space-y-1.5">' + rows.map(([k, v]) =>
        '<div class="flex justify-between gap-3 text-sm"><span class="text-gray-500">' + k + '</span><span class="text-white font-medium">' + v + '</span></div>'
    ).join('') + '</div>';
}

// --- History ------------------------------------------------------------

function addHistory(entry) {
    entry.time = new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    history.unshift(entry);
    if (history.length > 30) history.pop();
    renderHistory();
}

function clearHistory() {
    history = [];
    renderHistory();
}

function renderHistory() {
    const wrap = document.getElementById('history');
    if (history.length === 0) {
        wrap.innerHTML = '<p class="text-xs text-gray-500 text-center py-4">Nicio scanare încă</p>';
        return;
    }
    wrap.innerHTML = history.map(h => {
        const colorMap = { ok: 'bg-green-500/15 border-green-500/40 text-green-300', already: 'bg-amber-500/15 border-amber-500/40 text-amber-300', error: 'bg-red-500/15 border-red-500/40 text-red-300' };
        const label    = h.status === 'ok' ? 'Validat' : h.status === 'already' ? 'Deja validat' : 'Eșuat';
        const sub = h.payload?.activity?.title || h.payload?.booking?.confirmation_code || h.message || '';
        return '<div class="rounded-xl border ' + (colorMap[h.status] || '') + ' px-3 py-2">' +
            '<div class="flex justify-between gap-2 text-xs">' +
                '<span class="font-mono font-semibold uppercase">' + (h.code || '?') + '</span>' +
                '<span class="opacity-70">' + h.time + '</span>' +
            '</div>' +
            '<div class="text-xs mt-1 truncate"><strong>' + label + '</strong>' + (sub ? ' · ' + sub : '') + '</div>' +
        '</div>';
    }).join('');
}

// --- Camera scanner ----------------------------------------------------

function toggleCamera() {
    const reader = document.getElementById('qr-reader');
    const btn = document.getElementById('camera-toggle');
    if (cameraScanner) {
        try { cameraScanner.stop().then(() => cameraScanner.clear()); } catch (e) {}
        cameraScanner = null;
        reader.classList.add('hidden');
        btn.textContent = 'Pornește camera';
        return;
    }
    reader.classList.remove('hidden');
    btn.textContent = 'Oprește camera';
    cameraScanner = new Html5Qrcode('qr-reader');
    cameraScanner.start(
        { facingMode: 'environment' },
        { fps: 8, qrbox: { width: 240, height: 240 } },
        async (decodedText) => {
            // Avoid scanning the same code in rapid succession.
            if (window._lastScannedAt && Date.now() - window._lastScannedAt < 2000 && window._lastScanned === decodedText) return;
            window._lastScannedAt = Date.now();
            window._lastScanned = decodedText;
            if (mode === 'manual') {
                await lookupCode(decodedText);
            } else {
                await performCheckin(decodedText);
            }
        },
        () => { /* swallow per-frame "no QR detected" */ }
    ).catch(err => {
        cameraScanner = null;
        reader.classList.add('hidden');
        btn.textContent = 'Pornește camera';
        showError('Camera nu poate fi pornită: ' + (err?.message || err));
    });
}

// --- Boot ---------------------------------------------------------------

if (TOKEN) {
    showScanner();
} else {
    document.getElementById('login-screen').classList.add('flex');
}
</script>

</body>
</html>

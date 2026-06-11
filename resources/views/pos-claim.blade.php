<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Revendică biletele</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f3ff;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            color: #fff;
            padding: 24px 20px;
            text-align: center;
        }
        .card-header h1 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .card-header .event-info {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 8px;
            line-height: 1.4;
        }
        .card-body {
            padding: 24px 20px;
        }
        .intro-text {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .step-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
        }
        .step-dot.active {
            background: #7c3aed;
            color: #fff;
        }
        .step-dot.done {
            background: #10b981;
            color: #fff;
        }
        .step-dot.inactive {
            background: #e5e7eb;
            color: #9ca3af;
        }
        .step-line {
            width: 40px;
            height: 2px;
            background: #e5e7eb;
        }
        .step-line.done {
            background: #10b981;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 4px;
        }
        .form-group label .required {
            color: #ef4444;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            color: #1f2937;
            background: #fff;
            transition: border-color 0.2s;
            -webkit-appearance: none;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        .form-group input.error,
        .form-group select.error {
            border-color: #ef4444;
        }
        .form-group .error-text {
            font-size: 12px;
            color: #ef4444;
            margin-top: 2px;
            display: none;
        }
        .form-group .error-text.visible {
            display: block;
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-primary {
            background: #7c3aed;
            color: #fff;
            margin-top: 8px;
        }
        .btn-secondary {
            background: transparent;
            color: #6b7280;
            margin-top: 8px;
            font-weight: 500;
            text-decoration: underline;
        }
        .btn-secondary:hover {
            color: #374151;
        }
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error / Success states */
        .state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .state-icon.success { background: #d1fae5; }
        .state-icon.error { background: #fee2e2; }
        .state-icon svg { width: 32px; height: 32px; }
        .state-title {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 8px;
        }
        .state-message {
            font-size: 14px;
            color: #6b7280;
            text-align: center;
            line-height: 1.5;
        }

        .optional-label {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 400;
        }

        .options-screen {
            text-align: center;
        }
        .btn-download {
            background: #10b981;
            color: #fff;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-download:active {
            transform: scale(0.98);
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #9ca3af;
            font-size: 13px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        .btn-points {
            background: #7c3aed;
            color: #fff;
            margin-top: 0;
        }
        .points-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            line-height: 1.4;
        }

        .account-toggle {
            display: flex;
            background: #f3f4f6;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 18px;
            gap: 4px;
        }
        .account-tab {
            flex: 1;
            border: 0;
            background: transparent;
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            padding: 9px 12px;
            border-radius: 7px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .account-tab.active {
            background: #fff;
            color: #7c3aed;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .account-login-panel {
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
            text-align: center;
        }
        .account-login-text {
            color: #4c1d95;
            font-size: 13px;
            margin: 0 0 14px;
            line-height: 1.5;
        }
        .account-login-hint {
            color: #6b7280;
            font-size: 12px;
            margin: 12px 0 0;
        }
        .account-login-hint a {
            color: #7c3aed;
            font-weight: 600;
            text-decoration: none;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .alert.alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert.visible {
            display: block;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h1>🎫 Revendică biletele</h1>
        @if($claim)
            <div class="event-info">
                <strong>{{ $claim->event_name }}</strong>
                @if($claim->event_date)
                    <br>{{ $claim->event_date }}
                @endif
                @if($claim->venue_name)
                    — {{ $claim->venue_name }}
                @endif
            </div>
        @endif
    </div>

    <div class="card-body">

        {{-- Error states --}}
        @if($error === 'not_found')
            <div style="padding: 20px 0; text-align: center;">
                <div class="state-icon error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="state-title">Link invalid</div>
                <div class="state-message">Acest link nu este valid sau a fost șters.</div>
            </div>

        @elseif($error === 'expired')
            <div style="padding: 20px 0; text-align: center;">
                <div class="state-icon error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                </div>
                <div class="state-title">Link expirat</div>
                <div class="state-message">Acest link a expirat. Contactează organizatorul pentru asistență.</div>
            </div>

        @elseif($error === 'already_claimed')
            <div style="padding: 20px 0; text-align: center;">
                <div class="state-icon success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="8,12 11,15 16,9"/></svg>
                </div>
                <div class="state-title">Deja completat</div>
                <div class="state-message">Datele au fost deja completate pentru această comandă. Verifică-ți email-ul pentru bilete.</div>
            </div>

        @else
            {{-- Initial options screen --}}
            <div id="step-options" style="{{ ($step ?? 'required') !== 'required' ? 'display:none;' : '' }}">
                <div class="options-screen">
                    <p class="intro-text" style="text-align:center;">
                        Biletele tale pentru <strong>{{ $claim->event_name }}</strong> sunt gata!
                    </p>

                    <a href="/claim/{{ $claim->token }}/download" class="btn btn-download">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Descarcă biletul direct
                    </a>

                    <div class="divider">sau</div>

                    <div class="points-badge">
                        🎁 Completează datele și câștigă <strong>100 puncte AmBilet</strong> pe care le poți folosi ca reducere la orice eveniment viitor!
                    </div>

                    {{-- Logged-in-customer shortcut. Hidden by default; JS reveals
                         it when localStorage has an AmBilet customer session,
                         replacing the manual form path with a one-click pairing. --}}
                    <button type="button" class="btn btn-points" id="btn-link-account" style="display:none;">
                        Atașează biletele la contul <span id="link-account-name"></span>
                    </button>

                    <button type="button" class="btn btn-points" id="btn-show-form">
                        Completează și câștigă 100 puncte
                    </button>
                </div>
            </div>

            {{-- Claim form (hidden initially, shown on button click) --}}
            <div id="step-required" style="display:none;">
                <div class="step-indicator">
                    <div class="step-dot active" id="dot1">1</div>
                    <div class="step-line" id="line1"></div>
                    <div class="step-dot inactive" id="dot2">2</div>
                </div>

                {{-- Account-mode toggle — pick between filling the form
                     manually (gain 100 points as a new customer) and using
                     an existing AmBilet account (skip the form entirely
                     once logged in). --}}
                <div class="account-toggle">
                    <button type="button" class="account-tab active" data-mode="new" id="tab-new">Sunt client nou</button>
                    <button type="button" class="account-tab" data-mode="existing" id="tab-existing">Am cont AmBilet</button>
                </div>

                {{-- Panel shown when the visitor picks 'Am cont AmBilet' AND
                     isn't already logged in. JS hides this if it detects a
                     customer token in localStorage (auto-pair takes over). --}}
                <div id="account-login-panel" class="account-login-panel" style="display:none;">
                    <p class="account-login-text">
                        Conectează-te la contul tău AmBilet — biletele se atașează automat și apar în secțiunea „Biletele mele".
                    </p>
                    <a href="/login?redirect=/claim/{{ $claim->token }}" class="btn btn-primary">
                        Conectare la contul AmBilet
                    </a>
                    <p class="account-login-hint">
                        Nu ai cont? <a href="/register?redirect=/claim/{{ $claim->token }}">Crează unul în 30 secunde →</a>
                    </p>
                </div>

                <div class="points-badge" id="new-customer-badge" style="text-align:center;">
                    🎁 Completează formularul și primești <strong>100 puncte AmBilet</strong> — le poți folosi ca reducere la orice eveniment viitor!
                </div>

                <div class="alert alert-error" id="error-required"></div>

                <form id="form-required" novalidate>
                    <div class="form-group">
                        <label>Prenume <span class="required">*</span></label>
                        <input type="text" name="first_name" id="first_name" placeholder="ex: Andrei" autocomplete="given-name" required>
                        <div class="error-text" id="err-first_name">Prenumele este obligatoriu</div>
                    </div>
                    <div class="form-group">
                        <label>Nume <span class="required">*</span></label>
                        <input type="text" name="last_name" id="last_name" placeholder="ex: Popescu" autocomplete="family-name" required>
                        <div class="error-text" id="err-last_name">Numele este obligatoriu</div>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="email" placeholder="ex: andrei@email.com" autocomplete="email" required>
                        <div class="error-text" id="err-email">Introdu o adresă de email validă</div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-required">
                        Continuă
                    </button>
                </form>
            </div>

            {{-- Step 2: Optional --}}
            <div id="step-optional" style="{{ ($step ?? 'required') !== 'optional' ? 'display:none;' : '' }}">
                <div class="step-indicator">
                    <div class="step-dot done" id="dot1b">✓</div>
                    <div class="step-line done" id="line1b"></div>
                    <div class="step-dot active" id="dot2b">2</div>
                </div>

                <p class="intro-text">
                    Biletele vor fi trimise pe email. Poți completa și următoarele detalii opționale:
                </p>

                <div class="alert alert-error" id="error-optional"></div>

                <form id="form-optional" novalidate>
                    <div class="form-group">
                        <label>Telefon <span class="optional-label">(opțional)</span></label>
                        <input type="tel" name="phone" id="phone" placeholder="ex: 0712 345 678" autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label>Oraș <span class="optional-label">(opțional)</span></label>
                        <input type="text" name="city" id="city" placeholder="ex: București" autocomplete="address-level2">
                    </div>
                    <div class="form-group">
                        <label>Gen <span class="optional-label">(opțional)</span></label>
                        <select name="gender" id="gender">
                            <option value="">— Selectează —</option>
                            <option value="male">Masculin</option>
                            <option value="female">Feminin</option>
                            <option value="other">Altul</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data nașterii <span class="optional-label">(opțional)</span></label>
                        <input type="date" name="date_of_birth" id="date_of_birth" max="{{ date('Y-m-d') }}">
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-optional">
                        Salvează
                    </button>
                    <button type="button" class="btn btn-secondary" id="btn-skip">
                        Omite acest pas
                    </button>
                </form>
            </div>

            {{-- Success screen --}}
            <div id="step-success" style="display:none;">
                <div style="padding: 20px 0; text-align: center;">
                    <div class="state-icon success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="8,12 11,15 16,9"/></svg>
                    </div>
                    <div class="state-title">Gata!</div>
                    <div class="state-message">
                        Datele tale au fost salvate cu succes.<br>
                        Vei primi biletele pe email în câteva minute.
                    </div>
                </div>
            </div>

        @endif

    </div>
</div>

@if(!$error && $claim)
<script>
(function() {
    const TOKEN = @json($claim->token);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    // ─── Logged-in AmBilet customer detection ──────────────────────────
    // AmbiletAuth (assets/js/auth.js) stores the customer session in
    // localStorage under these keys. We don't import the script here
    // (this page is standalone), we just read the storage directly.
    const ambiletCustomerToken = (() => {
        try {
            if (localStorage.getItem('ambilet_user_type') !== 'customer') return null;
            return localStorage.getItem('ambilet_customer_token') || null;
        } catch { return null; }
    })();
    const ambiletCustomerData = (() => {
        try {
            const raw = localStorage.getItem('ambilet_customer_data');
            return raw ? JSON.parse(raw) : null;
        } catch { return null; }
    })();
    const isLoggedInCustomer = !!(ambiletCustomerToken && ambiletCustomerData);

    function showError(containerId, msg) {
        const el = document.getElementById(containerId);
        el.textContent = msg;
        el.classList.add('visible');
    }

    function hideError(containerId) {
        document.getElementById(containerId).classList.remove('visible');
    }

    function setLoading(btnId, loading) {
        const btn = document.getElementById(btnId);
        if (loading) {
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.innerHTML = '<span class="spinner"></span> Se trimite...';
        } else {
            btn.disabled = false;
            btn.textContent = btn.dataset.originalText || 'Trimite';
        }
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    async function postJson(url, data) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        };
        // Attach the AmBilet customer Sanctum token whenever the visitor
        // is logged in. The backend pairs the order to that customer
        // regardless of what the form said.
        if (ambiletCustomerToken) {
            headers['Authorization'] = 'Bearer ' + ambiletCustomerToken;
        }
        const res = await fetch(url, {
            method: 'POST',
            headers,
            body: JSON.stringify(data),
        });
        const json = await res.json();
        if (!res.ok) {
            throw new Error(json.message || Object.values(json.errors || {}).flat().join(', ') || 'Eroare necunoscută');
        }
        return json;
    }

    // Show form button (from options screen)
    const btnShowForm = document.getElementById('btn-show-form');
    if (btnShowForm) {
        btnShowForm.addEventListener('click', function() {
            document.getElementById('step-options').style.display = 'none';
            document.getElementById('step-required').style.display = 'block';
        });
    }

    // ─── Logged-in customer: surface the one-click pairing button ──────
    if (isLoggedInCustomer) {
        const linkBtn = document.getElementById('btn-link-account');
        const linkName = document.getElementById('link-account-name');
        if (linkBtn && linkName) {
            const displayName = ambiletCustomerData.first_name
                || ambiletCustomerData.email
                || 'tău';
            linkName.textContent = displayName;
            linkBtn.style.display = '';
            // Hide the "Completează datele" CTA — the account shortcut
            // is the natural path for a logged-in user.
            if (btnShowForm) btnShowForm.style.display = 'none';

            linkBtn.addEventListener('click', async function() {
                linkBtn.disabled = true;
                const orig = linkBtn.innerHTML;
                linkBtn.innerHTML = '<span class="spinner"></span> Se atașează...';
                try {
                    const resp = await postJson('/claim/' + TOKEN + '/step1', {
                        first_name: ambiletCustomerData.first_name || '',
                        last_name: ambiletCustomerData.last_name || '',
                        email: ambiletCustomerData.email,
                    });
                    if (resp.success) {
                        // Skip step-options and step-required entirely.
                        document.getElementById('step-options').style.display = 'none';
                        document.getElementById('step-required').style.display = 'none';
                        // Land on the optional details step so the user can
                        // top up their profile (phone / city / dob) if they
                        // want — or skip.
                        document.getElementById('step-optional').style.display = 'block';
                    } else {
                        throw new Error(resp.message || 'Nu s-a putut atașa contul');
                    }
                } catch (err) {
                    alert(err.message || 'Eroare la atașarea contului');
                    linkBtn.disabled = false;
                    linkBtn.innerHTML = orig;
                }
            });
        }
    }

    // ─── Account mode toggle inside step-required ──────────────────────
    const tabNew = document.getElementById('tab-new');
    const tabExisting = document.getElementById('tab-existing');
    const loginPanel = document.getElementById('account-login-panel');
    const newBadge = document.getElementById('new-customer-badge');
    const formReqEl = document.getElementById('form-required');

    function setMode(mode) {
        if (mode === 'existing') {
            tabNew.classList.remove('active');
            tabExisting.classList.add('active');
            if (loginPanel) loginPanel.style.display = '';
            if (newBadge) newBadge.style.display = 'none';
            if (formReqEl) formReqEl.style.display = 'none';
        } else {
            tabNew.classList.add('active');
            tabExisting.classList.remove('active');
            if (loginPanel) loginPanel.style.display = 'none';
            if (newBadge) newBadge.style.display = '';
            if (formReqEl) formReqEl.style.display = '';
        }
    }
    if (tabNew && tabExisting) {
        tabNew.addEventListener('click', () => setMode('new'));
        tabExisting.addEventListener('click', () => setMode('existing'));
    }

    // If the visitor is already logged in but lands on the form path
    // (rare — they'd usually use the account shortcut on step-options),
    // pre-fill the form so they only need to confirm.
    if (isLoggedInCustomer) {
        const fi = document.getElementById('first_name');
        const li = document.getElementById('last_name');
        const ei = document.getElementById('email');
        if (fi && !fi.value) fi.value = ambiletCustomerData.first_name || '';
        if (li && !li.value) li.value = ambiletCustomerData.last_name || '';
        if (ei && !ei.value) ei.value = ambiletCustomerData.email || '';
    }

    // Step 1 form
    const formReq = document.getElementById('form-required');
    if (formReq) {
        formReq.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError('error-required');

            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();

            // Client validation
            let valid = true;

            if (!firstName) {
                document.getElementById('first_name').classList.add('error');
                document.getElementById('err-first_name').classList.add('visible');
                valid = false;
            } else {
                document.getElementById('first_name').classList.remove('error');
                document.getElementById('err-first_name').classList.remove('visible');
            }

            if (!lastName) {
                document.getElementById('last_name').classList.add('error');
                document.getElementById('err-last_name').classList.add('visible');
                valid = false;
            } else {
                document.getElementById('last_name').classList.remove('error');
                document.getElementById('err-last_name').classList.remove('visible');
            }

            if (!email || !validateEmail(email)) {
                document.getElementById('email').classList.add('error');
                document.getElementById('err-email').classList.add('visible');
                valid = false;
            } else {
                document.getElementById('email').classList.remove('error');
                document.getElementById('err-email').classList.remove('visible');
            }

            if (!valid) return;

            setLoading('btn-required', true);

            try {
                await postJson('/claim/' + TOKEN + '/step1', {
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                });

                // Show step 2
                document.getElementById('step-required').style.display = 'none';
                document.getElementById('step-optional').style.display = 'block';
            } catch (err) {
                showError('error-required', err.message);
            } finally {
                setLoading('btn-required', false);
            }
        });
    }

    // Step 2 form
    const formOpt = document.getElementById('form-optional');
    if (formOpt) {
        formOpt.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError('error-optional');
            setLoading('btn-optional', true);

            const data = {};
            const phone = document.getElementById('phone').value.trim();
            const city = document.getElementById('city').value.trim();
            const gender = document.getElementById('gender').value;
            const dob = document.getElementById('date_of_birth').value;

            if (phone) data.phone = phone;
            if (city) data.city = city;
            if (gender) data.gender = gender;
            if (dob) data.date_of_birth = dob;

            try {
                await postJson('/claim/' + TOKEN + '/step2', data);
                showSuccess();
            } catch (err) {
                showError('error-optional', err.message);
            } finally {
                setLoading('btn-optional', false);
            }
        });
    }

    // Skip button
    const btnSkip = document.getElementById('btn-skip');
    if (btnSkip) {
        btnSkip.addEventListener('click', async function() {
            btnSkip.disabled = true;
            btnSkip.textContent = 'Se procesează...';

            try {
                await postJson('/claim/' + TOKEN + '/skip', {});
                showSuccess();
            } catch (err) {
                showError('error-optional', err.message);
                btnSkip.disabled = false;
                btnSkip.textContent = 'Omite acest pas';
            }
        });
    }

    function showSuccess() {
        const opts = document.getElementById('step-options');
        if (opts) opts.style.display = 'none';
        document.getElementById('step-required').style.display = 'none';
        document.getElementById('step-optional').style.display = 'none';
        document.getElementById('step-success').style.display = 'block';
    }
})();
</script>
@endif

</body>
</html>

<?php
/**
 * Extended Artist — Landing / Upsell + Status
 * Punctul de intrare pentru pachetul premium. Afiseaza statul curent
 * (inactiv / trial / active / cancelled / expired) si optiunile relevante:
 *   - inactiv: buton "Pornește trial 30 zile" + buton "Abonează-te"
 *   - trial:   countdown pana la expirare + buton "Treci pe abonament"
 *   - active:  status verde + linkuri catre cele 4 module + buton "Cancel"
 *   - expired: buton "Reactivează"
 *
 * Datele se incarca client-side via /api/proxy.php?action=artist.extended-artist.status
 * (acelasi pattern ca dashboard.php).
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Vizualizare';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <header class="mb-8 flex items-start gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-purple-600 shadow-lg">
                <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Extended Artist</h1>
                <p class="mt-1 text-muted">Setul premium pentru artiști: Fan CRM, Booking Marketplace, Smart EPK, Tour Optimizer.</p>
            </div>
        </header>

        <div id="ea-status-card" class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-2xl border border-border bg-white p-6 text-center text-muted">
                Se încarcă statusul abonamentului…
            </div>
        </div>

        <div class="mx-auto mt-10 max-w-5xl">
            <h2 class="mb-4 text-lg font-bold text-secondary">Ce primești</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <article class="rounded-2xl border border-border bg-white p-5">
                    <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="mb-1 text-base font-semibold text-secondary">Fan CRM</h3>
                    <p class="text-sm text-muted">Înțelegi cine sunt fanii tăi, unde locuiesc, ce cumpără și care sunt cei mai loiali.</p>
                </article>
                <article class="rounded-2xl border border-border bg-white p-5">
                    <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <h3 class="mb-1 text-base font-semibold text-secondary">Booking Marketplace</h3>
                    <p class="text-sm text-muted">Primești cereri de booking de la organizatori, venue-uri și agenții. Calendar + contracte automate.</p>
                </article>
                <article class="rounded-2xl border border-border bg-white p-5">
                    <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="mb-1 text-base font-semibold text-secondary">Smart EPK</h3>
                    <p class="text-sm text-muted">Press kit dinamic, share-abil, cu stats verificate live din platformă. Înlocuiește PDF-ul mort.</p>
                </article>
                <article class="rounded-2xl border border-border bg-white p-5">
                    <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="mb-1 text-base font-semibold text-secondary">Tour Optimizer</h3>
                    <p class="text-sm text-muted">Planifică turnee strategic: hartă cu densitatea fanilor, predicții bilete, optimizare rută.</p>
                </article>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    const card = document.getElementById('ea-status-card');
    if (!card) return;

    const token = localStorage.getItem('ambilet_artist_token');
    if (!token) {
        card.innerHTML = '<div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-900">Trebuie sa fii autentificat ca artist pentru a accesa Extended Artist.</div>';
        return;
    }

    const headers = { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token };

    Promise.all([
        fetch('/api/proxy.php?action=artist.extended-artist.status', { headers }).then(r => r.json()),
        fetch('/api/proxy.php?action=artist.extended-artist.pricing', { headers }).then(r => r.ok ? r.json() : null),
    ])
        .then(([statusResp, pricingResp]) => {
            const status = statusResp?.data || {};
            const pricing = pricingResp?.data || {};
            renderStatusCard(card, status, pricing);
        })
        .catch(() => {
            card.innerHTML = '<div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center text-red-900">Eroare la incarcarea statusului. Reincarca pagina.</div>';
        });

    function renderStatusCard(el, status, pricing) {
        const monthlyPrice = pricing.monthly_price || 99;
        const currency = pricing.currency || 'RON';
        const trialDays = pricing.trial_days || 30;

        if (status.enabled && status.status === 'active') {
            el.innerHTML = `
                <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-emerald-900">Abonament activ</h3>
                            <p class="text-sm text-emerald-700">${escapeHtml(detailLine(status))}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="/artist/cont/extended-artist/fan-crm" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Deschide Fan CRM</a>
                                ${status.granted_by === 'self_purchase' ? '<button id="ea-cancel-btn" class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Cancelează abonament</button>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            wireCancel();
            return;
        }

        if (status.enabled && status.status === 'trial') {
            el.innerHTML = `
                <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-500 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-blue-900">Trial activ</h3>
                            <p class="text-sm text-blue-700">${escapeHtml(detailLine(status))}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="/artist/cont/extended-artist/fan-crm" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Deschide Fan CRM</a>
                                <button id="ea-subscribe-btn" class="rounded-xl border border-blue-400 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">Treci pe abonament (${monthlyPrice} ${currency}/luna)</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            wireSubscribe();
            return;
        }

        if (status.enabled && status.status === 'cancelled') {
            el.innerHTML = `
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <h3 class="text-lg font-bold text-amber-900">Abonament cancelat</h3>
                    <p class="mt-1 text-sm text-amber-800">${escapeHtml(detailLine(status))}</p>
                    <div class="mt-4">
                        <button id="ea-subscribe-btn" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Reactivează (${monthlyPrice} ${currency}/luna)</button>
                    </div>
                </div>
            `;
            wireSubscribe();
            return;
        }

        const expiredCopy = status.status === 'expired' ? 'Trial-ul / abonamentul tău a expirat.' : 'Nu ai un abonament activ.';
        el.innerHTML = `
            <div class="rounded-2xl border border-border bg-white p-6">
                <h3 class="text-lg font-bold text-secondary">${expiredCopy}</h3>
                <p class="mt-1 text-sm text-muted">Activează Extended Artist și primești toate cele 4 module: Fan CRM, Booking Marketplace, Smart EPK, Tour Optimizer.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    ${status.can_start_trial ? `<button id="ea-trial-btn" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Pornește trial gratuit (${trialDays} zile)</button>` : ''}
                    <button id="ea-subscribe-btn" class="rounded-xl border border-border bg-white px-4 py-2 text-sm font-semibold text-secondary hover:bg-surface">${status.can_start_trial ? 'Sari peste trial — abonează-te' : 'Abonează-te'} (${monthlyPrice} ${currency}/luna)</button>
                </div>
            </div>
        `;
        wireTrial();
        wireSubscribe();
    }

    function detailLine(status) {
        const parts = [];
        if (status.granted_by === 'admin_override') parts.push('Activat manual de echipa marketplace');
        if (status.granted_by === 'self_purchase') parts.push('Plata recurenta lunara');
        if (status.granted_by === 'trial') parts.push('Trial gratuit');
        if (status.trial_ends_at) parts.push('Trial expira: ' + new Date(status.trial_ends_at).toLocaleDateString('ro-RO'));
        if (status.expires_at) parts.push('Urmatoarea facturare: ' + new Date(status.expires_at).toLocaleDateString('ro-RO'));
        return parts.join(' • ');
    }

    function wireTrial() {
        const btn = document.getElementById('ea-trial-btn');
        if (!btn) return;
        btn.addEventListener('click', () => {
            btn.disabled = true;
            btn.textContent = 'Se porneste...';
            fetch('/api/proxy.php?action=artist.extended-artist.start-trial', { method: 'POST', headers })
                .then(r => r.json())
                .then(() => location.reload());
        });
    }

    function wireSubscribe() {
        const btn = document.getElementById('ea-subscribe-btn');
        if (!btn) return;
        btn.addEventListener('click', () => {
            btn.disabled = true;
            btn.textContent = 'Se creeaza comanda...';
            fetch('/api/proxy.php?action=artist.extended-artist.subscribe', { method: 'POST', headers })
                .then(r => r.json())
                .then(payload => {
                    const url = payload?.data?.payment_url;
                    if (url) {
                        window.location.href = url;
                    } else {
                        alert(payload?.message || 'Comanda creata. Echipa va contacta in scurt timp pentru finalizarea platii.');
                        location.reload();
                    }
                });
        });
    }

    function wireCancel() {
        const btn = document.getElementById('ea-cancel-btn');
        if (!btn) return;
        btn.addEventListener('click', () => {
            if (!confirm('Sigur cancelezi abonamentul? Vei pastra acces pana la sfarsitul perioadei platite.')) return;
            btn.disabled = true;
            fetch('/api/proxy.php?action=artist.extended-artist.cancel', { method: 'POST', headers })
                .then(r => r.json())
                .then(() => location.reload());
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
})();
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>

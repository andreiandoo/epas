<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Semnează contractul';
$bodyClass = 'min-h-screen bg-surface';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
?>
<main class="max-w-3xl px-4 py-8 mx-auto">
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Semnează contractul</h1>
        <p class="mt-2 text-sm text-muted">Ultimul pas. Citește contractul, apoi semnează-l digital pentru a activa complet contul (publicare evenimente, retrageri).</p>
    </div>

    <div id="sc-loading" class="p-10 text-center">
        <div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div>
    </div>

    <div id="sc-content" class="hidden space-y-6">
        <!-- Contract preview -->
        <div class="overflow-hidden bg-white border rounded-2xl border-border">
            <div class="flex items-center justify-between px-5 py-3 border-b border-border">
                <span class="text-sm font-semibold text-secondary">Contract de prestări servicii</span>
                <a id="sc-open" href="#" target="_blank" class="text-xs font-medium text-primary hover:underline">Deschide în filă nouă ↗</a>
            </div>
            <iframe id="sc-frame" src="" class="w-full" style="height:520px;border:0;" title="Contract"></iframe>
            <div id="sc-frame-fallback" class="hidden px-5 py-4 text-sm text-muted">
                Previzualizarea nu a putut fi încărcată aici. <a id="sc-open2" href="#" target="_blank" class="font-medium text-primary hover:underline">Deschide contractul</a> ca să îl citești înainte de a semna.
            </div>
        </div>

        <!-- Signature -->
        <div class="p-5 bg-white border rounded-2xl border-border">
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-semibold text-secondary">Semnătura ta</label>
                <button type="button" id="sc-clear" class="text-xs font-medium text-muted hover:text-secondary">Șterge</button>
            </div>
            <div class="border rounded-xl border-border bg-slate-50" style="touch-action:none;">
                <canvas id="sc-pad" width="700" height="220" class="w-full" style="height:220px;display:block;cursor:crosshair;"></canvas>
            </div>
            <p class="mt-2 text-xs text-muted">Desenează semnătura cu mouse-ul sau cu degetul (pe telefon/tabletă).</p>

            <label class="flex items-start gap-2 mt-4 cursor-pointer">
                <input type="checkbox" id="sc-agree" class="w-4 h-4 mt-1 rounded border-border text-primary">
                <span class="text-sm text-secondary">Am citit și sunt de acord cu termenii Contractului de prestări servicii. Semnătura de mai sus reprezintă acordul meu, semnat electronic.</span>
            </label>

            <div class="flex flex-col gap-3 mt-5 sm:flex-row">
                <button type="button" id="sc-submit" disabled class="flex-1 px-4 py-3 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed">Semnează contractul</button>
                <a href="/organizator/events" class="px-4 py-3 text-sm font-medium text-center border rounded-lg text-muted border-border hover:bg-slate-50">Mai târziu (acces limitat)</a>
            </div>
        </div>
    </div>

    <div id="sc-signed" class="hidden p-10 text-center">
        <div class="flex items-center justify-center w-16 h-16 mx-auto rounded-full bg-emerald-100">
            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="mt-4 text-xl font-bold text-secondary">Contract semnat!</h2>
        <p class="mt-1 text-sm text-muted">Contul tău e complet activ. Te redirecționăm...</p>
    </div>
</main>

<script>
(function () {
    const $ = (id) => document.getElementById(id);

    async function boot() {
        let tries = 0;
        while (typeof AmbiletAPI === 'undefined' && tries < 20) { await new Promise(r => setTimeout(r, 100)); tries++; }
        if (typeof AmbiletAuth === 'undefined' || !AmbiletAuth.isOrganizer()) {
            window.location.href = '/organizator/login';
            return;
        }

        let data;
        try {
            const res = await AmbiletAPI.get('/organizer/contract');
            data = res.data || {};
        } catch (e) {
            window.location.href = '/organizator/events';
            return;
        }

        // Nothing to sign → straight to the account.
        if (data.is_signed || data.signature_required === false || !data.contract) {
            window.location.href = '/organizator/events';
            return;
        }

        const url = data.contract.download_url;
        $('sc-frame').src = url;
        $('sc-open').href = url;
        $('sc-open2').href = url;
        // If the cross-origin PDF can't be framed, offer the open-in-tab fallback.
        $('sc-frame').addEventListener('error', () => {
            $('sc-frame').classList.add('hidden');
            $('sc-frame-fallback').classList.remove('hidden');
        });

        $('sc-loading').classList.add('hidden');
        $('sc-content').classList.remove('hidden');

        initPad();
    }

    let hasDrawn = false;
    function initPad() {
        const canvas = $('sc-pad');
        const ctx = canvas.getContext('2d');
        ctx.lineWidth = 2.5;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0a1a3c';
        let drawing = false;

        const pos = (e) => {
            const r = canvas.getBoundingClientRect();
            const t = e.touches && e.touches[0] ? e.touches[0] : e;
            return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
        };
        const start = (e) => { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
        const move = (e) => { if (!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasDrawn = true; updateSubmit(); e.preventDefault(); };
        const end = () => { drawing = false; };

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);

        $('sc-clear').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            updateSubmit();
        });
        $('sc-agree').addEventListener('change', updateSubmit);
        $('sc-submit').addEventListener('click', submit);
    }

    function updateSubmit() {
        $('sc-submit').disabled = !(hasDrawn && $('sc-agree').checked);
    }

    async function submit() {
        if (!hasDrawn || !$('sc-agree').checked) return;
        const btn = $('sc-submit');
        btn.disabled = true;
        btn.textContent = 'Se semnează...';
        try {
            const dataUrl = $('sc-pad').toDataURL('image/png');
            const res = await AmbiletAPI.post('/organizer/contract/sign', { signature: dataUrl, agreement: true });
            if (res && res.success) {
                $('sc-content').classList.add('hidden');
                $('sc-signed').classList.remove('hidden');
                setTimeout(() => { window.location.href = '/organizator/events'; }, 1600);
            } else {
                throw new Error('sign_failed');
            }
        } catch (e) {
            console.error('[sign-contract]', e);
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error('Nu am putut salva semnătura. Încearcă din nou.');
            btn.disabled = false;
            btn.textContent = 'Semnează contractul';
        }
    }

    window.addEventListener('load', boot);
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

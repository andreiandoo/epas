<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Raport personal';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'staff_raport';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 text-xs text-muted mb-1">
                    <a href="/organizator/leisure-raport" class="hover:text-primary">📑 Raport general</a>
                    <span>›</span>
                    <span class="text-secondary font-semibold">Raport personal</span>
                </div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">👥 Raport activitate angajați</h1>
                <p class="mt-1 text-sm text-muted">Toate scanările QR ale angajaților permanenți în perioada selectată. Sursa: <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">leisure_staff_checkins</code>.</p>
            </div>
            <a href="/organizator/echipa" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border border-border rounded-lg hover:bg-slate-50">
                ⚙️ Gestionează angajați →
            </a>
        </div>

        <!-- Filtre + acțiuni -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">De la</span>
                <input id="staff-rep-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Până la</span>
                <input id="staff-rep-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
            </label>
            <button id="staff-rep-refresh" type="button" class="px-4 py-1.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark">Actualizează</button>
            <div class="ml-auto">
                <button id="staff-rep-export" type="button" class="px-3 py-1.5 text-xs font-medium bg-white border border-border rounded-lg hover:bg-slate-50">📥 Export CSV</button>
            </div>
        </div>

        <!-- Sumar (3 carduri) -->
        <div id="staff-rep-summary" class="hidden mb-6 grid grid-cols-1 sm:grid-cols-3 gap-3"></div>

        <!-- Listă detaliată -->
        <div class="bg-white border rounded-2xl border-border overflow-hidden">
            <div id="staff-rep-list" class="overflow-x-auto">
                <p class="p-8 text-center text-muted text-sm">Se încarcă...</p>
            </div>
        </div>

    </main>
</div>

<script>
// IIFE standalone — independent de team.php. Foloseste AmbiletAPI + AmbiletAuth deja
// incarcate prin scripts.php common bundle.
(function () {
    const $ = (id) => document.getElementById(id);

    function escAttr(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function escHtml(s) { return escAttr(s); }
    function fmtDateTime(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return iso;
        const pad = n => String(n).padStart(2, '0');
        return pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    async function loadStaffCheckins() {
        const params = {};
        if ($('staff-rep-from').value) params.from = $('staff-rep-from').value;
        if ($('staff-rep-to').value) params.to = $('staff-rep-to').value;
        const list = $('staff-rep-list');
        list.innerHTML = '<p class="p-8 text-center text-muted text-sm">Se încarcă...</p>';
        try {
            const res = await AmbiletAPI.get('/organizer/leisure/staff-checkins', params);
            const data = res.data || {};
            renderStaffSummary(data.per_staff || []);
            renderStaffCheckinsList(data.checkins || []);
        } catch (e) {
            list.innerHTML = '<p class="p-8 text-center text-rose-600 text-sm">Eroare: ' + (e?.message || '') + '</p>';
            $('staff-rep-summary').classList.add('hidden');
        }
    }

    function renderStaffSummary(perStaff) {
        const box = $('staff-rep-summary');
        if (!perStaff.length) { box.classList.add('hidden'); return; }
        const total = perStaff.reduce((s, r) => s + r.total, 0);
        const top = [...perStaff].sort((a, b) => b.total - a.total)[0];
        box.classList.remove('hidden');
        box.innerHTML = ''
            + '<div class="p-4 bg-white border border-border rounded-2xl"><p class="text-xs text-muted uppercase tracking-wider font-semibold mb-1">Total check-in-uri</p><p class="text-3xl font-bold text-secondary">' + total + '</p></div>'
            + '<div class="p-4 bg-white border border-border rounded-2xl"><p class="text-xs text-muted uppercase tracking-wider font-semibold mb-1">Angajați activi</p><p class="text-3xl font-bold text-secondary">' + perStaff.length + '</p></div>'
            + '<div class="p-4 bg-white border border-border rounded-2xl"><p class="text-xs text-muted uppercase tracking-wider font-semibold mb-1">Cele mai multe</p><p class="text-base font-bold text-secondary truncate">' + escHtml(top?.staff_name || '—') + '</p><p class="text-xs text-muted mt-1">' + (top?.total || 0) + ' scanări</p></div>';
    }

    function renderStaffCheckinsList(checkins) {
        const list = $('staff-rep-list');
        if (!checkins.length) { list.innerHTML = '<p class="p-8 text-center text-muted text-sm">Niciun check-in în perioada selectată.</p>'; return; }
        list.innerHTML = '<table class="w-full text-sm">'
            + '<thead class="bg-slate-50 border-b border-border"><tr class="text-left text-xs text-muted uppercase">'
            + '<th class="px-4 py-3">Data și ora</th><th class="px-4 py-3">Angajat</th>'
            + '<th class="px-4 py-3">Poziție</th><th class="px-4 py-3">Punct check-in</th>'
            + '</tr></thead><tbody>'
            + checkins.map(c => '<tr class="border-b border-border hover:bg-slate-50">'
                + '<td class="px-4 py-3 font-mono text-xs text-secondary">' + fmtDateTime(c.checked_in_at) + '</td>'
                + '<td class="px-4 py-3 font-semibold">' + escHtml(c.staff_name) + '</td>'
                + '<td class="px-4 py-3 text-muted">' + escHtml(c.position || '—') + '</td>'
                + '<td class="px-4 py-3 text-muted">' + escHtml(c.location || '—') + '</td></tr>').join('')
            + '</tbody></table>';
    }

    function exportStaffCheckins() {
        const params = new URLSearchParams();
        if ($('staff-rep-from').value) params.set('from', $('staff-rep-from').value);
        if ($('staff-rep-to').value) params.set('to', $('staff-rep-to').value);
        const url = '/api/proxy.php?action=organizer.leisure.staff.export' + (params.toString() ? '&' + params.toString() : '');
        fetch(url, { headers: { 'Authorization': 'Bearer ' + AmbiletAuth.getToken() } })
            .then(r => r.blob())
            .then(blob => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'staff-checkins-' + new Date().toISOString().slice(0,10) + '.csv';
                document.body.appendChild(a); a.click(); a.remove();
            })
            .catch(e => alert('Export eșuat: ' + e.message));
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Default range: ultimele 30 zile
        const now = new Date();
        const past = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
        const iso = d => d.toISOString().slice(0, 10);
        $('staff-rep-to').value = iso(now);
        $('staff-rep-from').value = iso(past);

        // Astept API ready si trigger
        let retries = 0;
        const waitForApi = setInterval(() => {
            retries++;
            if (typeof AmbiletAPI !== 'undefined' && typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isOrganizer && AmbiletAuth.isOrganizer()) {
                clearInterval(waitForApi);
                loadStaffCheckins();
            }
            if (retries > 20) clearInterval(waitForApi);
        }, 200);

        $('staff-rep-refresh')?.addEventListener('click', loadStaffCheckins);
        $('staff-rep-export')?.addEventListener('click', exportStaffCheckins);
    });
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

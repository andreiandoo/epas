<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Eveniment';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_event';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Evenimentul tău</h1>
            <p class="mt-1 text-sm text-muted">Detaliile locației și statusul curent.</p>
        </div>

        <div id="lv-loading" class="p-8 text-center">
            <div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div>
            <p class="mt-2 text-sm text-muted">Se încarcă...</p>
        </div>

        <div id="lv-empty" class="hidden p-8 text-center bg-white border rounded-2xl border-border">
            <p class="text-muted">Nu există încă un eveniment de tip Locație de agrement asignat acestui cont.</p>
        </div>

        <div id="lv-event-card" class="hidden">
            <div class="bg-white border rounded-2xl border-border overflow-hidden">
                <div id="lv-cover" class="h-48 bg-gradient-to-br from-emerald-700 to-emerald-900 relative">
                    <img id="lv-cover-img" class="absolute inset-0 w-full h-full object-cover" alt="">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                    <div class="absolute bottom-4 left-6 right-6 text-white">
                        <p id="lv-status" class="text-xs uppercase tracking-wider font-semibold mb-1">—</p>
                        <h2 id="lv-name" class="text-2xl lg:text-3xl font-bold"></h2>
                    </div>
                </div>
                <div class="p-6 grid md:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl">
                        <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Tip pagină</p>
                        <p class="font-semibold text-secondary">Locație de agrement</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl">
                        <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Tipuri de bilete</p>
                        <p id="lv-ticket-count" class="font-semibold text-secondary">—</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl">
                        <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Pagină publică</p>
                        <a id="lv-public-url" href="#" target="_blank" class="font-semibold text-primary hover:underline">Deschide →</a>
                    </div>
                </div>
                <div class="border-t border-border p-6">
                    <h3 class="text-sm font-bold text-secondary uppercase tracking-wider mb-3">Acțiuni rapide</h3>
                    <div class="flex flex-wrap gap-3">
                        <a href="/organizator/leisure-dashboard" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors">📊 Dashboard live</a>
                        <a href="/organizator/leisure-pos" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">🎫 Emite bilete (POS)</a>
                        <a href="/organizator/leisure" class="px-4 py-2 bg-white border border-border text-secondary text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">✏️ Editează conținut pagină</a>
                        <a id="lv-admin-link" href="#" target="_blank" class="px-4 py-2 bg-white border border-border text-secondary text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">⚙️ Configurare avansată (Tixello admin)</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') { $('lv-loading').textContent = 'API indisponibil.'; return; }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            $('lv-loading').classList.add('hidden');
            if (leisure.length === 0) { $('lv-empty').classList.remove('hidden'); return; }
            const ev = leisure[0];
            $('lv-name').textContent = ev.name || 'Evenimentul tău';
            $('lv-status').textContent = ev.status || (ev.is_published ? 'PUBLICAT' : 'DRAFT');
            $('lv-ticket-count').textContent = (ev.ticket_types_count || ev.tickets_sold || '—');
            if (ev.image) { $('lv-cover-img').src = ev.image; } else { $('lv-cover-img').style.display = 'none'; }
            $('lv-public-url').href = '/bilete/' + (ev.slug || ev.id) + (ev.is_published ? '' : '?preview=1');
            $('lv-admin-link').href = 'https://core.tixello.com/marketplace/events/' + ev.id + '/edit';
            $('lv-event-card').classList.remove('hidden');
        } catch (e) {
            console.error(e);
            $('lv-loading').textContent = 'Eroare la încărcarea evenimentului.';
        }
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>

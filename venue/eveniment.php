<?php
/**
 * Venue Owner — /venue/eveniment/{id}
 * Single-event analytics with insights (ce a mers / ce n-a mers).
 */
require_once dirname(__DIR__) . '/includes/config.php';

$eventId = (int) ($_GET['id'] ?? 0);
if ($eventId <= 0) {
    header('Location: /venue/evenimente');
    exit;
}

$pageTitle       = 'Analiză eveniment';
$venuePageTitle  = 'Analiză eveniment';
$bodyClass       = 'min-h-screen flex bg-slate-50';
$currentPage     = 'venue_evenimente';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8 space-y-6">
            <!-- Back link -->
            <a href="/venue/evenimente" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Înapoi la evenimente
            </a>

            <!-- Loading skeleton -->
            <div id="event-loading" class="space-y-4">
                <div class="animate-pulse p-6 bg-white border rounded-2xl border-slate-200 flex gap-4">
                    <div class="w-40 h-40 bg-slate-200 rounded-xl flex-shrink-0"></div>
                    <div class="flex-1 space-y-3">
                        <div class="h-6 w-3/4 bg-slate-200 rounded"></div>
                        <div class="h-3 w-1/2 bg-slate-100 rounded"></div>
                        <div class="h-3 w-1/3 bg-slate-100 rounded"></div>
                        <div class="grid grid-cols-4 gap-3 mt-4">
                            <div class="h-16 bg-slate-100 rounded"></div>
                            <div class="h-16 bg-slate-100 rounded"></div>
                            <div class="h-16 bg-slate-100 rounded"></div>
                            <div class="h-16 bg-slate-100 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content will land here -->
            <div id="event-content" class="hidden space-y-6"></div>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const eventId = parseInt(params.get('id'), 10);
    if (!eventId) { window.location.href = '/venue/evenimente'; return; }

    const fmtInt = n => Number(n || 0).toLocaleString('ro-RO');
    const fmtMoney = n => Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const fmtDate = d => {
        if (!d) return '—';
        try { return new Date(d).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' }); }
        catch (e) { return d; }
    };

    function resolvePoster(url) {
        if (!url) return null;
        if (/^https?:\/\//i.test(url)) return url;
        const trimmed = url.replace(/^\/+/, '');
        return trimmed.startsWith('storage/')
            ? 'https://core.tixello.com/' + trimmed
            : 'https://core.tixello.com/storage/' + trimmed;
    }

    function derivedStatus(ev) {
        if (ev.is_cancelled) return { text: 'Anulat', class: 'bg-red-100 text-red-700' };
        if (ev.is_postponed) return { text: 'Amânat', class: 'bg-amber-100 text-amber-700' };
        const d = ev.start_date;
        if (!d) return { text: 'Nespecificat', class: 'bg-slate-100 text-slate-500' };
        return d >= new Date().toISOString().slice(0, 10)
            ? { text: 'Programat', class: 'bg-emerald-100 text-emerald-700' }
            : { text: 'Trecut', class: 'bg-slate-100 text-slate-500' };
    }

    /**
     * Compute an "insights" object with what went well, what didn't
     * and suggestions — computed frontend-side from the event stats.
     * The values pull from the same data the analytics page uses.
     */
    function computeInsights(ev, salesBreakdown) {
        const stats = ev.stats || {};
        const sold = stats.tickets_sold || 0;
        const cap = stats.stock_total || 0;
        const ci = stats.checked_in_count || 0;
        const pct = cap > 0 ? Math.round(sold / cap * 100) : null;
        const ciPct = sold > 0 ? Math.round(ci / sold * 100) : null;

        const revenue = (salesBreakdown && salesBreakdown.total_revenue) || 0;
        const avgPrice = sold > 0 && revenue > 0 ? revenue / sold : 0;

        const wins = [];
        const issues = [];
        const tips = [];

        if (pct !== null) {
            if (pct >= 90) wins.push({ h: 'Sold out sau aproape', p: 'Ocupare de ' + pct + '% — evenimentul a fost aproape epuizat. Consideră ridicarea prețului cu 15-20% pentru evenimente similare viitoare.' });
            else if (pct >= 70) wins.push({ h: 'Ocupare foarte bună', p: 'Ai vândut ' + pct + '% din capacitate. Bun rezultat.' });
            else if (pct >= 40) issues.push({ h: 'Ocupare medie', p: 'Ai vândut doar ' + pct + '% din capacitate. Verifică promovarea și prețul.' });
            else issues.push({ h: 'Ocupare scăzută', p: 'Ai vândut doar ' + pct + '% din capacitate — trebuie reevaluat prețul, targetul sau timing-ul reclamelor.' });
        }
        if (ciPct !== null) {
            if (ciPct >= 90) wins.push({ h: 'Prezență excelentă', p: ciPct + '% din cumpărători au ajuns la eveniment. Rată foarte bună.' });
            else if (ciPct < 60 && sold > 20) issues.push({ h: 'Prezență scăzută', p: 'Doar ' + ciPct + '% au ajuns. Investighează cauzele — vremea, competiția, oboseala publicului.' });
        }
        if (revenue > 0) {
            if (avgPrice < 30) tips.push({ h: 'Preț mediu foarte mic', p: 'Prețul mediu (' + fmtMoney(avgPrice) + ' RON) e sub media pieței pentru evenimente live. Consideră un tier VIP.' });
        }
        if (ev.is_cancelled) issues.push({ h: 'Eveniment anulat', p: 'Contactează cumpărătorii pentru rambursări și oferă un cod de reducere pentru viitor.' });
        if (ev.is_postponed) tips.push({ h: 'Eveniment amânat', p: 'Actualizează cumpărătorii cu noua dată. Oferă opțiune de rambursare.' });

        if (!wins.length && !issues.length && !tips.length) {
            tips.push({ h: 'Date insuficiente', p: 'Evenimentul e prea nou sau prea vechi pentru un analiz semnificativ.' });
        }

        return { wins, issues, tips, sold, cap, pct, ci, ciPct, revenue, avgPrice };
    }

    // Fetch event + sales breakdown in parallel.
    let ev = null, salesBreakdown = null;
    try {
        const [evRes, sbRes] = await Promise.all([
            AmbiletVenueAPI.event(eventId),
            AmbiletVenueAPI.eventSalesBreakdown(eventId).catch(() => null),
        ]);
        if (evRes && evRes.success && evRes.data && evRes.data.event) {
            ev = evRes.data.event;
        }
        if (sbRes && sbRes.success && sbRes.data) {
            salesBreakdown = sbRes.data;
        }
    } catch (e) {
        console.error('event load failed', e);
    }

    document.getElementById('event-loading').classList.add('hidden');
    const content = document.getElementById('event-content');
    content.classList.remove('hidden');

    if (!ev) {
        content.innerHTML = `<div class="p-12 text-center bg-white border rounded-2xl border-slate-200">
            <p class="text-slate-500">Nu am putut încărca detaliile evenimentului.</p>
        </div>`;
        return;
    }

    const title = ev.title || ev.name || '—';
    const posterUrl = resolvePoster(ev.poster_url || ev.image || ev.featured_image);
    const st = derivedStatus(ev);
    const insights = computeInsights(ev, salesBreakdown);
    const organizerName = (ev.marketplace_organizer && ev.marketplace_organizer.name) || '—';
    const venueLabel = ev.venue ? `${ev.venue.name || ''}${ev.venue.city ? ' · ' + ev.venue.city : ''}` : '';

    const fillColor = insights.pct === null ? '#94a3b8' : insights.pct >= 75 ? '#10b981' : insights.pct >= 40 ? '#f59e0b' : '#ef4444';

    let html = '';

    // Hero
    html += `<section class="relative overflow-hidden bg-white border rounded-3xl border-slate-200 shadow-lg">
        <div class="relative flex flex-col md:flex-row gap-6 p-6">
            ${posterUrl ? `
                <div class="w-full md:w-56 h-56 rounded-2xl overflow-hidden flex-shrink-0 shadow-md">
                    <img src="${posterUrl}" alt="" class="w-full h-full object-cover">
                </div>
            ` : `
                <div class="w-full md:w-56 h-56 rounded-2xl flex flex-col items-center justify-center text-white flex-shrink-0 shadow-md" style="background:linear-gradient(135deg, #7c3aed, #ec4899);">
                    <p class="text-4xl font-black">${new Date(ev.start_date || Date.now()).getDate()}</p>
                    <p class="text-sm uppercase font-semibold tracking-wider">${new Date(ev.start_date || Date.now()).toLocaleDateString('ro-RO', { month: 'short' })}</p>
                </div>
            `}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="px-2.5 py-1 text-xs font-bold uppercase tracking-wider rounded-full ${st.class}">${st.text}</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 mb-3">${escapeHtml(title)}</h1>
                <div class="space-y-2 text-sm text-slate-600">
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="font-medium">${fmtDate(ev.start_date)}</span>
                        ${ev.start_time ? `<span class="text-slate-400">·</span><span>${ev.start_time.substring(0,5)}</span>` : ''}
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        ${escapeHtml(venueLabel)}
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857"/></svg>
                        Organizator: <strong>${escapeHtml(organizerName)}</strong>
                    </p>
                    ${ev.artists && ev.artists.length ? `
                        <p class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-slate-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                            <span>Artiști: <strong>${ev.artists.map(a => escapeHtml(a.name)).join(', ')}</strong></span>
                        </p>
                    ` : ''}
                </div>
            </div>
        </div>
    </section>`;

    // KPI grid
    html += `<section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="p-5 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="text-2xl">🎫</div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Bilete</span>
            </div>
            <p class="text-3xl font-black text-slate-900">${fmtInt(insights.sold)}${insights.cap > 0 ? `<span class="text-sm text-slate-400 font-normal">/${fmtInt(insights.cap)}</span>` : ''}</p>
            ${insights.pct !== null ? `
                <div class="mt-3 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width:${insights.pct}%; background:${fillColor};"></div>
                </div>
                <p class="mt-1.5 text-xs font-semibold" style="color:${fillColor};">${insights.pct}% ocupare</p>
            ` : ''}
        </div>
        <div class="p-5 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="text-2xl">✅</div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Check-in</span>
            </div>
            <p class="text-3xl font-black text-slate-900">${fmtInt(insights.ci)}</p>
            ${insights.ciPct !== null ? `<p class="mt-3 text-xs font-semibold text-slate-500">${insights.ciPct}% din cei ce au cumpărat</p>` : `<p class="mt-3 text-xs text-slate-400">Fără prezență înregistrată</p>`}
        </div>
        <div class="p-5 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="text-2xl">💰</div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Venit</span>
            </div>
            <p class="text-2xl font-black text-slate-900">${fmtMoney(insights.revenue)} <span class="text-sm text-slate-400 font-normal">RON</span></p>
            <p class="mt-3 text-xs text-slate-500">Total încasat</p>
        </div>
        <div class="p-5 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="text-2xl">🏷️</div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Preț mediu</span>
            </div>
            <p class="text-2xl font-black text-slate-900">${fmtMoney(insights.avgPrice)} <span class="text-sm text-slate-400 font-normal">RON</span></p>
            <p class="mt-3 text-xs text-slate-500">per bilet</p>
        </div>
    </section>`;

    // Insights (ce a mers / ce n-a mers / sfaturi)
    html += `<section class="grid gap-4 md:grid-cols-3">
        <div class="p-5 border rounded-2xl shadow-sm" style="background:linear-gradient(135deg, rgba(16,185,129,0.05), rgba(16,185,129,0.02)); border-color:rgba(16,185,129,0.2);">
            <div class="flex items-center gap-2 mb-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500 text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900">Ce a mers</h3>
            </div>
            ${insights.wins.length ? insights.wins.map(w => `
                <div class="mb-3 last:mb-0">
                    <p class="text-sm font-semibold text-slate-900">${escapeHtml(w.h)}</p>
                    <p class="text-xs text-slate-600 mt-1">${escapeHtml(w.p)}</p>
                </div>
            `).join('') : '<p class="text-xs text-slate-400">Fără puncte forte notabile.</p>'}
        </div>
        <div class="p-5 border rounded-2xl shadow-sm" style="background:linear-gradient(135deg, rgba(239,68,68,0.05), rgba(239,68,68,0.02)); border-color:rgba(239,68,68,0.2);">
            <div class="flex items-center gap-2 mb-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-500 text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900">Ce n-a mers</h3>
            </div>
            ${insights.issues.length ? insights.issues.map(w => `
                <div class="mb-3 last:mb-0">
                    <p class="text-sm font-semibold text-slate-900">${escapeHtml(w.h)}</p>
                    <p class="text-xs text-slate-600 mt-1">${escapeHtml(w.p)}</p>
                </div>
            `).join('') : '<p class="text-xs text-slate-400">Fără probleme detectate.</p>'}
        </div>
        <div class="p-5 border rounded-2xl shadow-sm" style="background:linear-gradient(135deg, rgba(59,130,246,0.05), rgba(59,130,246,0.02)); border-color:rgba(59,130,246,0.2);">
            <div class="flex items-center gap-2 mb-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500 text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900">Sfaturi</h3>
            </div>
            ${insights.tips.length ? insights.tips.map(w => `
                <div class="mb-3 last:mb-0">
                    <p class="text-sm font-semibold text-slate-900">${escapeHtml(w.h)}</p>
                    <p class="text-xs text-slate-600 mt-1">${escapeHtml(w.p)}</p>
                </div>
            `).join('') : '<p class="text-xs text-slate-400">Fără recomandări.</p>'}
        </div>
    </section>`;

    // Ticket types breakdown
    if (ev.ticket_types && ev.ticket_types.length) {
        html += `<section class="p-6 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Tipuri de bilete</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="py-2 px-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tip</th>
                            <th class="py-2 px-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Preț</th>
                            <th class="py-2 px-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stoc</th>
                            <th class="py-2 px-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Vândute</th>
                            <th class="py-2 px-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Check-in</th>
                            <th class="py-2 px-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Ocupare</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${ev.ticket_types.map(tt => {
                            const ttSold = tt.sold || 0;
                            const ttCap = tt.quota_total > 0 ? tt.quota_total : 0;
                            const ttPct = ttCap > 0 ? Math.round(ttSold / ttCap * 100) : null;
                            const ttPrice = (tt.price_cents || 0) / 100;
                            const ttCi = tt.checked_in || 0;
                            return `<tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="py-2 px-3 font-semibold">${escapeHtml(tt.name || '—')}</td>
                                <td class="py-2 px-3 text-right font-mono">${fmtMoney(ttPrice)} RON</td>
                                <td class="py-2 px-3 text-right text-slate-500">${ttCap > 0 ? fmtInt(ttCap) : '∞'}</td>
                                <td class="py-2 px-3 text-right font-semibold">${fmtInt(ttSold)}</td>
                                <td class="py-2 px-3 text-right text-slate-500">${fmtInt(ttCi)}</td>
                                <td class="py-2 px-3 text-right font-semibold" style="color:${ttPct === null ? '#94a3b8' : ttPct >= 75 ? '#10b981' : ttPct >= 40 ? '#f59e0b' : '#ef4444'};">${ttPct !== null ? ttPct + '%' : '—'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        </section>`;
    }

    // Sales breakdown (if available)
    if (salesBreakdown && salesBreakdown.by_channel && salesBreakdown.by_channel.length) {
        html += `<section class="p-6 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Distribuție vânzări pe canale</h2>
            <div class="space-y-2">
                ${salesBreakdown.by_channel.map(ch => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-b-0">
                        <span class="text-sm font-semibold text-slate-700">${escapeHtml(ch.source || 'Necunoscut')}</span>
                        <div class="flex items-center gap-4">
                            <span class="text-xs text-slate-500">${fmtInt(ch.tickets || 0)} bilete</span>
                            <span class="text-sm font-bold text-slate-900">${fmtMoney(ch.revenue || 0)} RON</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        </section>`;
    }

    content.innerHTML = html;
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

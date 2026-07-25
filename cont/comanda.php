<?php
$navActive = 'orders';
$pageTitle = 'Detalii comandă — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">COMANDĂ</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Detalii comandă</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Bilete, plată, factură, cronologie și opțiunile disponibile pentru această achiziție.</p></div>
<div x-data="orderPage()" x-init="load()">
<a href="/cont/comenzi" class="mb-5 inline-flex text-sm text-paper/45">← Înapoi la comenzi</a>
<div x-show="loading" class="py-10 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div></div>
<div x-show="!loading && !o" x-cloak class="card p-10 text-center"><p class="text-paper/45">Comanda nu a fost găsită.</p></div>
<div x-show="!loading && o" x-cloak>
<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h2 class="font-display text-3xl" x-text="'Comanda #' + (o?.order_number||'')"></h2><span class="badge" :class="o?.is_paid?'bg-emerald-500/10 text-emerald-300':'bg-amber-500/10 text-amber-300'" x-text="statusLabel(o?.status)"></span></div><p class="mt-2 text-sm text-paper/38" x-text="'Plasată pe ' + dateStr(o?.created_at)"></p></div><div class="flex flex-wrap gap-2"><button @click="showToast('Factura a fost descărcată')" class="btn-secondary rounded-full px-4 py-2.5 text-sm">Descarcă factura</button><a href="/cont/rambursare" class="btn-secondary rounded-full px-4 py-2.5 text-sm text-rose-200">Solicită rambursare</a></div></div>
<div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]"><div class="space-y-6"><section class="card overflow-hidden"><div class="border-b border-white/8 p-5"><h3 class="font-display text-2xl">Bilete comandate</h3></div><div class="p-5 grid gap-3"><template x-for="(t,i) in (o?.tickets||[])" :key="i"><article class="flex flex-col gap-4 rounded-2xl bg-white/[.025] p-4 sm:flex-row sm:items-center"><div class="grid h-14 w-14 place-items-center rounded-xl bg-wine/20 font-display text-xl">🎭</div><div class="flex-1"><strong class="font-display text-lg" x-text="o?.event || 'Spectacol'"></strong><p class="mt-1 text-xs text-paper/38" x-text="[t.type, t.seat_label].filter(Boolean).join(' · ')"></p></div><div class="sm:text-right"><span class="font-mono text-xs text-paper/40" x-text="t.code"></span></div></article></template><p x-show="!(o?.tickets||[]).length" class="text-sm text-paper/40">Biletele apar aici după confirmarea plății.</p></div></section><section class="card p-5"><h3 class="font-display text-2xl">Istoric comandă</h3><div class="relative mt-5 ml-2 border-l border-brass/25 pl-6"><div class="relative pb-6" x-show="o?.is_paid"><span class="absolute -left-[31px] top-0 h-3 w-3 rounded-full bg-emerald-400"></span><strong class="text-sm">Bilete emise</strong></div><div class="relative pb-6" x-show="o?.is_paid"><span class="absolute -left-[31px] top-0 h-3 w-3 rounded-full bg-brass"></span><strong class="text-sm">Plată confirmată</strong></div><div class="relative"><span class="absolute -left-[31px] top-0 h-3 w-3 rounded-full bg-white/25"></span><strong class="text-sm">Comandă creată</strong><p class="mt-1 text-xs text-paper/35" x-text="dateStr(o?.created_at)"></p></div></div></section></div><aside class="space-y-6"><section class="card p-5"><h3 class="font-display text-2xl">Sumar</h3><dl class="mt-4 grid gap-3 text-sm"><div class="flex justify-between"><dt class="text-paper/40">Bilete</dt><dd x-text="o?.tickets_count + ' buc.'"></dd></div><div class="mt-2 flex justify-between border-t border-white/8 pt-4 text-lg font-bold"><dt>Total</dt><dd class="text-brass-light" x-text="fmt(o?.total) + ' ' + (o?.currency||'RON')"></dd></div></dl></section><section class="card p-5"><h3 class="font-display text-2xl">Plată</h3><div class="mt-4 flex items-center gap-3 rounded-xl bg-white/[.025] p-4"><span class="grid h-9 w-14 place-items-center rounded bg-blue-900 text-xs font-bold">CARD</span><div><strong class="text-sm" x-text="o?.payment_method"></strong><p class="text-xs text-paper/35">Plată online (demo)</p></div></div></section><section class="card bg-wine/10 p-5"><h3 class="font-semibold">Ai nevoie de ajutor?</h3><p class="mt-2 text-xs leading-6 text-paper/42">Echipa de suport poate verifica plata, factura sau biletele.</p><a href="/cont/ajutor" class="mt-3 inline-flex text-sm text-brass-light">Contactează suportul →</a></section></aside></div>
</div>
</div>
<script>
function orderPage() {
    return {
        loading: true, o: null,
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        fmt(n){ return new Intl.NumberFormat('ro-RO').format(Math.round(n||0)); },
        dateStr(iso){ if(!iso) return ''; try { return new Date(iso).toLocaleString('ro-RO',{day:'numeric',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'}); } catch(e){ return ''; } },
        statusLabel(s){ return ({paid:'CONFIRMATĂ',confirmed:'CONFIRMATĂ',completed:'FINALIZATĂ',pending:'ÎN AȘTEPTARE',cancelled:'ANULATĂ',refunded:'RAMBURSATĂ'}[s]||(s||'').toUpperCase()); },
        async load(){
            const id = new URLSearchParams(location.search).get('id');
            const a = this.auth(); if(!a||!a.token||!id){ this.loading=false; return; }
            try { const r = await fetch('/api/proxy.php?action=acc-order&id='+encodeURIComponent(id), {headers:{'Authorization':'Bearer '+a.token}}); const d = await r.json().catch(()=>({})); if(d&&d.success) this.o=d.data; } catch(e){}
            this.loading=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

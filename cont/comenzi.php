<?php
$navActive = 'orders';
$pageTitle = 'Comenzile mele — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">COMENZI</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Comenzile mele</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Istoricul plăților, facturi, bilete emise și starea solicitărilor de rambursare.</p></div>
<div x-data="ordersPage()" x-init="load()">
    <div class="grid grid-cols-3 gap-3">
        <div class="card p-5"><p class="font-display text-3xl text-brass-light" x-text="stats.total_orders ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Total comenzi</p></div>
        <div class="card p-5"><p class="font-display text-3xl text-emerald-300" x-text="fmt(stats.total_spent) + ' lei'">0</p><p class="mt-1 text-xs text-paper/38">Total plătit</p></div>
        <div class="card p-5"><p class="font-display text-3xl text-amber-300" x-text="fmt(stats.saved) + ' lei'">0</p><p class="mt-1 text-xs text-paper/38">Economisit</p></div>
    </div>
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="flex gap-2 overflow-x-auto"><template x-for="item in [['all','Toate'],['paid','Confirmate'],['pending','În așteptare'],['cancelled','Anulate']]" :key="item[0]"><button @click="status=item[0]" :class="status===item[0]?'bg-brass text-ink':'border-white/10 text-paper/45'" class="whitespace-nowrap rounded-full border px-4 py-2 text-sm" x-text="item[1]"></button></template></div><input x-model="q" class="input max-w-xs" placeholder="Caută după număr sau spectacol"></div>

    <div x-show="loading" class="py-10 text-center text-muted"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div><p class="mt-2 text-paper/40">Se încarcă comenzile...</p></div>
    <div x-show="!loading && filtered().length === 0" x-cloak class="card mt-6 p-10 text-center"><p class="text-paper/45">Nu ai nicio comandă încă.</p><a href="/program" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Descoperă spectacole</a></div>

    <div class="mt-5 grid gap-4" x-show="!loading" x-cloak>
        <template x-for="o in filtered()" :key="o.id">
            <article class="card overflow-hidden">
                <button @click="open = open===o.id ? 0 : o.id" class="flex w-full flex-col gap-4 p-5 text-left sm:flex-row sm:items-center">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl" :class="o.is_paid?'bg-emerald-500/10 text-emerald-300':(o.status==='cancelled'?'bg-white/5 text-paper/35':'bg-amber-500/10 text-amber-300')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m5 12 4 4L19 6"/></svg>
                    </div>
                    <div class="flex-1"><div class="flex flex-wrap items-center gap-2"><strong x-text="'#' + o.order_number"></strong><span class="badge" :class="o.is_paid?'bg-emerald-500/10 text-emerald-300':(o.status==='cancelled'?'bg-white/5 text-paper/40':'bg-amber-500/10 text-amber-300')" x-text="statusLabel(o.status)"></span></div><p class="mt-1 text-xs text-paper/38" x-text="dateStr(o.created_at) + (o.event ? ' · ' + o.event : '') + ' · ' + o.tickets_count + ' bilet' + (o.tickets_count===1?'':'e')"></p></div>
                    <div class="sm:text-right"><strong class="block" x-text="fmt(o.total) + ' ' + o.currency"></strong><span class="text-xs text-paper/35" x-text="o.payment_method"></span></div>
                    <span :class="open===o.id?'rotate-90':''" class="transition"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m9 18 6-6-6-6"/></svg></span>
                </button>
                <div x-show="open===o.id" x-collapse class="border-t border-white/8 p-5"><div class="grid gap-4 md:grid-cols-[1fr_auto]"><div><p class="text-sm text-paper/50" x-text="o.is_paid ? 'Biletele au fost emise și sunt disponibile în cont.' : 'Comanda este în așteptarea confirmării plății.'"></p></div><div class="flex gap-2"><a :href="'/cont/comanda?id=' + o.id" class="btn-secondary rounded-full px-4 py-2.5 text-sm">Detalii</a><a href="/cont/bilete" class="btn-primary rounded-full px-4 py-2.5 text-sm font-bold">Bilete</a></div></div></div>
            </article>
        </template>
    </div>
</div>
<script>
function ordersPage() {
    return {
        loading: true, orders: [], stats: {}, status: 'all', q: '', open: 0,
        auth() { try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        fmt(n) { return new Intl.NumberFormat('ro-RO').format(Math.round(n||0)); },
        dateStr(iso) { if(!iso) return ''; try { return new Date(iso).toLocaleDateString('ro-RO',{day:'numeric',month:'long',year:'numeric'}); } catch(e){ return ''; } },
        statusLabel(s) { return ({paid:'CONFIRMATĂ',confirmed:'CONFIRMATĂ',completed:'FINALIZATĂ',pending:'ÎN AȘTEPTARE',cancelled:'ANULATĂ',refunded:'RAMBURSATĂ'}[s]||s.toUpperCase()); },
        filtered() {
            let list = this.orders;
            if (this.status === 'paid') list = list.filter(o => o.is_paid);
            else if (this.status !== 'all') list = list.filter(o => o.status === this.status);
            if (this.q) { const t = this.q.toLowerCase(); list = list.filter(o => (o.order_number||'').toLowerCase().includes(t) || (o.event||'').toLowerCase().includes(t)); }
            return list;
        },
        async load() {
            const a = this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try {
                const r = await fetch('/api/proxy.php?action=acc-orders', { headers:{'Authorization':'Bearer '+a.token} });
                const d = await r.json().catch(()=>({}));
                if (d && d.success) { this.orders = d.data||[]; this.stats = d.stats||{}; if(this.orders[0]) this.open=this.orders[0].id; }
            } catch(e){}
            this.loading = false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

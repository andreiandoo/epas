<?php
$navActive = 'tickets';
$pageTitle = 'Biletele mele — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">BILETE</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Biletele mele</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Accesează rapid codurile QR, descarcă biletele și gestionează transferurile sau modificările de program.</p></div>
<div x-data="ticketsPage()" x-init="load()">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-2 rounded-xl border border-white/8 bg-white/[.025] p-1">
            <button @click="tab='upcoming'" :class="tab==='upcoming'?'bg-brass text-ink':'text-paper/45'" class="rounded-lg px-4 py-2 text-sm font-semibold">Viitoare · <span x-text="up.length"></span></button>
            <button @click="tab='past'" :class="tab==='past'?'bg-brass text-ink':'text-paper/45'" class="rounded-lg px-4 py-2 text-sm font-semibold">Trecute · <span x-text="past.length"></span></button>
        </div>
        <button @click="window.print()" class="btn-secondary no-print inline-flex items-center gap-2 self-start rounded-full px-4 py-2.5 text-sm"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>Printează toate</button>
    </div>

    <div x-show="loading" class="py-10 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div><p class="mt-2 text-paper/40">Se încarcă biletele...</p></div>
    <div x-show="!loading && up.length===0 && past.length===0" x-cloak class="card mt-6 p-10 text-center"><p class="text-paper/45">Nu ai bilete încă.</p><a href="/program" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Descoperă spectacole</a></div>

    <div x-show="!loading && (up.length||past.length)" x-cloak>
        <div x-show="tab==='upcoming'" class="mt-6 grid gap-5">
            <template x-for="(t,i) in up" :key="i">
                <article class="print-card card overflow-hidden"><div class="grid lg:grid-cols-[130px_1fr_auto]">
                    <div class="grid min-h-32 place-items-center bg-wine/25 p-5 text-center"><strong class="font-display text-3xl" x-text="dayNum(t.date)"></strong><small class="tracking-[.15em] text-paper/40" x-text="monthTime(t)"></small></div>
                    <div class="p-5 sm:p-6"><div class="flex flex-wrap gap-2"><span class="badge bg-emerald-500/10 text-emerald-300">VALID</span><span class="badge bg-brass/10 text-brass-light" x-show="t.is_subscription">ABONAMENT</span></div><h2 class="mt-3 font-display text-2xl" x-text="t.event || 'Spectacol'"></h2><p class="mt-2 text-sm text-paper/42" x-text="[t.venue, t.seat_label].filter(Boolean).join(' · ')"></p><p class="mt-3 text-xs text-paper/30" x-text="'Cod ' + t.code"></p></div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-white/8 p-5 lg:w-52 lg:flex-col lg:justify-center lg:border-l lg:border-t-0"><button @click="showQR(t)" class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h2v2h-2v-2Zm4 0h2v6h-2m-4-2h2v2h-2v-2Z"/></svg>Vezi QR</button><a href="/cont/bilet" class="btn-secondary inline-flex w-full justify-center rounded-full px-4 py-2.5 text-sm">Detalii</a></div>
                </div></article>
            </template>
        </div>
        <div x-show="tab==='past'" x-cloak class="mt-6 grid gap-4">
            <template x-for="(t,i) in past" :key="i">
                <article class="card flex flex-col gap-4 p-5 opacity-75 sm:flex-row sm:items-center"><div class="grid h-16 w-16 shrink-0 place-items-center rounded-xl bg-white/5 font-display" x-text="dayNum(t.date)"></div><div class="flex-1"><h2 class="font-display text-xl" x-text="t.event || 'Spectacol'"></h2><p class="mt-1 text-xs text-paper/40" x-text="[t.seat_label, 'utilizat'].filter(Boolean).join(' · ')"></p></div><a href="/cont/scrie-recenzie" class="text-sm text-brass-light">Scrie o recenzie →</a></article>
            </template>
        </div>
    </div>

    <div x-cloak x-show="qr" x-transition.opacity class="fixed inset-0 z-[90] grid place-items-center bg-ink/90 p-5" @click.self="qr=false"><div class="w-full max-w-sm rounded-3xl bg-paper p-6 text-ink"><div class="flex items-center justify-between"><div><small class="font-bold tracking-[.15em] text-wine">BILET DIGITAL</small><h3 class="mt-1 font-display text-2xl" x-text="qrTicket.event || 'Spectacol'"></h3></div><button @click="qr=false" class="grid h-10 w-10 place-items-center rounded-full border border-black/10"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button></div><div class="mx-auto mt-6 grid aspect-square max-w-[240px] place-items-center rounded-2xl border-8 border-white bg-white shadow-xl"><canvas x-ref="qrcanvas" class="h-44 w-44"></canvas></div><p class="mt-5 text-center font-mono text-sm" x-text="qrTicket.code"></p><p class="mt-2 text-center text-xs text-black/50" x-text="qrTicket.seat_label || ''"></p></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
<script>
function ticketsPage() {
    return {
        loading: true, up: [], past: [], tab: 'upcoming', qr: false, qrTicket: {},
        auth() { try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        dayNum(iso) { if(!iso) return '—'; try { return new Date(iso).getDate(); } catch(e){ return '—'; } },
        monthTime(t) { if(!t.date) return ''; try { const d=new Date(t.date); const m=d.toLocaleDateString('ro-RO',{month:'short'}).toUpperCase(); return m + (t.time? ' · '+t.time.slice(0,5):''); } catch(e){ return ''; } },
        showQR(t) { this.qrTicket = t; this.qr = true; this.$nextTick(()=>{ if(window.QRCode && this.$refs.qrcanvas){ QRCode.toCanvas(this.$refs.qrcanvas, t.code||'TC', {width:176,margin:1}, ()=>{}); } }); },
        async load() {
            const a = this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try {
                const r = await fetch('/api/proxy.php?action=acc-tickets', { headers:{'Authorization':'Bearer '+a.token} });
                const d = await r.json().catch(()=>({}));
                if (d && d.success && d.data) { this.up = d.data.upcoming||[]; this.past = d.data.past||[]; }
            } catch(e){}
            this.loading = false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

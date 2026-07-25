<?php
$navActive = 'reviews';
$pageTitle = 'Recenziile mele — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">RECENZII</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Recenziile mele</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Recenzii publicate, în moderare și invitații de a evalua spectacolele la care ai participat.</p></div>
<div x-data="reviewsPage()" x-init="load()">
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4"><div class="card p-5"><p class="font-display text-3xl text-brass-light" x-text="stats.total ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Total recenzii</p></div><div class="card p-5"><p class="font-display text-3xl text-emerald-300" x-text="stats.published ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Publicate</p></div><div class="card p-5"><p class="font-display text-3xl text-amber-300" x-text="stats.pending ?? 0">0</p><p class="mt-1 text-xs text-paper/38">În moderare</p></div><div class="card p-5"><p class="font-display text-3xl text-brass-light" x-text="(stats.avg ?? 0)">0</p><p class="mt-1 text-xs text-paper/38">Rating mediu</p></div></div>
    <section class="card mt-5 border-amber-400/20 bg-amber-400/[.035] p-5" x-show="eligible > 0" x-cloak><div class="flex flex-col gap-4 sm:flex-row sm:items-center"><span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-500/10 text-amber-300"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 4h16v13H8l-4 4V4Zm4 4h8M8 12h5"/></svg></span><div class="flex-1"><strong x-text="'Ai ' + eligible + ' spectacol' + (eligible===1?'':'e') + ' care așteaptă o recenzie'"></strong><p class="mt-1 text-xs text-paper/38">Opinia poate fi trimisă numai după validarea participării.</p></div><a href="/cont/scrie-recenzie" class="btn-primary rounded-full px-5 py-2.5 text-sm font-bold">Scrie o recenzie</a></div></section>

    <div x-show="loading" class="py-10 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div></div>
    <div x-show="!loading && reviews.length===0" x-cloak class="card mt-6 p-10 text-center"><p class="text-paper/45">Nu ai scris nicio recenzie încă.</p><a href="/cont/scrie-recenzie" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Scrie prima recenzie</a></div>

    <div class="mt-5 grid gap-4" x-show="!loading && reviews.length" x-cloak>
        <template x-for="r in reviews" :key="r.id">
            <article class="card p-5"><div class="flex flex-col gap-4 sm:flex-row"><div class="h-20 w-20 shrink-0 rounded-xl bg-wine/20"></div><div class="flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="font-display text-xl" x-text="r.event || 'Spectacol'"></h2><span class="badge" :class="r.status==='published'?'bg-emerald-500/10 text-emerald-300':(r.status==='rejected'?'bg-rose-500/10 text-rose-300':'bg-amber-500/10 text-amber-300')" x-text="statusLabel(r.status)"></span></div><div class="mt-2 text-brass-light" x-text="stars(r.rating)"></div><h3 class="mt-3 font-semibold" x-text="r.title"></h3><p class="mt-2 text-sm leading-6 text-paper/45" x-text="r.body"></p><div class="mt-4 flex gap-4 text-xs"><span class="text-paper/30" x-text="dateStr(r.created_at)"></span></div></div></div></article>
        </template>
    </div>
</div>
<script>
function reviewsPage(){
    return {
        loading:true, reviews:[], stats:{}, eligible:0,
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        stars(n){ n=n||0; return '★★★★★☆☆☆☆☆'.slice(5-n,10-n); },
        statusLabel(s){ return ({published:'PUBLICATĂ',pending:'ÎN MODERARE',rejected:'RESPINSĂ'}[s]||(s||'').toUpperCase()); },
        dateStr(iso){ if(!iso) return ''; try { return new Date(iso).toLocaleDateString('ro-RO',{day:'numeric',month:'long',year:'numeric'}); } catch(e){ return ''; } },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-reviews',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success){ this.reviews=d.data||[]; this.stats=d.stats||{}; } } catch(e){}
            try { const r2=await fetch('/api/proxy.php?action=acc-reviews-eligible',{headers:{'Authorization':'Bearer '+a.token}}); const d2=await r2.json().catch(()=>({})); if(d2&&d2.success) this.eligible=(d2.data||[]).length; } catch(e){}
            this.loading=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

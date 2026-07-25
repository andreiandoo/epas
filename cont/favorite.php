<?php
$navActive = 'favorites';
$pageTitle = 'Favorite și alerte — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">FAVORITE</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Favorite și alerte</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Urmărește spectacole și artiști și primești alerte pentru date noi și locuri eliberate.</p></div>
<div x-data="favPage()" x-init="load()">
    <div class="flex gap-2 overflow-x-auto rounded-xl border border-white/8 bg-white/[.025] p-1">
        <button @click="tab='shows'" :class="tab==='shows'?'bg-brass text-ink':'text-paper/45'" class="rounded-lg px-4 py-2 text-sm font-semibold">Spectacole · <span x-text="shows.length"></span></button>
        <button @click="tab='artists'" :class="tab==='artists'?'bg-brass text-ink':'text-paper/45'" class="rounded-lg px-4 py-2 text-sm font-semibold">Artiști · <span x-text="artists.length"></span></button>
    </div>

    <div x-show="loading" class="py-10 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div></div>

    <div x-show="!loading" x-cloak>
        <div x-show="tab==='shows'">
            <div x-show="shows.length===0" class="card mt-6 p-10 text-center"><p class="text-paper/45">Niciun spectacol favorit încă.</p><a href="/repertoriu" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Vezi repertoriul</a></div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="f in shows" :key="f.id">
                    <article class="card card-hover overflow-hidden"><div class="h-36 bg-gradient-to-br from-drama/40 to-ink"></div><div class="p-5"><div class="flex justify-between"><span class="badge bg-brass/10 text-brass-light" x-text="f.category || 'SPECTACOL'"></span><button @click="remove(f)" class="text-rose-300" title="Elimină din favorite">♥</button></div><h3 class="mt-3 font-display text-2xl" x-text="f.title || 'Spectacol'"></h3><div class="mt-4 flex items-center justify-end"><a :href="f.slug ? '/spectacol/'+f.slug : '/program'" class="text-sm text-brass-light">Detalii →</a></div></div></article>
                </template>
            </div>
        </div>
        <div x-show="tab==='artists'" x-cloak>
            <div x-show="artists.length===0" class="card mt-6 p-10 text-center"><p class="text-paper/45">Niciun artist urmărit încă.</p><a href="/trupa" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Vezi trupa</a></div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="f in artists" :key="f.id">
                    <article class="card card-hover p-5 text-center"><div class="mx-auto h-24 w-24 rounded-full bg-gradient-to-br from-wine to-panel"></div><h3 class="mt-4 font-display text-xl" x-text="f.title || 'Artist'"></h3><button @click="remove(f)" class="mt-3 text-sm text-rose-300">♥ Urmărit</button></article>
                </template>
            </div>
        </div>
    </div>
</div>
<script>
function favPage(){
    return {
        loading:true, tab:'shows', shows:[], artists:[],
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-favorites',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success){ this.shows=d.data.events||[]; this.artists=d.data.artists||[]; } } catch(e){}
            this.loading=false;
        },
        async remove(f){
            const a=this.auth();
            try { await fetch('/api/proxy.php?action=acc-fav-toggle',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({item_type:f.item_type,item_id:f.item_id})}); } catch(e){}
            this.shows=this.shows.filter(x=>x.id!==f.id); this.artists=this.artists.filter(x=>x.id!==f.id);
            this.showToast('Eliminat din favorite');
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

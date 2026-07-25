<?php
$navActive = 'subscriptions';
$pageTitle = 'Abonamentele mele — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';

// Evenimente pentru consum (redeem) — injectate server-side
$eventsResp = api_get('/tenant-client/events', ['limit' => 30], 60);
$evList = [];
foreach (tc_events($eventsResp) as $ev) {
    $when = '';
    if (!empty($ev['start_date'])) { $ts = strtotime($ev['start_date']); if ($ts) { $when = date('d.m.Y', $ts); } }
    $evList[] = ['id' => $ev['id'] ?? 0, 'title' => $ev['title'] ?? '', 'date' => $when];
}
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">ABONAMENTE</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Abonamentele mele</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Vezi intrările rămase, locurile tale și rezervă reprezentațiile incluse.</p></div>
<div x-data="subsPage()" x-init="load()">
    <div x-show="loading" class="py-10 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div></div>

    <div x-show="!loading && subs.length===0" x-cloak class="card p-10 text-center"><p class="text-paper/45 mb-4">Nu ai niciun abonament activ.</p><a href="/abonamente" class="btn-primary inline-flex rounded-full px-6 py-3 text-sm font-bold">Vezi abonamentele</a></div>

    <div class="space-y-6" x-show="!loading" x-cloak>
        <template x-for="s in subs" :key="s.id">
            <section class="grain relative overflow-hidden rounded-3xl border border-brass/25 bg-gradient-to-br from-wine-dark via-panel to-coal p-6 sm:p-8">
                <div class="relative grid gap-7 lg:grid-cols-[1fr_.8fr] lg:items-start">
                    <div>
                        <span class="badge" :class="s.status==='active'?'bg-emerald-500/12 text-emerald-300':'bg-white/10 text-paper/50'" x-text="s.status==='active'?'ABONAMENT ACTIV':s.status.toUpperCase()"></span>
                        <h2 class="mt-4 font-display text-4xl" x-text="'Abonament ' + s.name"></h2>
                        <p class="mt-3 text-paper/50" x-show="s.valid_until" x-text="'Valabil până la ' + s.valid_until"></p>
                        <div class="mt-5" x-show="s.seat_labels && s.seat_labels.length">
                            <p class="text-xs text-paper/35 mb-2">LOCURILE TALE</p>
                            <div class="flex flex-wrap gap-2"><template x-for="l in s.seat_labels" :key="l"><span class="rounded-lg bg-ink/40 px-2 py-1 text-xs" x-text="l"></span></template></div>
                        </div>
                        <ul class="mt-5 space-y-1 text-sm" x-show="s.benefits && s.benefits.length"><template x-for="bft in s.benefits" :key="bft"><li class="flex items-center gap-2"><span class="text-brass-light">✓</span><span x-text="bft"></span></li></template></ul>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-ink/40 p-5">
                        <div class="flex items-end justify-between"><div><strong class="font-display text-5xl" x-text="s.shows_remaining ?? '∞'"></strong><span class="ml-2 text-paper/40">rămase</span></div><span class="text-sm text-brass-light" x-show="s.shows_included" x-text="Math.round(100*(s.shows_used||0)/(s.shows_included||1)) + '%'"></span></div>
                        <div class="mt-4 h-3 rounded-full bg-white/10" x-show="s.shows_included"><div class="h-full rounded-full bg-brass" :style="'width:' + Math.round(100*(s.shows_used||0)/(s.shows_included||1)) + '%'"></div></div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs"><div class="rounded-xl bg-white/[.035] p-3"><span class="text-paper/35">Folosite</span><strong class="mt-1 block text-lg" x-text="s.shows_used ?? 0"></strong></div><div class="rounded-xl bg-white/[.035] p-3"><span class="text-paper/35">Incluse</span><strong class="mt-1 block text-lg" x-text="s.shows_included ?? '∞'"></strong></div></div>

                        <div class="mt-5 border-t border-white/10 pt-4" x-show="s.status==='active' && (s.shows_remaining>0 || s.shows_remaining===null)">
                            <p class="text-xs text-paper/40 mb-2">Folosește la un spectacol</p>
                            <div class="flex gap-2">
                                <select x-model="pick[s.id]" class="input flex-1 text-sm"><option value="">Alege spectacolul...</option><template x-for="ev in avail(s)" :key="ev.id"><option :value="ev.id" x-text="ev.title + (ev.date?' — '+ev.date:'')"></option></template></select>
                                <button @click="redeem(s)" :disabled="!pick[s.id]||busy" class="btn-primary rounded-full px-4 text-sm font-bold">Rezervă</button>
                            </div>
                            <p x-show="err[s.id]" x-text="err[s.id]" class="mt-2 text-xs text-rose-300"></p>
                        </div>

                        <div class="mt-4" x-show="s.redeemed_events && s.redeemed_events.length">
                            <p class="text-xs text-paper/40 mb-1">Rezervate</p>
                            <template x-for="r in s.redeemed_events" :key="r.event_id"><div class="flex justify-between text-xs border-b border-white/8 py-1"><span x-text="r.title"></span><span class="text-paper/40" x-text="r.date"></span></div></template>
                        </div>
                    </div>
                </div>
            </section>
        </template>
        <section class="card p-5 sm:p-6" x-show="subs.length"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-2xl">Vrei un abonament nou?</h2><p class="mt-2 text-sm text-paper/40">Descoperă pachetele disponibile pentru stagiunea curentă.</p></div><a href="/abonamente" class="btn-secondary rounded-full px-5 py-3 text-sm">Vezi opțiunile</a></div></section>
    </div>
</div>
<script>
function subsPage(){
    return {
        loading:true, subs:[], busy:false, pick:{}, err:{},
        events: <?= json_encode($evList, JSON_UNESCAPED_UNICODE) ?>,
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        avail(s){ const used=(s.redeemed_events||[]).map(r=>r.event_id); return this.events.filter(e=>e.id && !used.includes(e.id)); },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=my-subscriptions',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success&&Array.isArray(d.data)) this.subs=d.data; } catch(e){}
            this.loading=false;
        },
        async redeem(s){
            const eid=this.pick[s.id]; if(!eid||this.busy) return;
            this.busy=true; this.err[s.id]=''; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=redeem&sub='+s.id,{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({event_id:parseInt(eid)})});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ this.pick[s.id]=''; await this.load(); this.showToast('Rezervare realizată'); }
                else this.err[s.id]=d.error||'Nu am putut rezerva.';
            } catch(e){ this.err[s.id]='Eroare de conexiune.'; }
            this.busy=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

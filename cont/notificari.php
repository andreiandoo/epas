<?php
$navActive = 'notifications';
$pageTitle = 'Notificări — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">NOTIFICĂRI</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Notificări</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Confirmări de comandă, schimbări de program, alerte de disponibilitate și beneficii.</p></div>
<div x-data="notifPage()" x-init="load()">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-2 overflow-x-auto">
            <template x-for="t in tabs" :key="t[0]">
                <button @click="tab=t[0]" :class="tab===t[0]?'bg-brass text-ink':'border-white/10 text-paper/45'" class="whitespace-nowrap rounded-full border px-4 py-2 text-sm" x-text="t[1] + countFor(t[0])"></button>
            </template>
        </div>
        <button @click="markAll()" class="text-sm text-brass-light">Marchează toate ca citite</button>
    </div>

    <div x-show="loading" class="py-12 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div></div>
    <div x-show="!loading && filtered().length===0" x-cloak class="card mt-6 p-10 text-center"><p class="text-paper/45">Nicio notificare în această categorie.</p></div>

    <div class="card mt-6 overflow-hidden divide-y divide-white/8" x-show="!loading && filtered().length" x-cloak>
        <template x-for="(n,i) in filtered()" :key="i">
            <article class="flex gap-4 p-5" :class="isRead(n)?'':'bg-brass/[.035]'">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" :class="iconBg(n.icon)">
                    <span x-html="iconSvg(n.icon)"></span>
                </span>
                <div class="flex-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <strong x-text="n.title"></strong>
                            <p class="mt-1 text-sm leading-6 text-paper/44" x-text="n.body"></p>
                        </div>
                        <span x-show="!isRead(n)" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brass"></span>
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-xs">
                        <span class="text-paper/30" x-text="ago(n.at)"></span>
                        <a :href="n.link" class="text-brass-light" x-text="n.link_label || 'Deschide'"></a>
                    </div>
                </div>
            </article>
        </template>
    </div>
</div>
<script>
function notifPage(){
    return {
        loading:true, tab:'all', items:[], readKeys:[],
        tabs:[['all','Toate'],['unread','Necitite'],['tickets','Bilete'],['program','Program'],['benefits','Beneficii']],
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        key(n){ return (n.type||'')+'|'+(n.title||'')+'|'+(n.at||''); },
        loadRead(){ try { this.readKeys = JSON.parse(localStorage.getItem('teatru_notif_read')||'[]'); } catch(e){ this.readKeys=[]; } },
        saveRead(){ try { localStorage.setItem('teatru_notif_read', JSON.stringify(this.readKeys.slice(-200))); } catch(e){} },
        isRead(n){ return this.readKeys.includes(this.key(n)); },
        markAll(){ this.items.forEach(n=>{ const k=this.key(n); if(!this.readKeys.includes(k)) this.readKeys.push(k); }); this.saveRead(); this.showToast('Toate notificările au fost marcate drept citite'); },
        filtered(){ if(this.tab==='all') return this.items; if(this.tab==='unread') return this.items.filter(n=>!this.isRead(n)); return this.items.filter(n=>n.type===this.tab); },
        countFor(t){ const n = t==='all'?this.items.length : (t==='unread'?this.items.filter(x=>!this.isRead(x)).length : this.items.filter(x=>x.type===t).length); return n?(' · '+n):''; },
        iconBg(ic){ return ({ticket:'bg-emerald-500/10 text-emerald-300',star:'bg-brass/10 text-brass-light',calendar:'bg-imaginario/15 text-teal-200'})[ic]||'bg-wine/15 text-rose-200'; },
        iconSvg(ic){
            const p = {
                ticket:'M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5V9a3 3 0 0 0 0 6v1.5a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5V15a3 3 0 0 0 0-6V7.5ZM14 8v8',
                star:'m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z',
                calendar:'M6 3v3m12-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z'
            }[ic] || 'M12 9v4m0 4h.01M10.3 3.7 2.6 18a2 2 0 0 0 1.8 3h15.2a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0Z';
            return '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="'+p+'"/></svg>';
        },
        ago(iso){ if(!iso) return ''; try { const d=new Date(iso), s=Math.floor((Date.now()-d)/1000); if(s<3600) return 'acum '+Math.max(1,Math.floor(s/60))+' min'; if(s<86400) return 'acum '+Math.floor(s/3600)+' h'; if(s<172800) return 'ieri'; return 'acum '+Math.floor(s/86400)+' zile'; } catch(e){ return ''; } },
        async load(){
            this.loadRead();
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-notifications',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success) this.items=d.data||[]; } catch(e){}
            this.loading=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

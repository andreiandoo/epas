<?php
$navActive = 'calendar';
$pageTitle = 'Calendarul meu — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">CALENDAR PERSONAL</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Calendarul meu</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Toate reprezentațiile rezervate și spectacolele urmărite, sincronizabile cu propriul calendar.</p></div>
<div x-data="calPage()" x-init="load()"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div class="flex gap-2"><button @click="view='month'" :class="view==='month'?'bg-brass text-ink':'border-white/10 text-paper/45'" class="rounded-full border px-4 py-2 text-sm">Lună</button><button @click="view='list'" :class="view==='list'?'bg-brass text-ink':'border-white/10 text-paper/45'" class="rounded-full border px-4 py-2 text-sm">Listă</button></div><div class="flex gap-2"><button @click="copy('https://teatru.tixello.ro/cont/calendar.ics','Link iCal copiat')" class="btn-secondary rounded-full px-4 py-2.5 text-sm">Abonare iCal</button><button @click="showToast('Calendar exportat')" class="btn-secondary rounded-full px-4 py-2.5 text-sm">Exportă</button></div></div>
<div x-show="view==='month'" class="card mt-6 overflow-hidden"><div class="flex items-center justify-between border-b border-white/8 p-5"><button class="grid h-9 w-9 place-items-center rounded-full border border-white/10">‹</button><h2 class="font-display text-2xl">Iulie 2026</h2><button class="grid h-9 w-9 place-items-center rounded-full border border-white/10">›</button></div><div class="grid grid-cols-7 border-b border-white/8 text-center text-[10px] font-bold tracking-widest text-paper/30"><div class="p-3">L</div><div class="p-3">M</div><div class="p-3">M</div><div class="p-3">J</div><div class="p-3">V</div><div class="p-3">S</div><div class="p-3">D</div></div><div class="grid grid-cols-7"><?php
for ($d = 1; $d <= 35; $d++) {
    $badge = '';
    if ($d === 3) { $badge = '<span class="absolute bottom-2 left-2 right-2 truncate rounded bg-imaginario/25 px-1.5 py-1 text-[9px] text-teal-100">Cartea Junglei</span>'; }
    if ($d === 28) { $badge = '<span class="absolute bottom-2 left-2 right-2 truncate rounded bg-wine/30 px-1.5 py-1 text-[9px] text-rose-100">A Jazzy Story</span>'; }
    echo '<button @click="selected=' . $d . '" :class="selected===' . $d . '?\'bg-brass/15 ring-1 ring-inset ring-brass/40\':\'\'" class="relative min-h-20 border-b border-r border-white/6 p-2 text-left text-sm hover:bg-white/[.03]"><span class="text-paper/60">' . $d . '</span>' . $badge . '</button>';
}
?></div></div>
<div x-cloak x-show="view==='list'" class="mt-6 grid gap-3">
    <div x-show="tickets.length===0" class="card p-10 text-center"><p class="text-paper/45">Nu ai reprezentații programate.</p><a href="/program" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Vezi programul</a></div>
    <template x-for="(t,i) in tickets" :key="i">
        <article class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center"><div class="grid h-14 w-14 place-items-center rounded-xl bg-wine/20 font-display text-xl" x-text="dayNum(t.date)"></div><div class="flex-1"><strong x-text="t.event || 'Spectacol'"></strong><p class="mt-1 text-xs text-paper/38" x-text="[(t.time||'').slice(0,5), t.venue, t.seat_label].filter(Boolean).join(' · ')"></p></div><a href="/cont/bilet" class="text-sm text-brass-light">Bilet →</a></article>
    </template>
</div>
<section class="card mt-6 p-5"><h2 class="font-display text-2xl">Preferințe calendar</h2><div class="mt-4 grid gap-4 md:grid-cols-3"><label class="rounded-xl bg-white/[.025] p-4"><span class="flex items-center justify-between text-sm">Bilete cumpărate<span class="switch on"></span></span><p class="mt-2 text-xs text-paper/35">Adăugate automat.</p></label><label class="rounded-xl bg-white/[.025] p-4"><span class="flex items-center justify-between text-sm">Reprezentații favorite<span class="switch on"></span></span><p class="mt-2 text-xs text-paper/35">Doar date confirmate.</p></label><label class="rounded-xl bg-white/[.025] p-4"><span class="flex items-center justify-between text-sm">Reminder cu 24h înainte<span class="switch on"></span></span><p class="mt-2 text-xs text-paper/35">Email și notificare.</p></label></div></section></div>
<script>
function calPage(){
    return {
        view:'month', selected:28, tickets:[],
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        dayNum(iso){ if(!iso) return '—'; try { return new Date(iso).getDate(); } catch(e){ return '—'; } },
        async load(){
            const a=this.auth(); if(!a||!a.token) return;
            try { const r=await fetch('/api/proxy.php?action=acc-tickets',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success&&d.data) this.tickets=d.data.upcoming||[]; } catch(e){}
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

<?php
$navActive = 'dashboard';
$pageTitle = 'Panou principal — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';

// Recomandate — evenimente reale injectate server-side (linkuri către /spectacol/{slug})
$recEvents = [];
foreach (tc_events(api_get('/tenant-client/events', ['limit' => 8], 120)) as $ev) {
    $slug = $ev['slug'] ?? '';
    if ($slug === '') { continue; }
    $when = '';
    if (!empty($ev['start_date'])) { $ts = strtotime($ev['start_date']); if ($ts) { $when = date('d.m.Y', $ts); } }
    $recEvents[] = [
        'title'    => $ev['title'] ?? 'Spectacol',
        'slug'     => $slug,
        'category' => $ev['category']['name'] ?? '',
        'date'     => $when,
    ];
    if (count($recEvents) >= 3) { break; }
}
?>
<div class="mb-9">
    <p class="text-[11px] font-bold tracking-[.22em] text-brass-light">PANOU PRINCIPAL</p>
    <h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Bun venit, <span x-text="firstName">spectator</span>.</h1>
    <p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Bilete, abonamente, favorite și toate interacțiunile tale cu teatrul, într-un singur loc.</p>
</div>

<div x-data="dashPage()" x-init="load()">
    <section class="grain relative overflow-hidden rounded-3xl border border-brass/20 bg-gradient-to-br from-wine-dark via-panel to-coal p-6 sm:p-8">
        <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brass/10 blur-3xl"></div>
        <div class="relative grid gap-8 xl:grid-cols-[1.2fr_.8fr] xl:items-end">
            <div>
                <template x-if="next">
                    <div>
                        <span class="badge bg-brass/12 text-brass-light" x-text="'URMĂTORUL SPECTACOL' + (daysTo(next.date)!==null ? ' · ' + daysLabel(next.date) : '')"></span>
                        <h2 class="mt-4 font-display text-4xl sm:text-5xl" x-text="next.event || 'Spectacol'"></h2>
                        <p class="mt-3 text-paper/60" x-text="[fullDate(next.date), (next.time||'').slice(0,5), next.venue, next.seat_label].filter(Boolean).join(' · ')"></p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="/cont/bilete" class="btn-primary inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-bold"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h2v2h-2v-2Zm4 0h2v6h-2m-4-2h2v2h-2v-2Z"/></svg>Deschide biletul</a>
                        </div>
                    </div>
                </template>
                <template x-if="!next && !loading">
                    <div>
                        <span class="badge bg-brass/12 text-brass-light">BINE AI VENIT</span>
                        <h2 class="mt-4 font-display text-4xl sm:text-5xl">Următoarea ta seară la teatru</h2>
                        <p class="mt-3 text-paper/60">Nu ai încă un bilet activ. Descoperă programul stagiunii curente.</p>
                        <div class="mt-6 flex flex-wrap gap-3"><a href="/program" class="btn-primary inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-bold">Vezi programul</a></div>
                    </div>
                </template>
                <div x-show="loading" class="h-24 animate-pulse rounded-2xl bg-white/[.04]"></div>
            </div>

            <template x-if="sub">
                <div class="rounded-2xl border border-white/10 bg-ink/45 p-5">
                    <div class="flex items-center justify-between"><span class="text-xs text-paper/40" x-text="('Abonament ' + (sub.name||'')).toUpperCase()"></span><span class="badge bg-emerald-500/12 text-emerald-300" x-show="sub.status==='active'">ACTIV</span></div>
                    <div class="mt-5 flex items-end justify-between"><div><strong class="font-display text-3xl" x-text="sub.shows_remaining ?? '∞'"></strong><span class="ml-2 text-sm text-paper/40" x-text="sub.shows_included ? ('din ' + sub.shows_included + ' intrări rămase') : 'intrări nelimitate'"></span></div><a href="/cont/abonamente" class="text-sm text-brass-light">Gestionează →</a></div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10" x-show="sub.shows_included"><div class="h-full rounded-full bg-brass" :style="'width:' + pct(sub) + '%'"></div></div>
                </div>
            </template>
            <template x-if="!sub && !loading">
                <div class="rounded-2xl border border-white/10 bg-ink/45 p-5">
                    <span class="text-xs text-paper/40">ABONAMENTE</span>
                    <p class="mt-3 text-sm text-paper/55">Nu ai un abonament activ momentan.</p>
                    <a href="/abonamente" class="btn-secondary mt-4 inline-flex rounded-full px-4 py-2 text-sm">Vezi abonamentele</a>
                </div>
            </template>
        </div>
    </section>

    <div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="card p-5"><p class="font-display text-3xl text-brass-light" x-text="s.upcoming_tickets ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Bilete viitoare</p></div>
        <div class="card p-5"><p class="font-display text-3xl text-emerald-300" x-text="s.active_subscriptions ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Abonamente active</p></div>
        <div class="card p-5"><p class="font-display text-3xl text-rose-300" x-text="s.favorites ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Favorite</p></div>
        <div class="card p-5"><p class="font-display text-3xl text-amber-300" x-text="s.points ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Puncte</p></div>
    </div>

    <div class="mt-7 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
        <div class="space-y-6">
            <section class="card p-5 sm:p-6">
                <div class="flex items-center justify-between"><div><h2 class="font-display text-2xl">Următoarele experiențe</h2><p class="mt-1 text-xs text-paper/35">Bilete și rezervări active</p></div><a href="/cont/bilete" class="text-sm text-brass-light">Vezi toate</a></div>
                <div x-show="loading" class="mt-5 h-20 animate-pulse rounded-2xl bg-white/[.04]"></div>
                <div x-show="!loading && upcoming.length===0" x-cloak class="mt-5 rounded-2xl border border-white/8 bg-ink/35 p-6 text-center text-sm text-paper/40">Nu ai bilete viitoare. <a href="/program" class="text-brass-light">Vezi programul</a></div>
                <div class="mt-5 grid gap-3" x-show="!loading && upcoming.length" x-cloak>
                    <template x-for="(t,i) in upcoming.slice(0,4)" :key="i">
                        <a href="/cont/bilete" class="card-hover flex flex-col gap-4 rounded-2xl border border-white/8 bg-ink/35 p-4 sm:flex-row sm:items-center">
                            <div class="grid h-16 w-16 shrink-0 place-items-center rounded-xl bg-wine/30 text-center"><strong class="font-display text-2xl" x-text="dnum(t.date)"></strong><small class="text-[10px] text-paper/40" x-text="dmon(t.date)"></small></div>
                            <div class="min-w-0 flex-1"><strong class="font-display text-xl" x-text="t.event || 'Spectacol'"></strong><p class="mt-1 text-xs text-paper/40" x-text="[(t.time||'').slice(0,5), t.venue, t.seat_label].filter(Boolean).join(' · ')"></p></div>
                            <span class="badge" :class="t.is_subscription ? 'bg-brass/10 text-brass-light' : 'bg-emerald-500/10 text-emerald-300'" x-text="t.is_subscription ? 'ABONAMENT' : 'CONFIRMAT'"></span>
                        </a>
                    </template>
                </div>
            </section>

            <section class="card p-5 sm:p-6">
                <div class="flex items-center justify-between"><h2 class="font-display text-2xl">Recomandate pentru tine</h2><a href="/repertoriu" class="text-sm text-brass-light">Repertoriu</a></div>
                <?php if (!empty($recEvents)): ?>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <?php foreach ($recEvents as $rev): ?>
                    <a href="/spectacol/<?= e($rev['slug']) ?>" class="card-hover rounded-2xl border border-white/8 bg-ink/35 p-5">
                        <?php if ($rev['category'] !== ''): ?><small class="text-brass-light"><?= e($rev['category']) ?></small><?php endif; ?>
                        <h3 class="mt-3 font-display text-xl"><?= e($rev['title']) ?></h3>
                        <p class="mt-2 text-xs leading-6 text-paper/38"><?= $rev['date'] !== '' ? e($rev['date']) : 'Rezervă-ți locul' ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="mt-5 rounded-2xl border border-white/8 bg-ink/35 p-6 text-center text-sm text-paper/40">Programul stagiunii va fi anunțat în curând. <a href="/repertoriu" class="text-brass-light">Vezi repertoriul</a></p>
                <?php endif; ?>
            </section>
        </div>

        <div class="space-y-6">
            <section class="card p-5"><div class="flex items-center justify-between"><h2 class="font-display text-xl">Acțiuni rapide</h2></div><div class="mt-4 grid gap-2"><a href="/cont/bilete" class="flex items-center gap-3 rounded-xl bg-white/[.035] p-3 text-sm hover:bg-brass/8"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5V9a3 3 0 0 0 0 6v1.5a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5V15a3 3 0 0 0 0-6V7.5ZM14 8v8"/></svg>Deschide biletele</a><a href="/cont/calendar" class="flex items-center gap-3 rounded-xl bg-white/[.035] p-3 text-sm hover:bg-brass/8"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M6 3v3m12-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm3 7h3v3H8z"/></svg>Calendar personal</a><a href="/cont/carduri-cadou" class="flex items-center gap-3 rounded-xl bg-white/[.035] p-3 text-sm hover:bg-brass/8"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 10h16v10H4V10Zm-1-4h18v4H3V6Zm9 0v14M12 6H8.5A2.5 2.5 0 1 1 11 3.5V6Zm0 0h3.5A2.5 2.5 0 1 0 13 3.5V6Z"/></svg>Folosește un card cadou</a><a href="/cont/ajutor" class="flex items-center gap-3 rounded-xl bg-white/[.035] p-3 text-sm hover:bg-brass/8"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9.1 9a3 3 0 1 1 4.8 2.4c-1 .8-1.9 1.3-1.9 2.6M12 18h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z"/></svg>Cere ajutor</a></div></section>

            <section class="card p-5"><div class="flex items-start gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-wine/20 text-rose-300"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12h4"/></svg></span><div><h3 class="font-semibold">Notificări</h3><p class="mt-1 text-xs leading-6 text-paper/40">Confirmări de comandă, puncte primite și noutăți despre abonamentele tale.</p><a href="/cont/notificari" class="mt-3 inline-flex text-sm text-brass-light">Vezi notificările →</a></div></div></section>

            <section class="card p-5"><h2 class="font-display text-xl">Profilul tău cultural</h2><p class="mt-3 text-xs leading-6 text-paper/40">Un rezumat al preferințelor și activității tale, generat din participările la spectacole.</p><a href="/cont/profil" class="mt-4 inline-flex text-sm text-brass-light">Vezi profilul →</a></section>
        </div>
    </div>
</div>
<script>
function dashPage(){
    const MON=['IAN','FEB','MAR','APR','MAI','IUN','IUL','AUG','SEP','OCT','NOV','DEC'];
    return {
        loading:true, s:{}, subs:[], upcoming:[],
        get sub(){ return this.subs[0] || null; },
        get next(){ return this.upcoming[0] || null; },
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        pct(su){ if(!su||!su.shows_included) return 0; return Math.round(100*(su.shows_used||0)/(su.shows_included||1)); },
        dnum(iso){ if(!iso) return '—'; try { return String(new Date(iso).getDate()).padStart(2,'0'); } catch(e){ return '—'; } },
        dmon(iso){ if(!iso) return ''; try { return MON[new Date(iso).getMonth()]; } catch(e){ return ''; } },
        fullDate(iso){ if(!iso) return ''; try { const d=new Date(iso).toLocaleDateString('ro-RO',{weekday:'long',day:'numeric',month:'long'}); return d.charAt(0).toUpperCase()+d.slice(1); } catch(e){ return ''; } },
        daysTo(iso){ if(!iso) return null; try { const d=new Date(iso); d.setHours(0,0,0,0); const t=new Date(); t.setHours(0,0,0,0); return Math.round((d-t)/86400000); } catch(e){ return null; } },
        daysLabel(iso){ const n=this.daysTo(iso); if(n===null) return ''; if(n<=0) return 'ASTĂZI'; if(n===1) return 'MÂINE'; return n+' ZILE'; },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            const h={'Authorization':'Bearer '+a.token};
            const get=(action)=>fetch('/api/proxy.php?action='+action,{headers:h}).then(r=>r.json()).catch(()=>({}));
            const [st,su,tk]=await Promise.all([get('acc-stats'),get('my-subscriptions'),get('acc-tickets')]);
            if(st&&st.success) this.s=st.data||{};
            if(su&&su.success&&Array.isArray(su.data)) this.subs=su.data.filter(x=>x.status!=='cancelled');
            if(tk&&tk.success&&tk.data) this.upcoming=(tk.data.upcoming||[]).slice().sort((x,y)=>String(x.date||'').localeCompare(String(y.date||'')));
            this.loading=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

<?php
/**
 * Închiderea layout-ului cont: overlays (meniu mobil + search) + toast + JS shell.
 */
$navGroups = $navGroups ?? require __DIR__ . '/nav.php';
$navActive = $navActive ?? '';
$itemClass = fn (bool $active) => $active
    ? 'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition border-brass/30 bg-brass/10 text-brass-light'
    : 'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition border-transparent text-paper/58 hover:bg-white/[.045] hover:text-paper';
?>
        </section>
    </div>
</main>

<!-- Mobile menu overlay -->
<div x-cloak x-show="mobileMenu" x-transition.opacity class="fixed inset-0 z-[70] bg-ink/98 p-5 lg:hidden overflow-y-auto">
    <div class="flex items-center justify-between">
        <strong class="font-display text-2xl">Contul meu</strong>
        <button @click="mobileMenu=false" class="grid h-11 w-11 place-items-center rounded-full border border-white/10"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <nav class="mt-7 grid gap-1">
        <?php foreach ($navGroups as $group => $items): ?>
        <p class="mb-2 mt-5 px-3 text-[10px] font-bold tracking-[.18em] text-paper/25"><?= e($group) ?></p>
        <?php foreach ($items as $it): $active = $it['key'] === $navActive; ?>
        <a href="<?= e($it['url']) ?>" class="<?= $itemClass($active) ?>"><?= $it['icon'] ?><span><?= e($it['label']) ?></span></a>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <a href="#" @click.prevent="logout()" class="mt-4 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-paper/40"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg>Ieșire din cont</a>
    </nav>
</div>

<!-- Search overlay -->
<div x-cloak x-show="searchOpen" x-transition.opacity class="fixed inset-0 z-[80] bg-ink/98 p-5 backdrop-blur-xl">
    <div class="mx-auto max-w-2xl pt-16">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-3xl">Caută</h2>
            <button @click="searchOpen=false" class="grid h-11 w-11 place-items-center rounded-full border border-white/10"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <input x-ref="search" x-model="query" class="input mt-7 text-lg" placeholder="Spectacol, comandă, ajutor...">
        <div class="mt-5 grid gap-2">
            <a href="/cont/bilete" class="card flex items-center justify-between p-4"><span>Biletele mele</span><span class="text-brass">→</span></a>
            <a href="/cont/comenzi" class="card flex items-center justify-between p-4"><span>Comenzile mele</span><span class="text-brass">→</span></a>
            <a href="/cont/ajutor" class="card flex items-center justify-between p-4"><span>Ajutor și suport</span><span class="text-brass">→</span></a>
        </div>
    </div>
</div>

<div x-cloak x-show="toast" x-transition class="fixed bottom-5 left-1/2 z-[100] -translate-x-1/2 rounded-full border border-brass/30 bg-coal px-5 py-3 text-sm text-brass-light shadow-2xl" x-text="toast"></div>

<script>
function accountShell(){
    return {
        mobileMenu:false, searchOpen:false, query:'', toast:'',
        userName:'Cont client', userEmail:'—', firstName:'Cont', initials:'AP', token:null,
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        init(){
            const a = this.auth();
            if(!a || !a.token){ window.location.href='/autentificare?next='+encodeURIComponent(location.pathname+location.search); return; }
            this.token = a.token;
            this.setUser(a.user);
            // date proaspete
            fetch('/api/proxy.php?action=me',{headers:{'Authorization':'Bearer '+a.token}})
                .then(r=>r.json()).then(d=>{ if(d && d.success && d.data){ this.setUser(d.data); localStorage.setItem('teatru_auth', JSON.stringify({token:a.token,user:d.data})); } })
                .catch(()=>{});
        },
        setUser(u){
            if(!u) return;
            this.userName = u.name || 'Cont client';
            this.userEmail = u.email || '';
            const parts = (u.name||'').trim().split(/\s+/);
            this.firstName = parts[0] || 'Cont';
            this.initials = ((parts[0]?.[0]||'')+(parts[1]?.[0]||'')).toUpperCase() || 'C';
        },
        showToast(m){ this.toast=m; setTimeout(()=>this.toast='',2600); },
        copy(t,m='Copiat'){ navigator.clipboard?.writeText(t); this.showToast(m); },
        close(){ this.mobileMenu=false; this.searchOpen=false; },
        async logout(){
            try { await fetch('/api/proxy.php?action=logout',{method:'POST',headers:{'Authorization':'Bearer '+(this.token||'')}}); } catch(e){}
            localStorage.removeItem('teatru_auth'); window.location.href='/';
        }
    };
}
</script>
</body>
</html>

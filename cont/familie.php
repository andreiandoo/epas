<?php
$navActive = 'family';
$pageTitle = 'Cont de familie — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">FAMILIE</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Cont de familie</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Beneficiari, profiluri pentru copii, bilete și abonamente gestionate împreună.</p></div>
<div x-data="familyPage()" x-init="load()">
    <section class="grain relative overflow-hidden rounded-3xl border border-imaginario/25 bg-gradient-to-br from-imaginario/35 via-panel to-coal p-6 sm:p-8"><div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><span class="badge bg-imaginario/20 text-teal-100">CONT DE FAMILIE</span><h2 class="mt-4 font-display text-4xl" x-text="'Familia ' + (lastName() || 'mea')">Familia mea</h2><p class="mt-3 max-w-2xl text-sm leading-7 text-paper/48">Gestionează beneficiarii, biletele pentru copii și utilizarea abonamentelor de familie.</p></div><button @click="add=true" class="btn-primary self-start rounded-full px-5 py-3 text-sm font-bold">Adaugă membru</button></div></section>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article class="card p-5"><div class="flex items-center gap-4"><span class="grid h-14 w-14 place-items-center rounded-full bg-wine font-display text-xl" x-text="initials">AP</span><div><h3 class="font-display text-xl" x-text="userName">Contul meu</h3><p class="text-xs text-paper/35">Administrator · adult</p></div></div><div class="mt-4 grid grid-cols-2 gap-2 text-center"><div class="rounded-xl bg-white/[.025] p-3"><strong x-text="tickets.length">0</strong><p class="text-[10px] text-paper/35">bilete active</p></div><div class="rounded-xl bg-white/[.025] p-3"><strong x-text="members.length + 1"></strong><p class="text-[10px] text-paper/35">membri</p></div></div></article>

        <template x-for="m in members" :key="m.id">
            <article class="card p-5" :class="m.relation==='child'?'border-imaginario/25':''">
                <div class="flex items-center gap-4"><span class="grid h-14 w-14 place-items-center rounded-full font-display text-xl" :class="m.relation==='child'?'bg-imaginario/25':'bg-brass/25'" x-text="mInitials(m.name)"></span><div><h3 class="font-display text-xl" x-text="m.name"></h3><p class="text-xs text-paper/35" x-text="m.relation==='child'?'Copil · gestionat de administrator':'Adult · poate primi bilete'"></p></div></div>
                <div class="mt-4 flex gap-2">
                    <span x-show="m.status==='invited'" class="badge bg-amber-500/10 text-amber-300 self-center">INVITAT</span>
                    <button @click="remove(m)" class="btn-secondary flex-1 rounded-xl py-2 text-xs">Elimină</button>
                </div>
            </article>
        </template>

        <article x-show="!loading && members.length===0" x-cloak class="card border-dashed border-white/12 p-5 text-center">
            <p class="text-sm text-paper/40">Niciun beneficiar adăugat încă.</p>
            <button @click="add=true" class="btn-primary mt-3 rounded-full px-4 py-2 text-xs font-bold">Adaugă primul membru</button>
        </article>
    </div>

    <section class="card mt-7 p-5 sm:p-6"><div class="flex items-center justify-between"><div><h2 class="font-display text-2xl">Abonament Familie</h2><p class="mt-1 text-sm text-paper/38">Gestionează abonamentele partajate cu beneficiarii.</p></div><a href="/cont/abonamente" class="text-sm text-brass-light">Gestionează</a></div><div class="mt-5 grid gap-3 md:grid-cols-2"><div class="rounded-xl bg-white/[.025] p-4"><strong class="text-sm">Beneficiari activi</strong><div class="mt-3 flex -space-x-2"><span class="grid h-9 w-9 place-items-center rounded-full border-2 border-panel bg-wine text-xs" x-text="initials"></span><template x-for="m in members.slice(0,4)" :key="m.id"><span class="grid h-9 w-9 place-items-center rounded-full border-2 border-panel text-xs" :class="m.relation==='child'?'bg-imaginario/40':'bg-brass/40'" x-text="mInitials(m.name)"></span></template></div></div><div class="rounded-xl bg-white/[.025] p-4"><strong class="text-sm">Permisiuni</strong><p class="mt-2 text-xs leading-6 text-paper/38">Doar administratorul poate cumpăra, transfera sau solicita rambursări.</p></div></div></section>

    <section class="card mt-7 p-5 sm:p-6"><h2 class="font-display text-2xl">Bilete ale familiei</h2>
        <div x-show="tickets.length===0" class="mt-4 rounded-xl bg-white/[.025] p-4 text-sm text-paper/40">Nu există bilete viitoare în cont.</div>
        <div class="mt-4 grid gap-3">
            <template x-for="(t,i) in tickets" :key="i">
                <div class="flex flex-col gap-3 rounded-xl bg-white/[.025] p-4 sm:flex-row sm:items-center"><div class="flex-1"><strong x-text="t.event || 'Spectacol'"></strong><p class="text-xs text-paper/35" x-text="[dateStr(t.date), t.seat_label].filter(Boolean).join(' · ')"></p></div><a href="/cont/bilete" class="text-sm text-brass-light">Vezi biletele</a></div>
            </template>
        </div>
    </section>

    <div x-cloak x-show="add" class="fixed inset-0 z-[90] grid place-items-center bg-ink/90 p-5" @click.self="add=false">
        <form @submit.prevent="submit()" class="card w-full max-w-md p-6"><div class="flex justify-between"><h3 class="font-display text-2xl">Adaugă membru</h3><button type="button" @click="add=false"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button></div>
            <label class="mt-5 block"><span class="label">Tip profil</span><select class="input" x-model="form.relation"><option value="adult">Adult cu email propriu</option><option value="child">Copil gestionat de administrator</option></select></label>
            <label class="mt-4 block"><span class="label">Nume complet</span><input class="input" x-model="form.name" required></label>
            <label class="mt-4 block" x-show="form.relation==='adult'"><span class="label">Email</span><input type="email" class="input" x-model="form.email" placeholder="email@exemplu.ro"></label>
            <label class="mt-4 block" x-show="form.relation==='child'"><span class="label">Data nașterii</span><input type="date" class="input" x-model="form.birthdate"></label>
            <p x-show="err" x-text="err" class="mt-3 text-sm text-rose-300"></p>
            <button :disabled="busy" class="btn-primary mt-5 w-full rounded-full px-5 py-3 font-bold disabled:opacity-40" x-text="busy?'Se salvează...':'Adaugă'"></button>
        </form>
    </div>
</div>
<script>
function familyPage(){
    return {
        loading:true, add:false, busy:false, err:'', members:[], tickets:[],
        form:{ relation:'adult', name:'', email:'', birthdate:'' },
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        lastName(){ const n=(this.userName||'').trim().split(/\s+/); return n.length>1?n[n.length-1]:''; },
        mInitials(name){ return (name||'').trim().split(/\s+/).map(w=>w[0]).slice(0,2).join('').toUpperCase()||'?'; },
        dateStr(iso){ if(!iso) return ''; try { return new Date(iso).toLocaleDateString('ro-RO',{day:'numeric',month:'long'}); } catch(e){ return ''; } },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-beneficiaries',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success) this.members=d.data||[]; } catch(e){}
            try { const r2=await fetch('/api/proxy.php?action=acc-tickets',{headers:{'Authorization':'Bearer '+a.token}}); const d2=await r2.json().catch(()=>({})); if(d2&&d2.success&&d2.data) this.tickets=d2.data.upcoming||[]; } catch(e){}
            this.loading=false;
        },
        async submit(){
            if(this.busy||!this.form.name) return;
            this.busy=true; this.err=''; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-beneficiary-add',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify(this.form)});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ this.add=false; this.form={relation:'adult',name:'',email:'',birthdate:''}; this.showToast('Membrul a fost adăugat'); await this.load(); }
                else this.err=d.error||'Nu am putut adăuga membrul.';
            } catch(e){ this.err='Eroare de conexiune.'; }
            this.busy=false;
        },
        async remove(m){
            if(!confirm('Elimini pe '+m.name+' din cont?')) return;
            const a=this.auth();
            try { await fetch('/api/proxy.php?action=acc-beneficiary-remove&id='+m.id,{method:'DELETE',headers:{'Authorization':'Bearer '+a.token}}); } catch(e){}
            this.members=this.members.filter(x=>x.id!==m.id); this.showToast('Membru eliminat');
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

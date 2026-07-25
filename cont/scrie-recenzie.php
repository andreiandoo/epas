<?php
$navActive = 'reviews';
$pageTitle = 'Scrie o recenzie — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">RECENZIE NOUĂ</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Scrie o recenzie</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Evaluează producția, distribuția și experiența în sală după o participare validată.</p></div>
<div x-data="writePage()" x-init="load()">
    <div x-show="!sent">
        <section class="card p-5 sm:p-6">
            <label class="block"><span class="label">Spectacol</span>
                <select class="input" x-model="form.event_id">
                    <option value="">Alege spectacolul...</option>
                    <template x-for="ev in eligible" :key="ev.id"><option :value="ev.id" x-text="ev.title + (ev.date?' — '+ev.date:'')"></option></template>
                </select>
            </label>
            <p x-show="!loading && eligible.length===0" class="mt-3 text-sm text-paper/40">Nu ai spectacole eligibile pentru recenzie momentan (recenziile pot fi trimise după participare).</p>
        </section>
        <form @submit.prevent="submit()" class="card mt-6 overflow-hidden"><div class="border-b border-white/8 p-5 sm:p-6"><h2 class="font-display text-3xl">Spune-ne cum a fost</h2><p class="mt-2 text-sm text-paper/40">Recenzia poate fi moderată înainte de publicare.</p></div><div class="p-5 sm:p-6"><div class="rounded-2xl bg-brass/[.055] p-6 text-center"><h3 class="font-semibold">Rating general</h3><div class="mt-4 flex justify-center gap-1"><template x-for="i in [1,2,3,4,5]" :key="i"><button type="button" @click="form.rating=i" :class="form.rating>=i?'text-brass-light opacity-100':'text-paper/15'" class="text-4xl transition">★</button></template></div><p class="mt-3 text-sm text-paper/42" x-text="form.rating?['','Foarte slab','Slab','Bun','Foarte bun','Excelent'][form.rating]:'Selectează ratingul'"></p></div><div class="mt-6 grid gap-4 sm:grid-cols-3"><label><span class="label">Spectacol</span><select class="input" x-model="form.aspects.show"><option value="5">5 – Excelent</option><option value="4">4 – Foarte bun</option><option value="3">3 – Bun</option></select></label><label><span class="label">Distribuție</span><select class="input" x-model="form.aspects.cast"><option value="5">5 – Excelent</option><option value="4">4 – Foarte bun</option><option value="3">3 – Bun</option></select></label><label><span class="label">Experiența în sală</span><select class="input" x-model="form.aspects.venue"><option value="4">4 – Foarte bun</option><option value="5">5 – Excelent</option><option value="3">3 – Bun</option></select></label></div><label class="mt-5 block"><span class="label">Titlul recenziei</span><input class="input" x-model="form.title" placeholder="Rezumat în câteva cuvinte"></label><label class="mt-4 block"><span class="label">Recenzia ta</span><textarea class="input" rows="6" x-model="form.body" placeholder="Ce ți-a plăcut? Cui ai recomanda spectacolul?" required></textarea></label><div class="mt-5 grid gap-3 sm:grid-cols-2"><label class="flex items-start gap-3 rounded-xl bg-white/[.025] p-4"><input type="checkbox" x-model="form.recommend" class="mt-1"><span><strong class="text-sm">Recomand spectacolul</strong><small class="mt-1 block text-paper/35">Aș sugera prietenilor să participe.</small></span></label><label class="flex items-start gap-3 rounded-xl bg-white/[.025] p-4"><input type="checkbox" x-model="form.is_anonymous" class="mt-1"><span><strong class="text-sm">Recenzie anonimă</strong><small class="mt-1 block text-paper/35">Numele nu va fi afișat public.</small></span></label></div><p x-show="err" x-text="err" class="mt-4 text-sm text-rose-300"></p><div class="mt-6 flex flex-wrap justify-end gap-3"><a href="/cont/recenzii" class="btn-secondary rounded-full px-5 py-3 text-sm">Anulează</a><button :disabled="form.rating===0||!form.event_id||busy" class="btn-primary rounded-full px-6 py-3 font-bold disabled:cursor-not-allowed disabled:opacity-40" x-text="busy?'Se trimite...':'Trimite recenzia'"></button></div></div></form>
    </div>
    <div x-cloak x-show="sent" class="card p-8 text-center"><span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-500/10 text-emerald-300"><svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m5 12 4 4L19 6"/></svg></span><h2 class="mt-5 font-display text-3xl">Mulțumim pentru recenzie</h2><p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-paper/45">Recenzia a fost trimisă spre moderare. Vei primi o notificare după publicare.</p><a href="/cont/recenzii" class="btn-primary mt-6 inline-flex rounded-full px-6 py-3 font-bold">Recenziile mele</a></div>
</div>
<script>
function writePage(){
    return {
        loading:true, sent:false, busy:false, err:'', eligible:[],
        form:{ event_id:'', rating:0, title:'', body:'', recommend:true, is_anonymous:false, aspects:{show:'5',cast:'5',venue:'4'} },
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-reviews-eligible',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success){ this.eligible=d.data||[]; const q=new URLSearchParams(location.search).get('event'); if(q) this.form.event_id=q; else if(this.eligible[0]) this.form.event_id=String(this.eligible[0].id); } } catch(e){}
            this.loading=false;
        },
        async submit(){
            if(this.busy||!this.form.event_id||!this.form.rating) return;
            this.busy=true; this.err=''; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-review-submit',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({...this.form,event_id:parseInt(this.form.event_id)})});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success) this.sent=true; else this.err=d.error||'Nu am putut trimite recenzia.';
            } catch(e){ this.err='Eroare de conexiune.'; }
            this.busy=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

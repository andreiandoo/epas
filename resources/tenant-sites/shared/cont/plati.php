<?php
$navActive = 'payments';
$pageTitle = t('Plăți și facturare') . ' — ' . (defined('SITE_NAME') ? SITE_NAME : t('Contul meu'));
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-primary-light"><?= te('PLĂȚI') ?></p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl"><?= te('Plăți și facturare') ?></h1><p class="mt-3 max-w-3xl text-sm leading-7 text-fg/48 sm:text-base"><?= te('Carduri tokenizate, portofele digitale și datele folosite pentru facturare.') ?></p></div>
<div x-data="payPage()" x-init="load()">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-2xl"><?= te('Carduri salvate') ?></h2><p class="mt-1 text-sm text-fg/38"><?= te('Datele complete ale cardului nu sunt stocate de noi.') ?></p></div><button @click="modal=true" class="btn-primary inline-flex items-center gap-2 self-start rounded-full px-5 py-3 text-sm font-bold"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 5v14M5 12h14"/></svg><?= te('Adaugă un card') ?></button></div>

    <section class="card mt-5 p-5">
        <div x-show="loading" class="py-8 text-center"><div class="mx-auto h-7 w-7 animate-spin rounded-full border-4 border-primary border-t-transparent"></div></div>
        <div x-show="!loading && cards.length===0" x-cloak class="rounded-2xl border border-dashed border-fg/12 p-6 text-center text-sm text-fg/40"><?= te('Niciun card salvat. Adaugă unul pentru plăți mai rapide.') ?></div>
        <div class="grid gap-4" x-show="!loading && cards.length" x-cloak>
            <template x-for="c in cards" :key="c.id">
                <article class="flex flex-col gap-4 rounded-2xl p-4 sm:flex-row sm:items-center" :class="c.is_default?'border border-primary/25 bg-primary/[.035]':'bg-fg/[.025]'">
                    <span class="grid h-10 w-16 place-items-center rounded-lg text-xs font-bold" :class="brandBg(c.brand)" x-text="brandLabel(c.brand)"></span>
                    <div class="flex-1"><strong x-text="brandName(c.brand)+' •••• '+c.last4"></strong><p class="mt-1 text-xs text-fg/35" x-text="c.exp?TS.t('Expiră {date}', {date: c.exp}):(c.holder||'')"></p></div>
                    <span x-show="c.is_default" class="badge bg-emerald-500/10 text-emerald-300"><?= te('IMPLICIT') ?></span>
                    <button x-show="!c.is_default" @click="setDefault(c)" class="text-xs text-primary-light"><?= te('Setează implicit') ?></button>
                    <button @click="remove(c)" class="text-xs text-fg/40"><?= te('Elimină') ?></button>
                </article>
            </template>
        </div>
    </section>

    <section class="card mt-6 p-5"><h2 class="font-display text-2xl"><?= te('Portofele digitale') ?></h2><div class="mt-4 grid gap-3 sm:grid-cols-2"><div class="flex items-center justify-between rounded-xl bg-fg/[.025] p-4"><div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-black font-bold"></span><div><strong class="text-sm">Apple Pay</strong><p class="text-xs text-fg/35"><?= te('Disponibil la checkout') ?></p></div></div><span class="badge bg-emerald-500/10 text-emerald-300"><?= te('ACTIV') ?></span></div><div class="flex items-center justify-between rounded-xl bg-fg/[.025] p-4"><div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-white font-bold text-blue-600">G</span><div><strong class="text-sm">Google Pay</strong><p class="text-xs text-fg/35"><?= te('Disponibil la checkout') ?></p></div></div><span class="badge bg-emerald-500/10 text-emerald-300"><?= te('ACTIV') ?></span></div></div></section>

    <section class="card mt-6 p-5"><div class="flex items-center justify-between"><h2 class="font-display text-2xl"><?= te('Adresă de facturare') ?></h2><a href="/cont/setari" class="text-sm text-primary-light"><?= te('Editează') ?></a></div><div class="mt-4 rounded-xl bg-fg/[.025] p-4 text-sm leading-7 text-fg/48"><strong class="text-fg" x-text="billing.name || userName"><?= te('Contul meu') ?></strong><br><span x-text="billing.email || userEmail"></span><br><span x-text="billing.phone || TS.t('Telefon neconfigurat')"></span></div></section>

    <section class="mt-5 flex gap-3 rounded-2xl border border-emerald-500/15 bg-emerald-500/[.035] p-4"><span class="text-emerald-300"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m5 12 4 4L19 6"/></svg></span><div><strong class="text-sm"><?= te('Plăți securizate') ?></strong><p class="mt-1 text-xs leading-6 text-fg/38"><?= te('Tokenizarea și procesarea cardurilor sunt realizate de furnizorul de plăți. Site-ul nu stochează date sensibile — reținem doar ultimele 4 cifre.') ?></p></div></section>

    <div x-cloak x-show="modal" class="fixed inset-0 z-[90] grid place-items-center bg-canvas/90 p-5" @click.self="modal=false">
        <form @submit.prevent="submit()" class="card w-full max-w-md p-6"><div class="flex justify-between"><h3 class="font-display text-2xl"><?= te('Adaugă un card') ?></h3><button type="button" @click="modal=false"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button></div>
            <div class="mt-5 rounded-2xl bg-gradient-to-br from-slate-700 to-slate-950 p-5"><div class="h-8 w-10 rounded bg-amber-200"></div><p class="mt-8 font-mono text-lg tracking-widest" x-text="form.number ? maskPreview(form.number) : '•••• •••• •••• ••••'"></p><div class="mt-5 flex justify-between text-xs text-fg/40"><span x-text="(form.holder||TS.t('NUME TITULAR')).toUpperCase()"></span><span x-text="form.exp||'MM/YY'"></span></div></div>
            <label class="mt-5 block"><span class="label"><?= te('Număr card') ?></span><input class="input" inputmode="numeric" x-model="form.number" placeholder="0000 0000 0000 0000" required></label>
            <label class="mt-4 block"><span class="label"><?= te('Nume titular') ?></span><input class="input" x-model="form.holder" placeholder="ANDREI POPESCU"></label>
            <div class="mt-4 grid grid-cols-2 gap-4"><label><span class="label"><?= te('Expirare') ?></span><input class="input" x-model="form.exp" placeholder="MM/YY"></label><label><span class="label">CVC</span><input class="input" placeholder="•••"></label></div>
            <p x-show="err" x-text="err" class="mt-3 text-sm text-rose-300"></p>
            <button :disabled="busy" class="btn-primary mt-5 w-full rounded-full px-5 py-3 font-bold disabled:opacity-40" x-text="busy?TS.t('Se salvează...'):TS.t('Salvează cardul')"></button>
        </form>
    </div>
</div>
<script>
function payPage(){
    return {
        loading:true, modal:false, busy:false, err:'', cards:[], billing:{},
        form:{ number:'', holder:'', exp:'' },
        auth(){ try { return JSON.parse(localStorage.getItem('site_auth')||'null'); } catch(e){ return null; } },
        brandName(b){ return ({visa:'Visa',mastercard:'Mastercard'})[b]||'Card'; },
        brandLabel(b){ return ({visa:'VISA',mastercard:'MC'})[b]||'CARD'; },
        brandBg(b){ return ({visa:'bg-blue-900',mastercard:'bg-orange-700'})[b]||'bg-slate-700'; },
        maskPreview(n){ const d=(n||'').replace(/\D/g,'').slice(-4); return '•••• •••• •••• '+(d||'••••'); },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-payment-methods',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success){ this.cards=d.data||[]; this.billing=d.billing||{}; } } catch(e){}
            this.loading=false;
        },
        async submit(){
            if(this.busy||!this.form.number) return;
            this.busy=true; this.err=''; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-payment-add',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify(this.form)});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ this.modal=false; this.form={number:'',holder:'',exp:''}; this.showToast(TS.t('Card adăugat')); await this.load(); }
                else this.err=d.error||TS.t('Nu am putut salva cardul.');
            } catch(e){ this.err=TS.t('Eroare de conexiune.'); }
            this.busy=false;
        },
        async setDefault(c){
            const a=this.auth();
            try { await fetch('/api/proxy.php?action=acc-payment-default&id='+c.id,{method:'POST',headers:{'Authorization':'Bearer '+a.token}}); } catch(e){}
            this.cards.forEach(x=>x.is_default = x.id===c.id); this.showToast(TS.t('Card setat ca implicit'));
        },
        async remove(c){
            if(!confirm(TS.t('Elimini cardul •••• {last4}?', {last4: c.last4}))) return;
            const a=this.auth();
            try { await fetch('/api/proxy.php?action=acc-payment-remove&id='+c.id,{method:'DELETE',headers:{'Authorization':'Bearer '+a.token}}); } catch(e){}
            await this.load(); this.showToast(TS.t('Card eliminat'));
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

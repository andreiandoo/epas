<?php
$navActive = 'gift-cards';
$pageTitle = 'Carduri cadou — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">CARDURI CADOU</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Cardurile mele</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Carduri primite, sold disponibil și activarea rapidă a unui cod.</p></div>
<div x-data="giftPage()" x-init="load()">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="card p-5"><p class="font-display text-3xl text-brass-light" x-text="stats.count ?? 0">0</p><p class="mt-1 text-xs text-paper/38">Carduri active</p></div>
            <div class="card p-5"><p class="font-display text-3xl text-emerald-300" x-text="money(stats.balance ?? 0)">0</p><p class="mt-1 text-xs text-paper/38">Sold disponibil</p></div>
            <div class="card p-5"><p class="font-display text-3xl text-amber-300" x-text="money(totalInitial())">0</p><p class="mt-1 text-xs text-paper/38">Valoare totală</p></div>
        </div>
        <div class="flex flex-wrap gap-3 self-start">
            <button @click="buy=true" class="btn-primary rounded-full px-5 py-3 text-sm font-bold">Cumpără un card</button>
            <button @click="redeem=true" class="btn-secondary rounded-full px-5 py-3 text-sm">Activează un cod</button>
        </div>
    </div>

    <div x-show="loading" class="py-12 text-center"><div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-brass border-t-transparent"></div></div>
    <div x-show="!loading && cards.length===0" x-cloak class="card mt-6 p-10 text-center"><p class="text-paper/45">Nu ai niciun card cadou. Activează un cod primit pentru a începe.</p><button @click="redeem=true" class="btn-primary mt-4 inline-flex rounded-full px-5 py-2.5 text-sm font-bold">Activează un cod</button></div>

    <div class="mt-6 grid gap-5 md:grid-cols-2" x-show="!loading && cards.length" x-cloak>
        <template x-for="c in cards" :key="c.id">
            <article class="overflow-hidden rounded-3xl border p-6" :class="c.status==='used'?'border-white/10 bg-gradient-to-br from-imaginario/30 to-coal opacity-70':'border-brass/30 bg-gradient-to-br from-brass/25 via-wine-dark to-coal'">
                <div class="flex items-start justify-between">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-brass/10 text-brass-light"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 10h16v10H4V10Zm-1-4h18v4H3V6Zm9 0v14M12 6H8.5A2.5 2.5 0 1 1 11 3.5V6Zm0 0h3.5A2.5 2.5 0 1 0 13 3.5V6Z"/></svg></span>
                    <span class="badge" :class="statusBg(c.status)" x-text="statusLabel(c.status)"></span>
                </div>
                <p class="mt-8 text-sm text-paper/40">CARD CADOU</p>
                <strong class="mt-1 block font-display text-4xl" x-text="money(c.balance)+' rămași'"></strong>
                <div class="mt-7 flex items-end justify-between">
                    <div>
                        <p class="text-xs text-paper/35" x-text="'Valoare inițială '+money(c.initial)+(c.recipient_name?(' · pentru '+c.recipient_name):'')"></p>
                        <p class="mt-1 font-mono text-sm" x-text="c.code"></p>
                    </div>
                    <button @click="copy(c.code,'Cod copiat')" class="text-sm text-brass-light">Copiază</button>
                </div>
            </article>
        </template>
    </div>

    <div x-cloak x-show="buy" class="fixed inset-0 z-[90] grid place-items-center bg-ink/90 p-5" @click.self="buy=false">
        <form @submit.prevent="purchase()" class="card w-full max-w-md p-6"><div class="flex justify-between"><h3 class="font-display text-2xl">Cumpără un card cadou</h3><button type="button" @click="buy=false"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button></div>
            <div class="mt-5 overflow-hidden rounded-3xl border border-brass/30 bg-gradient-to-br from-brass/25 via-wine-dark to-coal p-5"><p class="text-sm text-paper/40">CARD CADOU</p><strong class="mt-1 block font-display text-4xl" x-text="money(form.amount)"></strong><p class="mt-4 text-xs text-paper/40" x-text="form.recipient_name?('Pentru '+form.recipient_name):'Pentru cine dorești'"></p></div>
            <div class="mt-5"><span class="label">Valoare</span><div class="grid grid-cols-4 gap-2"><template x-for="v in [50,100,150,200]" :key="v"><button type="button" @click="form.amount=v" :class="Number(form.amount)===v?'bg-brass text-ink':'border border-white/10 text-paper/50'" class="rounded-xl py-2 text-sm font-bold" x-text="v"></button></template></div><input type="number" min="25" max="5000" class="input mt-2" x-model="form.amount" placeholder="Altă sumă (lei)"></div>
            <label class="mt-4 block"><span class="label">Destinatar (opțional)</span><input class="input" x-model="form.recipient_name" placeholder="Numele persoanei"></label>
            <label class="mt-4 block"><span class="label">Mesaj (opțional)</span><textarea class="input" rows="2" x-model="form.message" placeholder="Un gând pentru destinatar"></textarea></label>
            <p x-show="err" x-text="err" class="mt-3 text-sm text-rose-300"></p>
            <button :disabled="busy" class="btn-primary mt-5 w-full rounded-full px-5 py-3 font-bold disabled:opacity-40" x-text="busy?'Se procesează...':('Cumpără · '+money(form.amount))"></button>
            <p class="mt-2 text-center text-[11px] text-paper/30">Demo — cardul e emis instant, fără plată reală.</p>
        </form>
    </div>

    <div x-cloak x-show="redeem" class="fixed inset-0 z-[90] grid place-items-center bg-ink/90 p-5" @click.self="redeem=false">
        <form @submit.prevent="submit()" class="card w-full max-w-md p-6"><div class="flex justify-between"><h3 class="font-display text-2xl">Activează cardul</h3><button type="button" @click="redeem=false"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m6 6 12 12M18 6 6 18"/></svg></button></div>
            <label class="mt-5 block"><span class="label">Cod card cadou</span><input class="input font-mono" x-model="code" placeholder="TC-GIFT-XXXX" required></label>
            <p x-show="err" x-text="err" class="mt-3 text-sm text-rose-300"></p>
            <button :disabled="busy" class="btn-primary mt-5 w-full rounded-full px-5 py-3 font-bold disabled:opacity-40" x-text="busy?'Se activează...':'Activează'"></button>
        </form>
    </div>
</div>
<script>
function giftPage(){
    return {
        loading:true, redeem:false, buy:false, busy:false, err:'', cards:[], stats:{}, code:'',
        form:{ amount:100, recipient_name:'', message:'' },
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        money(v){ return (Number(v)||0).toLocaleString('ro-RO')+' lei'; },
        totalInitial(){ return this.cards.reduce((s,c)=>s+(Number(c.initial)||0),0); },
        statusLabel(s){ return ({active:'ACTIV',partial:'FOLOSIT PARȚIAL',used:'FOLOSIT'})[s]||(s||'').toUpperCase(); },
        statusBg(s){ return s==='used'?'bg-white/10 text-paper/50':(s==='partial'?'bg-amber-500/10 text-amber-300':'bg-brass text-ink'); },
        async load(){
            const a=this.auth(); if(!a||!a.token){ this.loading=false; return; }
            try { const r=await fetch('/api/proxy.php?action=acc-gift-cards',{headers:{'Authorization':'Bearer '+a.token}}); const d=await r.json().catch(()=>({})); if(d&&d.success){ this.cards=d.data||[]; this.stats=d.stats||{}; } } catch(e){}
            this.loading=false;
        },
        async submit(){
            if(this.busy||!this.code) return;
            this.busy=true; this.err=''; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-gift-redeem',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({code:this.code})});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ this.redeem=false; this.code=''; this.showToast('Cardul a fost activat'); await this.load(); }
                else this.err=d.error||'Cod invalid.';
            } catch(e){ this.err='Eroare de conexiune.'; }
            this.busy=false;
        },
        async purchase(){
            const amt=Number(this.form.amount);
            if(this.busy||!(amt>=25)){ this.err='Alege o valoare de minim 25 lei.'; return; }
            this.busy=true; this.err=''; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-gift-purchase',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({amount:amt,recipient_name:this.form.recipient_name,message:this.form.message})});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ this.buy=false; this.form={amount:100,recipient_name:'',message:''}; this.showToast('Card cadou emis'); await this.load(); }
                else this.err=d.error||'Nu am putut emite cardul.';
            } catch(e){ this.err='Eroare de conexiune.'; }
            this.busy=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>

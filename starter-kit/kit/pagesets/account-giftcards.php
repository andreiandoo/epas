<?php
/** PAGESET: account-giftcards — balances + redeem (proxy acc-giftcards). Feature-gated. */
layout('account', ['title' => 'Carduri cadou — ' . kit_cfg('site_name'), 'nav' => 'giftcards'], function () { ?>
  <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem">Carduri cadou</h1>
  <div x-data="{ items:[], loading:true, code:'', fmt(v,c){return new Intl.NumberFormat('ro-RO').format(v||0)+' '+(c||'RON')},
        async load(){ const r=await KitProxy('acc-giftcards'); this.items=(r&&r.data)||[]; this.loading=false; },
        async redeem(){ if(!this.code) return; const r=await KitProxy('acc-giftcard-redeem',{},{method:'POST',body:{code:this.code}}); if(r&&r.success){ this.code=''; this.load(); } else alert((r&&r.error)||'Cod invalid'); } }" x-init="load()">
    <form @submit.prevent="redeem()" class="kit-search" style="margin-bottom:1.5rem">
      <input class="kit-search" style="max-width:none" x-model="code" placeholder="Cod card cadou">
      <button class="kit-btn kit-btn--primary">Adaugă</button>
    </form>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <template x-for="g in items" :key="g.code">
        <div class="kit-stat"><div class="kit-stat__num" x-text="fmt(g.balance,g.currency)"></div><div class="kit-stat__label" x-text="'•••• '+(g.code||'').slice(-4)"></div></div>
      </template>
      <div x-show="!loading && items.length===0" class="kit-empty" style="grid-column:1/-1">Niciun card cadou.</div>
    </div>
  </div>
<?php });

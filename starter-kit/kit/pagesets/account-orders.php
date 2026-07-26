<?php
/** PAGESET: account-orders — order history (proxy acc-orders). */
layout('account', ['title' => 'Comenzi — ' . kit_cfg('site_name'), 'nav' => 'orders'], function () { ?>
  <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem"><?= e(t('account.my_orders')) ?></h1>
  <div x-data="{ items:[], loading:true, fmt(v,c){return new Intl.NumberFormat('ro-RO').format(v||0)+' '+(c||'RON')},
        async load(){ const r=await KitProxy('acc-orders'); this.items=(r&&r.data)||[]; this.loading=false; } }" x-init="load()">
    <template x-if="loading"><div class="kit-skeleton" style="height:70px"></div></template>
    <div style="display:flex;flex-direction:column;gap:.75rem">
      <template x-for="o in items" :key="o.id">
        <div class="kit-card" style="padding:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem">
          <div><strong x-text="'#'+o.id"></strong> <span class="kit-muted" style="font-size:.85rem" x-text="o.date"></span>
            <div class="kit-muted" style="font-size:.85rem" x-text="(o.items_count||o.tickets_count||0)+' '+<?= e(json_encode(mb_strtolower(t('common.tickets')))) ?>"></div></div>
          <div style="text-align:right"><div style="font-weight:700" x-text="fmt(o.total,o.currency)"></div>
            <span class="kit-badge" :style="'background:'+(o.status==='paid'?'var(--kit-success)':'var(--kit-text-muted)')+';color:#fff'" x-text="o.status"></span></div>
        </div>
      </template>
      <div x-show="!loading && items.length===0" class="kit-empty"><?= e(t('account.no_orders')) ?></div>
    </div>
  </div>
<?php });

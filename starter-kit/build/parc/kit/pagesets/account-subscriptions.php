<?php
/** PAGESET: account-subscriptions — my subscriptions (proxy acc-subscriptions). Feature-gated. */
layout('account', ['title' => 'Abonamente — ' . kit_cfg('site_name'), 'nav' => 'subscriptions'], function () { ?>
  <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem">Abonamentele mele</h1>
  <div x-data="{ items:[], loading:true, async load(){ const r=await KitProxy('acc-subscriptions'); this.items=(r&&r.data)||[]; this.loading=false; } }" x-init="load()">
    <template x-if="loading"><div class="kit-skeleton" style="height:120px"></div></template>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
      <template x-for="s in items" :key="s.id">
        <div class="kit-plan">
          <h3 class="kit-display" style="font-size:1.2rem" x-text="s.name"></h3>
          <p class="kit-muted" style="font-size:.9rem" x-text="'Valabil până la '+(s.expires_at||'—')"></p>
          <div class="kit-plan__price" style="font-size:1.8rem"><span x-text="s.remaining ?? '∞'"></span> <small style="font-size:.9rem">intrări rămase</small></div>
        </div>
      </template>
      <div x-show="!loading && items.length===0" class="kit-empty" style="grid-column:1/-1">Nu ai abonamente active. <a href="/abonamente" class="kit-btn kit-btn--outline" style="margin-top:1rem">Vezi ofertele</a></div>
    </div>
  </div>
<?php });

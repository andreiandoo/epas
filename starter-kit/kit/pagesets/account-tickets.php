<?php
/** PAGESET: account-tickets — my tickets (proxy acc-tickets). */
layout('account', ['title' => 'Bilete — ' . kit_cfg('site_name'), 'nav' => 'tickets'], function () { ?>
  <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem">Biletele mele</h1>
  <div x-data="{ items:[], loading:true, async load(){ const r=await KitProxy('acc-tickets'); this.items=(r&&r.data)||[]; this.loading=false; } }" x-init="load()">
    <template x-if="loading"><div class="kit-skeleton" style="height:80px"></div></template>
    <div style="display:flex;flex-direction:column;gap:.75rem">
      <template x-for="t in items" :key="t.code">
        <div class="kit-ticket">
          <div class="kit-ticket__stub"><b x-text="new Date(t.date).getDate()"></b><span x-text="t.month||''"></span></div>
          <div class="kit-ticket__body">
            <span class="kit-badge" :style="t.is_subscription?'':'background:var(--kit-success);color:#fff'" x-text="t.is_subscription?'Abonament':'Valid'"></span>
            <h3 class="kit-display" style="font-size:1.05rem;margin:.35rem 0 .2rem" x-text="t.event"></h3>
            <p class="kit-event-card__meta" x-text="[t.time,t.venue,t.seat_label].filter(Boolean).join(' • ')"></p>
          </div>
          <button class="kit-btn kit-btn--outline" @click="kitQR.show(t.code,t.event)">QR</button>
        </div>
      </template>
      <div x-show="!loading && items.length===0" class="kit-empty">Nu ai bilete.</div>
    </div>
  </div>
<?php });

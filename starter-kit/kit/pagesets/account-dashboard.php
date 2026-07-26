<?php
/** PAGESET: account-dashboard — stats + upcoming tickets (proxy acc-stats/acc-tickets). */
layout('account', ['title' => 'Panou — ' . kit_cfg('site_name'), 'nav' => 'dashboard'], function () { ?>
  <div x-data="{ stats:null, tickets:[], loading:true, async load(){
        const [s,t]=await Promise.all([KitProxy('acc-stats'),KitProxy('acc-tickets')]);
        this.stats=(s&&s.data)||{}; this.tickets=(t&&t.data)||[]; this.loading=false;
      } }" x-init="load()">
    <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem"><?= e(t('account.welcome')) ?></h1>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:2rem">
      <template x-if="loading"><div class="kit-skeleton" style="height:90px"></div></template>
      <template x-if="!loading"><div style="display:contents">
        <div class="kit-stat"><div class="kit-stat__num" x-text="stats.upcoming_tickets ?? 0"></div><div class="kit-stat__label"><?= e(t('account.upcoming')) ?></div></div>
        <div class="kit-stat"><div class="kit-stat__num" x-text="stats.orders ?? 0"></div><div class="kit-stat__label"><?= e(t('account.orders')) ?></div></div>
        <div class="kit-stat"><div class="kit-stat__num" x-text="stats.favorites ?? 0"></div><div class="kit-stat__label"><?= e(t('account.favorites')) ?></div></div>
        <div class="kit-stat"><div class="kit-stat__num" x-text="stats.points ?? 0"></div><div class="kit-stat__label"><?= e(t('account.points')) ?></div></div>
      </div></template>
    </div>
    <h2 class="kit-display" style="font-size:1.3rem;margin-bottom:1rem"><?= e(t('account.next_tickets')) ?></h2>
    <div style="display:flex;flex-direction:column;gap:.75rem">
      <template x-for="t in tickets" :key="t.code">
        <div class="kit-ticket">
          <div class="kit-ticket__stub"><b x-text="new Date(t.date).getDate()"></b><span x-text="t.month||''"></span></div>
          <div class="kit-ticket__body"><h3 class="kit-display" style="font-size:1.05rem" x-text="t.event"></h3>
            <p class="kit-event-card__meta" x-text="[t.time,t.venue,t.seat_label].filter(Boolean).join(' • ')"></p></div>
          <button class="kit-btn kit-btn--outline" @click="kitQR.show(t.code,t.event)">QR</button>
        </div>
      </template>
      <div x-show="!loading && tickets.length===0" class="kit-empty"><?= e(t('account.no_tickets')) ?></div>
    </div>
  </div>
<?php });

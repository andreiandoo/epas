<?php
/** PAGESET: account-favorites — saved events (proxy acc-favorites). */
layout('account', ['title' => t('menu.favorites') . ' — ' . kit_cfg('site_name'), 'nav' => 'favorites'], function () { ?>
  <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem"><?= e(t('menu.favorites')) ?></h1>
  <div x-data="{ items:[], loading:true, async load(){ const r=await KitProxy('acc-favorites'); this.items=(r&&r.data)||[]; this.loading=false; } }" x-init="load()">
    <template x-if="loading"><div class="kit-skeleton" style="height:120px"></div></template>
    <div class="kit-grid">
      <template x-for="e in items" :key="e.id">
        <a :href="e.url || '#'" class="kit-event-card kit-event-card--grid">
          <div class="kit-event-card__media"><img :src="e.poster_url || e.image || 'https://placehold.co/600x900'" :alt="e.title" loading="lazy"></div>
          <div class="kit-event-card__body">
            <h3 class="kit-event-card__title" x-text="e.title"></h3>
            <p class="kit-event-card__meta" x-text="[e.venue_name||e.venue, e.city].filter(Boolean).join(' • ')"></p>
          </div>
        </a>
      </template>
    </div>
    <div x-show="!loading && items.length===0" class="kit-empty"><?= e(t('account.no_favorites')) ?> <a href="<?= e(kit_cfg('cta_url','/')) ?>" class="kit-btn kit-btn--outline" style="margin-top:1rem"><?= e(kit_term('events_cap','Evenimente')) ?></a></div>
  </div>
<?php });

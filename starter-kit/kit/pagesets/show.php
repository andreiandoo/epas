<?php
/**
 * PAGESET: show  — single event. Feature-gated: reserved seating gets a
 * seat-map, general admission gets a ticket-selector.
 */
$slug  = $_GET['slug'] ?? '';
$event = $slug ? kit_event($slug) : null;

if (!$event) {
    http_response_code(404);
    layout('public', ['title' => kit_term('event_cap', 'Eveniment') . ' negăsit'], function () {
        component('empty-state', ['message' => kit_term('event_cap', 'Evenimentul') . ' nu a fost găsit.',
            'action' => ['label' => kit_term('events_cap', 'Vezi tot'), 'url' => kit_cfg('cta_url', '/')]]);
    });
    return;
}

$related = array_values(array_filter(kit_events(['per_page' => 4]), fn($e) => $e['id'] !== $event['id']));
$rail = kit_feature('seating')
    ? component_html('seat-map', ['event' => $event])
    : component_html('ticket-selector', ['event' => $event]);

layout('public', [
    'title'       => $event['title'] . ' — ' . kit_cfg('site_name'),
    'nav'         => '',
    'og_type'     => 'event',
    'event'       => $event,                                   // → Event JSON-LD
    'description' => $event['short_description'] ?: ($event['title'] . ' la ' . ($event['venue_name'] ?: kit_cfg('site_name'))),
    'image'       => $event['hero_image_url'] ?: $event['poster_url'],
],
function () use ($event, $related, $rail) {
    component('event-hero', ['event' => $event, 'slot' => $rail]);
    ?>
    <section class="kit-section"><div class="kit-container">
      <?php component('breadcrumb', ['items' => [
          ['label' => t('common.home'), 'url' => '/'],
          ['label' => kit_term('events_cap', 'Evenimente'), 'url' => kit_cfg('cta_url', '/')],
          ['label' => $event['title']],
      ]]); ?>

      <div class="kit-actions-row">
        <?php component('favorite-button', ['type' => 'event', 'id' => $event['id']]); ?>
        <?php $ics = kit_ics_link($event); if ($ics): ?>
          <a href="<?= e($ics) ?>" download="eveniment.ics" class="kit-btn kit-btn--outline">📅 <?= e(t('common.add_to_calendar')) ?></a>
        <?php endif; ?>
      </div>

      <?php if ($event['description']): ?><div style="max-width:48rem;margin:1.5rem 0"><?= kit_html($event['description']) ?></div><?php endif; ?>
      <?php if ($event['artists']): ?>
        <h2 class="kit-display" style="font-size:1.4rem;margin:2rem 0 1rem"><?= e(kit_term('artists_cap', 'Distribuție')) ?></h2>
        <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
          <?php foreach ($event['artists'] as $a) component('artist-card', ['artist' => ['name' => $a['name'], 'image' => $a['image'], 'url' => vm_url(kit_cfg('artist_url_pattern'), ['slug' => $a['slug']])]]); ?>
        </div>
      <?php endif; ?>
      <?php if (kit_feature('reviews')): ?>
        <div style="max-width:48rem;margin-top:2.5rem" x-data="kitReviews(<?= (int)$event['id'] ?>)" x-init="load()">
          <h2 class="kit-display" style="font-size:1.4rem;margin-bottom:1rem"><?= e(t('reviews.title')) ?></h2>
          <div class="kit-reviews">
            <template x-for="r in items" :key="r.id">
              <div class="kit-review">
                <div class="kit-review__stars"><template x-for="i in 5"><span :style="i<=r.rating?'color:var(--kit-accent)':'color:var(--kit-border)'">★</span></template></div>
                <p style="font-size:.9rem" x-show="r.body" x-text="r.body"></p>
                <p class="kit-muted" style="font-size:.8rem;margin-top:.4rem" x-text="[r.author,r.date].filter(Boolean).join(' • ')"></p>
              </div>
            </template>
            <div x-show="!loading && items.length===0" class="kit-empty"><?= e(t('reviews.none')) ?></div>
          </div>
          <div x-show="canWrite" x-cloak style="margin-top:1.5rem">
            <h3 class="kit-display" style="font-size:1.1rem;margin-bottom:.5rem"><?= e(t('reviews.write')) ?></h3>
            <div class="kit-stars-input"><template x-for="i in 5"><span :class="i<=rating?'on':''" @click="rating=i">★</span></template></div>
            <textarea class="kit-search" style="max-width:none;min-height:90px;margin:.5rem 0" x-model="body" placeholder="<?= e(t('reviews.placeholder')) ?>"></textarea>
            <button class="kit-btn kit-btn--primary" :disabled="busy||!rating" @click="submit()"><?= e(t('reviews.submit')) ?></button>
            <p x-show="done" x-cloak style="color:var(--kit-success);font-size:.9rem;margin-top:.5rem"><?= e(t('reviews.thanks')) ?></p>
          </div>
        </div>
        <script>
        function kitReviews(eventId){ return { items:[], loading:true, rating:0, body:'', busy:false, done:false,
          get canWrite(){ return !!(window.KitAuth && KitAuth.token()); },
          async load(){ try{ const r=await KitProxy('event-reviews',{event:eventId}); this.items=(r&&r.data)||[]; }catch(e){} this.loading=false; },
          async submit(){ if(!this.rating) return; this.busy=true;
            try{ await KitProxy('review-submit',{event:eventId},{method:'POST',body:{rating:this.rating,body:this.body}}); this.done=true; this.body=''; this.rating=0; this.load(); }
            catch(e){} finally{ this.busy=false; } } }; }
        </script>
      <?php endif; ?>

      <?php if ($related): ?>
        <h2 class="kit-display" style="font-size:1.4rem;margin:2.5rem 0 1rem"><?= e(t('common.see_also')) ?></h2>
        <?php component('event-grid', ['events' => $related]); ?>
      <?php endif; ?>
    </div></section>
<?php });

<?php
/**
 * COMPONENT: calendar  (month grid + day-filtered event list)
 * Input: $events (canonical[], req)
 * Alpine widget (kitCalendar in kit.js). Ports the teatru schedule page. The
 * list rows reuse the .kit-schedule-row look inside an Alpine template.
 */
$events = $events ?? [];
$js = array_map(fn($e) => [
    'id' => $e['id'], 'slug' => $e['slug'], 'title' => $e['title'],
    'cat' => $e['category'], 'date' => $e['date'], 'time' => $e['time'],
    'venue' => $e['venue_name'], 'price' => $e['price_from'], 'currency' => $e['currency'],
    'url' => $e['url'], 'soldOut' => $e['is_sold_out'], 'image' => $e['poster_url'] ?: $e['hero_image_url'],
], $events);
$first = $events[0]['date'] ?? date('Y-m-d');
?>
<div class="kit-calendar" x-data='kitCalendar(<?= e(json_encode($js, JSON_UNESCAPED_UNICODE)) ?>, "<?= e($first) ?>")'>
  <div class="kit-calendar__grid-wrap">
    <div class="kit-calendar__head">
      <button @click="prev()" aria-label="luna anterioară">‹</button>
      <strong x-text="monthNames[month] + ' ' + year" class="kit-display"></strong>
      <button @click="next()" aria-label="luna următoare">›</button>
    </div>
    <div class="kit-calendar__dow"><template x-for="d in ['L','M','M','J','V','S','D']"><span x-text="d"></span></template></div>
    <div class="kit-calendar__days">
      <template x-for="c in cells" :key="c.key">
        <button class="kit-calendar__day" :class="{ 'is-empty': !c.day, 'has-event': c.hasEvent, 'is-selected': selected === c.date }"
                @click="c.day && select(c.date)"><span x-text="c.day"></span></button>
      </template>
    </div>
  </div>
  <div class="kit-calendar__list">
    <template x-for="e in filtered" :key="e.id">
      <div class="kit-schedule-row">
        <div class="kit-schedule-row__date"><b x-text="new Date(e.date).getDate()"></b><span x-text="e.time"></span></div>
        <img :src="e.image || 'https://placehold.co/120x120'" alt="" class="kit-schedule-row__img">
        <div class="kit-schedule-row__info">
          <span class="kit-event-card__cat" x-text="e.cat"></span>
          <a :href="e.url" class="kit-schedule-row__title kit-display" x-text="e.title"></a>
          <p class="kit-event-card__meta" x-text="e.venue"></p>
        </div>
        <div class="kit-schedule-row__cta"><a :href="e.url" class="kit-btn" :class="e.soldOut?'kit-btn--outline':'kit-btn--primary'" x-text="e.soldOut?'Sold Out':'Bilete'"></a></div>
      </div>
    </template>
    <div x-show="filtered.length===0" class="kit-empty">Niciun eveniment în această perioadă.</div>
  </div>
</div>

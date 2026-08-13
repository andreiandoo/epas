<?php
/**
 * PAGESET: styleguide — renders every component + design tokens with sample data
 * in the ACTIVE theme. A template author opens /styleguide to see the whole kit
 * branded, and to spot anything the theme.css missed. Dev tool: served by the
 * dev-router; not shipped to production builds unless you add it to routes.
 */
$sampleEvent = vm_fill([
    'id' => 1, 'slug' => 'demo', 'title' => 'Eveniment demonstrativ', 'category' => kit_term('events_cap', 'Categorie'),
    'venue_name' => 'Sala Mare', 'city' => 'București', 'date' => date('Y-m-d', strtotime('+10 days')), 'time' => '19:00',
    'poster_url' => '', 'hero_image_url' => '', 'price_from' => 80.0, 'currency' => 'RON', 'is_promoted' => true,
    'url' => '#', 'short_description' => 'Un scurt rezumat al evenimentului pentru demonstrație.',
    'ticket_types' => [['name' => 'Categoria I', 'price' => 120, 'currency' => 'RON'], ['name' => 'Categoria II', 'price' => 80, 'currency' => 'RON']],
    'artists' => [['name' => 'Artist Unu', 'slug' => 'a1', 'image' => ''], ['name' => 'Artist Doi', 'slug' => 'a2', 'image' => '']],
], vm_event_defaults());
$soldOut = array_replace($sampleEvent, ['id' => 2, 'title' => 'Eveniment sold out', 'is_sold_out' => true, 'is_promoted' => false]);
$events = [$sampleEvent, $soldOut, array_replace($sampleEvent, ['id' => 3, 'title' => 'Al treilea eveniment', 'is_promoted' => false])];
$artist = vm_fill(['name' => 'Artist Demo', 'role' => 'Rol', 'image' => '', 'url' => '#'], vm_artist_defaults());
$venue  = vm_fill(['name' => 'Sala Mare', 'city' => 'București', 'image_url' => '', 'events_count' => 12, 'url' => '#'], vm_venue_defaults());

$tokens = ['--kit-primary','--kit-primary-dark','--kit-accent','--kit-bg','--kit-surface','--kit-surface-2','--kit-text','--kit-text-muted','--kit-border','--kit-success','--kit-warning','--kit-error'];

layout('public', ['title' => 'Styleguide — ' . kit_cfg('site_name'), 'nav' => ''], function () use ($events, $sampleEvent, $artist, $venue, $tokens) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:2rem;margin-bottom:.25rem">Styleguide</h1>
    <p class="kit-muted" style="margin-bottom:2rem">Toate componentele + tokenii, în tema activă (<?= e(kit_cfg('site_name')) ?>).</p>

    <h2 class="kit-display" style="font-size:1.3rem;margin:2rem 0 1rem">Tokens — culori</h2>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
      <?php foreach ($tokens as $tk): ?>
        <div class="kit-card" style="padding:0;overflow:hidden">
          <div style="height:48px;background:var(<?= $tk ?>);border-bottom:1px solid var(--kit-border)"></div>
          <div style="padding:.4rem .6rem;font-size:.72rem;font-family:monospace"><?= e($tk) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <h2 class="kit-display" style="font-size:1.3rem;margin:2rem 0 1rem">Tipografie</h2>
    <p class="kit-display" style="font-size:2rem">Display 2rem — <?= e(kit_cfg('site_name')) ?></p>
    <p class="kit-display" style="font-size:1.3rem">Display 1.3rem</p>
    <p>Body text — <?= e(t('common.from')) ?> 80 RON, <?= e(t('common.sold_out')) ?>.</p>
    <p class="kit-muted">Muted text.</p>

    <h2 class="kit-display" style="font-size:1.3rem;margin:2rem 0 1rem">Butoane & badge-uri</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem">
      <a class="kit-btn kit-btn--primary" href="#">Primary</a>
      <a class="kit-btn kit-btn--outline" href="#">Outline</a>
      <button class="kit-btn kit-btn--primary" disabled>Disabled</button>
      <span class="kit-badge kit-badge--soldout"><?= e(t('common.sold_out')) ?></span>
      <span class="kit-badge kit-badge--promoted"><?= e(t('common.recommended')) ?></span>
      <span class="kit-badge kit-badge--postponed"><?= e(t('common.postponed')) ?></span>
      <span class="kit-badge kit-badge--cancelled"><?= e(t('common.cancelled')) ?></span>
    </div>

    <?php
    $blocks = [
        'event-grid'        => fn() => component('event-grid', ['events' => $events]),
        'schedule-row'      => fn() => component('schedule-row', ['event' => $sampleEvent]),
        'hero'              => fn() => component('hero', ['title' => 'Hero', 'eyebrow' => 'Eyebrow', 'subtitle' => 'Subtitlu de hero.', 'actions' => [['label' => 'CTA', 'url' => '#']]]),
        'cta'               => fn() => component('cta', ['title' => 'Call to action', 'text' => 'Text CTA.', 'action' => ['label' => 'Acțiune', 'url' => '#']]),
        'breadcrumb'        => fn() => component('breadcrumb', ['items' => [['label' => t('common.home'), 'url' => '#'], ['label' => 'Secțiune', 'url' => '#'], ['label' => 'Curent']]]),
        'pagination'        => fn() => component('pagination', ['current' => 2, 'last' => 5, 'base' => '#']),
        'step-indicator'    => fn() => component('step-indicator', ['steps' => [t('step.cart'), t('step.details'), t('step.payment')], 'current' => 2]),
        'stat-tile'         => fn() => component('stat-tile', ['num' => '42', 'label' => 'Etichetă']),
        'search-bar'        => fn() => component('search-bar', ['action' => '#']),
        'subscription-card' => fn() => component('subscription-card', ['plan' => ['name' => 'Abonament', 'price' => 600, 'currency' => 'RON', 'subtitle' => 'Subtitlu', 'is_featured' => true, 'benefits' => ['Beneficiu 1', 'Beneficiu 2'], 'cta_label' => 'Alege', 'cta_url' => '#']]),
        'review-card'       => fn() => component('review-card', ['review' => ['rating' => 4, 'title' => 'Foarte bun', 'body' => 'Recenzie demonstrativă.', 'author' => 'Client', 'date' => '2026']]),
        'ticket-card'       => fn() => component('ticket-card', ['ticket' => ['event' => 'Eveniment', 'venue' => 'Sala Mare', 'date' => date('Y-m-d'), 'time' => '19:00', 'seat_label' => 'R3 L12', 'code' => 'TIX-1']]),
        'empty-state'       => fn() => component('empty-state', ['message' => 'Nimic aici.']),
    ];
    foreach ($blocks as $name => $render): ?>
      <h2 class="kit-display" style="font-size:1.15rem;margin:2rem 0 .75rem"><code style="font-size:.9rem;color:var(--kit-primary)"><?= e($name) ?></code></h2>
      <?php $render(); ?>
    <?php endforeach; ?>

    <h2 class="kit-display" style="font-size:1.15rem;margin:2rem 0 .75rem"><code style="font-size:.9rem;color:var(--kit-primary)">artist-card / venue-card</code></h2>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <?php component('artist-card', ['artist' => $artist]); ?>
      <?php component('venue-card', ['venue' => $venue]); ?>
    </div>
  </div></section>
<?php });

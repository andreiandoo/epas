<?php
/**
 * COMPONENT: ticket-selector  (quantity picker for general-admission events)
 * Input: $event canonical event with ticket_types[] — REQUIRED
 *
 * Client behaviour is Alpine (loaded by the layout). Adds selected tickets to
 * the client cart in localStorage under the kit cart key. For reserved-seating
 * events, use the seat-map component instead.
 */
$event = $event ?? [];
$types = $event['ticket_types'] ?? [];
if (!$event) return;
$soldOut = !empty($event['is_sold_out']);
?>
<div class="kit-ticket-selector" x-data='kitTicketSelector(<?= e(json_encode([
    "event_id" => $event["id"],
    "title"    => $event["title"],
    "url"      => $event["url"],
    "image"    => $event["poster_url"],
    "currency" => $event["currency"],
    "types"    => array_map(fn($t) => [
        "name"  => $t["name"],
        "price" => $t["sale_price"] ?? $t["price"],
    ], $types),
], JSON_UNESCAPED_UNICODE)) ?>)'>
  <?php if ($soldOut): ?>
    <p class="kit-badge kit-badge--soldout" style="font-size:.85rem"><?= e(t('common.sold_out')) ?></p>
  <?php elseif (!$types): ?>
    <a href="<?= e($event['url']) ?>" class="kit-btn kit-btn--primary" style="width:100%"><?= e(t('common.tickets')) ?></a>
  <?php else: ?>
    <template x-for="(t,i) in types" :key="i">
      <div class="kit-ticket-row">
        <div>
          <div style="font-weight:600" x-text="t.name"></div>
          <div class="kit-muted" style="font-size:.85rem" x-text="fmt(t.price)"></div>
        </div>
        <div class="kit-qty">
          <button type="button" @click="dec(i)" aria-label="minus">−</button>
          <span x-text="qty[i]||0"></span>
          <button type="button" @click="inc(i)" aria-label="plus">+</button>
        </div>
      </div>
    </template>
    <div class="kit-ticket-total"><span><?= e(t('common.total')) ?></span><strong x-text="fmt(total())"></strong></div>
    <button type="button" class="kit-btn kit-btn--primary" style="width:100%" :disabled="total()<=0" @click="addToCart()"><?= e(t('common.add_to_cart')) ?></button>
  <?php endif; ?>
</div>

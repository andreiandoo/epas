<?php
/**
 * PAGESET: confirmare — order confirmation.
 *   GET proxy 'order-summary?order=ID' → { data: { id, status, total, currency,
 *        items:[{title,qty,seats,price}], tickets:[{event,seat_label,code}] } }
 * Clears the cart on load.
 */
layout('public', ['title' => 'Confirmare — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:44rem"
      x-data="kitConfirm(<?= (int)($_GET['order'] ?? 0) ?>)" x-init="init()">
    <div x-show="loading" class="kit-skeleton" style="height:200px"></div>

    <div x-show="!loading && ok" x-cloak style="text-align:center">
      <div style="font-size:3rem">✅</div>
      <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin:.5rem 0"><?= e(t('confirm.thanks')) ?></h1>
      <p class="kit-muted" x-text="L.confirmed.replace('{id}', '#'+orderId)"></p>

      <div class="kit-ordersum" style="text-align:left;margin-top:2rem">
        <template x-for="(l,i) in (order.items||[])" :key="i">
          <div class="kit-ordersum__row"><span class="kit-muted" x-text="l.title"></span><span x-text="fmt(l.price)"></span></div>
        </template>
        <div class="kit-ordersum__row kit-ordersum__row--total"><span><?= e(t('common.total')) ?></span><strong x-text="fmt(order.total)"></strong></div>
      </div>

      <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:1.5rem" x-show="(order.tickets||[]).length">
        <template x-for="t in (order.tickets||[])" :key="t.code">
          <div class="kit-ticket">
            <div class="kit-ticket__body">
              <h3 class="kit-display" style="font-size:1.05rem" x-text="t.event"></h3>
              <p class="kit-event-card__meta" x-text="t.seat_label"></p>
            </div>
            <button class="kit-btn kit-btn--outline" @click="kitQR.show(t.code,t.event)">QR</button>
          </div>
        </template>
      </div>
      <a href="/cont/bilete" class="kit-btn kit-btn--primary" style="margin-top:1.5rem"><?= e(t('confirm.my_tickets')) ?></a>
    </div>

    <div x-show="!loading && !ok" x-cloak class="kit-empty">
      <?= e(t('confirm.not_found')) ?> <a href="/" class="kit-btn kit-btn--outline" style="margin-top:1rem"><?= e(t('common.home')) ?></a>
    </div>
  </div></section>
  <?php component('qr-modal'); ?>
  <script>
  function kitConfirm(orderId){ return {
    orderId, loading:true, ok:false, order:{},
    L: <?= json_encode(['confirmed' => t('confirm.confirmed')], JSON_UNESCAPED_UNICODE) ?>,
    fmt(v){ return new Intl.NumberFormat('ro-RO').format(v||0)+' '+(this.order.currency||(window.KIT&&KIT.currency)||'RON'); },
    async init(){
      try{ localStorage.removeItem((window.KIT&&KIT.cartKey)||'kit_cart'); }catch(e){}
      if(!this.orderId){ this.loading=false; return; }
      try{ const r=await KitProxy('order-summary',{order:this.orderId}); if(r&&r.data){ this.order=r.data; this.ok=true; } }catch(e){}
      this.loading=false;
    }
  }; }
  </script>
<?php });

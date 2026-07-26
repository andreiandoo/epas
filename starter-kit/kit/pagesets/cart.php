<?php
/** PAGESET: cart — client-side cart (localStorage via KitCart) + order summary. */
layout('public', ['title' => 'Coș — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" x-data="{
      items: (window.KitCart ? KitCart.all() : []),
      fmt(v){ return (window.KIT&&KIT.currency? '' : '')+ new Intl.NumberFormat('ro-RO').format(v)+' '+(window.KIT&&KIT.currency||'RON'); },
      get total(){ return this.items.reduce((t,l)=> t + (l.price||0)*(l.qty|| (l.seats&&l.seats.length)||1), 0); },
      remove(i){ this.items.splice(i,1); localStorage.setItem((window.KIT&&KIT.cartKey)||'kit_cart', JSON.stringify(this.items)); }
    }">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem"><?= e(t('cart.title')) ?></h1>
    <?php component('step-indicator', ['steps' => [t('step.cart'), t('step.details'), t('step.payment')], 'current' => 1]); ?>
    <div style="display:grid;gap:2rem;grid-template-columns:1fr" class="kit-cart-layout">
      <div>
        <template x-for="(l,i) in items" :key="i">
          <div class="kit-cart-line">
            <img :src="l.image || 'https://placehold.co/120'" alt="" class="kit-cart-line__img">
            <div class="kit-cart-line__info">
              <span class="kit-cart-line__title kit-display" x-text="l.title"></span>
              <span class="kit-muted" style="font-size:.85rem" x-text="(l.ticket||'')+(l.qty?(' × '+l.qty):'')"></span>
            </div>
            <div class="kit-cart-line__price" x-text="fmt((l.price||0)*(l.qty||1))"></div>
            <button class="kit-btn kit-btn--outline" @click="remove(i)">✕</button>
          </div>
        </template>
        <div x-show="items.length===0" class="kit-empty"><?= e(t('cart.empty')) ?> <a href="<?= e(kit_cfg('cta_url','/')) ?>" class="kit-btn kit-btn--outline" style="margin-top:1rem"><?= e(t('cart.see_events')) ?> <?= e(kit_term('events','evenimente')) ?></a></div>
      </div>
      <div x-show="items.length>0" class="kit-ordersum">
        <h3 class="kit-display" style="font-size:1.2rem;margin-bottom:.5rem"><?= e(t('cart.summary')) ?></h3>
        <div class="kit-ordersum__row kit-ordersum__row--total"><span><?= e(t('common.total')) ?></span><strong x-text="fmt(total)"></strong></div>
        <a href="/finalizare" class="kit-btn kit-btn--primary" style="width:100%;margin-top:.75rem"><?= e(t('common.continue')) ?></a>
      </div>
    </div>
  </div></section>
<?php });

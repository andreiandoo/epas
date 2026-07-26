<?php
/** PAGESET: checkout — contact form + order summary (client totals). */
layout('public', ['title' => 'Finalizare — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem">Finalizare comandă</h1>
    <?php component('step-indicator', ['steps' => ['Coș', 'Date', 'Plată'], 'current' => 2]); ?>
    <div style="display:grid;gap:2rem;grid-template-columns:1fr" class="kit-cart-layout"
         x-data="{ items:(window.KitCart?KitCart.all():[]), fmt(v){return new Intl.NumberFormat('ro-RO').format(v)+' '+((window.KIT&&KIT.currency)||'RON')}, get total(){return this.items.reduce((t,l)=>t+(l.price||0)*(l.qty||1),0)} }">
      <form class="kit-ordersum" onsubmit="event.preventDefault(); alert('Demo checkout — integrează /api/proxy.php?action=checkout');">
        <h3 class="kit-display" style="font-size:1.2rem;margin-bottom:.75rem">Datele tale</h3>
        <div style="display:flex;flex-direction:column;gap:.75rem">
          <input class="kit-search" style="max-width:none" name="name" placeholder="Nume complet" required>
          <input class="kit-search" style="max-width:none" type="email" name="email" placeholder="Email" required>
          <input class="kit-search" style="max-width:none" name="phone" placeholder="Telefon">
          <label style="font-size:.85rem"><input type="checkbox" required> Sunt de acord cu termenii și condițiile</label>
        </div>
        <button class="kit-btn kit-btn--primary" style="width:100%;margin-top:1rem">Plătește</button>
      </form>
      <div class="kit-ordersum">
        <h3 class="kit-display" style="font-size:1.2rem;margin-bottom:.5rem">Sumar</h3>
        <template x-for="(l,i) in items" :key="i">
          <div class="kit-ordersum__row"><span class="kit-muted" x-text="l.title"></span><span x-text="fmt((l.price||0)*(l.qty||1))"></span></div>
        </template>
        <div class="kit-ordersum__row kit-ordersum__row--total"><span>Total</span><strong x-text="fmt(total)"></strong></div>
      </div>
    </div>
  </div></section>
<?php });

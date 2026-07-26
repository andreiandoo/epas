<?php
/**
 * PAGESET: checkout — LIVE. Loads cart + payment methods, submits to the proxy.
 *   POST proxy 'checkout' { items, contact:{name,email,phone}, payment_method }
 *     → { data: { order_id, payment_url? } }
 *   payment_url → redirect to PSP; else → /confirmare?order=order_id (cart cleared).
 */
layout('public', ['title' => 'Finalizare — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" x-data="kitCheckout()" x-init="init()">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem">Finalizare comandă</h1>
    <?php component('step-indicator', ['steps' => ['Coș', 'Date', 'Plată'], 'current' => 2]); ?>

    <div x-show="items.length===0" class="kit-empty">Coșul este gol. <a href="<?= e(kit_cfg('cta_url','/')) ?>" class="kit-btn kit-btn--outline" style="margin-top:1rem">Înapoi</a></div>

    <div x-show="items.length>0" style="display:grid;gap:2rem;grid-template-columns:1fr" class="kit-cart-layout">
      <form class="kit-ordersum" @submit.prevent="submit()">
        <h3 class="kit-display" style="font-size:1.2rem;margin-bottom:.75rem">Datele tale</h3>
        <div style="display:flex;flex-direction:column;gap:.75rem">
          <input class="kit-search" style="max-width:none" x-model="contact.name" placeholder="Nume complet" required>
          <input class="kit-search" style="max-width:none" type="email" x-model="contact.email" placeholder="Email" required>
          <input class="kit-search" style="max-width:none" x-model="contact.phone" placeholder="Telefon">
        </div>

        <h3 class="kit-display" style="font-size:1.2rem;margin:1.25rem 0 .5rem">Metodă de plată</h3>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <template x-for="pm in methods" :key="pm.id">
            <label class="kit-payopt" :class="method===pm.id?'is-active':''">
              <input type="radio" :value="pm.id" x-model="method" style="margin-right:.5rem">
              <span x-text="pm.name"></span>
              <small class="kit-muted" x-show="pm.hint" x-text="pm.hint" style="display:block;margin-left:1.4rem"></small>
            </label>
          </template>
          <p x-show="methods.length===0" class="kit-muted" style="font-size:.85rem">Se încarcă metodele de plată…</p>
        </div>

        <label style="font-size:.85rem;display:block;margin:1rem 0"><input type="checkbox" x-model="terms" required> Sunt de acord cu termenii și condițiile</label>
        <p x-show="error" x-text="error" style="color:var(--kit-error);font-size:.9rem"></p>
        <button class="kit-btn kit-btn--primary" style="width:100%" :disabled="submitting || !terms || !method">
          <span x-text="submitting ? 'Se procesează…' : 'Plătește'"></span>
        </button>
      </form>

      <div class="kit-ordersum">
        <h3 class="kit-display" style="font-size:1.2rem;margin-bottom:.5rem">Sumar</h3>
        <template x-for="(l,i) in items" :key="i">
          <div class="kit-ordersum__row"><span class="kit-muted" x-text="l.title + (l.seats?(' ('+l.seats.length+' locuri)'):(l.qty?(' × '+l.qty):''))"></span><span x-text="fmt((l.price||0)*(l.seats?1:(l.qty||1)))"></span></div>
        </template>

        <div style="display:flex;gap:.4rem;margin:.75rem 0">
          <input class="kit-search" style="max-width:none;flex:1" x-model="promo" placeholder="Cod promoțional / card cadou">
          <button type="button" class="kit-btn kit-btn--outline" @click="applyPromo()" :disabled="promoBusy">Aplică</button>
        </div>
        <p x-show="promoMsg" x-text="promoMsg" :style="discount>0?'color:var(--kit-success)':'color:var(--kit-error)'" style="font-size:.82rem"></p>

        <div class="kit-ordersum__totals">
          <div class="kit-ordersum__row"><span class="kit-muted">Subtotal</span><span x-text="fmt(subtotal())"></span></div>
          <div class="kit-ordersum__row" x-show="discount>0"><span class="kit-muted">Reducere</span><span x-text="'−'+fmt(discount)"></span></div>
          <div class="kit-ordersum__row kit-ordersum__row--total"><span>Total</span><strong x-text="fmt(total())"></strong></div>
        </div>
      </div>
    </div>
  </div></section>

  <script>
  function kitCheckout(){ return {
    items: (window.KitCart?KitCart.all():[]), methods: [], method: '', contact:{name:'',email:'',phone:''}, terms:false, submitting:false, error:'',
    promo:'', promoCode:'', promoBusy:false, promoMsg:'', discount:0,
    fmt(v){ return new Intl.NumberFormat('ro-RO').format(v)+' '+((window.KIT&&KIT.currency)||'RON'); },
    subtotal(){ return this.items.reduce((t,l)=>t+(l.price||0)*(l.seats?1:(l.qty||1)),0); },
    total(){ return Math.max(0, this.subtotal() - this.discount); },
    async init(){
      const r = await KitProxy('payment-methods');
      this.methods = (r&&r.data)|| [{id:'card',name:'Card bancar',hint:'Visa, Mastercard'}];
      if(this.methods[0]) this.method = this.methods[0].id;
    },
    async applyPromo(){
      if(!this.promo){ return; } this.promoBusy=true; this.promoMsg='';
      try{
        const r = await KitProxy('promo-validate',{},{method:'POST',body:{code:this.promo, subtotal:this.subtotal()}});
        const d=(r&&r.data)||{};
        if(r&&r.success && (d.discount||d.amount)){
          this.discount = Number(d.discount||d.amount); this.promoCode=this.promo;
          this.promoMsg = d.message || ('Cod aplicat: −'+this.fmt(this.discount));
        } else { this.discount=0; this.promoCode=''; this.promoMsg=(r&&r.error)||'Cod invalid.'; }
      }catch(e){ this.promoMsg='Nu am putut valida codul.'; }
      finally{ this.promoBusy=false; }
    },
    async submit(){
      this.error=''; this.submitting=true;
      try{
        const r = await KitProxy('checkout',{},{method:'POST',body:{ items:this.items, contact:this.contact, payment_method:this.method, promo_code:this.promoCode }});
        const d = (r&&r.data)||{};
        if(d.payment_url){ window.location.href=d.payment_url; return; }
        if(d.order_id||d.id){ localStorage.removeItem((window.KIT&&KIT.cartKey)||'kit_cart'); window.location.href='/confirmare?order='+(d.order_id||d.id); return; }
        this.error = (r&&r.error) || 'Plata nu a putut fi inițiată.';
      }catch(e){ this.error='Eroare de rețea. Încearcă din nou.'; }
      finally{ this.submitting=false; }
    }
  }; }
  </script>
<?php });

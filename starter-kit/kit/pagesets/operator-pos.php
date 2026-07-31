<?php
/**
 * PAGESET: operator-pos — the till.
 *
 * Catalogue on the left, running basket on the right, one tap per product.
 * The basket lives only in this page's state: a POS sale is immediate, so
 * there is nothing to persist and nothing to recover.
 *
 * Prices come from the server for the chosen channel — the operator never does
 * arithmetic, and counter prices can differ from online without anyone editing
 * a spreadsheet. Selling is refused until a cashier shift is open, because the
 * shift is what the end-of-day reconciliation is computed against.
 */
$PAGE = $PAGE ?? [];
layout('operator', ['title' => t('operator.pos') . ' — ' . kit_cfg('site_name'),
                    'nav' => 'pos', 'requires' => 'pos'],
function () { ?>
<div x-data="kitOperatorPos()" x-init="load()">

  <!-- Cashier shift ------------------------------------------------------ -->
  <section class="kit-op__shift">
    <template x-if="!shift">
      <form class="kit-op__shiftbar" @submit.prevent="openShift()">
        <div>
          <strong><?= e(t('operator.shift_closed')) ?></strong>
          <p class="kit-muted" style="margin:.15rem 0 0;font-size:.85rem"><?= e(t('operator.shift_closed_msg')) ?></p>
        </div>
        <input class="kit-op__input kit-op__input--sm" type="number" step="0.01" min="0"
               x-model="float" placeholder="<?= e(t('operator.float')) ?>">
        <button class="kit-btn kit-btn--primary" :disabled="busy"><?= e(t('operator.open_shift')) ?></button>
      </form>
    </template>
    <template x-if="shift">
      <div class="kit-op__shiftbar">
        <div>
          <strong x-text="'<?= e(t('operator.shift_open')) ?> · ' + (shift.label || '')"></strong>
          <p class="kit-muted" style="margin:.15rem 0 0;font-size:.85rem">
            <span x-text="(shift.totals && shift.totals.orders) || 0"></span> <?= e(t('operator.sales')) ?> ·
            <span x-text="fmt(((shift.totals && shift.totals.total_cents) || 0)/100)"></span>
          </p>
        </div>
        <button type="button" class="kit-btn kit-btn--outline" @click="closeShift()" :disabled="busy">
          <?= e(t('operator.close_shift')) ?>
        </button>
      </div>
    </template>
  </section>

  <div class="kit-op__pos">
    <!-- Catalogue -------------------------------------------------------- -->
    <section>
      <div class="kit-op__cats">
        <template x-for="c in categories" :key="c">
          <button type="button" class="kit-booking__chip" :class="cat===c?'is-selected':''" @click="cat=c"
                  x-text="label(c)"></button>
        </template>
      </div>

      <p x-show="loading" class="kit-muted">…</p>
      <div class="kit-op__grid">
        <template x-for="p in visible()" :key="p.ticket_type_id">
          <button type="button" class="kit-op__prod" @click="add(p)" :disabled="!shift">
            <span class="kit-op__prodname" x-text="p.name"></span>
            <strong x-text="fmt(p.price_cents/100)"></strong>
          </button>
        </template>
      </div>
      <p x-show="!loading && !visible().length" class="kit-muted"><?= e(t('cart.empty')) ?></p>
    </section>

    <!-- Basket ----------------------------------------------------------- -->
    <aside class="kit-op__basket">
      <h2 class="kit-display" style="font-size:1.1rem;margin:0 0 .75rem"><?= e(t('cart.summary')) ?></h2>

      <p x-show="!lines.length" class="kit-muted"><?= e(t('cart.empty')) ?></p>
      <template x-for="(l,i) in lines" :key="i">
        <div class="kit-op__line">
          <div>
            <div x-text="l.name"></div>
            <small class="kit-muted" x-text="fmt(l.unit/100) + ' × ' + l.qty"></small>
          </div>
          <div class="kit-qty">
            <button type="button" @click="dec(i)" aria-label="minus">−</button>
            <span x-text="l.qty"></span>
            <button type="button" @click="inc(i)" aria-label="plus">+</button>
          </div>
          <strong x-text="fmt(l.unit*l.qty/100)"></strong>
        </div>
      </template>

      <div class="kit-op__total">
        <span><?= e(t('common.total')) ?></span>
        <strong x-text="fmt(total()/100)"></strong>
      </div>

      <div class="kit-op__pay">
        <template x-for="m in ['cash','card']" :key="m">
          <button type="button" class="kit-booking__chip" :class="method===m?'is-selected':''"
                  @click="method=m" x-text="m==='cash' ? '<?= e(t('operator.cash')) ?>' : '<?= e(t('operator.card')) ?>'"></button>
        </template>
      </div>

      <p class="kit-booking__error" x-show="error" x-text="error" x-cloak></p>

      <button type="button" class="kit-btn kit-btn--primary kit-op__submit"
              :disabled="!lines.length || !shift || busy" @click="checkout()">
        <span x-text="busy ? '…' : '<?= e(t('operator.charge')) ?>'"></span>
      </button>
      <button type="button" class="kit-btn kit-btn--outline" style="width:100%;margin-top:.5rem"
              x-show="lines.length" @click="lines=[]"><?= e(t('operator.clear')) ?></button>
    </aside>
  </div>

  <!-- Receipt ------------------------------------------------------------ -->
  <div class="kit-op__receipt" x-show="receipt" x-cloak>
    <div class="kit-op__card">
      <h2 class="kit-display" style="margin:0 0 .5rem">✅ <?= e(t('operator.sold')) ?></h2>
      <p class="kit-muted" style="margin:0 0 1rem">
        <?= e(t('operator.order')) ?> #<span x-text="receipt && receipt.order_id"></span> ·
        <strong x-text="receipt && fmt(receipt.total)"></strong>
      </p>
      <ul class="kit-op__codes">
        <template x-for="t in (receipt && receipt.tickets) || []" :key="t.code">
          <li><code x-text="t.code"></code> <span class="kit-muted" x-text="t.type"></span></li>
        </template>
      </ul>
      <button type="button" class="kit-btn kit-btn--primary" style="width:100%" @click="printReceipt()">
        <?= e(t('operator.print')) ?>
      </button>
      <button type="button" class="kit-btn kit-btn--outline" style="width:100%;margin-top:.5rem" @click="receipt=null">
        <?= e(t('operator.next_sale')) ?>
      </button>
    </div>
  </div>
</div>

<script>
function kitOperatorPos(){ return {
  items: [], categories: [], cat: 'all', lines: [], method: 'cash',
  shift: null, loading: true, busy: false, error: '', receipt: null, float: '',
  L: <?= json_encode([
      'access' => t('operator.cat_access'), 'rental' => t('operator.cat_rental'),
      'activity' => t('operator.cat_activity'), 'all' => t('operator.cat_all'),
      'shift_needed' => t('operator.shift_needed'), 'net' => t('common.network_error'),
  ], JSON_UNESCAPED_UNICODE) ?>,
  fmt(v){ return new Intl.NumberFormat('ro-RO',{minimumFractionDigits:2}).format(Number(v||0))
          + ' ' + ((window.KIT&&KIT.currency)||'RON'); },
  label(c){ return this.L[c] || c; },
  async load(){
    try {
      const [cat, sh] = await Promise.all([
        KitOperator.api('op-catalog'),
        KitOperator.api('op-cashier')
      ]);
      this.items = (cat && cat.data && cat.data.items) || [];
      this.categories = ['all', ...new Set(this.items.map(i => i.category))];
      this.shift = (sh && sh.data) || null;
    } catch(e) { this.error = this.L.net; }
    finally { this.loading = false; }
  },
  visible(){ return this.cat === 'all' ? this.items : this.items.filter(i => i.category === this.cat); },
  add(p){
    if (!this.shift) { this.error = this.L.shift_needed; return; }
    this.error = '';
    const at = this.lines.findIndex(l => l.ticket_type_id === p.ticket_type_id);
    if (at >= 0) this.lines[at].qty++;
    else this.lines.push({ ticket_type_id: p.ticket_type_id, name: p.name, unit: p.price_cents, qty: 1 });
  },
  inc(i){ this.lines[i].qty++; },
  dec(i){ if (--this.lines[i].qty <= 0) this.lines.splice(i,1); },
  total(){ return this.lines.reduce((t,l) => t + l.unit*l.qty, 0); },
  async openShift(){
    this.busy = true; this.error='';
    try {
      const r = await KitOperator.api('op-cashier-open', {}, { method:'POST',
        body:{ float: Number(this.float||0) } });
      if (r && r.data) this.shift = r.data; else this.error = (r&&r.error)||this.L.net;
    } catch(e){ this.error = this.L.net; } finally { this.busy = false; }
  },
  async closeShift(){
    const counted = prompt('<?= e(t('operator.counted_prompt')) ?>');
    if (counted === null) return;
    this.busy = true;
    try {
      const r = await KitOperator.api('op-cashier-close', {}, { method:'POST',
        body:{ counted: Number(counted||0) } });
      if (r && r.success !== false) { this.shift = null; this.lines = []; }
      else this.error = (r&&r.error)||this.L.net;
    } catch(e){ this.error = this.L.net; } finally { this.busy = false; }
  },
  async checkout(){
    if (this.busy || !this.lines.length) return;
    this.busy = true; this.error = '';
    try {
      const r = await KitOperator.api('op-sale', {}, { method:'POST', body:{
        items: this.lines.map(l => ({ ticket_type_id: l.ticket_type_id, qty: l.qty })),
        payment_method: this.method
      }});
      if (r && r.data && r.data.order_id) {
        this.receipt = r.data; this.lines = [];
        const sh = await KitOperator.api('op-cashier');   // refresh the running total
        this.shift = (sh && sh.data) || this.shift;
      } else { this.error = (r && r.error) || this.L.net; }
    } catch(e){ this.error = this.L.net; } finally { this.busy = false; }
  },
  printReceipt(){ window.print(); }
}; }
</script>
<?php });

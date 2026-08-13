<?php
/**
 * PAGESET: operator-rentals — hand equipment out and take it back.
 *
 * Starting a rental binds a ticket to a physical unit; ending it computes the
 * overtime surcharge server-side. Active rentals are sorted so anything past
 * its planned return is impossible to miss — that is the whole job of this
 * screen when the boats are due back.
 */
$PAGE = $PAGE ?? [];
layout('operator', ['title' => t('operator.rentals') . ' — ' . kit_cfg('site_name'),
                    'nav' => 'rentals', 'requires' => 'rentals'],
function () { ?>
<div x-data="kitOperatorRentals()" x-init="load()">

  <!-- Hand out ----------------------------------------------------------- -->
  <form class="kit-op__card kit-op__start" @submit.prevent="start()">
    <h2 class="kit-display" style="font-size:1.1rem;margin:0 0 .75rem"><?= e(t('operator.rental_start')) ?></h2>
    <div class="kit-op__startgrid">
      <div>
        <label class="kit-op__label" for="r-code"><?= e(t('operator.ticket_code')) ?></label>
        <input id="r-code" class="kit-op__input" x-model="code" autocomplete="off"
               placeholder="<?= e(t('operator.scan_placeholder')) ?>">
      </div>
      <div>
        <label class="kit-op__label" for="r-res"><?= e(t('operator.equipment')) ?></label>
        <select id="r-res" class="kit-op__input" x-model="resourceId">
          <option value=""><?= e(t('operator.pick_equipment')) ?></option>
          <template x-for="u in available" :key="u.id">
            <option :value="u.id" x-text="u.name + (u.label ? ' · ' + u.label : '')"></option>
          </template>
        </select>
      </div>
      <button class="kit-btn kit-btn--primary" :disabled="busy || !code || !resourceId">
        <?= e(t('operator.hand_out')) ?>
      </button>
    </div>
    <p class="kit-booking__error" x-show="error" x-text="error" x-cloak></p>
    <p class="kit-muted" x-show="!available.length && !loading"><?= e(t('operator.no_equipment')) ?></p>
  </form>

  <!-- Out right now ------------------------------------------------------ -->
  <h3 class="kit-op__h3">
    <?= e(t('operator.rental_active')) ?>
    <span class="kit-muted" x-text="'(' + active.length + ')'"></span>
  </h3>

  <p x-show="loading" class="kit-muted">…</p>
  <p x-show="!loading && !active.length" class="kit-muted"><?= e(t('operator.none_out')) ?></p>

  <ul class="kit-op__rentals">
    <template x-for="r in active" :key="r.id">
      <li :data-overdue="r.overdue ? '1' : '0'">
        <div>
          <strong x-text="r.resource"></strong>
          <small class="kit-muted" x-text="r.label"></small>
        </div>
        <div class="kit-muted">
          <span x-text="'<?= e(t('operator.since')) ?> ' + time(r.started_at)"></span>
          <template x-if="r.planned_end_at">
            <span x-text="' · <?= e(t('operator.due')) ?> ' + time(r.planned_end_at)"></span>
          </template>
        </div>
        <button type="button" class="kit-btn kit-btn--outline" @click="end(r)" :disabled="busy">
          <?= e(t('operator.take_back')) ?>
        </button>
      </li>
    </template>
  </ul>

  <!-- Return summary ----------------------------------------------------- -->
  <div class="kit-op__receipt" x-show="closed" x-cloak>
    <div class="kit-op__card">
      <h2 class="kit-display" style="margin:0 0 .5rem">✅ <?= e(t('operator.returned')) ?></h2>
      <p x-show="closed && closed.overtime_minutes > 0" class="kit-muted">
        <?= e(t('operator.overtime')) ?>:
        <strong x-text="closed && closed.overtime_minutes"></strong> min ·
        <strong x-text="closed && fmt(closed.surcharge)"></strong>
      </p>
      <p x-show="closed && !closed.overtime_minutes" class="kit-muted"><?= e(t('operator.on_time')) ?></p>
      <button type="button" class="kit-btn kit-btn--primary" style="width:100%" @click="closed=null">
        <?= e(t('common.back')) ?>
      </button>
    </div>
  </div>
</div>

<script>
function kitOperatorRentals(){ return {
  active: [], available: [], code: '', resourceId: '',
  loading: true, busy: false, error: '', closed: null,
  L: <?= json_encode(['net' => t('common.network_error')], JSON_UNESCAPED_UNICODE) ?>,
  fmt(v){ return new Intl.NumberFormat('ro-RO',{minimumFractionDigits:2}).format(Number(v||0))
          + ' ' + ((window.KIT&&KIT.currency)||'RON'); },
  time(iso){ return iso ? new Date(iso).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}) : ''; },
  async load(){
    try {
      const r = await KitOperator.api('op-rentals');
      const d = (r && r.data) || {};
      // Overdue first: that is what the operator is looking for.
      this.active = (d.active || []).sort((a,b) => (b.overdue?1:0) - (a.overdue?1:0));
      this.available = d.available || [];
    } catch(e){ this.error = this.L.net; }
    finally { this.loading = false; }
  },
  async start(){
    if (this.busy) return;
    this.busy = true; this.error = '';
    try {
      const r = await KitOperator.api('op-rental-start', {}, { method:'POST',
        body:{ ticket_code: this.code.trim(), resource_id: Number(this.resourceId) } });
      if (r && r.success !== false) { this.code=''; this.resourceId=''; await this.load(); }
      else this.error = (r && r.error) || this.L.net;
    } catch(e){ this.error = this.L.net; } finally { this.busy = false; }
  },
  async end(r){
    if (this.busy) return;
    this.busy = true; this.error = '';
    try {
      const res = await KitOperator.api('op-rental-end', { rental: r.id }, { method:'POST', body:{} });
      if (res && res.data) { this.closed = res.data; await this.load(); }
      else this.error = (res && res.error) || this.L.net;
    } catch(e){ this.error = this.L.net; } finally { this.busy = false; }
  }
}; }
</script>
<?php });

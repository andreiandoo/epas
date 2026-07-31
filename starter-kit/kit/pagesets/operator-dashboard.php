<?php
/**
 * PAGESET: operator-dashboard — today, at a glance.
 *
 * The landing page for every role, so it must be readable by someone who only
 * has check-in rights. Numbers refresh on an interval because this screen tends
 * to sit open on a tablet behind the counter all day.
 */
$PAGE = $PAGE ?? [];
layout('operator', ['title' => t('operator.dashboard') . ' — ' . kit_cfg('site_name'), 'nav' => 'dashboard'],
function () { ?>
<div x-data="kitOperatorDash()" x-init="load(); timer = setInterval(() => load(true), 60000)">

  <div class="kit-op__hello">
    <h1 class="kit-display" style="margin:0"><span x-text="greeting()"></span><span x-text="op ? ', ' + op.name : ''"></span></h1>
    <p class="kit-muted" style="margin:.25rem 0 0" x-text="d.date"></p>
  </div>

  <div class="kit-op__stats">
    <div class="kit-op__stat">
      <span class="kit-op__statnum" x-text="fmt(d.revenue_today)"></span>
      <span class="kit-muted"><?= e(t('operator.revenue_today')) ?></span>
    </div>
    <div class="kit-op__stat">
      <span class="kit-op__statnum" x-text="d.orders_today || 0"></span>
      <span class="kit-muted"><?= e(t('operator.orders_today')) ?></span>
    </div>
    <div class="kit-op__stat">
      <span class="kit-op__statnum" x-text="d.scanned_today || 0"></span>
      <span class="kit-muted"><?= e(t('operator.scanned_today')) ?></span>
    </div>
    <div class="kit-op__stat">
      <span class="kit-op__statnum" x-text="d.rentals_active || 0"></span>
      <span class="kit-muted"><?= e(t('operator.rentals_out')) ?></span>
    </div>
  </div>

  <!-- Cashier state is the one thing that blocks selling, so it is loud. -->
  <div class="kit-op__card kit-op__shiftcard" x-show="op && op.can && op.can.pos" x-cloak>
    <template x-if="d.cashier_open">
      <div>
        <strong><?= e(t('operator.shift_open')) ?></strong>
        <p class="kit-muted" style="margin:.25rem 0 0">
          <span x-text="d.cashier && d.cashier.label"></span> ·
          <span x-text="(d.cashier && d.cashier.totals && d.cashier.totals.orders) || 0"></span>
          <?= e(t('operator.sales')) ?> ·
          <span x-text="fmt(((d.cashier && d.cashier.totals && d.cashier.totals.total_cents) || 0)/100)"></span>
        </p>
      </div>
    </template>
    <template x-if="!d.cashier_open">
      <div>
        <strong><?= e(t('operator.shift_closed')) ?></strong>
        <p class="kit-muted" style="margin:.25rem 0 0"><?= e(t('operator.shift_closed_msg')) ?></p>
      </div>
    </template>
    <a href="/operator/pos" class="kit-btn kit-btn--primary"><?= e(t('operator.go_pos')) ?></a>
  </div>

  <p class="kit-booking__error" x-show="error" x-text="error" x-cloak></p>
</div>

<script>
function kitOperatorDash(){ return {
  d: {}, op: null, error: '', timer: null,
  L: <?= json_encode([
      'morning' => t('operator.good_morning'), 'day' => t('operator.good_day'),
      'evening' => t('operator.good_evening'), 'net' => t('common.network_error'),
  ], JSON_UNESCAPED_UNICODE) ?>,
  fmt(v){ return new Intl.NumberFormat('ro-RO',{minimumFractionDigits:2}).format(Number(v||0))
          + ' ' + ((window.KIT&&KIT.currency)||'RON'); },
  greeting(){ const h = new Date().getHours();
    return h < 11 ? this.L.morning : (h < 18 ? this.L.day : this.L.evening); },
  async load(quiet){
    try {
      const r = await KitOperator.api('op-dashboard');
      if (r && r.data) { this.d = r.data; this.error = ''; }
      this.op = KitOperator.profile();
    } catch(e){ if (!quiet) this.error = this.L.net; }
  }
}; }
</script>
<?php });

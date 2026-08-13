<?php
/**
 * PAGESET: operator-checkin — gate scanning.
 *
 * One always-focused input. A hardware barcode scanner types the code and
 * presses Enter, so the field must never lose focus between scans; a phone
 * camera app pasting into it works the same way. The last few results stay on
 * screen because the person at the gate looks at the previous one while the
 * next guest walks up.
 */
$PAGE = $PAGE ?? [];
layout('operator', ['title' => t('operator.checkin') . ' — ' . kit_cfg('site_name'),
                    'nav' => 'checkin', 'requires' => 'checkin'],
function () { ?>
<div x-data="kitOperatorCheckin()" x-init="focus()">

  <form class="kit-op__scanbar" @submit.prevent="scan()">
    <input id="op-scan" class="kit-op__input kit-op__input--big" x-model="code"
           placeholder="<?= e(t('operator.scan_placeholder')) ?>"
           autocomplete="off" autocapitalize="off" spellcheck="false" x-ref="input">
    <button class="kit-btn kit-btn--primary" :disabled="busy || !code">
      <?= e(t('operator.scan')) ?>
    </button>
  </form>

  <!-- Last result, large enough to read at arm's length -------------------- -->
  <div class="kit-op__result" x-show="last" x-cloak :data-result="last && last.result">
    <p class="kit-op__resulticon" x-text="icon(last && last.result)"></p>
    <h2 class="kit-display" x-text="headline(last)"></h2>
    <p class="kit-muted" x-show="last && last.data">
      <strong x-text="last && last.data && last.data.type"></strong>
      · <code x-text="last && last.data && last.data.code"></code>
    </p>
  </div>

  <h3 class="kit-op__h3" x-show="log.length"><?= e(t('operator.recent')) ?></h3>
  <ul class="kit-op__log">
    <template x-for="(r,i) in log" :key="i">
      <li :data-result="r.result">
        <span x-text="icon(r.result)"></span>
        <code x-text="r.code"></code>
        <span class="kit-muted" x-text="r.note"></span>
        <small class="kit-muted" x-text="r.at"></small>
      </li>
    </template>
  </ul>
</div>

<script>
function kitOperatorCheckin(){ return {
  code: '', busy: false, last: null, log: [],
  L: <?= json_encode([
      'ok' => t('operator.scan_ok'), 'dup' => t('operator.scan_dup'),
      'bad' => t('operator.scan_bad'), 'net' => t('common.network_error'),
  ], JSON_UNESCAPED_UNICODE) ?>,
  focus(){ this.$nextTick(() => this.$refs.input && this.$refs.input.focus()); },
  icon(r){ return r === 'ok' ? '✅' : (r === 'already_scanned' ? '⚠️' : '⛔'); },
  headline(l){
    if (!l) return '';
    if (l.result === 'ok') return this.L.ok;
    if (l.result === 'already_scanned') return this.L.dup;
    return l.error || this.L.bad;
  },
  async scan(){
    const code = (this.code || '').trim();
    if (!code || this.busy) return;
    this.busy = true; this.code = '';
    try {
      const r = await KitOperator.api('op-scan', {}, { method:'POST', body:{ code } });
      const res = { result: (r && r.result) || 'invalid', error: r && r.error, data: r && r.data };
      this.last = res;
      this.log.unshift({ code, result: res.result, note: res.error || (res.data && res.data.type) || '',
                         at: new Date().toLocaleTimeString('ro-RO') });
      this.log = this.log.slice(0, 12);
    } catch(e) {
      this.last = { result: 'invalid', error: this.L.net };
    } finally {
      this.busy = false;
      this.focus();          // hardware scanners fire straight into the field
    }
  }
}; }
</script>
<?php });

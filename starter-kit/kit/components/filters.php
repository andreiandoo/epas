<?php
/**
 * COMPONENT: filters  (SSR listing filter bar — GET form, no JS required)
 * Input:
 *   $action  form target URL (e.g. '/evenimente')
 *   $fields  [ ['name'=>'cat','label'=>'Categorie','options'=>[['value','label']],'value'=>current], ... ]
 *   $sort    optional sort field spec (same option shape) under name 'sort'
 */
$action = $action ?? '';
$fields = $fields ?? [];
?>
<form class="kit-filters" method="get" action="<?= e($action) ?>">
  <?php foreach ($fields as $f): ?>
    <label class="kit-filters__field">
      <span class="kit-muted"><?= e($f['label'] ?? '') ?></span>
      <select name="<?= e($f['name']) ?>" onchange="this.form.submit()">
        <option value=""><?= e($f['placeholder'] ?? 'Toate') ?></option>
        <?php foreach (($f['options'] ?? []) as $o): ?>
          <?php [$val,$lab] = [$o['value'] ?? $o[0] ?? '', $o['label'] ?? $o[1] ?? '']; ?>
          <option value="<?= e($val) ?>"<?= ((string)($f['value'] ?? '') === (string)$val) ? ' selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endforeach; ?>
  <noscript><button class="kit-btn kit-btn--outline" type="submit">Filtrează</button></noscript>
</form>

<?php
/**
 * bilete.online v2: the filter bar of a listing hub (/experiente, /atractii and their /{oras}/… versions).
 *
 * Rendered by v2/am-hub.php when the page sets $hub['filter']. Everything here is plain GET: each control is a
 * form field, every state has its own URL, and the whole thing works with JavaScript off. assets/v2/js/hub-filter.js
 * only makes it nicer — it turns the long lists into searchable dropdowns and applies a change without the button.
 *
 * $hub['filter'] keys:
 *   action   where the form submits (the page itself, without the page number)
 *   search   ['name' => 'q', 'value' => …, 'placeholder' => …]      optional free-text box
 *   fields   [['name','label','value','type' => 'select'|'date','options','find','hint']]
 *            options: [[value, label]] or ['group' => 'Label', 'items' => [[value, label]]]
 *            find:    placeholder of the search box inside the dropdown; marks the list as long
 *   chips    ['label' => 'Tip', 'items' => [[text, href, active]]]  the one facet that stays crawlable
 *   hidden   ['tip' => 'muzeu']  what the chips hold: it has no field of its own, so it travels as a hidden
 *            input and a change to any other control does not drop it
 *   active   [[text, href]]   the filters in force; the href is the same list without that one
 *   reset    href of the unfiltered list, or null when nothing is filtered
 *   count    "12 experiențe în Ploiești"
 */

$hf = $hub['filter'];
$hfFields = $hf['fields'] ?? [];
$hfActive = $hf['active'] ?? [];
$hfChips = $hf['chips'] ?? null;
$hfCount = count($hfActive);
?>
<form class="hf<?= $hfCount ? ' has-active' : '' ?>" method="get" action="<?= v2_e($hf['action']) ?>" data-hub-filter>
  <?php foreach (($hf['hidden'] ?? []) as $hk => $hv): if ((string) $hv === '') continue; ?>
  <input type="hidden" name="<?= v2_e($hk) ?>" value="<?= v2_e($hv) ?>">
  <?php endforeach; ?>
  <div class="hf-bar">
    <?php if (!empty($hf['search'])): ?>
    <div class="hf-q">
      <?= v2_ic('magnifying-glass') ?>
      <label class="sr" for="hf-q"><?= v2_e($hf['search']['placeholder']) ?></label>
      <input id="hf-q" type="search" name="<?= v2_e($hf['search']['name']) ?>" value="<?= v2_e($hf['search']['value']) ?>" placeholder="<?= v2_e($hf['search']['placeholder']) ?>" autocomplete="off" enterkeyhint="search">
      <?php if ($hf['search']['value'] !== ''): ?><a class="hf-q-x" href="<?= v2_e($hf['search']['clear']) ?>" aria-label="Șterge căutarea"><?= v2_ic('x') ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
    <button class="hf-toggle" type="button" data-hf-toggle aria-expanded="false" aria-controls="hf-fields"><?= v2_ic('list') ?>Filtre<?php if ($hfCount): ?><span class="hf-n"><?= $hfCount ?></span><?php endif; ?></button>
  </div>

  <div class="hf-fields" id="hf-fields">
    <?php foreach ($hfFields as $f): $fid = 'hf-' . $f['name']; ?>
    <p class="hf-field">
      <label for="<?= v2_e($fid) ?>"><?= v2_e($f['label']) ?></label>
      <?php if (($f['type'] ?? 'select') === 'date'): ?>
      <span class="hf-ctl"><input id="<?= v2_e($fid) ?>" type="date" name="<?= v2_e($f['name']) ?>" value="<?= v2_e($f['value']) ?>" min="<?= v2_e($f['min'] ?? '') ?>"></span>
      <?php else: ?>
      <span class="hf-ctl hf-sel"<?= !empty($f['find']) ? ' data-hf-find="' . v2_e($f['find']) . '"' : '' ?>>
        <select id="<?= v2_e($fid) ?>" name="<?= v2_e($f['name']) ?>">
          <?php foreach ($f['options'] as $o): ?>
            <?php if (isset($o['group'])): ?>
            <optgroup label="<?= v2_e($o['group']) ?>">
              <?php foreach ($o['items'] as [$ov, $ol]): ?><option value="<?= v2_e($ov) ?>"<?= (string) $ov === (string) $f['value'] ? ' selected' : '' ?>><?= v2_e($ol) ?></option><?php endforeach; ?>
            </optgroup>
            <?php else: ?>
            <option value="<?= v2_e($o[0]) ?>"<?= (string) $o[0] === (string) $f['value'] ? ' selected' : '' ?>><?= v2_e($o[1]) ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <?= v2_ic('caret-down') ?>
      </span>
      <?php endif; ?>
    </p>
    <?php endforeach; ?>
    <p class="hf-acts">
      <button class="btn btn-primary hf-go" type="submit">Arată rezultatele</button>
      <?php if (!empty($hf['reset'])): ?><a class="hf-reset" href="<?= v2_e($hf['reset']) ?>">Șterge filtrele</a><?php endif; ?>
    </p>
  </div>

  <?php if ($hfChips && !empty($hfChips['items'])): ?>
  <div class="hf-chips">
    <p class="hf-chips-l"><?= v2_e($hfChips['label']) ?></p>
    <div class="fchips">
      <?php foreach ($hfChips['items'] as [$ctext, $chref, $cactive]): ?>
      <a class="fchip" href="<?= v2_e($chref) ?>"<?= $cactive ? ' aria-current="true"' : '' ?>><?= v2_e($ctext) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="hf-foot">
    <p class="hf-count"><?= v2_e($hf['count']) ?></p>
    <?php if ($hfActive): ?>
    <ul class="hf-active" aria-label="Filtre active">
      <?php foreach ($hfActive as [$atext, $ahref]): ?>
      <li><a href="<?= v2_e($ahref) ?>" aria-label="Elimină filtrul <?= v2_e($atext) ?>"><?= v2_e($atext) ?><?= v2_ic('x') ?></a></li>
      <?php endforeach; ?>
      <?php if (!empty($hf['reset'])): ?><li class="hf-active-all"><a href="<?= v2_e($hf['reset']) ?>">Șterge tot</a></li><?php endif; ?>
    </ul>
    <?php endif; ?>
  </div>
</form>

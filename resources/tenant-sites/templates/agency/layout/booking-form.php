<?php
/**
 * Formularul de cerere de booking (bookingForm din assets/fx.js → proxy booking-request →
 * core). Variabile: $bfArtist (slug-ul artistului, opțional), $bfRoster (lista pentru
 * alegere, când nu e un artist anume). Cererea ajunge la agenție pe email și în panou.
 */
$bfArtist = $bfArtist ?? '';
$bfRoster = $bfRoster ?? [];
$types = ['concert' => t('Concert'), 'festival' => t('Festival'), 'private' => t('Eveniment privat'), 'corporate' => t('Eveniment corporate'), 'other' => t('Altceva')];
?>
<form class="ag-form" x-data="bookingForm(<?= e(json_encode(['artist' => $bfArtist], JSON_UNESCAPED_UNICODE)) ?>)" @submit.prevent="submit()" novalidate>
    <div x-show="!done">
        <div class="ag-form__grid">
            <?php if ($bfRoster): ?>
            <label class="ag-field ag-field--wide"><span><?= te('Artistul') ?></span>
                <select x-model="f.artist"><option value=""><?= te('Încă nu știu, propuneți-mi voi') ?></option><?php foreach ($bfRoster as $a): ?><option value="<?= e($a['slug']) ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <label class="ag-field"><span><?= te('Nume') ?> *</span><input type="text" x-model="f.name" autocomplete="name" required maxlength="150" :class="bad('name') && 'is-bad'"></label>
            <label class="ag-field"><span><?= te('Companie / organizator') ?></span><input type="text" x-model="f.company" autocomplete="organization" maxlength="190"></label>
            <label class="ag-field"><span><?= te('Email') ?> *</span><input type="email" x-model="f.email" autocomplete="email" required maxlength="190" :class="bad('email') && 'is-bad'"></label>
            <label class="ag-field"><span><?= te('Telefon') ?></span><input type="tel" x-model="f.phone" autocomplete="tel" maxlength="40"></label>
            <label class="ag-field"><span><?= te('Tipul evenimentului') ?></span>
                <select x-model="f.event_type"><option value=""><?= te('Alege') ?></option><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label class="ag-field"><span><?= te('Data') ?></span><input type="date" x-model="f.event_date" :min="today"></label>
            <label class="ag-field"><span><?= te('Orașul') ?></span><input type="text" x-model="f.city" maxlength="120"></label>
            <label class="ag-field"><span><?= te('Locația') ?></span><input type="text" x-model="f.venue" maxlength="190"></label>
            <label class="ag-field"><span><?= te('Câți oameni') ?></span><input type="number" min="1" x-model="f.capacity"></label>
            <label class="ag-field"><span><?= te('Buget estimat') ?></span>
                <select x-model="f.budget"><option value=""><?= te('Prefer să discutăm') ?></option><option>&lt; 2.000 €</option><option>2.000–5.000 €</option><option>5.000–15.000 €</option><option>15.000–40.000 €</option><option>&gt; 40.000 €</option></select></label>
            <label class="ag-field ag-field--wide"><span><?= te('Spune-ne mai multe') ?></span><textarea x-model="f.message" rows="4" maxlength="3000" placeholder="<?= te('Programul, publicul, ce așteptări ai de la concert…') ?>"></textarea></label>
        </div>
        <label class="ag-check"><input type="checkbox" x-model="f.consent"><span><?= te('Sunt de acord ca {name} să folosească aceste date ca să-mi răspundă la cerere.', ['name' => SITE_NAME]) ?> <a href="/confidentialitate" class="ag-link"><?= te('Confidențialitate') ?></a></span></label>
        <p class="ag-form__err" x-show="error" x-cloak x-text="error" role="alert"></p>
        <button type="submit" class="ag-btn ag-btn--lg" :disabled="busy"><span x-text="busy ? TS.t('Se trimite…') : TS.t('Trimite cererea')"><?= te('Trimite cererea') ?></span> <?= AG_ARROW ?></button>
    </div>
    <div class="ag-form__done" x-show="done" x-cloak role="status">
        <b><?= te('Cererea a plecat.') ?></b>
        <p><?= te('Ți-am trimis o confirmare pe email. Revenim în cel mult două zile lucrătoare.') ?></p>
    </div>
</form>

<?php
/**
 * Lista fanilor: abonare la newsletterul trupei (componenta comună newsletterForm din
 * commerce.js → core). Acordul se bifează explicit; dezabonarea e în fiecare email.
 */
?>
<section class="bd-section bd-news" id="newsletter" aria-labelledby="bd-news-title">
    <div class="bd-wrap bd-news__grid">
        <div data-reveal>
            <p class="bd-eyebrow"><?= e($s['eyebrow'] ?? t('Lista fanilor')) ?></p>
            <h2 class="bd-h2" id="bd-news-title"><?= e($s['title'] ?? '') ?></h2>
            <?php if (!empty($s['text'])): ?><p class="bd-lead"><?= ts_md($s['text']) ?></p><?php endif; ?>
        </div>
        <form class="bd-news__form" x-data="newsletterForm" @submit.prevent="submit()" novalidate data-reveal>
            <div x-show="!done">
                <div class="bd-news__row">
                    <label class="bd-field"><span><?= te('Prenume') ?></span><input type="text" x-model="first_name" autocomplete="given-name" maxlength="100"></label>
                    <label class="bd-field"><span><?= te('Email') ?></span><input type="email" x-model="email" autocomplete="email" required maxlength="190"></label>
                </div>
                <label class="bd-check"><input type="checkbox" x-model="consent"><span><?= te('Vreau să primesc pe email noutățile {name}: date noi, bilete, lansări. Mă pot dezabona oricând.', ['name' => SITE_NAME]) ?> <a href="/confidentialitate" class="bd-link"><?= te('Confidențialitate') ?></a></span></label>
                <p class="bd-news__err" x-show="error" x-cloak x-text="error" role="alert"></p>
                <button type="submit" class="bd-btn bd-btn--lg" :disabled="busy"><span x-text="busy ? TS.t('Un moment…') : <?= e(json_encode((string) ($s['button_label'] ?? t('Mă abonez')), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>"><?= e($s['button_label'] ?? t('Mă abonez')) ?></span> <?= BD_ARROW ?></button>
            </div>
            <div class="bd-news__done" x-show="done" x-cloak role="status">
                <b><?= te('Ești pe listă.') ?></b>
                <p><?= te('Ți-am trimis un email de bun venit. Afli primul de datele noi.') ?></p>
            </div>
        </form>
    </div>
</section>

<?php
/**
 * Lista fanilor: abonare la newsletterul petrecerii (componenta comună newsletterForm din
 * commerce.js → core). Acordul se bifează explicit; dezabonarea e în fiecare email.
 */
?>
<section class="pt-section pt-news" id="newsletter" aria-labelledby="pt-news-title">
    <div class="pt-wrap pt-news__grid">
        <div data-reveal>
            <p class="pt-eyebrow"><?= e($s['eyebrow'] ?? t('Lista fanilor')) ?></p>
            <h2 class="pt-h2" id="pt-news-title"><?= e($s['title'] ?? '') ?></h2>
            <?php if (!empty($s['text'])): ?><p class="pt-lead"><?= ts_md($s['text']) ?></p><?php endif; ?>
        </div>
        <form class="pt-news__form" x-data="newsletterForm" @submit.prevent="submit()" novalidate data-reveal>
            <div x-show="!done">
                <div class="pt-news__row">
                    <label class="pt-field"><span><?= te('Prenume') ?></span><input type="text" x-model="first_name" autocomplete="given-name" maxlength="100"></label>
                    <label class="pt-field"><span><?= te('Email') ?></span><input type="email" x-model="email" autocomplete="email" required maxlength="190"></label>
                </div>
                <label class="pt-check"><input type="checkbox" x-model="consent"><span><?= te('Vreau să primesc pe email noutățile {name}: date noi, bilete, lansări. Mă pot dezabona oricând.', ['name' => SITE_NAME]) ?> <a href="/confidentialitate" class="pt-link"><?= te('Confidențialitate') ?></a></span></label>
                <p class="pt-news__err" x-show="error" x-cloak x-text="error" role="alert"></p>
                <button type="submit" class="pt-btn pt-btn--lg" :disabled="busy"><span x-text="busy ? TS.t('Un moment…') : <?= e(json_encode((string) ($s['button_label'] ?? t('Mă abonez')), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>"><?= e($s['button_label'] ?? t('Mă abonez')) ?></span> <?= PT_ARROW ?></button>
            </div>
            <div class="pt-news__done" x-show="done" x-cloak role="status">
                <b><?= te('Ești pe listă.') ?></b>
                <p><?= te('Ți-am trimis un email de bun venit. Afli primul de datele noi.') ?></p>
            </div>
        </form>
    </div>
</section>

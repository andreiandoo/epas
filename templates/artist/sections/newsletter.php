<?php
/**
 * Lista fanilor: abonare la newsletterul artistului (componenta comună newsletterForm din
 * commerce.js → core). Acordul se bifează explicit; dezabonarea e în fiecare email.
 */
?>
<section class="ar-section ar-news" id="newsletter" aria-labelledby="ar-news-title">
    <div class="ar-wrap ar-news__grid">
        <div data-reveal>
            <p class="ar-eyebrow"><?= e($s['eyebrow'] ?? t('Lista fanilor')) ?></p>
            <h2 class="ar-h2" id="ar-news-title"><?= e($s['title'] ?? '') ?></h2>
            <?php if (!empty($s['text'])): ?><p class="ar-lead"><?= ts_md($s['text']) ?></p><?php endif; ?>
        </div>
        <form class="ar-news__form" x-data="newsletterForm" @submit.prevent="submit()" novalidate data-reveal>
            <div x-show="!done">
                <div class="ar-news__row">
                    <label class="ar-field"><span><?= te('Prenume') ?></span><input type="text" x-model="first_name" autocomplete="given-name" maxlength="100"></label>
                    <label class="ar-field"><span><?= te('Email') ?></span><input type="email" x-model="email" autocomplete="email" required maxlength="190"></label>
                </div>
                <label class="ar-check"><input type="checkbox" x-model="consent"><span><?= te('Vreau să primesc pe email noutățile {name}: date noi, bilete, lansări. Mă pot dezabona oricând.', ['name' => SITE_NAME]) ?> <a href="/confidentialitate" class="ar-link"><?= te('Confidențialitate') ?></a></span></label>
                <p class="ar-news__err" x-show="error" x-cloak x-text="error" role="alert"></p>
                <button type="submit" class="ar-btn ar-btn--lg" :disabled="busy"><span x-text="busy ? TS.t('Un moment…') : <?= e(json_encode((string) ($s['button_label'] ?? t('Mă abonez')), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>"><?= e($s['button_label'] ?? t('Mă abonez')) ?></span> <?= AR_ARROW ?></button>
            </div>
            <div class="ar-news__done" x-show="done" x-cloak role="status">
                <b><?= te('Ești pe listă.') ?></b>
                <p><?= te('Ți-am trimis un email de bun venit. Afli primul de datele noi.') ?></p>
            </div>
        </form>
    </div>
</section>

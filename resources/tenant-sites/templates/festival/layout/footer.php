<?php
/** Subsolul festivalului: numele uriaș, date, linkuri, rețele, legal + bara de cookies. */
$footer = (array) site('footer', []);
$ev = fest_event();
$socials = array_filter(['Instagram' => $footer['instagram'] ?? '', 'Facebook' => $footer['facebook'] ?? '', 'TikTok' => $footer['tiktok'] ?? '', 'YouTube' => $footer['youtube'] ?? '']);
?>
<footer class="fx-foot">
    <div class="fx-wrap">
        <div class="fx-foot__top">
            <div>
                <p class="fx-eyebrow"><?= e(fest_date_range($ev)) ?><?= fest_place($ev) !== '' ? ' · ' . e(fest_place($ev)) : '' ?></p>
                <?php if (!empty($footer['text'])): ?><p class="fx-foot__text"><?= ts_md($footer['text']) ?></p><?php endif; ?>
                <a href="/bilete" class="fx-btn" style="margin-top:24px"><?= te('Cumpără bilete') ?> <?= FEST_ARROW ?></a>
            </div>
            <div class="fx-foot__cols">
                <div>
                    <h4><?= te('Festival') ?></h4>
                    <a href="/lineup"><?= te('Lineup') ?></a><a href="/program"><?= te('Program') ?></a><a href="/bilete"><?= te('Bilete') ?></a><a href="/#info"><?= te('Info') ?></a>
                </div>
                <div>
                    <h4><?= te('Contul tău') ?></h4>
                    <a href="/cont/bilete"><?= te('Biletele mele') ?></a><a href="/cont/comenzi"><?= te('Comenzile mele') ?></a><a href="/cos"><?= te('Coșul meu') ?></a>
                </div>
                <div>
                    <h4><?= te('Contact') ?></h4>
                    <?php if (!empty($footer['email'])): ?><a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a><?php endif; ?>
                    <?php if (!empty($footer['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>"><?= e($footer['phone']) ?></a><?php endif; ?>
                    <?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
                </div>
            </div>
        </div>
        <p class="fx-foot__name" aria-hidden="true"><?= e(SITE_NAME) ?></p>
        <div class="fx-foot__legal">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <a href="/termeni"><?= te('Termeni și condiții') ?></a>
            <a href="/confidentialitate"><?= te('Confidențialitate') ?></a>
            <a href="/cookies"><?= te('Cookies') ?></a>
            <a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false"><?= te('Setări cookies') ?></a>
            <?= ts_lang_switch('fx-lang', '', 'is-on') ?>
            <span class="fx-foot__tix"><?= te('Ticketing') ?> <a href="https://tixello.ro" target="_blank" rel="noopener">tixello</a></span>
        </div>
    </div>
</footer>
<?= ts_cookie_bar() ?>
<?php if (!defined('TS_SHARED_PAGE')): ?>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="fx-toast" x-text="msg" role="status"></div>
<?php endif; ?>
</body>
</html>

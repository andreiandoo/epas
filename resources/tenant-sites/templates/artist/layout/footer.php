<?php
/** Subsolul: următorul concert, linkuri, rețele, numele uriaș, legal + cookies. */
$footer = (array) site('footer', []);
$next = null;
foreach (ar_upcoming() as $ev) {
    if (ar_status($ev)[0] !== 'sold_out') { $next = $ev; break; }
}
$socials = array_filter(['Instagram' => $footer['instagram'] ?? '', 'TikTok' => $footer['tiktok'] ?? '', 'YouTube' => $footer['youtube'] ?? '', 'Facebook' => $footer['facebook'] ?? '']);
?>
<footer class="ar-foot">
    <div class="ar-wrap">
        <div class="ar-foot__top">
            <div>
                <?php if ($next): ?>
                <p class="ar-eyebrow"><?= te('Următorul concert') ?></p>
                <p class="ar-foot__next"><?= e(ar_city($next)) ?></p>
                <p class="ar-foot__text"><?= e(ar_when($next)) ?> · <?= e(ar_venue($next)) ?></p>
                <a href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>#bilete" class="ar-btn ar-btn--light" style="margin-top:22px"><?= te('Cumpără bilete') ?> <?= AR_ARROW ?></a>
                <?php elseif (!empty($footer['text'])): ?>
                <p class="ar-foot__text"><?= ts_md($footer['text']) ?></p>
                <?php endif; ?>
            </div>
            <div class="ar-foot__cols">
                <div><h4><?= e(SITE_NAME) ?></h4><a href="/turneu"><?= te('Turneu') ?></a><a href="/#muzica"><?= te('Muzică') ?></a><a href="/#despre"><?= te('Despre') ?></a></div>
                <div><h4><?= te('Contul tău') ?></h4><a href="/cont/bilete"><?= te('Biletele mele') ?></a><a href="/cont/comenzi"><?= te('Comenzile mele') ?></a><a href="/cos"><?= te('Coșul meu') ?></a></div>
                <div><h4><?= te('Contact') ?></h4>
                    <?php if (!empty($footer['email'])): ?><a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a><?php endif; ?>
                    <?php if (!empty($footer['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>"><?= e($footer['phone']) ?></a><?php endif; ?>
                    <?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
                </div>
            </div>
        </div>
        <p class="ar-foot__name" aria-hidden="true"><?= e(SITE_NAME) ?></p>
        <div class="ar-foot__legal">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <a href="/termeni"><?= te('Termeni și condiții') ?></a>
            <a href="/confidentialitate"><?= te('Confidențialitate') ?></a>
            <a href="/cookies"><?= te('Cookies') ?></a>
            <a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false"><?= te('Setări cookies') ?></a>
            <?= ts_lang_switch('ar-lang', '', 'is-on') ?>
            <span class="ar-foot__tix"><?= te('Ticketing') ?> <a href="https://tixello.ro" target="_blank" rel="noopener">tixello</a></span>
        </div>
    </div>
</footer>
<?= ts_cookie_bar() ?>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="ar-toast" x-text="msg" role="status"></div>
</body>
</html>

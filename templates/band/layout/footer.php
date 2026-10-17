<?php
/** Subsolul: următorul concert, linkuri, rețele, numele uriaș, legal + cookies. */
$footer = (array) site('footer', []);
$next = null;
foreach (bd_upcoming() as $ev) {
    if (!in_array(bd_status($ev)[0], ['sold_out', 'external'], true)) { $next = $ev; break; }
}
$socials = array_filter(['Instagram' => $footer['instagram'] ?? '', 'TikTok' => $footer['tiktok'] ?? '', 'YouTube' => $footer['youtube'] ?? '', 'Facebook' => $footer['facebook'] ?? '']);
?>
<footer class="bd-foot">
    <div class="bd-wrap">
        <div class="bd-foot__top">
            <div>
                <?php if ($next): ?>
                <p class="bd-eyebrow"><?= te('Următorul concert') ?></p>
                <p class="bd-foot__next"><?= e(bd_city($next)) ?></p>
                <p class="bd-foot__text"><?= e(bd_when($next)) ?> · <?= e(bd_venue($next)) ?></p>
                <a href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>#bilete" class="bd-btn bd-btn--light" style="margin-top:22px"><?= te('Cumpără bilete') ?> <?= BD_ARROW ?></a>
                <?php elseif (!empty($footer['text'])): ?>
                <p class="bd-foot__text"><?= ts_md($footer['text']) ?></p>
                <?php endif; ?>
            </div>
            <div class="bd-foot__cols">
                <div><h4><?= e(SITE_NAME) ?></h4><a href="/turneu"><?= te('Turneu') ?></a><a href="/#echipaj"><?= te('Echipaj') ?></a><a href="/#discografie"><?= te('Discografie') ?></a><a href="/#newsletter"><?= te('Newsletter') ?></a></div>
                <div><h4><?= te('Contul tău') ?></h4><a href="/cont/bilete"><?= te('Biletele mele') ?></a><a href="/cont/comenzi"><?= te('Comenzile mele') ?></a><a href="/cos"><?= te('Coșul meu') ?></a></div>
                <div><h4><?= te('Contact') ?></h4>
                    <?php if (!empty($footer['email'])): ?><a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a><?php endif; ?>
                    <?php if (!empty($footer['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>"><?= e($footer['phone']) ?></a><?php endif; ?>
                    <?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
                </div>
            </div>
        </div>
        <p class="bd-foot__name" aria-hidden="true"><?= e(SITE_NAME) ?></p>
        <div class="bd-foot__legal">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <a href="/termeni"><?= te('Termeni și condiții') ?></a>
            <a href="/confidentialitate"><?= te('Confidențialitate') ?></a>
            <a href="/cookies"><?= te('Cookies') ?></a>
            <a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false"><?= te('Setări cookies') ?></a>
            <?= ts_lang_switch('bd-lang', '', 'is-on') ?>
            <span class="bd-foot__tix"><?= te('Ticketing') ?> <a href="https://tixello.ro" target="_blank" rel="noopener">tixello</a></span>
        </div>
    </div>
</footer>
<?= ts_cookie_bar() ?>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="bd-toast" x-text="msg" role="status"></div>
</body>
</html>

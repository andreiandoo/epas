<?php
/** Subsolul: următorul concert, linkuri, rețele, numele uriaș, legal + cookies. */
$footer = (array) site('footer', []);
$next = null;
foreach (ag_upcoming() as $ev) {
    if (!in_array(ag_status($ev)[0], ['sold_out', 'external'], true)) { $next = $ev; break; }
}
$socials = array_filter(['Instagram' => $footer['instagram'] ?? '', 'TikTok' => $footer['tiktok'] ?? '', 'YouTube' => $footer['youtube'] ?? '', 'Facebook' => $footer['facebook'] ?? '']);
?>
<footer class="ag-foot">
    <div class="ag-wrap">
        <div class="ag-foot__top">
            <div>
                <?php if ($next): ?>
                <p class="ag-eyebrow"><?= te('Următorul concert') ?></p>
                <p class="ag-foot__next"><?= e(ag_city($next)) ?></p>
                <p class="ag-foot__text"><?= e(ag_when($next)) ?> · <?= e(ag_venue($next)) ?></p>
                <a href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>#bilete" class="ag-btn ag-btn--light" style="margin-top:22px"><?= te('Cumpără bilete') ?> <?= AG_ARROW ?></a>
                <?php elseif (!empty($footer['text'])): ?>
                <p class="ag-foot__text"><?= ts_md($footer['text']) ?></p>
                <?php endif; ?>
            </div>
            <div class="ag-foot__cols">
                <div><h4><?= e(SITE_NAME) ?></h4><a href="/artisti"><?= te('Artiști') ?></a><a href="/evenimente"><?= te('Evenimente') ?></a><a href="/booking"><?= te('Booking') ?></a><a href="/#despre"><?= te('Despre') ?></a></div>
                <div><h4><?= te('Contul tău') ?></h4><a href="/cont/bilete"><?= te('Biletele mele') ?></a><a href="/cont/comenzi"><?= te('Comenzile mele') ?></a><a href="/cos"><?= te('Coșul meu') ?></a></div>
                <div><h4><?= te('Contact') ?></h4>
                    <?php if (!empty($footer['email'])): ?><a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a><?php endif; ?>
                    <?php if (!empty($footer['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>"><?= e($footer['phone']) ?></a><?php endif; ?>
                    <?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
                </div>
            </div>
        </div>
        <p class="ag-foot__name" aria-hidden="true"><?= e(SITE_NAME) ?></p>
        <div class="ag-foot__legal">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <a href="/termeni"><?= te('Termeni și condiții') ?></a>
            <a href="/confidentialitate"><?= te('Confidențialitate') ?></a>
            <a href="/cookies"><?= te('Cookies') ?></a>
            <a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false"><?= te('Setări cookies') ?></a>
            <?= ts_lang_switch('ag-lang', '', 'is-on') ?>
            <span class="ag-foot__tix"><?= te('Ticketing') ?> <a href="https://tixello.ro" target="_blank" rel="noopener">tixello</a></span>
        </div>
    </div>
</footer>
<?= ts_cookie_bar() ?>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="ag-toast" x-text="msg" role="status"></div>
</body>
</html>

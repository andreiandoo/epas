<?php
/** Subsolul: următoarea gală, linkuri, rețele, numele promoției uriaș, legal + cookies. */
$footer = (array) site('footer', []);
$next = gx_upcoming()[0] ?? null;
$socials = array_filter(['Instagram' => $footer['instagram'] ?? '', 'Facebook' => $footer['facebook'] ?? '', 'TikTok' => $footer['tiktok'] ?? '', 'YouTube' => $footer['youtube'] ?? '']);
?>
<footer class="gx-foot">
    <div class="gx-wrap">
        <div class="gx-foot__top">
            <div>
                <?php if ($next): ?>
                <p class="gx-eyebrow"><?= te('Următoarea gală') ?></p>
                <p class="gx-foot__next"><?= e($next['title']) ?></p>
                <p class="gx-foot__text"><?= e(gx_when($next)) ?> · <?= e(gx_place($next)) ?></p>
                <a href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>#bilete" class="gx-btn" style="margin-top:20px"><?= te('Cumpără bilete') ?> <?= GX_ARROW ?></a>
                <?php elseif (!empty($footer['text'])): ?>
                <p class="gx-foot__text"><?= ts_md($footer['text']) ?></p>
                <?php endif; ?>
            </div>
            <div class="gx-foot__cols">
                <div><h4><?= te('Promoția') ?></h4><a href="/gale"><?= te('Gale') ?></a><a href="/luptatori"><?= te('Luptători') ?></a><a href="/gale#rezultate"><?= te('Rezultate') ?></a></div>
                <div><h4><?= te('Contul tău') ?></h4><a href="/cont/bilete"><?= te('Biletele mele') ?></a><a href="/cont/comenzi"><?= te('Comenzile mele') ?></a><a href="/cos"><?= te('Coșul meu') ?></a></div>
                <div><h4><?= te('Contact') ?></h4>
                    <?php if (!empty($footer['email'])): ?><a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a><?php endif; ?>
                    <?php if (!empty($footer['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>"><?= e($footer['phone']) ?></a><?php endif; ?>
                    <?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
                </div>
            </div>
        </div>
        <p class="gx-foot__name" aria-hidden="true"><?= e(SITE_NAME) ?></p>
        <div class="gx-foot__legal">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <a href="/termeni"><?= te('Termeni și condiții') ?></a>
            <a href="/confidentialitate"><?= te('Confidențialitate') ?></a>
            <a href="/cookies"><?= te('Cookies') ?></a>
            <a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false"><?= te('Setări cookies') ?></a>
            <?= ts_lang_switch('gx-lang', '', 'is-on') ?>
            <span class="gx-foot__tix"><?= te('Ticketing') ?> <a href="https://tixello.ro" target="_blank" rel="noopener">tixello</a></span>
        </div>
    </div>
</footer>
<?= ts_cookie_bar() ?>
<?php if (!defined('TS_SHARED_PAGE')): ?>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="gx-toast" x-text="msg" role="status"></div>
<?php endif; ?>
</body>
</html>

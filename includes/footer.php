<?php
/** Footer comun + închiderea documentului. */
?>
<footer class="site-foot">
    <div class="wrap">
        <div class="site-foot__big" aria-hidden="true">Karate WUKF</div>
        <div class="site-foot__grid">
            <div>
                <div class="site-foot__brand">
                    <img src="/assets/logo-wukf.png" alt="" width="52" height="52" loading="lazy">
                    <span><?= e(SITE_NAME) ?></span>
                </div>
                <p style="max-width:44ch">Biletele pentru cupele și campionatele naționale se cumpără online și ajung pe email, cu cod QR pentru accesul în sală.</p>
            </div>
            <div>
                <h3>Bilete</h3>
                <ul>
                    <li><a href="/competitii">Calendarul competițiilor</a></li>
                    <li><a href="/cos">Coșul meu</a></li>
                    <li><a href="/biletele-mele">Biletele mele</a></li>
                </ul>
            </div>
            <div>
                <h3>Federație</h3>
                <ul>
                    <li><a href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">wukf.ro</a></li>
                    <li><a href="<?= e(SITE_FEDERATION) ?>/category/evenimente/nationale/" target="_blank" rel="noopener">Evenimente naționale</a></li>
                </ul>
            </div>
        </div>
        <div class="site-foot__base">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <span>Ticketing by <a href="https://tixello.ro" target="_blank" rel="noopener"><b>Tixello</b></a></span>
        </div>
    </div>
</footer>

<div class="toast" x-data="toastHost" x-show="show" x-cloak x-transition.opacity x-text="msg" role="status"></div>
<?= $pageFootScripts ?? '' ?>
</body>
</html>

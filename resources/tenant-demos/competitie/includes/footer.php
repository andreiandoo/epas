<?php
/** Footer comun + închiderea documentului. */
?>
<footer class="site-foot">
    <div class="site-foot__big" aria-hidden="true"><span>Karate WUKF · Federația Română</span></div>
    <div class="wrap">
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
                <h3>Informații</h3>
                <ul>
                    <li><a href="/termeni">Termeni și condiții</a></li>
                    <li><a href="/confidentialitate">Confidențialitate</a></li>
                    <li><a href="/cookies">Cookies</a></li>
                    <li><a href="https://anpc.ro/" target="_blank" rel="nofollow noopener">ANPC</a></li>
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
        <div class="site-foot__trust">
            <ul class="cards" aria-label="Carduri acceptate la plată">
                <li class="cards__label">Plată cu cardul</li>
                <li>VISA</li>
                <li>Mastercard</li>
                <li>Maestro</li>
            </ul>
            <div class="anpc">
                <a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener"><img src="/assets/anpc-sal.png" alt="ANPC: Soluționarea alternativă a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
                <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener"><img src="/assets/anpc-sol.png" alt="Soluționarea online a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
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

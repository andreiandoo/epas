<?php
/** Footer comun + închiderea documentului. */
?>
<footer class="site-foot">
    <div class="site-foot__big" aria-hidden="true"><span>Karate WUKF</span></div>
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
                <h3>Federație</h3>
                <ul>
                    <li><a href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">wukf.ro</a></li>
                    <li><a href="<?= e(SITE_FEDERATION) ?>/category/evenimente/nationale/" target="_blank" rel="noopener">Evenimente naționale</a></li>
                </ul>
            </div>
        </div>
        <div class="foot-pay">
            <div class="foot-pay__col">
                <span class="foot-pay__title">Plată securizată cu cardul</span>
                <ul class="paycards" aria-label="Carduri acceptate">
                    <li title="Visa">
                        <svg viewBox="0 0 64 40" role="img" aria-label="Visa"><rect width="64" height="40" rx="6" fill="#fff"/><text x="32" y="27" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="700" font-style="italic" fill="#1A1F71">VISA</text></svg>
                    </li>
                    <li title="Mastercard">
                        <svg viewBox="0 0 64 40" role="img" aria-label="Mastercard"><rect width="64" height="40" rx="6" fill="#fff"/><circle cx="26" cy="20" r="11" fill="#EB001B"/><circle cx="38" cy="20" r="11" fill="#F79E1B"/><path d="M32 10.8a11 11 0 010 18.4 11 11 0 010-18.4z" fill="#FF5F00"/></svg>
                    </li>
                    <li title="Maestro">
                        <svg viewBox="0 0 64 40" role="img" aria-label="Maestro"><rect width="64" height="40" rx="6" fill="#fff"/><circle cx="26" cy="20" r="11" fill="#EB001B"/><circle cx="38" cy="20" r="11" fill="#00A2E5"/><path d="M32 10.8a11 11 0 010 18.4 11 11 0 010-18.4z" fill="#7375CF"/></svg>
                    </li>
                </ul>
                <p class="foot-pay__note">Datele cardului se introduc direct în pagina procesatorului de plăți și nu ajung pe acest site.</p>
            </div>
            <div class="foot-pay__col foot-pay__col--end">
                <span class="foot-pay__title">Protecția consumatorului</span>
                <div class="anpc">
                    <a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener"><img src="/assets/anpc-sal.png" alt="ANPC: Soluționarea alternativă a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
                    <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener"><img src="/assets/anpc-sol.png" alt="Soluționarea online a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
                </div>
            </div>
        </div>
        <div class="foot-base">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <nav class="foot-base__links" aria-label="Informații legale">
                <a href="/termeni">Termeni și condiții</a>
                <a href="/confidentialitate">Confidențialitate</a>
                <a href="/cookies">Cookies</a>
                <a href="https://anpc.ro/" target="_blank" rel="nofollow noopener">ANPC</a>
            </nav>
            <a class="foot-base__by" href="https://tixello.ro" target="_blank" rel="noopener">Ticketing by <b>Tixello</b></a>
        </div>
    </div>
</footer>

<div class="toast" x-data="toastHost" x-show="show" x-cloak x-transition.opacity x-text="msg" role="status"></div>
<?= $pageFootScripts ?? '' ?>
</body>
</html>

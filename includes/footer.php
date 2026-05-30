<?php
/**
 * bilete.online — site footer
 *
 * Closes <main> (unless $skipMainTag was set in header.php), renders the
 * footer markup, loads page-bottom scripts, then closes </body></html>.
 *
 * Variables a page can set BEFORE include:
 *   $skipMainTag      — true if the page handles its own <main>
 *   $footerCategories — override default categories list (each ['label','href'])
 *   $footerCities     — override default cities list (each ['label','href'])
 *   $footerExtraJs    — array of extra JS files to load (relative paths)
 *   $extraBodyEnd     — raw HTML to inject just before </body>
 *   $hideCookieBanner — true to suppress the cookie consent module
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}

$skipMainTag = $skipMainTag ?? false;
$footerExtraJs = $footerExtraJs ?? [];
$extraBodyEnd = $extraBodyEnd ?? '';
$hideCookieBanner = $hideCookieBanner ?? false;

// Footer categories — pulls top-level EVENT categories with all=1 so the
// list shows up even on a marketplace that only has activities. Cached 10
// min; static fallback if API is unreachable.
if (! isset($footerCategories)) {
    if (! function_exists('api_cached')) {
        require_once __DIR__ . '/api.php';
    }
    $resp = api_cached('footer_event_top_categories', fn () => api_get('/events/categories', ['all' => 1, 'parents_only' => 1]), 600);
    $rows = $resp['data']['categories'] ?? [];
    $footerCategories = [];
    foreach ((is_array($rows) ? $rows : []) as $c) {
        $footerCategories[] = [
            'label' => $c['name'] ?? $c['slug'] ?? '',
            'href'  => '/' . ($c['slug'] ?? ''),
        ];
        if (count($footerCategories) >= 6) break;
    }
    if (empty($footerCategories)) {
        $footerCategories = [
            ['label' => 'Escape rooms',         'href' => '/escape-rooms'],
            ['label' => 'Muzee & expoziții',    'href' => '/muzee-expozitii'],
            ['label' => 'Parcuri de distracții','href' => '/parcuri-de-distractii'],
            ['label' => 'Parcuri de aventură',  'href' => '/parcuri-de-aventura'],
            ['label' => 'Acvarii & grădini zoo','href' => '/acvarii-zoo-animale'],
            ['label' => 'Ateliere & experiențe','href' => '/ateliere-experiente-creative'],
        ];
    }
}

$footerCities = $footerCities ?? [
    ['label' => 'București',    'href' => '/bucuresti'],
    ['label' => 'Cluj-Napoca',  'href' => '/cluj-napoca'],
    ['label' => 'Brașov',       'href' => '/brasov'],
    ['label' => 'Timișoara',    'href' => '/timisoara'],
    ['label' => 'Iași',         'href' => '/iasi'],
    ['label' => 'Constanța',    'href' => '/constanta'],
];

$currentYear = date('Y');
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '';
?>
<?php if (!$skipMainTag): ?>
</main>
<?php endif; ?>

<footer class="bg-ink text-paper/70" role="contentinfo" itemscope itemtype="https://schema.org/Organization">
    <meta itemprop="name" content="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?>">
    <meta itemprop="url" content="<?= htmlspecialchars(SITE_URL, ENT_QUOTES) ?>">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
        <div class="grid md:grid-cols-[1.4fr_1fr_1fr_1fr] gap-10">

            <!-- brand block -->
            <div>
                <a href="/" class="flex items-center gap-2.5 mb-5" aria-label="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?> — acasă">
                    <span class="grid place-items-center w-9 h-9 bg-vermilion text-paper rounded-md -rotate-3">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/>
                        </svg>
                    </span>
                    <span class="font-display text-xl font-700 text-paper">bilete<span class="text-vermilion">.</span>online</span>
                </a>
                <p class="text-sm leading-relaxed max-w-xs">
                    Bilete pentru experiențe de agrement din România. Operat tehnologic de
                    <a href="https://tixello.ro" class="text-ochre underline-wobble" rel="noopener" itemprop="parentOrganization">Tixello</a>.
                </p>

                <?php if ($supportEmail): ?>
                <p class="mt-5 text-xs font-mono tracking-wider text-paper/40">CONTACT</p>
                <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES) ?>" class="text-sm hover:text-paper transition" itemprop="email"><?= htmlspecialchars($supportEmail) ?></a>
                <?php endif; ?>

                <!-- social — placeholders, fill in once bilete.online accounts exist -->
                <div class="mt-5 flex items-center gap-3" aria-label="Rețele sociale">
                    <a href="#" aria-label="Facebook" class="grid place-items-center w-9 h-9 rounded-full bg-paper/5 hover:bg-vermilion hover:text-paper transition">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.5 9.9V15h-2.5v-3h2.5V9.5C10.5 7 12 5.7 14.2 5.7c1 0 2.1.2 2.1.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 3h-2.3v6.9A10 10 0 0 0 22 12Z"/></svg>
                    </a>
                    <a href="#" aria-label="Instagram" class="grid place-items-center w-9 h-9 rounded-full bg-paper/5 hover:bg-vermilion hover:text-paper transition">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/></svg>
                    </a>
                    <a href="#" aria-label="TikTok" class="grid place-items-center w-9 h-9 rounded-full bg-paper/5 hover:bg-vermilion hover:text-paper transition">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor" aria-hidden="true"><path d="M16 3v2.6a4.4 4.4 0 0 0 4.4 4.4V12a6.4 6.4 0 0 1-4.4-1.7v6.2a5.5 5.5 0 1 1-5.5-5.5h.6v2.5a3 3 0 1 0 2.4 2.9V3H16Z"/></svg>
                    </a>
                </div>
            </div>

            <!-- categories -->
            <nav aria-labelledby="footer-cat">
                <h3 id="footer-cat" class="font-mono text-xs tracking-wider text-paper/40 mb-4">CATEGORII</h3>
                <ul class="space-y-2.5 text-sm">
                    <?php foreach ($footerCategories as $cat): ?>
                    <li><a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" class="hover:text-paper transition"><?= htmlspecialchars($cat['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- cities -->
            <nav aria-labelledby="footer-cities">
                <h3 id="footer-cities" class="font-mono text-xs tracking-wider text-paper/40 mb-4">ORAȘE</h3>
                <ul class="space-y-2.5 text-sm">
                    <?php foreach ($footerCities as $city): ?>
                    <li><a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" class="hover:text-paper transition"><?= htmlspecialchars($city['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- platform -->
            <nav aria-labelledby="footer-platform">
                <h3 id="footer-platform" class="font-mono text-xs tracking-wider text-paper/40 mb-4">PLATFORMĂ</h3>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/pentru-locatii" class="hover:text-paper transition">Listează-ți locația</a></li>
                    <li><a href="/card-cadou" class="hover:text-paper transition">Carduri cadou</a></li>
                    <li><a href="/cum-functioneaza" class="hover:text-paper transition">Cum funcționează</a></li>
                    <li><a href="/cont" class="hover:text-paper transition">Contul meu</a></li>
                    <li><a href="/ajutor" class="hover:text-paper transition">Ajutor &amp; suport</a></li>
                    <li><a href="/termeni" class="hover:text-paper transition">Termeni &amp; condiții</a></li>
                    <li><a href="/confidentialitate" class="hover:text-paper transition">Confidențialitate</a></li>
                    <li><a href="/cookies" class="hover:text-paper transition">Cookies</a></li>
                </ul>
            </nav>
        </div>

        <div class="mt-14 pt-7 border-t border-paper/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-paper/40 font-mono">
            <span>&copy; <?= $currentYear ?> <?= htmlspecialchars(SITE_NAME) ?> · toate drepturile rezervate</span>
            <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-vermilion"></span>
                ADMISSION · ROMÂNIA
            </span>
        </div>
    </div>
</footer>

<!-- ===================== CORE JS (api, auth, cart, utils, notifications) =====================
     These define the BileteOnline* globals every page relies on
     (BileteOnlineAPI, BileteOnlineAuth, BileteOnlineCart, BileteOnlineUtils,
     BileteOnlineNotifications). Load order matters: config → utils → api →
     auth → cart → notifications → tracking. All `defer`, so they execute
     after HTML parsing in document order, before page-specific scripts. -->
<script defer src="<?= asset('assets/js/config.js') ?>"></script>
<script defer src="<?= asset('assets/js/utils.js') ?>"></script>
<script defer src="<?= asset('assets/js/api.js') ?>"></script>
<script defer src="<?= asset('assets/js/auth.js') ?>"></script>
<script defer src="<?= asset('assets/js/cart.js') ?>"></script>
<script defer src="<?= asset('assets/js/components/notifications.js') ?>"></script>
<script defer src="<?= asset('assets/js/tracking.js') ?>"></script>

<!-- ===================== PAGE-BOTTOM SCRIPTS ===================== -->
<script defer src="<?= asset('assets/js/components/scroll-reveal.js') ?>"></script>
<?php foreach ($footerExtraJs as $jsPath): ?>
<script defer src="<?= asset(ltrim($jsPath, '/')) ?>"></script>
<?php endforeach; ?>

<?php if (! $hideCookieBanner): ?>
    <?php include __DIR__ . '/cookie-consent.php'; ?>
<?php endif; ?>

<?php echo $extraBodyEnd; ?>
</body>
</html>

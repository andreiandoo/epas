<?php
/**
 * Navigația template-ului teatru. Meniul, butonul și identitatea vin din core.
 * $activeNav (opțional) = calea paginii curente, pentru evidențiere.
 */
$activeNav = $activeNav ?? '';
if (!str_starts_with($activeNav, '/')) {
    // paginile portate din demo setează chei („repertoire”); evidențiem după adresă
    $activeNav = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
}
$navItems  = (array) site('nav', []);
$headerCta = (array) site('header_cta', []);
$logoUrl   = site('branding.logo');
if (!function_exists('nav_cls')) {
    function nav_cls(string $url, string $active): string {
        $on = $url !== '/' && $active !== '' && ($active === $url || str_starts_with($active, rtrim($url, '/') . '/'));
        return $on ? 'text-primary font-medium' : 'text-fg/80 hover:text-primary transition-colors';
    }
}
?>
<body class="antialiased">
    <?php if (SITE_PREVIEW): ?>
    <div class="fixed bottom-4 left-4 z-[200] rounded-full bg-amber-400 px-4 py-2 text-xs font-bold text-black shadow-xl">
        Previzualizare ciornă · <a href="/?preview=0" class="underline">ieși</a>
    </div>
    <?php endif; ?>
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 px-4 lg:px-8 py-4 bg-canvas/95 backdrop-blur-md border-b border-primary/10" x-data="{open:false}">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <a href="/" class="flex items-center gap-3 min-w-0">
                <?php if ($logoUrl): ?>
                    <img src="<?= e($logoUrl) ?>" alt="<?= e(SITE_NAME) ?>" class="h-12 w-auto max-w-[180px] object-contain">
                <?php else: ?>
                    <div class="w-12 h-12 shrink-0 rounded-full border-2 border-primary flex items-center justify-center">
                        <span class="font-display text-primary text-xl"><?= e(SITE_LOGO_TEXT) ?></span>
                    </div>
                <?php endif; ?>
                <div class="hidden sm:block min-w-0">
                    <p class="font-display text-lg leading-tight truncate"><?= e(SITE_NAME) ?></p>
                    <?php if (SITE_CITY !== ''): ?><p class="text-xs text-primary tracking-widest uppercase truncate"><?= e(SITE_CITY) ?></p><?php endif; ?>
                </div>
            </a>
            <div class="hidden lg:flex items-center gap-8">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= e(ts_href($item['url'] ?? '')) ?>" class="<?= nav_cls(ts_href($item['url'] ?? ''), $activeNav) ?>"><?= e($item['label'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center gap-4">
                <a href="/cos" class="text-fg/80 hover:text-primary transition-colors" title="Coșul meu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </a>
                <div x-data="headerAuth()" x-init="init()" class="hidden sm:block">
                    <a x-show="!user" href="/autentificare" class="text-fg/80 hover:text-primary transition-colors">Contul meu</a>
                    <a x-show="user" x-cloak href="/cont" class="inline-flex items-center gap-2 rounded-full border border-primary/20 py-1.5 pl-1.5 pr-3 hover:border-primary/50 transition-colors" title="Contul meu">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-accent text-xs font-bold text-fg" x-text="initials"></span>
                        <span class="text-xs" x-text="firstName"></span>
                    </a>
                </div>
                <?php if (!empty($headerCta['label']) && !empty($headerCta['url'])): ?>
                    <a href="<?= e(ts_href($headerCta['url'])) ?>" class="btn-primary px-5 py-2.5 rounded text-sm hidden sm:inline-block"><?= e($headerCta['label']) ?></a>
                <?php endif; ?>
                <button type="button" class="lg:hidden text-fg/80" @click="open=!open" aria-label="Meniu">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.5" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <div x-cloak x-show="open" x-transition.opacity class="lg:hidden max-w-7xl mx-auto mt-4 grid gap-1 border-t border-primary/10 pt-4">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= e(ts_href($item['url'] ?? '')) ?>" class="rounded px-2 py-2 <?= nav_cls(ts_href($item['url'] ?? ''), $activeNav) ?>"><?= e($item['label'] ?? '') ?></a>
            <?php endforeach; ?>
            <a href="/cont" class="rounded px-2 py-2 text-fg/80">Contul meu</a>
            <?php if (!empty($headerCta['label']) && !empty($headerCta['url'])): ?>
                <a href="<?= e(ts_href($headerCta['url'])) ?>" class="btn-primary mt-2 rounded px-5 py-3 text-center text-sm"><?= e($headerCta['label']) ?></a>
            <?php endif; ?>
        </div>
    </nav>
    <script>
    function headerAuth(){return{
        user:null, firstName:'', initials:'',
        init(){
            try{
                var a=JSON.parse(localStorage.getItem('site_auth')||'null');
                if(a&&a.token&&a.user){
                    var u=a.user;
                    this.user=u;
                    var fn=(u.first_name||u.firstName||(u.name||'').trim().split(/\s+/)[0]||'').trim();
                    this.firstName=fn||'Contul meu';
                    var full=(u.name||((u.first_name||'')+' '+(u.last_name||''))).trim()||u.email||'';
                    this.initials=(full.split(/\s+/).map(function(p){return p[0];}).slice(0,2).join('')||'?').toUpperCase();
                }
            }catch(e){}
        }
    };}
    </script>

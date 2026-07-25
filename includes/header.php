<?php
/**
 * Unified navigation for the teatru skin.
 * Set $activeNav before including to highlight the current item:
 *   'repertoire' | 'schedule' | 'subscriptions' | 'troupe' | 'about' | 'contact'
 */
$activeNav = $activeNav ?? '';
$siteName  = defined('SITE_NAME') ? SITE_NAME : 'Teatrul Național';
$siteCity  = defined('SITE_CITY') ? SITE_CITY : 'BUCUREȘTI';
$logoText  = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TN';
function nav_cls($key, $active) {
    return $key === $active ? 'text-gold font-medium' : 'text-ivory/80 hover:text-gold transition-colors';
}
?>
<body class="antialiased">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 px-4 lg:px-8 py-4 bg-midnight/95 backdrop-blur-md border-b border-gold/10">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full border-2 border-gold flex items-center justify-center">
                    <span class="font-display text-gold text-xl"><?= htmlspecialchars($logoText) ?></span>
                </div>
                <div class="hidden sm:block">
                    <p class="font-display text-lg leading-tight"><?= htmlspecialchars($siteName) ?></p>
                    <p class="text-xs text-gold tracking-widest"><?= htmlspecialchars($siteCity) ?></p>
                </div>
            </a>
            <div class="hidden lg:flex items-center gap-8">
                <a href="/repertoriu" class="<?= nav_cls('repertoire', $activeNav) ?>">Repertoriu</a>
                <a href="/program" class="<?= nav_cls('schedule', $activeNav) ?>">Program</a>
                <a href="/abonamente" class="<?= nav_cls('subscriptions', $activeNav) ?>">Abonamente</a>
                <a href="/trupa" class="<?= nav_cls('troupe', $activeNav) ?>">Trupa</a>
                <a href="/despre" class="<?= nav_cls('about', $activeNav) ?>">Despre noi</a>
                <a href="/contact" class="<?= nav_cls('contact', $activeNav) ?>">Contact</a>
            </div>
            <div class="flex items-center gap-4">
                <a href="/cos" class="text-ivory/80 hover:text-gold transition-colors" title="Coșul meu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </a>
                <div x-data="headerAuth()" x-init="init()" class="hidden sm:block" style="[x-cloak]">
                    <a x-show="!user" href="/autentificare" class="text-ivory/80 hover:text-gold transition-colors">Contul meu</a>
                    <a x-show="user" x-cloak href="/cont/profil" class="inline-flex items-center gap-2 rounded-full border border-gold/20 py-1.5 pl-1.5 pr-3 hover:border-gold/50 transition-colors" title="Contul meu">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-burgundy text-xs font-bold text-ivory" x-text="initials">CD</span>
                        <span class="text-xs" x-text="firstName">Client</span>
                    </a>
                </div>
                <a href="/program" class="btn-gold px-5 py-2.5 rounded text-sm">Cumpără bilete</a>
            </div>
        </div>
    </nav>
    <style>[x-cloak]{display:none!important}</style>
    <script>
    function headerAuth(){return{
        user:null, firstName:'', initials:'',
        init(){
            try{
                var a=JSON.parse(localStorage.getItem('teatru_auth')||'null');
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

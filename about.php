<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Despre noi — ' . SITE_NAME;
$activeNav = 'about';
$pageExtraStyles = '
    .timeline-item { position: relative; padding-left: 2rem; border-left: 1px solid rgba(212,175,55,.2); }
    .timeline-item::before { content: ""; position: absolute; left: -6px; top: 4px; width: 11px; height: 11px; border-radius: 9999px; background: #D4AF37; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<!-- Hero -->
<section class="relative min-h-[60vh] flex items-center justify-center pt-20">
    <div class="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1503095396549-807759245b35?w=1920&q=80" alt="" class="w-full h-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-t from-midnight via-midnight/70 to-midnight/40"></div>
    </div>
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
        <p class="text-gold tracking-[0.3em] text-sm mb-6 uppercase">Tradiție</p>
        <h1 class="font-display text-5xl sm:text-7xl mb-6"><span class="italic">Despre</span> <?= e(SITE_NAME) ?></h1>
        <p class="text-xl text-ivory/70 max-w-2xl mx-auto">Peste un secol de tradiție, inovație și excelență în arta teatrală românească.</p>
    </div>
</section>

<!-- Mission -->
<section class="py-24 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
        <div>
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Misiunea noastră</p>
            <h2 class="font-display text-4xl lg:text-5xl mb-6">Teatrul ca <span class="italic">oglindă</span> a societății</h2>
            <p class="text-ivory/70 text-lg leading-relaxed mb-6">De la înființare și până astăzi, instituția noastră a fost nu doar un așezământ cultural, ci o tribună a conștiinței, un spațiu al dialogului și al reflecției asupra condiției umane.</p>
            <p class="text-ivory/70 leading-relaxed mb-6">Misiunea noastră este să aducem pe scenă spectacole care provoacă, emoționează și inspiră — îmbinând tradiția cu inovația, clasicul cu contemporanul.</p>
            <p class="text-ivory/70 leading-relaxed">Credem că teatrul are puterea de a transforma individul și comunitatea deopotrivă, și ne dedicăm acestui ideal în fiecare stagiune.</p>
        </div>
        <div class="relative">
            <div class="absolute -inset-4 bg-gradient-to-r from-burgundy/20 to-gold/20 blur-3xl opacity-30"></div>
            <img src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?w=800&q=80" alt="" class="relative rounded-xl border border-gold/20">
        </div>
    </div>
</section>

<!-- Stats -->
<section class="py-16 px-4 lg:px-8 bg-charcoal/30">
    <div class="max-w-7xl mx-auto grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
        <div><p class="font-display text-5xl lg:text-7xl text-gold mb-2">100+</p><p class="text-warm-gray">ani de tradiție</p></div>
        <div><p class="font-display text-5xl lg:text-7xl text-gold mb-2">900</p><p class="text-warm-gray">Locuri Sala Mare</p></div>
        <div><p class="font-display text-5xl lg:text-7xl text-gold mb-2">50+</p><p class="text-warm-gray">Spectacole în repertoriu</p></div>
        <div><p class="font-display text-5xl lg:text-7xl text-gold mb-2">300K</p><p class="text-warm-gray">Spectatori anual</p></div>
    </div>
</section>

<!-- Timeline -->
<section class="py-24 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-16">
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Repere istorice</p>
            <h2 class="font-display text-4xl">O istorie de legendă</h2>
        </div>
        <div class="space-y-8">
            <div class="timeline-item pb-8"><p class="text-gold font-display text-2xl mb-2">Fondare</p><h3 class="font-display text-xl mb-2">Înființarea instituției</h3><p class="text-ivory/70">Teatrul este înființat ca scenă de referință, devenind un reper al vieții culturale.</p></div>
            <div class="timeline-item pb-8"><p class="text-gold font-display text-2xl mb-2">Sediu propriu</p><h3 class="font-display text-xl mb-2">Primul sediu permanent</h3><p class="text-ivory/70">După ani de reprezentații în diverse locații, teatrul își inaugurează sediul permanent.</p></div>
            <div class="timeline-item pb-8"><p class="text-gold font-display text-2xl mb-2">Modernizare</p><h3 class="font-display text-xl mb-2">Sala Mare modernă</h3><p class="text-ivory/70">Sala Mare, cu o capacitate de 900 de locuri, este dotată cu tehnologie de scenă de ultimă generație.</p></div>
            <div class="timeline-item"><p class="text-gold font-display text-2xl mb-2">Azi</p><h3 class="font-display text-xl mb-2">Stagiunea curentă</h3><p class="text-ivory/70">Teatrul continuă să inoveze cu producții care îmbină tradiția cu experimentul, atrăgând noi generații de spectatori.</p></div>
        </div>
    </div>
</section>

<!-- Venues -->
<section class="py-24 px-4 lg:px-8 bg-gradient-to-b from-burgundy/10 to-transparent">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Spațiile noastre</p>
            <h2 class="font-display text-4xl">Sălile de spectacol</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-8">
            <div class="bg-charcoal rounded-xl overflow-hidden">
                <img src="https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=800&q=80" alt="Sala Mare" class="w-full h-64 object-cover">
                <div class="p-8">
                    <h3 class="font-display text-2xl mb-4">Sala Mare</h3>
                    <p class="text-ivory/70 mb-6">Spațiul principal de reprezentație, cu locuri distribuite în staluri, loji și balcon. Dotată cu tehnologie de ultimă generație.</p>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div><p class="font-display text-2xl text-gold">900</p><p class="text-sm text-warm-gray">Locuri</p></div>
                        <div><p class="font-display text-2xl text-gold">18m</p><p class="text-sm text-warm-gray">Deschidere scenă</p></div>
                        <div><p class="font-display text-2xl text-gold">12m</p><p class="text-sm text-warm-gray">Adâncime</p></div>
                    </div>
                </div>
            </div>
            <div class="bg-charcoal rounded-xl overflow-hidden">
                <img src="https://images.unsplash.com/photo-1503095396549-807759245b35?w=800&q=80" alt="Sala Studio" class="w-full h-64 object-cover">
                <div class="p-8">
                    <h3 class="font-display text-2xl mb-4">Sala Studio</h3>
                    <p class="text-ivory/70 mb-6">Spațiu intim dedicat producțiilor experimentale și spectacolelor de cameră, cu configurație flexibilă.</p>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div><p class="font-display text-2xl text-gold">150</p><p class="text-sm text-warm-gray">Locuri</p></div>
                        <div><p class="font-display text-2xl text-gold">Flexibil</p><p class="text-sm text-warm-gray">Configurație</p></div>
                        <div><p class="font-display text-2xl text-gold">Black box</p><p class="text-sm text-warm-gray">Tip sală</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-20 px-4 lg:px-8 bg-burgundy/20">
    <div class="max-w-3xl mx-auto text-center">
        <div class="divider-ornate mb-6"><span class="text-gold text-2xl">✦</span></div>
        <h2 class="font-display text-3xl lg:text-4xl mb-4">Vizitează-ne</h2>
        <p class="text-ivory/70 mb-8">Te așteptăm să descoperi magia teatrului. Rezervă-ți locul la unul dintre spectacolele noastre.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/program" class="btn-gold px-8 py-4 rounded-lg">Vezi programul</a>
            <a href="/contact" class="btn-outline px-8 py-4 rounded-lg">Contactează-ne</a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';

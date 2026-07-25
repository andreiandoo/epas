<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Abonamente — ' . SITE_NAME;
$activeNav = 'subscriptions';
$pageExtraStyles = '
    .subscription-card { background: linear-gradient(180deg,#1A1A1A 0%,#0A0A0A 100%); border: 1px solid rgba(212,175,55,.12); transition: all .4s ease; }
    .subscription-card:hover { border-color: rgba(212,175,55,.35); transform: translateY(-6px); }
    .subscription-card.featured { border-color: #D4AF37; background: linear-gradient(180deg, rgba(212,175,55,.08), transparent); }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

$check = '<svg class="w-5 h-5 text-gold flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';

// Planuri reale din admin (tenant). Fallback la exemple dacă nu există încă.
$plans = [];
foreach (tc_subscriptions() as $s) {
    $plans[] = [
        'name'     => $s['name'] ?? '',
        'code'     => $s['slug'] ?? '',
        'price'    => (int) round((float) ($s['price'] ?? 0)),
        'currency' => $s['currency'] ?? 'RON',
        'sub'      => $s['subtitle'] ?? '',
        'featured' => (bool) ($s['is_featured'] ?? false),
        'features' => array_values(array_filter($s['benefits'] ?? [])),
    ];
}
if (empty($plans)) {
    $plans = [
        ['name'=>'Clasic','code'=>'clasic','price'=>350,'currency'=>'RON','sub'=>'5 spectacole • Staluri','featured'=>false,'features'=>['5 spectacole la alegere','Locuri în Staluri','Economie ~15%','Valabil toată stagiunea']],
        ['name'=>'Premium','code'=>'premium','price'=>600,'currency'=>'RON','sub'=>'10 spectacole • Balcon','featured'=>true,'features'=>['10 spectacole la alegere','Locuri premium în Balcon','Economie ~30%','Acces prioritar la premiere','Program de fidelitate']],
        ['name'=>'Familie','code'=>'familie','price'=>1200,'currency'=>'RON','sub'=>'4 persoane • 5 spectacole','featured'=>false,'features'=>['4 bilete per spectacol','5 spectacole la alegere','Locuri alăturate garantate','Economie ~25%','Ateliere pentru copii']],
    ];
}
?>
<section class="pt-32 pb-16 px-4 lg:px-8 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-burgundy/10 via-transparent to-transparent"></div>
    <div class="max-w-7xl mx-auto relative z-10 text-center">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Stagiunea curentă</p>
        <h1 class="font-display text-5xl lg:text-7xl mb-6">Abonamente</h1>
        <p class="text-ivory/70 text-xl max-w-2xl mx-auto">Descoperiți întreaga stagiune cu un abonament. Economisiți până la 30% și bucurați-vă de beneficii exclusive.</p>
    </div>
</section>

<section class="py-16 px-4 lg:px-8" x-data="subsPage()">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($plans as $p): ?>
        <div class="subscription-card <?= $p['featured'] ? 'featured' : '' ?> rounded-2xl p-8 relative">
            <?php if ($p['featured']): ?><div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-gold text-midnight text-xs font-bold px-4 py-1 rounded-full">RECOMANDAT</div><?php endif; ?>
            <div class="mb-6"><p class="text-gold text-sm tracking-wider mb-2">ABONAMENT</p><h3 class="font-display text-3xl"><?= e($p['name']) ?></h3></div>
            <div class="mb-6"><p class="font-display text-5xl text-gold"><?= (int) $p['price'] ?> <span class="text-xl"><?= e($p['currency'] ?? 'RON') ?></span></p><?php if (!empty($p['sub'])): ?><p class="text-warm-gray text-sm"><?= e($p['sub']) ?></p><?php endif; ?></div>
            <ul class="space-y-3 mb-8">
                <?php foreach ($p['features'] as $f): ?><li class="flex items-center gap-3"><?= $check ?><span class="text-ivory/80"><?= e($f) ?></span></li><?php endforeach; ?>
            </ul>
            <button @click="choosePlan('<?= e($p['code']) ?>')" class="<?= $p['featured'] ? 'btn-gold' : 'btn-outline' ?> w-full py-3 rounded-lg block text-center">Alege abonamentul</button>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="text-center text-warm-gray text-sm mt-12 max-w-2xl mx-auto">Abonamentele necesită un cont. Reduceri sociale (elevi, studenți, pensionari) și pentru grupuri organizate — contactați casieria.</p>
</section>
<script>
function subsPage() {
    return {
        isLoggedIn() { try { return !!(JSON.parse(localStorage.getItem('teatru_auth') || 'null')?.token); } catch(e) { return false; } },
        choosePlan(slug) {
            const dest = '/abonament?plan=' + encodeURIComponent(slug);
            if (this.isLoggedIn()) { window.location.href = dest; }
            else { window.location.href = '/autentificare?next=' + encodeURIComponent(dest); }
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';

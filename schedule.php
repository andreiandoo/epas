<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$resp   = api_get('/tenant-client/events', ['per_page' => 100]);
$events = tc_events($resp);

// Sortare cronologică + grupare pe lună
usort($events, fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));
$months = [];
$monthNames = [1=>'Ianuarie',2=>'Februarie',3=>'Martie',4=>'Aprilie',5=>'Mai',6=>'Iunie',7=>'Iulie',8=>'August',9=>'Septembrie',10=>'Octombrie',11=>'Noiembrie',12=>'Decembrie'];
foreach ($events as $e) {
    $ts = !empty($e['start_date']) ? strtotime($e['start_date']) : null;
    $key = $ts ? date('Y-m', $ts) : 'other';
    $label = $ts ? ($monthNames[(int)date('n',$ts)] . ' ' . date('Y',$ts)) : 'Programare';
    $months[$key]['label'] = $label;
    $months[$key]['events'][] = $e;
}

$pageTitle = 'Program — ' . SITE_NAME;
$activeNav = 'schedule';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-12 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Bilete</p>
        <h1 class="font-display text-5xl lg:text-6xl mb-6">Program</h1>
        <p class="text-ivory/70 text-lg max-w-2xl">Calendarul reprezentațiilor. Alegeți data și rezervați-vă locul.</p>
    </div>
</section>

<section class="pb-24 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <?php if (empty($events)): ?>
            <p class="text-center text-warm-gray py-16">Momentan nu sunt reprezentații programate.</p>
        <?php else: foreach ($months as $m): ?>
        <div class="mb-12">
            <h2 class="font-display text-2xl text-gold mb-6"><?= e($m['label']) ?></h2>
            <div class="space-y-3">
                <?php foreach ($m['events'] as $e):
                    $ts = !empty($e['start_date']) ? strtotime($e['start_date']) : null; ?>
                <a href="/spectacol/<?= e($e['slug'] ?? '') ?>" class="flex items-center gap-6 bg-charcoal/50 hover:bg-charcoal rounded-xl p-5 transition-colors group">
                    <div class="text-center min-w-[64px]">
                        <p class="font-display text-3xl text-gold"><?= $ts ? date('d', $ts) : '—' ?></p>
                        <p class="text-xs text-warm-gray uppercase"><?= $ts ? substr($monthNames[(int)date('n',$ts)] ?? '',0,3) : '' ?></p>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-display text-xl group-hover:text-gold transition-colors"><?= e($e['title'] ?? '') ?></h3>
                        <p class="text-sm text-warm-gray">
                            <?php if ($ts && !empty($e['start_time'])): ?><?= substr($e['start_time'],0,5) ?> · <?php endif; ?>
                            <?= e($e['venue']['name'] ?? '') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <?php if (!empty($e['is_sold_out'])): ?>
                            <span class="text-burgundy-light text-sm font-semibold">Sold out</span>
                        <?php else: ?>
                            <span class="btn-gold px-5 py-2.5 rounded text-sm inline-block">Bilete</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</section>
<?php
include __DIR__ . '/includes/footer.php';

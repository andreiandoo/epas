<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$status  = $_GET['status'] ?? '';

$summary = $orderId ? tc_order_summary($orderId) : null;
$ok = $summary && ($summary['is_paid'] ?? false);

$pageTitle = ($ok ? 'Comandă confirmată' : 'Comandă anulată') . ' — ' . SITE_NAME;
$pageExtraStyles = '
    @keyframes ep-pop { 0% { transform: scale(.6); opacity: 0; } 60% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }
    @keyframes ep-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
    .ep-pop { animation: ep-pop .5s cubic-bezier(.2,.8,.2,1) both; }
    .ep-rise { animation: ep-rise .5s ease both; }
    .ep-d1 { animation-delay: .1s; } .ep-d2 { animation-delay: .22s; } .ep-d3 { animation-delay: .34s; }
    .ep-ticket { background: repeating-linear-gradient(90deg, transparent, transparent 10px, rgba(212,175,55,.04) 10px, rgba(212,175,55,.04) 11px); }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<?php if ($ok): $ev = $summary['event'] ?? null; ?>
<!-- Step indicator -->
<div class="pt-28 pb-6 px-4">
    <div class="max-w-3xl mx-auto flex items-center justify-center gap-3 text-sm">
        <?php foreach (['Coș', 'Finalizare', 'Confirmare'] as $i => $label): ?>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-gold text-midnight flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
            <span class="hidden sm:inline text-gold"><?= $label ?></span>
        </div>
        <?php if ($i < 2): ?><div class="w-10 h-px bg-gold/40"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<main class="pb-20 px-4">
    <div class="max-w-3xl mx-auto">
        <!-- Success hero -->
        <div class="text-center mb-10">
            <div class="ep-pop w-20 h-20 rounded-full bg-gold/10 border border-gold/30 flex items-center justify-center mx-auto mb-5">
                <svg class="w-10 h-10 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1 class="font-display text-4xl lg:text-5xl mb-2">Plata a fost procesată!</h1>
            <p class="text-ivory/60">Comanda <span class="text-gold font-mono">#<?= e((string) $summary['order_id']) ?></span> a fost confirmată.</p>
        </div>

        <!-- Email confirmation -->
        <div class="ep-rise ep-d1 flex items-center gap-4 p-4 mb-6 bg-charcoal rounded-xl border border-gold/10">
            <div class="w-12 h-12 rounded-lg bg-gold/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1">
                <p class="font-medium">Biletele au fost trimise pe email</p>
                <p class="text-warm-gray text-sm"><?= e($summary['customer_email'] ?? '') ?></p>
            </div>
            <svg class="w-6 h-6 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>

        <!-- Order details -->
        <div class="ep-rise ep-d2 bg-charcoal rounded-2xl border border-gold/10 overflow-hidden mb-6">
            <div class="flex items-center justify-between p-5 border-b border-gold/10">
                <h2 class="font-display text-xl">Detalii comandă</h2>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gold/15 text-gold">Confirmată</span>
            </div>
            <div class="p-5">
                <?php if ($ev): ?>
                <!-- Event -->
                <div class="flex gap-4 mb-6">
                    <?php if (!empty($ev['poster'])): ?>
                    <img src="<?= e($ev['poster']) ?>" alt="" class="w-20 h-24 object-cover rounded-lg border border-gold/10">
                    <?php endif; ?>
                    <div>
                        <?php if (!empty($ev['slug'])): ?>
                        <a href="/spectacol/<?= e($ev['slug']) ?>" class="font-display text-lg hover:text-gold transition-colors"><?= e($ev['title'] ?? '') ?></a>
                        <?php else: ?>
                        <p class="font-display text-lg"><?= e($ev['title'] ?? '') ?></p>
                        <?php endif; ?>
                        <?php
                            $when = '';
                            if (!empty($ev['date'])) { $ts = strtotime($ev['date']); if ($ts) { $when = date('d.m.Y', $ts); } }
                            if (!empty($ev['time'])) { $when .= ($when ? ' · ' : '') . substr($ev['time'], 0, 5); }
                        ?>
                        <?php if ($when): ?><p class="text-warm-gray text-sm mt-1"><?= e($when) ?></p><?php endif; ?>
                        <?php if (!empty($ev['venue'])): ?><p class="text-warm-gray text-sm"><?= e($ev['venue']) ?><?= !empty($ev['city']) ? ', ' . e($ev['city']) : '' ?></p><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tickets -->
                <?php if (!empty($summary['tickets'])): ?>
                <div class="ep-ticket bg-midnight/60 rounded-xl p-4 mb-6">
                    <h4 class="text-sm text-gold tracking-wider uppercase mb-3">Bilete achiziționate (<?= count($summary['tickets']) ?>)</h4>
                    <div class="space-y-2">
                        <?php foreach ($summary['tickets'] as $t): ?>
                        <div class="flex items-center justify-between gap-3 text-sm border-b border-gold/5 last:border-0 pb-2 last:pb-0">
                            <div>
                                <span class="font-medium"><?= e($t['seat_label'] ?? ($t['type'] ?? 'Bilet')) ?></span>
                                <?php if (!empty($t['seat_label']) && !empty($t['type'])): ?><span class="text-warm-gray"> · <?= e($t['type']) ?></span><?php endif; ?>
                            </div>
                            <span class="font-mono text-xs text-warm-gray"><?= e($t['code'] ?? '') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment -->
                <div class="grid sm:grid-cols-2 gap-5 pt-5 border-t border-gold/10">
                    <div>
                        <h4 class="text-sm text-warm-gray mb-2">Metodă de plată</h4>
                        <div class="flex items-center gap-3 bg-midnight/60 rounded-lg p-3">
                            <div class="w-10 h-7 rounded bg-gradient-to-r from-amber-600 to-amber-800 flex items-center justify-center text-[8px] font-bold text-white">DEMO</div>
                            <p class="text-sm"><?= e($summary['payment_method'] ?? 'Card') ?></p>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm text-warm-gray mb-2">Sumar plată</h4>
                        <div class="flex justify-between items-center bg-midnight/60 rounded-lg p-3">
                            <span class="text-warm-gray text-sm">Total achitat</span>
                            <span class="font-display text-2xl text-gold"><?= e(number_format((float) ($summary['total'] ?? 0), 0, ',', '.')) ?> <?= e($summary['currency'] ?? 'RON') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="ep-rise ep-d3 grid sm:grid-cols-2 gap-4">
            <a href="/cont" class="flex items-center justify-center gap-3 p-4 bg-charcoal rounded-xl border border-gold/10 hover:border-gold/40 transition-colors">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Vezi biletele în cont</span>
            </a>
            <a href="/repertoriu" class="flex items-center justify-center gap-3 p-4 bg-charcoal rounded-xl border border-gold/10 hover:border-gold/40 transition-colors">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5l7 7-7 7M3 12h18"/></svg>
                <span>Vezi alte spectacole</span>
            </a>
        </div>
    </div>
</main>
<script>try { localStorage.removeItem('teatru_cart'); } catch(e) {}</script>

<?php else: ?>
<section class="pt-40 pb-32 px-4 text-center">
    <div class="max-w-lg mx-auto">
        <div class="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <h1 class="font-display text-4xl mb-3">Plată neefectuată</h1>
        <p class="text-ivory/70 mb-8">Comanda nu a fost finalizată. Poți încerca din nou oricând.</p>
        <a href="/repertoriu" class="btn-gold px-8 py-4 rounded-lg inline-block">Vezi spectacolele</a>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php';

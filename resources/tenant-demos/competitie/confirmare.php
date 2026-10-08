<?php
require_once __DIR__ . '/includes/boot.php';

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$summary = $orderId ? tc_order_summary($orderId) : null;
$ok      = $summary && !empty($summary['is_paid']);
$oev     = $summary['event'] ?? null;
$tickets = $summary['tickets'] ?? [];

// Sumarul comenzii nu are data la competițiile pe mai multe zile: o luăm din eveniment
$when = '';
if (!empty($oev['slug']) && ($full = tc_event($oev['slug']))) {
    $when = ev_date_label($full);
} elseif (!empty($oev['date']) && ($day = ev_day($oev['date']))) {
    [$y, $m, $d] = array_map('intval', explode('-', $day));
    $when = $d . ' ' . RO_MONTHS[$m] . ' ' . $y;
}
$where = implode(', ', array_filter([$oev['venue'] ?? '', $oev['city'] ?? '']));

$pageTitle = ($ok ? 'Comandă confirmată' : 'Comandă neconfirmată') . ' — ' . SITE_SHORT;
$pageExtraHead = '<meta name="robots" content="noindex"><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>';
include __DIR__ . '/includes/head.php';
?>
<main class="wrap">
<?php if ($ok): ?>
    <div class="done">
        <span class="eyebrow">Comanda #<?= e((string) $summary['order_id']) ?> · plătită</span>
        <h1>Ne vedem în tribună!</h1>
        <p>Am trimis <?= count($tickets) === 1 ? 'biletul' : 'cele ' . count($tickets) . ' bilete' ?> la <b><?= e($summary['customer_email'] ?? '') ?></b>. Le găsești și mai jos: arată codul QR la intrare, de pe telefon sau tipărit.</p>
        <?= part_flow(4) ?>
    </div>

    <section style="padding:20px 0 28px">
        <div class="tickets">
            <?php foreach ($tickets as $t): ?>
            <div class="ticket">
                <div class="ticket__main">
                    <div class="ticket__type"><?= e($t['type'] ?? 'Bilet') ?></div>
                    <div class="ticket__title"><?= e($oev['title'] ?? '') ?></div>
                    <div class="ticket__meta"><?= e(implode(' · ', array_filter([$when, $where]))) ?></div>
                    <div class="ticket__code"><?= e($t['code'] ?? '') ?></div>
                </div>
                <div class="ticket__qr"><div data-qr="<?= e($t['code'] ?? '') ?>"></div></div>
                <div class="belt"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section style="padding:0 0 88px">
        <div class="panel" style="max-width:520px">
            <div class="sum" style="padding-top:10px">
                <div><span>Număr comandă</span><span>#<?= e((string) $summary['order_id']) ?></span></div>
                <div><span>Bilete</span><span><?= count($tickets) ?></span></div>
                <div><span>Metodă de plată</span><span><?= e($summary['payment_method'] ?? 'Card') ?></span></div>
                <div class="sum__total"><span>Total plătit</span><b><?= e(lei($summary['total'] ?? 0)) ?></b></div>
            </div>
            <div class="panel__body" style="display:flex;flex-wrap:wrap;gap:12px">
                <button type="button" class="btn" onclick="window.print()">Tipărește biletele</button>
                <a class="btn btn--ghost" href="/competitii">Alte competiții</a>
            </div>
        </div>
    </section>

    <script>
        try { localStorage.removeItem('wukf_cart'); } catch (e) {}
        window.addEventListener('load', function () {
            if (!window.QRCode) { return; }
            document.querySelectorAll('[data-qr]').forEach(function (el) {
                new QRCode(el, { text: el.getAttribute('data-qr'), width: 184, height: 184, correctLevel: QRCode.CorrectLevel.M });
            });
        });
    </script>
<?php else: ?>
    <div class="panel empty" style="margin:56px 0 96px">
        <h2><?= $summary ? 'Plata nu a fost confirmată' : 'Comanda nu a fost găsită' ?></h2>
        <p><?= $summary ? 'Nu s-a încasat nimic. Biletele sunt încă în coș și poți relua plata.' : 'Verifică linkul primit pe email sau reia comanda din calendar.' ?></p>
        <a class="btn btn--red" href="<?= $summary ? '/finalizare' : '/competitii' ?>"><?= $summary ? 'Reia plata' : 'Vezi competițiile' ?></a>
    </div>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

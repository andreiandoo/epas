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
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex"><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light">
<?php if ($ok): ?>
    <section class="done">
        <div class="wrap">
            <div class="done__check" data-enter="0"><?= ICON_CHECK ?></div>
            <span class="label">Comanda #<?= e((string) $summary['order_id']) ?> · plătită</span>
            <h1 data-split data-split-now>Ne vedem în tribună</h1>
            <p data-enter="0.4">Am trimis <?= count($tickets) === 1 ? 'biletul' : 'cele ' . count($tickets) . ' bilete' ?> la <b><?= e($summary['customer_email'] ?? '') ?></b>. Le găsești și mai jos: arată codul QR la intrare, de pe telefon sau tipărit.</p>
            <div data-enter="0.5"><?= part_flow(4) ?></div>
        </div>
    </section>

    <div class="wrap">
        <section style="padding:44px 0 28px">
            <div class="tickets" data-reveal-group>
                <?php foreach ($tickets as $t): ?>
                <div class="ticket">
                    <div class="ticket__main">
                        <div class="ticket__type"><?= e($t['type'] ?? 'Bilet') ?></div>
                        <div class="ticket__title"><?= e($oev['title'] ?? '') ?></div>
                        <div class="ticket__meta"><?= e(implode(' · ', array_filter([$when, $where]))) ?></div>
                        <div class="ticket__code"><?= e($t['code'] ?? '') ?></div>
                        <a class="link ticket__dl no-print" data-dl="<?= e($t['code'] ?? '') ?>" href="#" hidden>Descarcă biletul</a>
                    </div>
                    <div class="ticket__qr"><div data-qr="<?= e($t['code'] ?? '') ?>"></div></div>
                    <div class="belt-bg"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section style="padding:0 0 96px">
            <div class="panel no-print" style="max-width:540px">
                <div class="sum" style="padding-top:12px">
                    <div><span>Număr comandă</span><span class="mono">#<?= e((string) $summary['order_id']) ?></span></div>
                    <div><span>Bilete</span><span><?= count($tickets) ?></span></div>
                    <div><span>Metodă de plată</span><span><?= e($summary['payment_method'] ?? 'Card') ?></span></div>
                    <div class="sum__total"><span>Total plătit</span><b><?= e(lei($summary['total'] ?? 0)) ?></b></div>
                </div>
                <div class="panel__body" style="display:flex;flex-wrap:wrap;gap:12px">
                    <a class="btn" data-dl="" href="#" hidden><?= count($tickets) === 1 ? 'Descarcă biletul (PDF)' : 'Descarcă biletele (PDF)' ?></a>
                    <button type="button" class="btn btn--ghost" onclick="window.print()">Tipărește</button>
                    <a class="btn btn--ghost" href="/competitii">Alte competiții</a>
                    <p class="fine" data-dl-missing hidden style="flex-basis:100%;margin:0">Descărcarea în PDF e disponibilă din browserul în care ai plasat comanda sau din <a class="link" href="/biletele-mele">Biletele mele</a>, după autentificare.</p>
                </div>
            </div>
        </section>
    </div>

    <script>
        try { localStorage.removeItem('wukf_cart'); } catch (e) {}
        // Butoanele de descărcare: tokenul comenzii e ținut în browserul din care s-a cumpărat
        (function () {
            var orderId = <?= (int) $summary['order_id'] ?>, token = window.WUKF && WUKF.Orders.token(orderId);
            if (!token) { var note = document.querySelector('[data-dl-missing]'); if (note) { note.hidden = false; } return; }
            document.querySelectorAll('[data-dl]').forEach(function (a) {
                a.href = WUKF.pdfUrl(orderId, token, a.getAttribute('data-dl') || '');
                a.hidden = false;
            });
        })();
        window.addEventListener('load', function () {
            if (!window.QRCode) { return; }
            document.querySelectorAll('[data-qr]').forEach(function (el) {
                new QRCode(el, { text: el.getAttribute('data-qr'), width: 208, height: 208, correctLevel: QRCode.CorrectLevel.M });
            });
        });
    </script>
<?php else: ?>
    <section class="phead phead--slim">
        <div class="wrap">
            <span class="label label--red"><?= $summary ? 'Plată neconfirmată' : 'Comandă negăsită' ?></span>
            <h1><?= $summary ? 'Plata nu a fost confirmată' : 'Comanda nu a fost găsită' ?></h1>
            <p><?= $summary ? 'Nu s-a încasat nimic. Dacă biletele mai sunt în coș, poți relua plata.' : 'Verifică linkul primit pe email sau reia comanda din calendar.' ?></p>
            <p style="margin-top:28px"><?= part_btn($summary ? 'Reia plata' : 'Vezi competițiile', $summary ? '/finalizare' : '/competitii') ?></p>
        </div>
    </section>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

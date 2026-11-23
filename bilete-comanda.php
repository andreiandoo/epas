<?php
/**
 * /bilete-comanda?order=MKT-XXXX
 *
 * Landing page for the "Descarca biletele" button in the order email.
 * Mail apps and their in-app browsers often block file downloads and
 * remote images, so this page renders every ticket with its QR code on
 * the server (no JS needed) and offers the PDF in several ways.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$orderRef = trim((string) ($_GET['order'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{4,64}$/', $orderRef)) {
    $orderRef = '';
}
// Access: the order key from the email link (k), or the email the order was
// placed with (form below, for links without a key). An order number alone
// shows nothing.
$orderKey = preg_match('/^[a-f0-9]{16,64}$/', (string) ($_GET['k'] ?? '')) ? (string) $_GET['k'] : '';
$postedEmail = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? trim((string) ($_POST['email'] ?? '')) : '';

$order = null;
if ($orderRef !== '' && ($orderKey !== '' || $postedEmail !== '')) {
    $res = api_get('/orders/' . rawurlencode($orderRef), array_filter(['key' => $orderKey, 'email' => $postedEmail], 'strlen'));
    $found = $res['success'] ? ($res['data']['order'] ?? null) : null;
    if ($found && !empty($found['access_verified'])) {
        $order = $found;
        $orderKey = (string) ($found['download_key'] ?? $orderKey);
        if ($postedEmail !== '' && $orderKey !== '') {
            // Verified by email: continue on the keyed URL so a reload keeps working
            header('Location: /bilete-comanda?order=' . rawurlencode($orderRef) . '&k=' . rawurlencode($orderKey), true, 303);
            exit;
        }
    }
}
$accessFailed = !$order && $postedEmail !== '';
$canDownload = $order && !empty($order['can_download_tickets']);

$tickets = [];
if ($canDownload) {
    foreach (($order['tickets'] ?? []) as $t) {
        $code = (string) ($t['code'] ?? $t['barcode'] ?? '');
        if ($code === '') continue;
        $tickets[] = $t + ['_code' => $code];
    }
}

// QR codes are fetched here and inlined as data URIs, so they show up even
// where the browser can't reach the QR service. Fallback: a plain image URL.
function bc_qr_url(string $code): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '300x300', 'data' => $code, 'color' => '000000',
        'bgcolor' => 'ffffff', 'qzone' => '2', 'margin' => '0', 'format' => 'png',
    ]);
}
$qrImages = [];
if ($tickets) {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($tickets as $i => $t) {
        $ch = curl_init(bc_qr_url($t['_code']));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1);
    } while ($running && $status === CURLM_OK);
    foreach ($handles as $i => $ch) {
        $body = curl_multi_getcontent($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && is_string($body) && str_starts_with($body, "\x89PNG");
        $qrImages[$i] = $ok ? 'data:image/png;base64,' . base64_encode($body) : bc_qr_url($tickets[$i]['_code']);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
}

$pdfBase = '/api/proxy.php?action=order.download-tickets-pdf&order=' . rawurlencode($orderRef) . '&k=' . rawurlencode($orderKey);
$pageUrl = SITE_URL . '/bilete-comanda?order=' . rawurlencode($orderRef) . '&k=' . rawurlencode($orderKey);
$event = $order['event'] ?? null;
$eventDate = '';
if (!empty($event['date'])) {
    $ts = strtotime($event['date']);
    if ($ts) $eventDate = date('d.m.Y', $ts) . (!empty($event['time']) ? ' · ' . $event['time'] : '');
}

$pageTitle = 'Biletele tale';
$pageDescription = 'Biletele comenzii tale AmBilet.';
$noIndex = true;
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<style>
    .bc-wrap { max-width: 720px; margin: 0 auto; padding: 96px 16px 48px; color: #1E293B; }
    .bc-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 16px; padding: 20px; margin-bottom: 16px; }
    .bc-h1 { font-size: 28px; line-height: 1.2; font-weight: 800; margin: 0 0 6px; }
    .bc-sub { color: #64748B; font-size: 15px; margin: 0; }
    .bc-actions { display: grid; gap: 10px; margin-top: 18px; }
    .bc-btn { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 12px 18px; border-radius: 12px; font-weight: 700; font-size: 16px; text-decoration: none; border: 1px solid transparent; cursor: pointer; width: 100%; }
    .bc-btn-primary { background: #A51C30; color: #fff; }
    .bc-btn-primary:hover { background: #8B1728; }
    .bc-btn-ghost { background: #fff; color: #1E293B; border-color: #E2E8F0; }
    .bc-btn-ghost:hover { background: #F8FAFC; }
    .bc-help { margin-top: 14px; font-size: 14px; color: #64748B; line-height: 1.55; }
    .bc-help strong { color: #1E293B; }
    .bc-ticket { display: flex; gap: 18px; align-items: center; }
    .bc-qr { flex: 0 0 auto; width: 168px; height: 168px; background: #fff; border-radius: 8px; }
    .bc-qr img { width: 168px; height: 168px; display: block; image-rendering: pixelated; }
    .bc-meta { min-width: 0; font-size: 14px; color: #64748B; line-height: 1.6; }
    .bc-meta .bc-type { font-size: 17px; font-weight: 700; color: #1E293B; }
    .bc-meta .bc-code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 15px; font-weight: 700; color: #1E293B; letter-spacing: .04em; }
    .bc-count { font-size: 13px; color: #64748B; margin: 22px 0 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .bc-note { font-size: 13px; color: #64748B; text-align: center; margin-top: 18px; }
    @media (max-width: 480px) {
        .bc-wrap { padding-top: 88px; }
        .bc-ticket { flex-direction: column; text-align: center; }
        .bc-qr, .bc-qr img { width: 220px; height: 220px; }
    }
    @media print {
        header, footer, .bc-actions, .bc-help, .bc-note, .no-print { display: none !important; }
        .bc-wrap { padding-top: 0; }
        .bc-card { break-inside: avoid; }
    }
</style>

<main class="bc-wrap">
<?php if (!$order && $orderRef !== ''): ?>
    <div class="bc-card">
        <h1 class="bc-h1">Biletele comenzii #<?= $h($orderRef) ?></h1>
        <p class="bc-sub">Ca să-ți protejăm biletele, confirmă adresa de email cu care ai plasat comanda.</p>
        <form method="post" action="/bilete-comanda?order=<?= $h(rawurlencode($orderRef)) ?>" style="margin-top:18px;" novalidate>
            <label for="bc-email" style="display:block;font-size:14px;font-weight:600;margin-bottom:6px;">Adresa de email a comenzii</label>
            <input id="bc-email" name="email" type="email" inputmode="email" autocomplete="email" required
                   value="<?= $h($postedEmail) ?>"
                   <?= $accessFailed ? 'aria-invalid="true" aria-describedby="bc-email-err"' : '' ?>
                   style="width:100%;min-height:48px;padding:12px 14px;font-size:16px;border:1px solid <?= $accessFailed ? '#EF4444' : '#E2E8F0' ?>;border-radius:12px;outline-color:#A51C30;">
            <?php if ($accessFailed): ?>
            <p id="bc-email-err" role="alert" style="margin:8px 0 0;font-size:14px;color:#B91C1C;">Adresa nu se potrivește cu comanda. Folosește emailul pe care ai primit confirmarea.</p>
            <?php endif; ?>
            <button type="submit" class="bc-btn bc-btn-primary" style="margin-top:14px;">Vezi biletele</button>
        </form>
        <p class="bc-help">Poți vedea biletele și în contul tău, la <a href="/cont/bilete" style="color:#A51C30;font-weight:700;">Biletele mele</a>. Pentru ajutor, scrie-ne la <a href="mailto:contact@ambilet.ro" style="color:#A51C30;font-weight:700;">contact@ambilet.ro</a>.</p>
    </div>
<?php elseif (!$order): ?>
    <div class="bc-card">
        <h1 class="bc-h1">Nu am găsit comanda</h1>
        <p class="bc-sub">Verifică linkul din email. Dacă problema continuă, intră în contul tău la <a href="/cont/bilete" style="color:#A51C30;font-weight:700;">Biletele mele</a> sau scrie-ne la <a href="mailto:contact@ambilet.ro" style="color:#A51C30;font-weight:700;">contact@ambilet.ro</a>.</p>
    </div>
<?php elseif (!$canDownload): ?>
    <div class="bc-card">
        <h1 class="bc-h1">Comanda #<?= $h($order['order_number'] ?? $orderRef) ?></h1>
        <p class="bc-sub">Biletele vor fi disponibile aici imediat ce plata este confirmată. Reîncarcă pagina peste câteva minute.</p>
    </div>
<?php else: ?>
    <div class="bc-card">
        <h1 class="bc-h1">Biletele tale</h1>
        <p class="bc-sub">
            Comanda #<?= $h($order['order_number'] ?? $orderRef) ?> · <?= count($tickets) ?> <?= count($tickets) === 1 ? 'bilet' : 'bilete' ?>
            <?php if (!empty($event['name'])): ?><br><strong style="color:#1E293B;"><?= $h($event['name']) ?></strong><?php endif; ?>
            <?php if ($eventDate || !empty($event['venue'])): ?><br><?= $h(implode(' · ', array_filter([$eventDate, $event['venue'] ?? '', $event['city'] ?? '']))) ?><?php endif; ?>
        </p>

        <div class="bc-actions">
            <a class="bc-btn bc-btn-primary" href="<?= $h($pdfBase . '&dl=1') ?>" download>
                <?= amb_icon('download', 'w-5 h-5') ?> Descarcă biletele (PDF)
            </a>
            <a class="bc-btn bc-btn-ghost" href="<?= $h($pdfBase . '&inline=1') ?>" target="_blank" rel="noopener">
                <?= amb_icon('file-text', 'w-5 h-5') ?> Deschide PDF-ul
            </a>
            <button type="button" class="bc-btn bc-btn-ghost" data-copy="<?= $h($pageUrl) ?>" hidden>
                <?= amb_icon('copy', 'w-5 h-5') ?> <span>Copiază linkul paginii</span>
            </button>
        </div>

        <p class="bc-help">
            <strong>Nu pornește descărcarea?</strong> Aplicațiile de email blochează uneori fișierele.
            Poți intra cu biletele de mai jos direct de pe telefon (codurile QR sunt valabile la intrare),
            poți face o captură de ecran, sau deschide această pagină în Chrome ori Safari
            (meniul aplicației → „Deschide în browser”).
        </p>
    </div>

    <p class="bc-count">Codurile QR pentru intrare</p>
    <?php foreach ($tickets as $i => $t):
        $seat = $t['seat'] ?? null;
        $seatParts = [];
        if (is_array($seat)) {
            if (!empty($seat['section_name'])) $seatParts[] = $seat['section_name'];
            if (!empty($seat['row_label'])) $seatParts[] = 'Rând ' . $seat['row_label'];
            if (!empty($seat['seat_number'])) $seatParts[] = 'Loc ' . $seat['seat_number'];
            if (!$seatParts && !empty($seat['label'])) $seatParts[] = $seat['label'];
        }
    ?>
    <div class="bc-card">
        <div class="bc-ticket">
            <div class="bc-qr"><img src="<?= $h($qrImages[$i]) ?>" alt="Cod QR bilet <?= $h($t['_code']) ?>" width="168" height="168"></div>
            <div class="bc-meta">
                <div class="bc-type"><?= $h($t['type'] ?? 'Bilet') ?></div>
                <?php if (!empty($t['attendee_name'])): ?><div>Beneficiar: <strong style="color:#1E293B;"><?= $h($t['attendee_name']) ?></strong></div><?php endif; ?>
                <?php if ($seatParts): ?><div>Loc: <strong style="color:#1E293B;"><?= $h(implode(' / ', $seatParts)) ?></strong></div><?php endif; ?>
                <?php if (!empty($t['ticket_series'])): ?><div>Serie: <?= $h($t['ticket_series']) ?></div><?php endif; ?>
                <div class="bc-code"><?= $h($t['_code']) ?></div>
                <div>Biletul <?= $i + 1 ?> din <?= count($tickets) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="bc-note">Biletele sunt și în contul tău AmBilet, la <a href="/cont/bilete" style="color:#A51C30;font-weight:700;">Biletele mele</a>.</p>
<?php endif; ?>
</main>

<script>
(function () {
    var b = document.querySelector('[data-copy]');
    if (!b || !navigator.clipboard) return;
    b.hidden = false;
    b.addEventListener('click', function () {
        navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function () {
            b.querySelector('span').textContent = 'Link copiat';
            setTimeout(function () { b.querySelector('span').textContent = 'Copiază linkul paginii'; }, 2500);
        });
    });
})();
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>

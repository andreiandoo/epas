<?php
/**
 * Payment redirect — initiates payment for a pending order.
 * Used by whitelabel sites: they create the order, then redirect here.
 *
 * URL: /plata/{order_number}?return_url=https://site-organizator.ro/multumim
 *
 * Flow:
 * 1. Look up order by order_number to get numeric ID
 * 2. Call /orders/{id}/pay to get payment URL + form data
 * 3. For Netopia: auto-submit POST form
 * 4. For Stripe: GET redirect
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$orderNumber = $_GET['order'] ?? '';
$returnUrl = $_GET['return_url'] ?? SITE_URL . '/multumim?order=' . urlencode($orderNumber);
$cancelUrl = $_GET['cancel_url'] ?? SITE_URL;

if (!$orderNumber) {
    header('Location: ' . SITE_URL);
    exit;
}

// Leisure POS „Via email": /plata/{order_number}?t={token}. Clientul vine din
// emailul cu linkul de plată și nu are cont — comanda se identifică prin
// număr + token, iar mesajele sunt în limba comenzii (ro / hu / en).
$linkToken = isset($_GET['t']) ? (string) $_GET['t'] : '';
if ($linkToken !== '') {
    $linkUrl = SITE_URL . '/plata/' . rawurlencode($orderNumber) . '?t=' . rawurlencode($linkToken);
    // back=1 = întoarcerea de la procesator: afișăm starea, nu pornim altă plată.
    $isReturn = !empty($_GET['back']);

    $linkResult = api_post('/orders/pay-by-link', [
        'order_number' => $orderNumber,
        'token' => $linkToken,
        'initiate' => !$isReturn,
        'return_url' => $linkUrl . '&back=1',
        'cancel_url' => $linkUrl . '&back=1',
    ]);
    $linkData = $linkResult['data'] ?? [];
    $linkState = $linkData['state'] ?? 'error';
    $linkLang = in_array($linkData['locale'] ?? '', ['ro', 'hu', 'en'], true) ? $linkData['locale'] : 'ro';

    $linkTexts = [
        'ro' => [
            'redirect' => ['Vă redirecționăm către plată…', 'Veți fi dus imediat pe pagina securizată a procesatorului de plăți.'],
            'paid' => ['Plata a fost confirmată', 'Vă mulțumim! Biletele au fost trimise pe adresa dumneavoastră de email.'],
            'pending' => ['Plata se procesează', 'Dacă plata a fost efectuată, veți primi biletele pe email în câteva minute. Dacă plata nu a fost finalizată, o puteți relua.'],
            'declined' => ['Plata nu a reușit', 'Plata nu a fost acceptată. Puteți încerca din nou, cu același card sau cu altul.'],
            'failed' => ['Plata nu a reușit', 'Comanda nu a putut fi plătită. Vă rugăm să contactați locația pentru un nou link de plată.'],
            'expired' => ['Linkul de plată a expirat', 'Comanda nu mai este disponibilă. Vă rugăm să contactați locația pentru o comandă nouă.'],
            'invalid' => ['Link invalid', 'Acest link de plată nu este valid.'],
            'error' => ['Plata nu a putut fi inițiată', 'Vă rugăm să încercați din nou în câteva minute.'],
            'retry' => 'Reluați plata',
            'order' => 'Comandă',
        ],
        'hu' => [
            'redirect' => ['Átirányítjuk a fizetéshez…', 'Azonnal a fizetési szolgáltató biztonságos oldalára kerül.'],
            'paid' => ['A fizetés megtörtént', 'Köszönjük! A jegyeket elküldtük az Ön e-mail-címére.'],
            'pending' => ['A fizetés feldolgozás alatt', 'Ha a fizetés sikeres volt, néhány percen belül e-mailben megkapja jegyeit. Ha a fizetés nem fejeződött be, újrapróbálhatja.'],
            'declined' => ['A fizetés nem sikerült', 'A fizetést nem fogadták el. Újrapróbálhatja ugyanazzal vagy egy másik kártyával.'],
            'failed' => ['A fizetés nem sikerült', 'A rendelést nem sikerült kifizetni. Kérjük, vegye fel a kapcsolatot a helyszínnel egy új fizetési linkért.'],
            'expired' => ['A fizetési link lejárt', 'A rendelés már nem érhető el. Kérjük, vegye fel a kapcsolatot a helyszínnel egy új rendelésért.'],
            'invalid' => ['Érvénytelen link', 'Ez a fizetési link nem érvényes.'],
            'error' => ['A fizetést nem sikerült elindítani', 'Kérjük, próbálja újra néhány perc múlva.'],
            'retry' => 'Fizetés újra',
            'order' => 'Rendelés',
        ],
        'en' => [
            'redirect' => ['Redirecting you to the payment page…', 'You will be taken to the payment processor\'s secure page in a moment.'],
            'paid' => ['Payment confirmed', 'Thank you! Your tickets have been sent to your email address.'],
            'pending' => ['Your payment is being processed', 'If your payment went through, you will receive your tickets by email within a few minutes. If it was not completed, you can try again.'],
            'declined' => ['The payment was not successful', 'The payment was not accepted. You can try again, with the same card or a different one.'],
            'failed' => ['The payment was not successful', 'The order could not be paid. Please contact the venue for a new payment link.'],
            'expired' => ['This payment link has expired', 'The order is no longer available. Please contact the venue for a new order.'],
            'invalid' => ['Invalid link', 'This payment link is not valid.'],
            'error' => ['The payment could not be started', 'Please try again in a few minutes.'],
            'retry' => 'Try again',
            'order' => 'Order',
        ],
    ][$linkLang];

    $hasPayment = $linkState === 'ready' && !empty($linkData['payment_url']);
    $isPostForm = $hasPayment && !empty($linkData['form_data']);

    // Stripe/alt procesator — redirect GET direct.
    if ($hasPayment && !$isPostForm) {
        header('Location: ' . $linkData['payment_url']);
        exit;
    }

    $stateKey = $hasPayment ? 'redirect' : (isset($linkTexts[$linkState]) && is_array($linkTexts[$linkState]) ? $linkState : 'error');
    [$linkTitle, $linkBody] = $linkTexts[$stateKey];
    $showRetry = in_array($stateKey, ['pending', 'declined', 'error'], true);
    $accent = $stateKey === 'paid' ? '#1F4E37' : (in_array($stateKey, ['redirect', 'pending'], true) ? '#1f2937' : '#b91c1c');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    ?>
    <!DOCTYPE html><html lang="<?= $linkLang ?>"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($linkTitle) ?></title></head>
    <body style="margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f4f6;color:#1f2937;">
        <div style="max-width:460px;margin:0 auto;padding:64px 20px;">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:32px 28px;text-align:center;">
                <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:<?= $accent ?>;"><?= htmlspecialchars($linkTitle) ?></h1>
                <p style="margin:0;font-size:15px;line-height:1.6;color:#4b5563;"><?= htmlspecialchars($linkBody) ?></p>
                <?php if ($stateKey !== 'invalid'): ?>
                <p style="margin:18px 0 0;font-size:13px;color:#9ca3af;"><?= htmlspecialchars($linkTexts['order']) ?>: <span style="font-family:monospace;"><?= htmlspecialchars($orderNumber) ?></span></p>
                <?php endif; ?>
                <?php if ($showRetry): ?>
                <a href="<?= htmlspecialchars($linkUrl) ?>" style="display:inline-block;margin-top:24px;background:#1F4E37;color:#fff;font-size:15px;font-weight:700;padding:13px 28px;border-radius:10px;text-decoration:none;"><?= htmlspecialchars($linkTexts['retry']) ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isPostForm): ?>
        <form id="pf" method="POST" action="<?= htmlspecialchars($linkData['payment_url']) ?>">
            <?php foreach ($linkData['form_data'] as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
        </form>
        <script>document.getElementById('pf').submit();</script>
        <?php endif; ?>
    </body></html>
    <?php
    exit;
}

// Step 1: Get order ID — prefer direct order_id param, fall back to API lookup
$orderId = !empty($_GET['order_id']) ? (int) $_GET['order_id'] : null;

if (!$orderId) {
    // Legacy: try to look up order by number via API
    $orderLookup = api_get('/customer/orders/' . urlencode($orderNumber));
    $orderId = $orderLookup['data']['id'] ?? $orderLookup['data']['order']['id'] ?? null;

    if (!$orderId && !empty($orderLookup['data'])) {
        if (is_numeric($orderLookup['data']['id'] ?? null)) {
            $orderId = $orderLookup['data']['id'];
        }
    }
}

if (!$orderId) {
    // Can't find order — show error with redirect
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Eroare</title></head>
    <body style="font-family:system-ui;text-align:center;padding:60px 20px;background:#080808;color:#f0ede6;">
        <h2>Comanda nu a fost găsită</h2>
        <p style="color:rgba(240,237,230,0.45);margin:12px 0 24px;">Comandă: <?= htmlspecialchars($orderNumber) ?></p>
        <a href="<?= htmlspecialchars($cancelUrl) ?>" style="color:#D4A843;">Înapoi →</a>
    </body></html>
    <?php
    exit;
}

// Step 2: Initiate payment
$payResult = api_post('/orders/' . $orderId . '/pay', [
    'return_url' => $returnUrl,
    'cancel_url' => $cancelUrl,
]);

$payData = $payResult['data'] ?? [];

// Step 3: Netopia — auto-submit POST form
if (!empty($payData['form_data']) && !empty($payData['payment_url'])) {
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecționare plată...</title></head>
    <body style="font-family:system-ui;text-align:center;padding:60px 20px;background:#080808;color:#f0ede6;">
        <p>Se redirecționează către procesatorul de plăți...</p>
        <form id="pf" method="POST" action="<?= htmlspecialchars($payData['payment_url']) ?>">
            <?php foreach ($payData['form_data'] as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
        </form>
        <script>document.getElementById('pf').submit();</script>
    </body></html>
    <?php
    exit;
}

// Step 4: Stripe/other — GET redirect
if (!empty($payData['payment_url'])) {
    header('Location: ' . $payData['payment_url']);
    exit;
}

// Fallback — payment initiation failed
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Eroare plată</title></head>
<body style="font-family:system-ui;text-align:center;padding:60px 20px;background:#080808;color:#f0ede6;">
    <h2>Nu s-a putut iniția plata</h2>
    <p style="color:rgba(240,237,230,0.45);margin:12px 0;">Comandă: <?= htmlspecialchars($orderNumber) ?></p>
    <p style="color:rgba(240,237,230,0.45);font-size:13px;"><?= htmlspecialchars($payResult['error'] ?? 'Eroare necunoscută') ?></p>
    <a href="<?= htmlspecialchars($cancelUrl) ?>" style="color:#D4A843;display:inline-block;margin-top:24px;">Înapoi →</a>
</body></html>
<?php
exit;

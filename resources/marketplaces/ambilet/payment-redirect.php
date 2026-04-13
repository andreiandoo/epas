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

// Step 1: Look up order by order_number to get ID
// The /customer/order-confirmation/{ref} endpoint returns order data by order_number
$orderLookup = api_get('/customer/order-confirmation/' . urlencode($orderNumber));
$orderId = $orderLookup['data']['id'] ?? $orderLookup['data']['order']['id'] ?? null;

// Fallback: try nested data structures
if (!$orderId && !empty($orderLookup['data'])) {
    // Some endpoints return order directly in data
    if (is_numeric($orderLookup['data']['id'] ?? null)) {
        $orderId = $orderLookup['data']['id'];
    }
}

if (!$orderId) {
    // Can't find order — show error with redirect
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Eroare</title></head>
    <body style="font-family:system-ui;text-align:center;padding:60px 20px;background:#080808;color:#f0ede6;">
        <h2>Comanda nu a fost găsită</h2>
        <p style="color:rgba(240,237,230,0.45);margin:12px 0 24px;">Comandă: <?= htmlspecialchars($orderNumber) ?></p>
        <a href="<?= htmlspecialchars($returnUrl) ?>" style="color:#D4A843;">Înapoi →</a>
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
    <a href="<?= htmlspecialchars($returnUrl) ?>" style="color:#D4A843;display:inline-block;margin-top:24px;">Înapoi →</a>
</body></html>
<?php
exit;

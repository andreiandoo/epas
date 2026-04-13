<?php
/**
 * Payment redirect page — initiates payment for an order and redirects to processor.
 * Used by whitelabel sites that create orders via API but need marketplace to handle payment.
 *
 * URL: /plata/{order_number}?return_url=https://site-organizator.ro/multumim
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$orderNumber = $_GET['order'] ?? '';
$returnUrl = $_GET['return_url'] ?? SITE_URL . '/multumim';
$cancelUrl = $_GET['cancel_url'] ?? SITE_URL;

if (!$orderNumber) {
    header('Location: ' . SITE_URL);
    exit;
}

// Look up order to get ID
$orderData = api_get('/customer/order-confirmation/' . urlencode($orderNumber));
$order = $orderData['data'] ?? null;

if (!$order || empty($order['id'])) {
    // Try alternative: the order might be accessible via order number directly
    // Show a simple loading page that tries to initiate payment
    $pageTitle = 'Procesare plată...';
    $bodyClass = 'bg-surface';
    require_once __DIR__ . '/includes/head.php';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div style="text-align:center;padding:60px 20px;">
        <div class="skeleton" style="width:48px;height:48px;border-radius:50%;margin:0 auto 16px;"></div>
        <h1 class="text-xl font-bold text-secondary">Se procesează plata...</h1>
        <p class="mt-2 text-muted">Te redirecționăm către procesatorul de plăți.</p>
        <p class="mt-4 text-sm text-muted">Dacă nu ești redirecționat automat, <a href="<?= SITE_URL ?>/multumim?order=<?= htmlspecialchars($orderNumber) ?>">click aici</a>.</p>
    </div>
    <script>
        // Try to initiate payment via API
        (async function() {
            try {
                const resp = await fetch('<?= SITE_URL ?>/api/proxy.php?action=order-confirmation&ref=<?= urlencode($orderNumber) ?>');
                const data = await resp.json();
                const orderId = data.data?.id;

                if (!orderId) {
                    window.location.href = <?= json_encode($returnUrl) ?>;
                    return;
                }

                const payResp = await fetch('<?= SITE_URL ?>/api/proxy.php?action=orders.pay&id=' + orderId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        return_url: <?= json_encode($returnUrl) ?>,
                        cancel_url: <?= json_encode($cancelUrl) ?>,
                    }),
                    credentials: 'include',
                });
                const payData = await payResp.json();

                if (payData.data?.form_data) {
                    // Netopia POST form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = payData.data.payment_url;
                    for (const [key, value] of Object.entries(payData.data.form_data)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }
                    document.body.appendChild(form);
                    form.submit();
                } else if (payData.data?.payment_url) {
                    window.location.href = payData.data.payment_url;
                } else {
                    window.location.href = <?= json_encode($returnUrl) ?>;
                }
            } catch (e) {
                console.error('Payment error:', e);
                window.location.href = <?= json_encode($returnUrl) ?>;
            }
        })();
    </script>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    require_once __DIR__ . '/includes/scripts.php';
    exit;
}

// If we got the order, initiate payment directly via API
$payResult = api_post('/orders/' . $order['id'] . '/pay', [
    'return_url' => $returnUrl,
    'cancel_url' => $cancelUrl,
]);

$payData = $payResult['data'] ?? [];

// Netopia: POST form submission
if (!empty($payData['form_data'])) {
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecționare plată...</title></head>
    <body>
        <p style="text-align:center;padding:40px;font-family:system-ui;">Se redirecționează către procesatorul de plăți...</p>
        <form id="payForm" method="POST" action="<?= htmlspecialchars($payData['payment_url']) ?>">
            <?php foreach ($payData['form_data'] as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
        </form>
        <script>document.getElementById('payForm').submit();</script>
    </body></html>
    <?php
    exit;
}

// Stripe/other: GET redirect
if (!empty($payData['payment_url'])) {
    header('Location: ' . $payData['payment_url']);
    exit;
}

// Fallback: redirect to return URL
header('Location: ' . $returnUrl);
exit;

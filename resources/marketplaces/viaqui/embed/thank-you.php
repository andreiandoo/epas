<?php
/**
 * Embed: Thank you / order confirmation page.
 * URL: /embed/{organizer-slug}/multumim?order=MKT-XXXXX
 */
require_once __DIR__ . '/includes/embed-init.php';

$orderNumber = $_GET['order'] ?? '';
$pageTitle = v2_t('Thank you') . ' · ' . $orgName;

require_once __DIR__ . '/includes/embed-head.php';
?>

<div style="text-align:center;padding:40px 20px;">
    <!-- Success icon -->
    <div style="width:64px;height:64px;margin:0 auto 16px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;">
        <svg style="width:32px;height:32px;color:#16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    </div>

    <h1 style="margin:0;font-size:24px;font-weight:700;color:<?= $textColor ?>;"><?= v2_te('Thank you for your order!') ?></h1>
    <p style="margin:12px 0 0;font-size:15px;color:<?= $mutedColor ?>;max-width:400px;margin-left:auto;margin-right:auto;">
        <?= v2_te('Your order has been placed. You will get your tickets by email in a few minutes.') ?>
    </p>

    <?php if ($orderNumber): ?>
    <div style="margin:24px auto 0;padding:12px 20px;background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:10px;display:inline-block;">
        <span style="font-size:13px;color:<?= $mutedColor ?>;"><?= v2_te('Order number:') ?></span>
        <span style="font-size:16px;font-weight:700;color:<?= $textColor ?>;margin-left:8px;"><?= htmlspecialchars($orderNumber) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top:32px;">
        <a href="/embed/<?= htmlspecialchars($organizerSlug) ?>" class="embed-btn">
            <?= v2_te('Back to events') ?>
        </a>
    </div>
</div>

<script>
    // Notify parent window about order completion
    if (window.parent !== window) {
        window.parent.postMessage({
            type: 'tixello:order:complete',
            orderNumber: <?= json_encode($orderNumber) ?>,
        }, '*');
    }
</script>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>

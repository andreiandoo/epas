<?php
/**
 * Embed: Cart page.
 * URL: /embed/{organizer-slug}/cos
 */
require_once __DIR__ . '/includes/embed-init.php';

$pageTitle = 'Coș — ' . $orgName;

require_once __DIR__ . '/includes/embed-head.php';
?>

<!-- Back link -->
<a href="/embed/<?= htmlspecialchars($organizerSlug) ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:<?= $mutedColor ?>;margin-bottom:16px;">
    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Continuă cumpărăturile
</a>

<h1 style="margin:0 0 20px;font-size:22px;font-weight:700;color:<?= $textColor ?>;">Coșul tău</h1>

<!-- Cart items (rendered by JS) -->
<div id="embed-cart-container">
    <div id="embed-cart-empty" style="text-align:center;padding:40px 0;color:<?= $mutedColor ?>;">
        <svg style="width:48px;height:48px;margin:0 auto 12px;color:<?= $borderColor ?>;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
        <p>Coșul tău este gol.</p>
        <a href="/embed/<?= htmlspecialchars($organizerSlug) ?>" class="embed-btn" style="margin-top:16px;">
            Vezi evenimente
        </a>
    </div>
    <div id="embed-cart-items-list" style="display:none;">
        <!-- Rendered by embed-app.js -->
    </div>
</div>

<!-- Cart total + Checkout button -->
<div id="embed-cart-footer" style="display:none;margin-top:24px;padding:16px;background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:600;font-size:16px;color:<?= $textColor ?>;">Total</span>
        <span id="embed-cart-page-total" style="font-size:20px;font-weight:700;color:<?= htmlspecialchars($accentColor) ?>;">0 RON</span>
    </div>
    <a href="/embed/<?= htmlspecialchars($organizerSlug) ?>/checkout" class="embed-btn" style="width:100%;margin-top:12px;text-align:center;">
        Continuă spre checkout
    </a>
</div>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>

<?php
/**
 * Embed: Checkout page.
 * URL: /embed/{organizer-slug}/checkout
 */
require_once __DIR__ . '/includes/embed-init.php';

$pageTitle = 'Checkout — ' . $orgName;

require_once __DIR__ . '/includes/embed-head.php';
?>

<h1 style="margin:0 0 20px;font-size:22px;font-weight:700;color:<?= $textColor ?>;">Finalizare comandă</h1>

<div style="display:flex;flex-direction:column;gap:20px;max-width:640px;">
    <!-- Order summary -->
    <div style="padding:16px;background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:12px;">
        <h2 style="margin:0 0 12px;font-size:16px;font-weight:600;color:<?= $textColor ?>;">Sumar comandă</h2>
        <div id="embed-checkout-items" style="font-size:14px;color:<?= $mutedColor ?>;"></div>
        <div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid <?= $borderColor ?>;">
            <span style="font-weight:600;">Total</span>
            <span id="embed-checkout-total" style="font-weight:700;color:<?= htmlspecialchars($accentColor) ?>;">0 RON</span>
        </div>
    </div>

    <!-- Customer form -->
    <div style="padding:16px;background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:12px;">
        <h2 style="margin:0 0 12px;font-size:16px;font-weight:600;color:<?= $textColor ?>;">Datele tale</h2>
        <form id="embed-checkout-form" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:<?= $textColor ?>;margin-bottom:4px;">Nume *</label>
                    <input type="text" id="emb-last-name" required style="width:100%;padding:10px 12px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:<?= $bgColor ?>;color:<?= $textColor ?>;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:<?= $textColor ?>;margin-bottom:4px;">Prenume *</label>
                    <input type="text" id="emb-first-name" required style="width:100%;padding:10px 12px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:<?= $bgColor ?>;color:<?= $textColor ?>;">
                </div>
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:<?= $textColor ?>;margin-bottom:4px;">Email *</label>
                <input type="email" id="emb-email" required style="width:100%;padding:10px 12px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:<?= $bgColor ?>;color:<?= $textColor ?>;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:<?= $textColor ?>;margin-bottom:4px;">Telefon *</label>
                <input type="tel" id="emb-phone" required style="width:100%;padding:10px 12px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:<?= $bgColor ?>;color:<?= $textColor ?>;">
            </div>
            <div style="margin-top:4px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:<?= $mutedColor ?>;cursor:pointer;">
                    <input type="checkbox" id="emb-terms" required style="width:16px;height:16px;">
                    Accept <a href="<?= SITE_URL ?>/termeni" target="_blank" style="color:<?= htmlspecialchars($accentColor) ?>;">termenii și condițiile</a>
                </label>
            </div>
            <button type="submit" id="emb-submit-btn" class="embed-btn" style="width:100%;margin-top:8px;">
                Plătește cu cardul
            </button>
            <div id="emb-checkout-error" style="display:none;padding:10px;background:#fef2f2;color:#dc2626;border-radius:8px;font-size:13px;text-align:center;"></div>
        </form>
    </div>
</div>

<script src="<?= SITE_URL ?>/embed/assets/js/embed-checkout.js"></script>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>

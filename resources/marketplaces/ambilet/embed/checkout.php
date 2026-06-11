<?php
/**
 * Embed: Unified Cart + Checkout page.
 * URL: /embed/{organizer-slug}/checkout
 */
require_once __DIR__ . '/includes/embed-init.php';

$pageTitle = 'Checkout — ' . $orgName;

require_once __DIR__ . '/includes/embed-head.php';
?>

<!-- Back link -->
<a href="<?= $baseUrl ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:<?= $mutedColor ?>;margin-bottom:16px;text-decoration:none;">
    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Continuă cumpărăturile
</a>

<h1 style="margin:0 0 20px;font-size:22px;font-weight:700;color:<?= $textColor ?>;">Finalizare comandă</h1>

<!-- Empty cart state -->
<div id="emb-empty" style="display:none;text-align:center;padding:40px 0;color:<?= $mutedColor ?>;">
    <svg style="width:48px;height:48px;margin:0 auto 12px;color:<?= $borderColor ?>;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
    <p>Coșul tău este gol.</p>
    <a href="<?= $baseUrl ?>" class="embed-btn" style="margin-top:16px;">Vezi evenimente</a>
</div>

<!-- Main checkout content -->
<div id="emb-checkout-wrap" style="display:none;">
    <div style="display:flex;gap:24px;flex-wrap:wrap;">

        <!-- Left: Customer form -->
        <div style="flex:1;min-width:320px;">
            <!-- Cart items (editable) -->
            <div style="background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:14px;padding:16px;margin-bottom:20px;">
                <h2 style="margin:0 0 12px;font-size:16px;font-weight:600;color:<?= $textColor ?>;">Biletele tale</h2>
                <div id="emb-cart-items"></div>
            </div>

            <!-- Promo code -->
            <div style="background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:14px;padding:16px;margin-bottom:20px;">
                <div style="display:flex;gap:8px;">
                    <input type="text" id="emb-promo" placeholder="Cod reducere" style="flex:1;padding:10px 12px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:transparent;color:<?= $textColor ?>;">
                    <button onclick="EmbedCheckout.applyPromo()" style="padding:10px 16px;background:<?= $isDark ? '#334155' : '#f1f5f9' ?>;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;color:<?= $textColor ?>;">Aplică</button>
                </div>
                <div id="emb-promo-msg" style="display:none;margin-top:8px;font-size:12px;"></div>
            </div>

            <!-- Customer details -->
            <div style="background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:14px;padding:16px;">
                <h2 style="margin:0 0 12px;font-size:16px;font-weight:600;color:<?= $textColor ?>;">Datele tale</h2>
                <form id="emb-form" style="display:flex;flex-direction:column;gap:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:500;color:<?= $mutedColor ?>;margin-bottom:4px;">Nume *</label>
                            <input type="text" id="emb-last-name" required style="width:100%;padding:10px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:transparent;color:<?= $textColor ?>;">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:500;color:<?= $mutedColor ?>;margin-bottom:4px;">Prenume *</label>
                            <input type="text" id="emb-first-name" required style="width:100%;padding:10px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:transparent;color:<?= $textColor ?>;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:<?= $mutedColor ?>;margin-bottom:4px;">Email *</label>
                        <input type="email" id="emb-email" required style="width:100%;padding:10px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:transparent;color:<?= $textColor ?>;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:<?= $mutedColor ?>;margin-bottom:4px;">Telefon *</label>
                        <input type="tel" id="emb-phone" required style="width:100%;padding:10px;border:1px solid <?= $borderColor ?>;border-radius:8px;font-size:14px;background:transparent;color:<?= $textColor ?>;">
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:<?= $mutedColor ?>;cursor:pointer;">
                        <input type="checkbox" id="emb-terms" required style="width:16px;height:16px;">
                        Accept <a href="<?= SITE_URL ?>/termeni-si-conditii" target="_blank">termenii și condițiile</a>
                    </label>
                </form>
            </div>
        </div>

        <!-- Right: Order summary (sticky) -->
        <div style="width:340px;flex-shrink:0;" id="emb-summary-sidebar">
            <div style="position:sticky;top:70px;background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:14px;padding:16px;">
                <h2 style="margin:0 0 12px;font-size:16px;font-weight:600;color:<?= $textColor ?>;">Sumar comandă</h2>
                <div id="emb-summary-lines" style="font-size:13px;color:<?= $mutedColor ?>;"></div>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid <?= $borderColor ?>;">
                    <div id="emb-discount-line" style="display:none;margin-bottom:8px;display:flex;justify-content:space-between;font-size:13px;color:#16a34a;"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:700;font-size:16px;color:<?= $textColor ?>;">Total</span>
                        <span id="emb-total" style="font-size:20px;font-weight:700;color:<?= htmlspecialchars($accentColor) ?>;">0 RON</span>
                    </div>
                </div>
                <button id="emb-pay-btn" onclick="EmbedCheckout.submit()" class="embed-btn" style="width:100%;margin-top:14px;padding:14px 24px;font-size:15px;" disabled>
                    Plătește cu cardul
                </button>
                <div id="emb-error" style="display:none;margin-top:10px;padding:10px;background:#fef2f2;color:#dc2626;border-radius:8px;font-size:13px;text-align:center;"></div>
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 767px) {
        #emb-summary-sidebar { width: 100% !important; }
    }
</style>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>
<script src="<?= SITE_URL ?>/embed/assets/js/embed-checkout.js"></script>

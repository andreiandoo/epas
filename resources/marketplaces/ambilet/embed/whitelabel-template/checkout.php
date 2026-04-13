<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$pageTitle = 'Checkout — ' . ORG_NAME;
require_once __DIR__ . '/includes/head.php';
?>

<a href="/" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-bottom:16px;">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Continuă cumpărăturile
</a>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Finalizare comandă</h1>

<div id="wl-empty" style="display:none;text-align:center;padding:40px 0;color:var(--muted);">
    <p>Coșul tău este gol.</p>
    <a href="/" class="wl-btn" style="margin-top:16px;">Vezi evenimente</a>
</div>

<div id="wl-checkout-wrap" style="display:none;">
    <div style="display:flex;gap:24px;flex-wrap:wrap;" class="wl-two-col">
        <div style="flex:1;min-width:320px;">
            <!-- Cart items -->
            <div class="wl-section">
                <h2 class="wl-section-title">Biletele tale</h2>
                <div id="wl-ck-items"></div>
            </div>

            <!-- Promo code -->
            <div class="wl-section">
                <div style="display:flex;gap:8px;">
                    <input type="text" id="wl-promo" class="wl-input" placeholder="Cod reducere" style="flex:1;">
                    <button onclick="WLCheckout.applyPromo()" class="wl-btn wl-btn-outline" style="padding:10px 16px;font-size:13px;">Aplică</button>
                </div>
                <div id="wl-promo-msg" style="display:none;margin-top:8px;font-size:12px;"></div>
            </div>

            <!-- Customer form -->
            <div class="wl-section">
                <h2 class="wl-section-title">Datele tale</h2>
                <form id="wl-form" style="display:flex;flex-direction:column;gap:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div><label class="wl-label">Nume *</label><input type="text" id="wl-ln" class="wl-input" required></div>
                        <div><label class="wl-label">Prenume *</label><input type="text" id="wl-fn" class="wl-input" required></div>
                    </div>
                    <div><label class="wl-label">Email *</label><input type="email" id="wl-em" class="wl-input" required></div>
                    <div><label class="wl-label">Telefon *</label><input type="tel" id="wl-ph" class="wl-input" required></div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);cursor:pointer;">
                        <input type="checkbox" id="wl-terms" required style="width:16px;height:16px;">
                        Accept <a href="/terms" target="_blank">termenii și condițiile</a>
                    </label>
                </form>
            </div>
        </div>

        <!-- Right: summary -->
        <div style="width:340px;flex-shrink:0;" class="wl-sidebar">
            <div style="position:sticky;top:70px;" class="wl-section">
                <h2 class="wl-section-title">Sumar comandă</h2>
                <div id="wl-ck-summary" style="font-size:13px;color:var(--muted);"></div>
                <div id="wl-ck-discount" style="display:none;margin-top:8px;font-size:13px;color:#16a34a;display:flex;justify-content:space-between;"></div>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-weight:700;font-size:16px;">Total</span>
                    <span id="wl-ck-total" style="font-size:20px;font-weight:700;color:var(--accent);">0 RON</span>
                </div>
                <button id="wl-pay-btn" onclick="WLCheckout.submit()" class="wl-btn" style="width:100%;margin-top:14px;padding:14px;font-size:15px;" disabled>Plătește cu cardul</button>
                <div id="wl-ck-error" style="display:none;margin-top:10px;padding:10px;background:#fef2f2;color:#dc2626;border-radius:8px;font-size:13px;text-align:center;"></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/checkout.js"></script>

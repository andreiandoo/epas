<?php
/**
 * COMPONENT: qr-modal  (single shared modal for ticket QR codes)
 * Input: none. Render ONCE per page (e.g. in the account layout). Open it from
 * anywhere with kitQR.show(code, title) — provided by kit.js.
 *
 * kit.js draws a real QR if window.QRCode (qrcodejs) is loaded; otherwise it
 * shows the code as text. Load qrcode.js in the template if you want the image.
 */
?>
<div class="kit-qr" id="kit-qr" hidden>
  <div class="kit-qr__backdrop" onclick="kitQR.hide()"></div>
  <div class="kit-qr__panel" role="dialog" aria-modal="true">
    <button class="kit-qr__close" onclick="kitQR.hide()" aria-label="Închide">✕</button>
    <h3 class="kit-display" id="kit-qr-title" style="font-size:1.1rem;margin-bottom:.75rem"></h3>
    <div id="kit-qr-canvas" class="kit-qr__canvas"></div>
    <p class="kit-muted" id="kit-qr-code" style="font-size:.8rem;margin-top:.5rem;text-align:center"></p>
  </div>
</div>

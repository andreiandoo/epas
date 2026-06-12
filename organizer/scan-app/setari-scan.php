<?php
$scanPage      = 'setari-scan';
$scanPageTitle = 'Setări scanner';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">
  <div class="scanapp-card scanapp-card--placeholder">
    <h1 class="scanapp-card__title">Setări scanner</h1>
    <p class="scanapp-card__text">Etapa 1 — placeholder. Toggle vibrație / sunet / auto-confirm, mod offline, Gate Manager, Staff Assignment, logout — urmează în Etapa 7.</p>
  </div>

  <!-- Banner Android: redirect către descărcarea APK pentru experiență nativă. -->
  <div class="scanapp-banner scanapp-banner--android" id="scanapp-android-banner" hidden>
    <div class="scanapp-banner__icon">📱</div>
    <div class="scanapp-banner__body">
      <div class="scanapp-banner__title">Folosești Android?</div>
      <div class="scanapp-banner__text">Aplicația nativă oferă o experiență mai bună pentru scanare îndelungată (camera optimizată, fără limita Wake Lock).</div>
      <a class="scanapp-banner__cta" href="https://ambilet.ro/android" target="_blank" rel="noopener">Descarcă APK Android</a>
    </div>
  </div>

  <!-- Banner iOS: instrucțiuni Add-to-Home-Screen. -->
  <div class="scanapp-banner scanapp-banner--ios" id="scanapp-ios-banner" hidden>
    <div class="scanapp-banner__icon">🍎</div>
    <div class="scanapp-banner__body">
      <div class="scanapp-banner__title">Folosești iPhone / iPad?</div>
      <div class="scanapp-banner__text">Atinge butonul <b>Distribuie</b> (pătrat cu săgeată) în Safari, apoi alege <b>Adaugă pe ecranul de start</b>. Aplicația va arăta și se va comporta ca o app nativă.</div>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var ua = navigator.userAgent || '';
    var isAndroid = /Android/i.test(ua);
    var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var inStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches ||
                       (window.navigator && window.navigator.standalone === true);
    if (isAndroid) {
      var a = document.getElementById('scanapp-android-banner');
      if (a) a.hidden = false;
    } else if (isIOS && !inStandalone) {
      var b = document.getElementById('scanapp-ios-banner');
      if (b) b.hidden = false;
    }
  });
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>

<?php
/**
 * Gift card landing: /card-cadou (v2 design).
 *
 * Static landing with a live configurator: value, recipient, delivery, design and message update the card previews
 * (gift.js). Buying a gift card is not wired on this site (no proxy action, cart item or payment step), so "Adaugă
 * în coș" says so and points to the contact page instead of silently doing nothing.
 *
 * Top to bottom: hero (live card), why, configurator + preview, how it works, occasions, eligible activities,
 * balance check, FAQ, final CTA.
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$gcMoney = fn (int $value): string => v2_thousands($value) . ' RON';
$amounts = [50, 100, 150, 250, 500, 1000];
$defaultAmount = 250;
$themes = [['wow', 'Wow / surpriză'], ['natura', 'Natură / calm'], ['sarbatoare', 'Sărbătoare'], ['premium', 'Premium']];
$tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');

$why = [
    ['Libertate de alegere', 'Destinatarul alege orașul, categoria, data și activitatea potrivită.', ''],
    ['Livrare rapidă', 'Cardul poate fi livrat digital pe email, imediat sau programat.', 'is-mint'],
    ['Mesaj personalizat', 'Adaugi un mesaj care transformă cardul într-un cadou personal.', ''],
    ['Sold reutilizabil', 'Dacă nu se folosește integral, soldul poate rămâne disponibil conform regulamentului.', 'is-deep'],
];
$steps = [
    ['Alegi valoarea', 'Selectezi suma potrivită sau o valoare personalizată.'],
    ['Scrii mesajul', 'Adaugi numele destinatarului și o urare personală.'],
    ['Îl trimiți', 'Cardul ajunge pe email imediat sau la data aleasă.'],
    ['Ei aleg experiența', 'Codul se folosește în coș sau checkout pentru activități eligibile.'],
];
$useCases = [
    ['Zi de naștere', 'Pentru cineva care preferă amintiri în loc de obiecte.'],
    ['Cuplu', 'O ieșire în doi: muzeu, atelier, escape room sau tur.'],
    ['Familie', 'Activități pentru copii, weekenduri și vacanțe.'],
    ['Corporate', 'Cadouri pentru echipe, clienți sau parteneri.'],
    ['Last minute', 'Cadou digital, rapid, fără livrare fizică.'],
    ['Mulțumesc', 'Un gest elegant pentru cineva care a ajutat.'],
];
$eligible = [
    ['escape-rooms', 'Escape rooms'], ['muzee-expozitii', 'Muzee'], ['parcuri-de-distractii', 'Parcuri'],
    ['natura-outdoor', 'Natură'], ['ateliere-experiente-creative', 'Ateliere'], ['familie-copii', 'Familie'],
];
$faqs = [
    ['Cum se livrează cardul cadou?', 'Cardul cadou este livrat digital pe email, fie către tine, fie direct către destinatar, în funcție de opțiunea aleasă.'],
    ['Unde poate fi folosit?', 'Poate fi folosit pentru activitățile eligibile de pe bilete.online: escape rooms, muzee, parcuri, ateliere, natură și alte experiențe listate.'],
    ['Poate fi folosit parțial?', 'Da. Dacă soldul cardului este mai mare decât valoarea comenzii, diferența poate rămâne disponibilă până la expirarea cardului, conform regulamentului.'],
    ['Pot programa trimiterea?', 'Da, cardul poate fi trimis imediat sau programat pentru o dată aleasă, dacă această opțiune este activă în checkout.'],
    ['Pot cumpăra carduri cadou pentru companie?', 'Da. Pentru volume mai mari sau cadouri corporate, poți folosi formularul de contact sau o pagină dedicată comenzilor bulk.'],
];

$pageTitleRaw = 'Card cadou bilete.online — dăruiește o experiență, nu un obiect';
$pageDescription = 'Card cadou digital bilete.online pentru activități, experiențe și ieșiri memorabile. Alegi valoarea, scrii mesajul, destinatarul primește email cu cod unic.';
$canonicalUrl = SITE_URL . '/card-cadou';
$ogImage = v2_asset('img/cat-familie-copii.webp');
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => 'Card cadou bilete.online',
    'description' => 'Card cadou digital pentru activități și experiențe — escape rooms, muzee, parcuri, ateliere, natură.',
    'brand' => ['@type' => 'Brand', 'name' => 'bilete.online'],
    'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => 'RON', 'lowPrice' => (string) min($amounts), 'highPrice' => (string) max($amounts), 'offerCount' => (string) count($amounts)],
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Card cadou', 'item' => $canonicalUrl],
    ],
]];

$gcArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['gift.css'];
$v2Scripts = ['gift.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="gc-hero" aria-labelledby="gc-h">
    <?= $gcArches ?>
    <svg class="gc-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="gc-hero-in">
      <div>
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><span aria-current="page">Card cadou</span></nav>
        <p class="gc-kicker">Card cadou digital · experiențe · bilete QR</p>
        <h1 class="gc-h" id="gc-h">Dăruiește ceva de făcut.</h1>
        <p class="gc-lead">Un card cadou bilete.online nu obligă pe nimeni să aleagă un obiect. Îi lași să aleagă o experiență: escape room, muzeu, parc, atelier, natură sau o ieșire de weekend.</p>
        <div class="gc-cta">
          <a class="btn btn-light" href="#cumpara">Cumpără card cadou<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#cum-functioneaza">Cum funcționează</a>
        </div>
      </div>

      <div class="gc-hero-art" aria-hidden="true">
        <div class="gc-card is-hero" data-theme="wow">
          <div class="gc-card-top"><span>Gift card</span><span>bilete.online</span></div>
          <p class="gc-card-amount" data-gc="amount"><?= v2_e($gcMoney($defaultAmount)) ?></p>
          <p class="gc-card-for">pentru <strong data-gc="recipient" data-fallback="cineva care merită o ieșire bună">cineva care merită o ieșire bună</strong></p>
          <div class="gc-card-bottom">
            <div><small>Cod exemplu</small><b>GIFT-2026-WOW</b></div>
            <span class="gc-card-gift"><?= v2_ic('gift') ?></span>
          </div>
        </div>
        <div class="gc-note is-quote"><small>Mesaj personalizat</small><p>„Alege o experiență care te scoate din casă.”</p></div>
        <div class="gc-note is-uses"><small>Se poate folosi la</small><ul><li>escape rooms</li><li>muzee</li><li>parcuri</li><li>ateliere</li><li>natură</li></ul></div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== WHY ===================== -->
  <section class="sec gc-why" aria-labelledby="gc-why-h">
    <div class="wrap gc-why-grid">
      <div class="gc-why-intro">
        <p class="kicker">De ce</p>
        <h2 id="gc-why-h">Un cadou care nu rămâne pe raft.</h2>
        <p>Cardul cadou este perfect când nu știi exact ce activitate ar prefera cineva, dar știi sigur că i-ar prinde bine o ieșire, o experiență sau un moment memorabil.</p>
      </div>
      <ul class="gc-why-list">
        <?php foreach ($why as [$whyTitle, $whyText, $whyClass]): ?>
        <li class="gc-why-card <?= $whyClass ?>"><h3><?= v2_e($whyTitle) ?></h3><p><?= v2_e($whyText) ?></p></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== CONFIGURATOR ===================== -->
  <section class="sec gc-build" id="cumpara" aria-labelledby="gc-build-h">
    <div class="wrap gc-build-grid">
      <div>
        <p class="kicker">Configurator</p>
        <h2 id="gc-build-h">Construiește cardul cadou.</h2>
        <p class="gc-build-lead">Alege valoarea, destinatarul, mesajul și momentul livrării. Cardul se generează și se trimite digital pe email.</p>

        <form class="gc-form" id="gc-form" novalidate>
          <div class="gc-fields">
            <div class="gc-field">
              <label for="gc-amount">Valoare card</label>
              <select id="gc-amount">
                <?php foreach ($amounts as $amount): ?><option value="<?= $amount ?>"<?= $amount === $defaultAmount ? ' selected' : '' ?>><?= v2_thousands($amount) ?> lei</option><?php endforeach; ?>
              </select>
            </div>
            <div class="gc-field">
              <label for="gc-recipient">Pentru cine este?</label>
              <input id="gc-recipient" type="text" maxlength="60" autocomplete="off" placeholder="ex. Maria, Alex, Ana și Vlad">
            </div>
            <div class="gc-field">
              <label for="gc-email">Email destinatar</label>
              <input id="gc-email" type="email" autocomplete="off" placeholder="destinatar@example.ro">
            </div>
            <div class="gc-field">
              <label for="gc-delivery">Când se trimite?</label>
              <select id="gc-delivery">
                <option value="now">Imediat după cumpărare</option>
                <option value="scheduled">La o dată aleasă</option>
                <option value="me">Îl trimit eu mai târziu</option>
              </select>
            </div>
            <div class="gc-field" id="gc-date-field" hidden>
              <label for="gc-date">Data trimiterii</label>
              <input id="gc-date" type="date" min="<?= v2_e($tomorrow) ?>">
            </div>
            <div class="gc-field">
              <label for="gc-theme">Design</label>
              <select id="gc-theme">
                <?php foreach ($themes as [$themeKey, $themeLabel]): ?><option value="<?= $themeKey ?>"><?= v2_e($themeLabel) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="gc-field is-wide">
              <label for="gc-message">Mesaj personalizat</label>
              <textarea id="gc-message" rows="4" maxlength="180" placeholder="Scrie un mesaj scurt pentru destinatar." aria-describedby="gc-count"></textarea>
              <span class="gc-count" id="gc-count">0/180 caractere</span>
            </div>
          </div>

          <div class="gc-info">
            <span class="gc-info-ic"><?= v2_ic('envelope-simple') ?></span>
            <p><b>Ce primește destinatarul?</b>Un email cu cardul cadou, cod unic, mesajul tău și link direct către activitățile eligibile.</p>
          </div>

          <div class="gc-actions">
            <button class="btn btn-primary" type="button" id="gc-add"><?= v2_ic('shopping-cart-simple') ?>Adaugă în coș</button>
            <button class="btn btn-ghost" type="button" id="gc-show" aria-controls="gc-preview">Previzualizează</button>
          </div>
          <p class="gc-msg" id="gc-msg" role="status"></p>
        </form>
      </div>

      <aside class="gc-preview-wrap" aria-label="Previzualizare card cadou">
        <div class="gc-card is-preview" id="gc-preview" data-theme="wow" tabindex="-1">
          <div class="gc-card-top"><span>Gift card</span><span>bilete.online</span></div>
          <p class="gc-card-amount" data-gc="amount"><?= v2_e($gcMoney($defaultAmount)) ?></p>
          <p class="gc-card-for">pentru <strong data-gc="recipient" data-fallback="cineva drag">cineva drag</strong></p>
          <p class="gc-card-message" data-gc="message" data-fallback="Alege o experiență care te scoate din casă.">Alege o experiență care te scoate din casă.</p>
          <div class="gc-card-bottom">
            <div><small>Cod card</small><b>GIFT-2026-WOW</b></div>
            <span class="gc-card-gift"><?= v2_ic('gift') ?></span>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <!-- ===================== HOW IT WORKS ===================== -->
  <section class="sec gc-how" id="cum-functioneaza" aria-labelledby="gc-how-h">
    <div class="wrap">
      <div class="gc-how-intro"><p class="kicker">Cum funcționează</p><h2 id="gc-how-h">Din cadou în bilet, în câțiva pași.</h2></div>
      <ol class="gc-steps">
        <?php foreach ($steps as $si => [$stepTitle, $stepText]): ?>
        <li class="gc-step<?= $si === count($steps) - 1 ? ' is-last' : '' ?>"><span class="gc-step-n"><?= $si + 1 ?></span><h3><?= v2_e($stepTitle) ?></h3><p><?= v2_e($stepText) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ===================== OCCASIONS ===================== -->
  <section class="sec gc-uses" aria-labelledby="gc-uses-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap gc-uses-grid">
      <div>
        <p class="kicker">Pentru ce ocazii</p>
        <h2 id="gc-uses-h">Când nu vrei încă un cadou generic.</h2>
        <p>Cardul cadou funcționează pentru oameni diferiți pentru că nu presupune că știi exact ce vor. Le dai opțiuni, nu o alegere forțată.</p>
      </div>
      <ul class="gc-uses-list">
        <?php foreach ($useCases as [$useTitle, $useText]): ?><li><h3><?= v2_e($useTitle) ?></h3><p><?= v2_e($useText) ?></p></li><?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== ELIGIBLE ===================== -->
  <section class="sec gc-eligible" aria-labelledby="gc-eligible-h">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <p class="kicker">Activități eligibile</p>
          <h2 id="gc-eligible-h">La ce se poate folosi?</h2>
          <p class="gc-eligible-lead">Cardul cadou poate fi folosit pentru activitățile eligibile din platformă: escape rooms, muzee, parcuri, ateliere, natură sau experiențe pentru familie.</p>
        </div>
        <a class="btn btn-ghost" href="/categorii">Vezi toate categoriile<?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="gc-cats">
        <?php foreach ($eligible as [$catSlug, $catTitle]): ?>
        <li><a class="gc-cat" href="/<?= v2_e($catSlug) ?>">
          <span class="gc-cat-media"><img src="<?= v2_e(v2_asset('img/cat-' . $catSlug . '-320.webp')) ?>" srcset="<?= v2_e(v2_asset('img/cat-' . $catSlug . '-320.webp')) ?> 320w, <?= v2_e(v2_asset('img/cat-' . $catSlug . '.webp')) ?> 640w" sizes="(min-width: 1024px) 15vw, (min-width: 600px) 30vw, 45vw" width="640" height="800" alt="" loading="lazy" decoding="async"></span>
          <b><?= v2_e($catTitle) ?><?= v2_ic('arrow-right') ?></b>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== BALANCE ===================== -->
  <section class="sec gc-balance" aria-labelledby="gc-balance-h">
    <div class="wrap gc-balance-grid">
      <div>
        <p class="kicker">Gestionare sold</p>
        <h2 id="gc-balance-h">Cod unic. Sold clar. Folosire simplă.</h2>
        <p>Destinatarul introduce codul în coș sau checkout. Dacă valoarea comenzii este mai mică decât soldul disponibil, diferența poate rămâne pe card, conform regulamentului.</p>
      </div>
      <div class="gc-check">
        <small>Verificare card</small>
        <h3>GIFT-2026-WOW</h3>
        <dl>
          <div class="is-mint"><dt>Sold disponibil</dt><dd>180 lei</dd></div>
          <div><dt>Valabil până la</dt><dd>2027</dd></div>
        </dl>
        <a class="btn btn-primary" href="/voucher">Verifică un card<?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec gc-faq" aria-labelledby="gc-faq-h">
    <div class="wrap gc-faq-grid">
      <div><p class="kicker">FAQ</p><h2 id="gc-faq-h">Întrebări frecvente</h2></div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="gc-final" aria-labelledby="gc-final-h">
    <div class="wrap">
      <div class="gc-final-in">
        <?= $gcArches ?>
        <div>
          <p class="kicker">Cadou digital</p>
          <h2 id="gc-final-h">Trimite o experiență, nu încă un obiect.</h2>
          <p>Alege valoarea, scrie mesajul și lasă destinatarul să aleagă activitatea potrivită.</p>
        </div>
        <a class="btn btn-light" href="#cumpara">Cumpără card cadou<?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>

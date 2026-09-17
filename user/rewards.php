<?php
/**
 * Customer points and referrals: /cont/puncte and /cont/punctele-mele (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the balance and the progress to the next
 * level, four counters, how points work (with the marketplace's real conversion and limits), the customer level and its
 * perks, the referral block (#afiliere: link, stats, copy / share / new code), the points history with a type filter,
 * the expiry and rules cards, and the login prompt. rewards.js reads /customer/rewards/config, /customer/rewards,
 * /customer/referrals and /customer/rewards/history; points about to expire come from the dashboard summary, and only
 * when the programme makes points expire.
 *
 * Fixed on the way: the referral link was built as /r/<code>, which is a 404 (core's link is /?ref=<code>); "Cod nou"
 * posted to an endpoint api.js doesn't map, and the proxy pointed at a core route that doesn't exist; the "maximum per
 * order" line showed a number of points as lei; "Expiră curând" read a field /customer/rewards never sends; the
 * regulations link (/termeni-program-puncte) is a 404, so the rules card links to the points questions in the help
 * centre instead.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = 'Punctele mele — ' . SITE_NAME;
$pageDescription = 'Punctele tale bonus pe bilete.online: sold disponibil, expirare, istoric, nivel client și link de afiliere.';
$canonicalUrl = SITE_URL . '/cont/puncte';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'rewards.css'];
$v2Scripts = ['account.js', 'rewards.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('points'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="pt-guard" hidden aria-labelledby="pt-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="pt-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a vedea punctele.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Fpuncte">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="pt-content">
      <!-- HERO -->
      <section class="acc-hero pt-hero" aria-labelledby="pt-h">
        <div>
          <p class="acc-kicker">Loyalty wallet</p>
          <h1 class="acc-h" id="pt-h">Punctele mele</h1>
          <p class="acc-lead">Vezi soldul punctelor bonus, valoarea estimată, cum le poți folosi, ce puncte urmează să expire și câte ai câștigat din comenzi sau afiliere.</p>
          <div class="pt-cta">
            <a class="btn btn-light" href="/cont/recomandari"><?= v2_ic('coins') ?>Folosește puncte</a>
            <a class="btn btn-outline-light" href="#afiliere">Invită prieteni</a>
          </div>
        </div>
        <article class="pt-wallet" aria-labelledby="pt-wallet-k">
          <p class="acc-k" id="pt-wallet-k">Sold disponibil</p>
          <p class="pt-balance" id="pt-balance">0</p>
          <p class="pt-balance-sub">puncte bonus · aproximativ <strong id="pt-balance-lei">0 lei</strong></p>
          <div class="pt-bar" id="pt-bar-hero" role="progressbar" aria-label="Progres către nivelul următor" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
          <p class="pt-bar-note" id="pt-next-hero">Se încarcă nivelul…</p>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="pt-stats" aria-label="Pe scurt">
        <article class="pt-stat"><p class="acc-k">Puncte disponibile</p><p class="pt-stat-v" id="pt-s-balance">0</p><p class="pt-stat-p">≈ <span id="pt-s-balance-lei">0 lei</span></p></article>
        <article class="pt-stat is-mint"><p class="acc-k">Câștigate total</p><p class="pt-stat-v" id="pt-s-earned">0</p><p class="pt-stat-p">din comenzi și afiliere</p></article>
        <article class="pt-stat"><p class="acc-k">Folosite</p><p class="pt-stat-v" id="pt-s-spent">0</p><p class="pt-stat-p">≈ <span id="pt-s-spent-lei">0 lei</span> reduceri</p></article>
        <article class="pt-stat is-warm"><p class="acc-k">Expiră curând</p><p class="pt-stat-v" id="pt-s-expiring">0</p><p class="pt-stat-p" id="pt-s-expiring-p">fără expirare imediată</p></article>
      </section>

      <!-- HOW IT WORKS + LEVEL -->
      <section class="pt-row">
        <div class="acc-panel">
          <div class="pt-panel-head">
            <div><p class="acc-k">Cum funcționează</p><h2>Folosești punctele direct în checkout.</h2></div>
            <a class="btn btn-primary" href="/categorii">Caută activități</a>
          </div>
          <ol class="pt-steps">
            <li><span aria-hidden="true">1</span><h3>Cumperi</h3><p>La comenzile eligibile primești puncte bonus după confirmare.</p></li>
            <li><span aria-hidden="true">2</span><h3>Strângi</h3><p>Punctele se adună în cont și apar în istoricul tranzacțiilor.</p></li>
            <li><span aria-hidden="true">3</span><h3>Reduci</h3><p>La checkout alegi câte puncte vrei să aplici în comanda eligibilă.</p></li>
          </ol>
          <div class="pt-rate">
            <b>Conversie actuală</b>
            <ul id="pt-rate"><li>Se încarcă regulile programului…</li></ul>
          </div>
        </div>

        <div class="acc-panel">
          <p class="acc-k">Nivel client</p>
          <h2 class="pt-tier" id="pt-tier">—</h2>
          <p class="pt-p" id="pt-tier-desc" hidden></p>
          <div class="pt-ladder">
            <div class="pt-ladder-names"><span id="pt-tier-from">—</span><span id="pt-tier-to">—</span></div>
            <div class="pt-bar is-green" id="pt-bar-tier" role="progressbar" aria-label="Progres în nivelul curent" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
            <p class="pt-bar-note" id="pt-next-tier">—</p>
          </div>
          <ul class="pt-perks" id="pt-perks"></ul>
        </div>
      </section>

      <!-- REFERRALS -->
      <section class="pt-aff" id="afiliere" aria-labelledby="pt-aff-h">
        <div>
          <p class="acc-kicker">Afiliere client</p>
          <h2 class="pt-aff-h" id="pt-aff-h">Invită prieteni. Câștigă puncte când cumpără.</h2>
          <p class="acc-lead">Distribuie linkul tău de afiliat. Când un prieten creează cont și cumpără prima activitate eligibilă, primești puncte bonus. Prietenul poate primi și el un beneficiu, dacă există campanie activă.</p>
          <p class="pt-aff-reward" id="pt-aff-reward" hidden></p>
          <dl class="pt-aff-stats">
            <div><dt>clickuri</dt><dd id="pt-r-clicks">0</dd></div>
            <div><dt>conturi create</dt><dd id="pt-r-accounts">0</dd></div>
            <div><dt>comenzi eligibile</dt><dd id="pt-r-orders">0</dd></div>
          </dl>
        </div>
        <article class="pt-link" aria-labelledby="pt-link-k">
          <p class="acc-k" id="pt-link-k">Linkul tău</p>
          <p class="pt-link-big" id="pt-link-display">—</p>
          <label class="pt-sr" for="pt-link">Linkul tău de afiliat</label>
          <input class="pt-link-input" id="pt-link" type="text" readonly value="">
          <div class="pt-link-cta">
            <button class="btn btn-primary" type="button" id="pt-copy" disabled>Copiază link</button>
            <a class="btn btn-ghost" id="pt-wa" href="https://wa.me/" target="_blank" rel="noopener" hidden>Share WhatsApp</a>
            <button class="btn btn-ghost" type="button" id="pt-share" hidden>Trimite</button>
            <button class="btn btn-ghost" type="button" id="pt-regen" disabled aria-controls="pt-confirm" aria-expanded="false">Cod nou</button>
          </div>
          <div class="pt-confirm" id="pt-confirm" hidden>
            <p>Sigur regenerezi codul? Linkul curent va fi invalidat.</p>
            <div>
              <button class="btn btn-primary" type="button" id="pt-regen-yes">Da, generează cod nou</button>
              <button class="btn btn-ghost" type="button" id="pt-regen-no">Anulează</button>
            </div>
          </div>
          <p class="pt-code">Cod afiliat: <strong id="pt-code">—</strong></p>
          <p class="pt-link-status" id="pt-link-status" role="status"></p>
        </article>
      </section>

      <!-- HISTORY + SIDE -->
      <section class="pt-row is-history">
        <div class="acc-panel">
          <div class="pt-panel-head">
            <div><p class="acc-k">Istoric</p><h2>Tranzacții puncte</h2></div>
            <label class="acc-field pt-type"><span class="pt-sr">Tip tranzacții</span>
              <span class="acc-select"><select id="pt-type">
                <option value="all">Toate</option>
                <option value="earned">Câștigate</option>
                <option value="spent">Folosite</option>
                <option value="expired">Expirate</option>
                <option value="affiliate">Afiliere</option>
              </select><?= v2_ic('caret-down') ?></span>
            </label>
          </div>
          <div class="acc-skel pt-rows-skel" id="pt-h-skel" aria-hidden="true"><i></i><i></i><i></i></div>
          <p class="pt-empty" id="pt-h-empty" hidden>Nu există tranzacții pentru filtrul ales.</p>
          <p class="pt-empty is-error" id="pt-h-error" hidden>Nu am putut încărca istoricul. <button type="button" id="pt-h-retry">Încearcă din nou</button></p>
          <div class="pt-table-wrap" id="pt-h-table" hidden>
            <table class="pt-table">
              <thead><tr><th scope="col">Data</th><th scope="col">Descriere</th><th scope="col" class="is-type">Tip</th><th scope="col" class="is-pts">Puncte</th></tr></thead>
              <tbody id="pt-h-rows"></tbody>
            </table>
          </div>
          <button class="btn btn-ghost pt-more" type="button" id="pt-h-more" hidden>Încarcă mai multe</button>
        </div>

        <aside class="pt-side">
          <div class="acc-panel pt-exp" id="pt-exp">
            <p class="acc-k">Expirare</p>
            <h2 id="pt-exp-h">Niciun punct în expirare</h2>
            <p class="pt-p" id="pt-exp-p">Continuă să cumperi pentru a câștiga puncte noi.</p>
            <a class="btn btn-primary" href="/cont/recomandari">Vezi recomandări</a>
          </div>
          <div class="acc-panel">
            <p class="acc-k">Reguli</p>
            <h2>Pe scurt</h2>
            <ul class="pt-rules">
              <li><b>Câștigare:</b> la comenzi eligibile, după confirmare.</li>
              <li><b>Folosire:</b> direct în checkout, în limita regulilor.</li>
              <li><b>Afiliere:</b> puncte după prima comandă eligibilă a prietenului.</li>
              <li id="pt-rule-exp"><b>Expirare:</b> punctele pot avea termen de valabilitate.</li>
            </ul>
            <a class="pt-help" href="/ajutor?categorie=bonus">Întrebări despre puncte<?= v2_ic('arrow-right') ?></a>
          </div>
        </aside>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>

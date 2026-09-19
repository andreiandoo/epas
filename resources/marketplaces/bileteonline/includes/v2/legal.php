<?php
/**
 * bilete.online v2: the legal pages (/termeni → terms.php, /confidentialitate → privacy.php). One layout: the hero, a
 * table of contents (sticky beside the text on wide screens, folded above it on phones; legal.js marks the section
 * in view), the company that runs the platform, the numbered sections and a contact block. The pages give the
 * sections as [id, title, html]; the html is written here in the repo, never user input.
 *
 * The company data lives here once (V2_LEGAL_COMPANY), for both documents.
 */

const V2_LEGAL_UPDATED = '19 septembrie 2026';

const V2_LEGAL_COMPANY = [
    'name' => 'TIXELLO S.R.L.',
    'cui' => '54166771',
    'reg' => 'J2026014682005',
    'vat' => 'neplătitor de TVA',
    'address' => 'Str. Mihai Eminescu nr. 22, Camera 1, Ploiești, jud. Prahova, 100329',
    'phone' => '0750 292 962',
    'phone_href' => '+40750292962',
    'hours' => 'luni - vineri, 09:00 - 18:00',
];

const V2_LEGAL_DOCS = [
    ['/termeni', 'Termeni și condiții'],
    ['/confidentialitate', 'Politica de confidențialitate'],
    ['/cookies', 'Politica de cookies'],
];

/**
 * @param array{key: string, kicker: string, title: string, lead: string, sections: list<array{0: string, 1: string, 2: string}>} $page
 */
function v2_legal_render(array $page): void
{
    $company = V2_LEGAL_COMPANY;
    $email = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'contact@bilete.online';
    ?>
<main id="main" tabindex="-1">
  <section class="lg-hero" aria-labelledby="lg-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="lg-in">
      <p class="lg-kicker"><?= v2_e($page['kicker']) ?></p>
      <h1 class="lg-h" id="lg-h"><?= v2_e($page['title']) ?></h1>
      <p class="lg-lead"><?= v2_e($page['lead']) ?></p>
      <p class="lg-meta"><span><?= v2_ic('calendar-blank') ?>Actualizat la <?= v2_e(V2_LEGAL_UPDATED) ?></span><span><?= v2_ic('buildings') ?>Operator: <?= v2_e($company['name']) ?></span></p>
    </div>
    <div id="hdr-sentinel" aria-hidden="true"></div>
  </section>

  <section class="sec lg-body" aria-label="Conținutul documentului">
    <div class="wrap lg-grid">
      <aside class="lg-side">
        <details class="lg-toc" id="lg-toc">
          <summary><?= v2_ic('list') ?><span>Cuprins</span><?= v2_ic('caret-down', 'ic lg-toc-caret') ?></summary>
          <ol>
            <?php foreach ($page['sections'] as $i => [$id, $title]): ?>
            <li><a href="#<?= v2_e($id) ?>"><span><?= $i + 1 ?>.</span><?= v2_e($title) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </details>
        <nav class="lg-docs" aria-label="Documente legale">
          <p>Documente</p>
          <?php foreach (V2_LEGAL_DOCS as [$href, $label]): ?>
          <a href="<?= v2_e($href) ?>"<?= $href === '/' . $page['key'] ? ' aria-current="page"' : '' ?>><?= v2_e($label) ?></a>
          <?php endforeach; ?>
        </nav>
      </aside>

      <article class="lg-prose">
        <div class="lg-company">
          <p class="lg-company-k"><?= v2_ic('buildings') ?>Cine operează bilete.online</p>
          <dl>
            <div class="is-wide"><dt>Denumire</dt><dd><?= v2_e($company['name']) ?></dd></div>
            <div><dt>CUI</dt><dd><?= v2_e($company['cui']) ?> · <?= v2_e($company['vat']) ?></dd></div>
            <div><dt>Nr. Reg. Comerțului</dt><dd><?= v2_e($company['reg']) ?></dd></div>
            <div class="is-wide"><dt>Sediul social</dt><dd><?= v2_e($company['address']) ?></dd></div>
            <div><dt>Email</dt><dd><a href="mailto:<?= v2_e($email) ?>"><?= v2_e($email) ?></a></dd></div>
            <div><dt>Telefon</dt><dd><a href="tel:<?= v2_e($company['phone_href']) ?>"><?= v2_e($company['phone']) ?></a> · <?= v2_e($company['hours']) ?></dd></div>
          </dl>
        </div>

        <?php foreach ($page['sections'] as $i => [$id, $title, $html]): ?>
        <section class="lg-sec" id="<?= v2_e($id) ?>" aria-labelledby="<?= v2_e($id) ?>-h">
          <h2 id="<?= v2_e($id) ?>-h"><span class="lg-n"><?= $i + 1 ?>.</span><?= v2_e($title) ?></h2>
          <?= $html ?>
        </section>
        <?php endforeach; ?>
      </article>
    </div>

    <div class="wrap">
      <div class="lg-final">
        <div>
          <p class="lg-final-k">Întrebări?</p>
          <h2>Scrie-ne și îți răspundem cât mai repede.</h2>
        </div>
        <div class="lg-final-cta">
          <a class="btn lg-btn-white" href="/contact">Pagina de contact<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="mailto:<?= v2_e($email) ?>"><?= v2_ic('envelope-simple') ?><?= v2_e($email) ?></a>
        </div>
      </div>
    </div>
  </section>
</main>
    <?php
}

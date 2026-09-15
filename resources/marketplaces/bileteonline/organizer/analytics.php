<?php
/**
 * Organizer activity analytics: /organizator/analytics/{id} (analytics.php?event={id}), v2 design.
 *
 * Inside the v2 organizer shell. One activity over a period (7, 30, 90 days or everything): net revenue, tickets, page
 * views, conversion and the days left, each total with the chosen period beside it and its change against the period
 * before; sales over time with the campaigns marked; estimates for the next days and for the activity's day; ticket type
 * performance; campaigns with ROI (add, edit, delete, copy the UTM parameters); traffic sources and top locations, with a
 * map of Romania; recent sales; goals (add, edit, delete). Export: the period's data as CSV, the report as PDF.
 * org-analytics.js reads /organizer/events/{id}/analytics, /goals, /milestones and /organizer/events through the proxy.
 * Address: /organizator/analytics/{id}?perioada=7z|30z|90z (everything when missing).
 *
 * Fixed on the way:
 * - Export opened /analytics/export, which core does not have, with the session token in the address;
 * - the revenue change set all-time revenue against the previous period alone, "unique" visitors repeated the page views
 *   and the progress bars measured sales against made-up targets (1.5 × sales, 100.000 lei);
 * - the "live" map showed the period's cities as people online "now", on tiles from a third-party library: the map is
 *   drawn here from the Natural Earth outline (includes/v2/map-romania.svg) and says what it counts;
 * - goals read in English ("Revenue Goal", "1,224.00 EUR"), so did campaign types and "2 hours ago";
 * - the chart library came unpinned from a CDN and its page views per day were not measured: charts are drawn here;
 * - the forecast kept counting after the activity and past its capacity; goals and campaigns could not be edited or deleted.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$oaEventId = isset($_GET['event']) && is_string($_GET['event']) && ctype_digit($_GET['event']) ? (string) (int) $_GET['event'] : '';
$oaPeriods = ['7z' => '7 zile', '30z' => '30 de zile', '90z' => '90 de zile', 'tot' => 'Tot'];
$oaPeriod = isset($_GET['perioada']) && is_string($_GET['perioada']) && isset($oaPeriods[$_GET['perioada']]) ? $_GET['perioada'] : 'tot';

$pageTitleRaw = 'Analiză activitate — ' . SITE_NAME;
$pageDescription = 'Analiza unei activități pe bilete.online: vânzări în timp, estimări, bilete, campanii, surse de trafic, locații și obiective.';
$canonicalUrl = SITE_URL . '/organizator/analytics' . ($oaEventId !== '' ? '/' . $oaEventId : '');
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-analytics.css'];
$v2Scripts = ['organizer.js', 'org-analytics.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$oaStat = function (string $key, string $label, string $icon, string $extra = '') {
    return '<article class="oa-stat" id="oa-st-' . $key . '">'
        . '<div class="oa-stat-top"><span class="oa-stat-ic">' . v2_ic($icon) . '</span><p class="oa-stat-k">' . $label . '</p></div>'
        . '<div class="oa-stat-vrow"><p class="oa-stat-v" id="oa-s-' . $key . '"><span class="org-skel oa-sk"></span></p><span class="oa-stat-tag" id="oa-s-' . $key . '-t"></span></div>'
        . '<p class="oa-stat-p" id="oa-s-' . $key . '-p"></p>'
        . '<div class="oa-stat-m" id="oa-s-' . $key . '-m" hidden><span class="oa-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100"><i></i></span><span class="oa-stat-mp"></span></div>'
        . $extra
        . '<div class="oa-stat-per oa-dep" id="oa-s-' . $key . '-per" hidden></div>'
        . '</article>';
};
$oaX = '<button class="oa-x" type="button" data-close aria-label="Închide">' . v2_ic('x') . '</button>';
$oaMapFile = __DIR__ . '/../includes/v2/map-romania.svg';
$oaMap = is_file($oaMapFile) ? (string) file_get_contents($oaMapFile) : '';

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="oa<?= $oaEventId === '' ? ' is-none' : '' ?>" id="oa" data-event="<?= htmlspecialchars($oaEventId, ENT_QUOTES) ?>">
  <header class="oa-head">
    <a class="oa-back" href="/organizator/activities"><?= v2_ic('arrow-left') ?>Înapoi la activități</a>
    <div class="oa-head-row">
      <div class="oa-head-main">
        <span class="oa-thumb" id="oa-thumb" hidden></span>
        <div class="oa-head-t">
          <p class="org-k">Analiză activitate</p>
          <h1 class="oa-h" id="oa-title">Analiză activitate</h1>
          <p class="oa-info" id="oa-info"></p>
        </div>
      </div>
      <div class="oa-actions" id="oa-actions"<?= $oaEventId === '' ? ' hidden' : '' ?>>
        <a class="btn btn-ghost" id="oa-report" href="/organizator/report<?= $oaEventId !== '' ? '/' . $oaEventId : '' ?>"><?= v2_ic('file-text') ?>Raport complet</a>
        <div class="oa-menu" id="oa-menu">
          <button class="btn btn-primary" type="button" id="oa-export" aria-haspopup="menu" aria-expanded="false" aria-controls="oa-export-menu" disabled><?= v2_ic('download-simple') ?><span data-label>Export</span><?= v2_ic('caret-down', 'ic oa-caret') ?></button>
          <div class="oa-menu-list" id="oa-export-menu" role="menu" aria-labelledby="oa-export" hidden>
            <button class="oa-menu-i" type="button" role="menuitem" tabindex="-1" data-export="csv"><?= v2_ic('file-csv') ?><span><b>Datele perioadei (CSV)</b><small id="oa-csv-p">Zile, bilete, trafic și locații</small></span></button>
            <button class="oa-menu-i" type="button" role="menuitem" tabindex="-1" data-export="pdf"><?= v2_ic('file-text') ?><span><b>Raportul activității (PDF)</b><small>Tot ce s-a vândut, de la început</small></span></button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <section class="org-panel oa-bar" aria-label="Activitatea și perioada">
    <div class="oa-combo" id="oa-combo">
      <label class="oa-f-l" for="oa-event-q" id="oa-pick-l">Activitatea</label>
      <div class="oa-combo-box">
        <?= v2_ic('magnifying-glass', 'ic oa-combo-ic') ?>
        <input id="oa-event-q" type="text" role="combobox" aria-expanded="false" aria-controls="oa-event-list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="Caută o activitate">
        <button class="oa-combo-x" type="button" id="oa-event-clear" aria-label="Golește căutarea" hidden><?= v2_ic('x') ?></button>
        <?= v2_ic('caret-down', 'ic oa-combo-caret') ?>
      </div>
      <ul class="oa-combo-list" id="oa-event-list" role="listbox" aria-labelledby="oa-pick-l" hidden></ul>
    </div>
    <div class="oa-period">
      <span class="oa-f-l" id="oa-period-l">Perioada</span>
      <div class="oa-seg" role="group" aria-labelledby="oa-period-l">
        <?php foreach ($oaPeriods as $oaKey => $oaLabel): ?><button class="oa-seg-b" type="button" data-period="<?= $oaKey ?>" aria-pressed="<?= $oaKey === $oaPeriod ? 'true' : 'false' ?>"><?= $oaLabel ?></button><?php endforeach; ?>
      </div>
    </div>
    <div class="oa-bar-end">
      <span class="oa-live" id="oa-live" hidden><i aria-hidden="true"></i><span id="oa-live-n"></span></span>
      <button class="btn btn-ghost oa-map-btn" type="button" id="oa-map-open" disabled><?= v2_ic('map-trifold') ?>Harta vizitatorilor</button>
    </div>
    <p class="oa-scope">Totalurile sunt de la începutul vânzărilor. Graficul, estimările, trendul biletelor, sursele de trafic și locațiile urmează perioada aleasă.</p>
  </section>

  <section class="org-panel oa-pickall" id="oa-pickall" aria-labelledby="oa-pickall-h" hidden>
    <div class="org-panel-head">
      <div><p class="org-k">Pentru început</p><h2 class="org-panel-h" id="oa-pickall-h">Alege o activitate</h2><p class="org-panel-p">Analiza se deschide pentru o activitate anume. Caut-o mai sus sau alege una de aici.</p></div>
      <a class="org-more" href="/organizator/activities">Toate activitățile<?= v2_ic('arrow-right') ?></a>
    </div>
    <ul class="oa-evlist" id="oa-evlist"><li><span class="org-skel oa-sk-row"></span></li></ul>
  </section>

  <div class="org-empty oa-none" id="oa-none" hidden></div>

  <div class="oa-body" id="oa-body"<?= $oaEventId === '' ? ' hidden' : '' ?>>
    <section class="oa-stats" aria-labelledby="oa-stats-h">
      <h2 class="sr" id="oa-stats-h">Pe scurt</h2>
      <?= $oaStat('revenue', 'Venituri nete', 'coins', '<button class="oa-stat-link" type="button" id="oa-s-revenue-set" hidden>' . v2_ic('plus') . 'Setează un obiectiv de venituri</button>') ?>
      <?= $oaStat('tickets', 'Bilete vândute', 'ticket') ?>
      <?= $oaStat('views', 'Vizualizări', 'eye') ?>
      <?= $oaStat('conversion', 'Rată conversie', 'chart-line-up') ?>
      <?= $oaStat('days', 'Până la activitate', 'calendar-blank') ?>
    </section>

    <div class="oa-grid is-wide">
      <section class="org-panel oa-chart oa-dep" id="oa-chart" aria-labelledby="oa-chart-h">
        <div class="org-panel-head">
          <div><p class="org-k">În timp</p><h2 class="org-panel-h" id="oa-chart-h">Performanță vânzări</h2><p class="org-panel-p" id="oa-chart-p"></p></div>
          <div class="oa-toggles" role="group" aria-label="Ce arată graficul">
            <button class="oa-tg is-rev" type="button" data-metric="rev" aria-pressed="true"><i aria-hidden="true"></i>Venit net pe zi</button>
            <button class="oa-tg is-tix" type="button" data-metric="tix" aria-pressed="true"><i aria-hidden="true"></i>Bilete vândute</button>
          </div>
        </div>
        <div class="oa-plot" id="oa-plot" tabindex="0" role="group" aria-roledescription="grafic" aria-label="Grafic vânzări">
          <div class="oa-tip" id="oa-tip" hidden></div>
          <p class="oa-plot-msg" id="oa-plot-msg" hidden>Nicio vânzare în perioada aleasă.</p>
        </div>
        <p class="sr" id="oa-plot-live" aria-live="polite"></p>
        <ol class="oa-marks" id="oa-marks" aria-label="Campaniile din grafic" hidden></ol>
        <dl class="oa-totals" id="oa-totals"></dl>
        <p class="oa-chart-note">Vizualizările nu sunt măsurate pe zile, așa că apar doar ca total, mai sus.</p>
      </section>

      <section class="oa-fc oa-dep" id="oa-fc" aria-labelledby="oa-fc-h">
        <div class="oa-fc-head"><span class="oa-fc-ic"><?= v2_ic('trend-up') ?></span><div><h2 class="oa-fc-h" id="oa-fc-h">Estimări</h2><p class="oa-fc-p">După ritmul vânzărilor din perioada aleasă; ultimele 7 zile cântăresc mai mult.</p></div></div>
        <div class="oa-fc-box"><p class="oa-fc-k" id="oa-fc-next-k">Următoarele 7 zile</p><dl class="oa-fc-grid"><div><dt>Venit net estimat</dt><dd class="is-net" id="oa-fc-rev">—</dd></div><div><dt>Bilete estimate</dt><dd id="oa-fc-tix">—</dd></div></dl></div>
        <div class="oa-fc-box" id="oa-fc-end"><p class="oa-fc-k" id="oa-fc-end-k">La data activității</p><dl class="oa-fc-grid"><div><dt>Venit net total</dt><dd class="is-net" id="oa-fc-trev">—</dd></div><div><dt>Bilete în total</dt><dd id="oa-fc-ttix">—</dd></div></dl></div>
        <p class="oa-fc-note" id="oa-fc-note"></p>
      </section>
    </div>

    <div class="oa-grid is-wide">
      <section class="org-panel oa-dep" id="oa-types-panel" aria-labelledby="oa-types-h">
        <div class="org-panel-head"><div><p class="org-k">Bilete</p><h2 class="org-panel-h" id="oa-types-h">Performanță tipuri bilete</h2><p class="org-panel-p" id="oa-types-p"></p></div></div>
        <div class="oa-table-wrap">
          <table class="oa-table" id="oa-table">
            <caption class="sr">Vânzările fiecărui tip de bilet</caption>
            <thead><tr><th scope="col">Tip bilet</th><th scope="col" class="is-num">Preț</th><th scope="col" class="is-num">Vândute</th><th scope="col" class="is-num">Venituri</th><th scope="col" class="is-num is-conv">Conversie</th><th scope="col" class="is-num is-trend">Trend</th></tr></thead>
            <tbody id="oa-types"><tr><td colspan="6"><span class="org-skel oa-sk-row"></span></td></tr></tbody>
            <tfoot id="oa-types-foot"></tfoot>
          </table>
        </div>
      </section>

      <section class="org-panel" aria-labelledby="oa-camp-h">
        <div class="org-panel-head"><div><p class="org-k">Promovare</p><h2 class="org-panel-h" id="oa-camp-h">Campanii și ROI</h2></div><button class="btn btn-ghost oa-add" type="button" id="oa-camp-add" disabled><?= v2_ic('plus') ?>Adaugă</button></div>
        <div class="oa-cards" id="oa-camps"><span class="org-skel oa-sk-row"></span></div>
      </section>
    </div>

    <div class="oa-grid">
      <section class="org-panel oa-dep" id="oa-traffic-panel" aria-labelledby="oa-traffic-h">
        <div class="org-panel-head"><div><p class="org-k">De unde vin</p><h2 class="org-panel-h" id="oa-traffic-h">Surse de trafic</h2><p class="org-panel-p" id="oa-traffic-p"></p></div></div>
        <ul class="oa-bars" id="oa-traffic"><li><span class="org-skel oa-sk-row"></span></li></ul>
      </section>
      <section class="org-panel oa-dep" id="oa-loc-panel" aria-labelledby="oa-loc-h">
        <div class="org-panel-head"><div><p class="org-k">Unde sunt</p><h2 class="org-panel-h" id="oa-loc-h">Locații top</h2><p class="org-panel-p" id="oa-loc-p"></p></div><button class="org-more oa-linkbtn" type="button" id="oa-loc-map" disabled><?= v2_ic('map-trifold') ?>Vezi pe hartă</button></div>
        <ol class="oa-rank" id="oa-locations"><li><span class="org-skel oa-sk-row"></span></li></ol>
      </section>
    </div>

    <div class="oa-grid is-wide">
      <section class="org-panel" aria-labelledby="oa-sales-h">
        <div class="org-panel-head"><div><p class="org-k">Vânzări</p><h2 class="org-panel-h" id="oa-sales-h">Vânzări recente</h2><p class="org-panel-p">Ultimele comenzi plătite, cu suma achitată de client.</p></div><a class="org-more" id="oa-all-sales" href="/organizator/vanzari">Toate vânzările<?= v2_ic('arrow-right') ?></a></div>
        <ul class="oa-sales" id="oa-sales"><li><span class="org-skel oa-sk-row"></span></li></ul>
      </section>
      <section class="org-panel" aria-labelledby="oa-goals-h">
        <div class="org-panel-head"><div><p class="org-k">Ținte</p><h2 class="org-panel-h" id="oa-goals-h">Obiective</h2></div><button class="btn btn-ghost oa-add" type="button" id="oa-goal-add" disabled><?= v2_ic('plus') ?>Adaugă</button></div>
        <div class="oa-cards" id="oa-goals"><span class="org-skel oa-sk-row"></span></div>
      </section>
    </div>
  </div>

  <dialog class="oa-dialog" id="oa-goal-d" aria-labelledby="oa-goal-h">
    <form class="oa-d-inner" id="oa-goal-form" novalidate>
      <div class="oa-d-head"><h2 class="oa-d-h" id="oa-goal-h">Obiectiv nou</h2><?= $oaX ?></div>
      <label class="oa-f">
        <span class="oa-f-l">Ce urmărești</span>
        <span class="oa-select"><select id="oa-goal-type" aria-describedby="oa-goal-type-help"><option value="revenue">Venituri nete</option><option value="tickets">Bilete vândute</option><option value="visitors">Vizitatori</option><option value="conversion_rate">Rată de conversie</option></select><?= v2_ic('caret-down') ?></span>
        <span class="oa-help" id="oa-goal-type-help"></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l">Numele obiectivului</span>
        <input id="oa-goal-name" type="text" maxlength="255" autocomplete="off" aria-describedby="oa-goal-name-err">
        <span class="oa-err" id="oa-goal-name-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l" id="oa-goal-target-l">Ținta</span>
        <span class="oa-amount"><input id="oa-goal-target" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-goal-target-err"><span id="oa-goal-unit" aria-hidden="true">lei</span></span>
        <span class="oa-err" id="oa-goal-target-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l">Termen (opțional)</span>
        <input id="oa-goal-deadline" type="date" aria-describedby="oa-goal-deadline-help oa-goal-deadline-err">
        <span class="oa-help" id="oa-goal-deadline-help"></span>
        <span class="oa-err" id="oa-goal-deadline-err" hidden></span>
      </label>
      <div class="oa-form-err" id="oa-goal-err" role="alert" hidden></div>
      <div class="oa-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="oa-goal-go"><span data-label>Adaugă obiectivul</span></button></div>
    </form>
  </dialog>

  <dialog class="oa-dialog is-small" id="oa-del-d" aria-labelledby="oa-del-h" aria-describedby="oa-del-p">
    <div class="oa-d-inner">
      <h2 class="oa-d-h" id="oa-del-h">Ștergi?</h2>
      <p class="oa-d-p" id="oa-del-p"></p>
      <div class="oa-form-err" id="oa-del-err" role="alert" hidden></div>
      <div class="oa-d-act"><button class="btn btn-ghost" type="button" data-close>Renunță</button><button class="btn oa-danger" type="button" id="oa-del-go"><span data-label>Șterge</span></button></div>
    </div>
  </dialog>

  <dialog class="oa-dialog is-wide" id="oa-camp-d" aria-labelledby="oa-campd-h">
    <form class="oa-d-inner" id="oa-camp-form" novalidate>
      <div class="oa-d-head"><h2 class="oa-d-h" id="oa-campd-h">Campanie nouă</h2><?= $oaX ?></div>
      <label class="oa-f">
        <span class="oa-f-l">Numele campaniei</span>
        <input id="oa-camp-title" type="text" maxlength="255" autocomplete="off" placeholder="ex: Reclamă Facebook, septembrie" aria-describedby="oa-camp-title-err">
        <span class="oa-err" id="oa-camp-title-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l">Tipul</span>
        <span class="oa-select"><select id="oa-camp-type" aria-describedby="oa-camp-type-help">
          <optgroup label="Reclame plătite"><option value="campaign_fb">Facebook Ads</option><option value="campaign_instagram">Instagram Ads</option><option value="campaign_google">Google Ads</option><option value="campaign_tiktok">TikTok Ads</option><option value="campaign_other">Influencer sau alte reclame</option></optgroup>
          <optgroup label="Alte acțiuni"><option value="email">Campanie email</option><option value="price">Schimbare de preț</option><option value="announcement">Anunț</option><option value="press">Comunicat de presă</option><option value="lineup">Program nou sau invitați noi</option><option value="custom">Altceva</option></optgroup>
        </select><?= v2_ic('caret-down') ?></span>
        <span class="oa-help" id="oa-camp-type-help"></span>
      </label>
      <div class="oa-f-row">
        <label class="oa-f"><span class="oa-f-l">Începe pe</span><input id="oa-camp-start" type="date" aria-describedby="oa-camp-start-err"><span class="oa-err" id="oa-camp-start-err" hidden></span></label>
        <label class="oa-f"><span class="oa-f-l">Se termină pe (opțional)</span><input id="oa-camp-end" type="date" aria-describedby="oa-camp-end-err"><span class="oa-err" id="oa-camp-end-err" hidden></span></label>
      </div>
      <p class="oa-help" id="oa-camp-dates-help"></p>
      <label class="oa-f">
        <span class="oa-f-l">Buget (opțional)</span>
        <span class="oa-amount"><input id="oa-camp-budget" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-camp-budget-help oa-camp-budget-err"><span aria-hidden="true">lei</span></span>
        <span class="oa-help" id="oa-camp-budget-help">Cu un buget, calculăm ROI-ul campaniei.</span>
        <span class="oa-err" id="oa-camp-budget-err" hidden></span>
      </label>
      <label class="oa-f">
        <span class="oa-f-l">Descriere (opțional)</span>
        <textarea id="oa-camp-desc" rows="3" maxlength="2000"></textarea>
      </label>
      <fieldset class="oa-fs" id="oa-camp-track">
        <legend>Urmărire în linkuri</legend>
        <label class="oa-f"><span class="oa-f-l">ID-ul campaniei în platformă (opțional)</span><input id="oa-camp-pid" type="text" maxlength="100" autocomplete="off" spellcheck="false" placeholder="ex: 120215478965421"></label>
        <div class="oa-f-row">
          <label class="oa-f"><span class="oa-f-l">utm_source</span><input id="oa-camp-utm_source" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
          <label class="oa-f"><span class="oa-f-l">utm_medium</span><input id="oa-camp-utm_medium" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
          <label class="oa-f"><span class="oa-f-l">utm_campaign</span><input id="oa-camp-utm_campaign" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
          <label class="oa-f"><span class="oa-f-l">utm_content (opțional)</span><input id="oa-camp-utm_content" type="text" maxlength="100" autocomplete="off" spellcheck="false"></label>
        </div>
        <p class="oa-help">Pune aceiași parametri în linkul reclamei, ca vânzările să fie atribuite campaniei. Ce lași gol completăm noi: sursa și mediul după tip, numele din titlul campaniei.</p>
      </fieldset>
      <fieldset class="oa-fs" id="oa-camp-impact" hidden>
        <legend>Impact (opțional)</legend>
        <label class="oa-f"><span class="oa-f-l">Ce a influențat</span><span class="oa-select"><select id="oa-camp-metric"><option value="">Nu aleg</option><option value="tickets_sold">Bilete vândute</option><option value="page_views">Vizualizări ale paginii</option><option value="revenue">Venituri</option><option value="conversion_rate">Rată de conversie</option></select><?= v2_ic('caret-down') ?></span></label>
        <div class="oa-f-row">
          <label class="oa-f"><span class="oa-f-l">Valoarea înainte</span><input id="oa-camp-base" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-camp-base-err"><span class="oa-err" id="oa-camp-base-err" hidden></span></label>
          <label class="oa-f"><span class="oa-f-l">Valoarea după</span><input id="oa-camp-post" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oa-camp-post-err"><span class="oa-err" id="oa-camp-post-err" hidden></span></label>
        </div>
      </fieldset>
      <fieldset class="oa-fs" id="oa-camp-results" hidden>
        <legend>Rezultate din platformă</legend>
        <div class="oa-f-row">
          <label class="oa-f"><span class="oa-f-l">Afișări</span><input id="oa-camp-impr" type="text" inputmode="numeric" autocomplete="off" aria-describedby="oa-camp-impr-err"><span class="oa-err" id="oa-camp-impr-err" hidden></span></label>
          <label class="oa-f"><span class="oa-f-l">Clickuri</span><input id="oa-camp-clicks" type="text" inputmode="numeric" autocomplete="off" aria-describedby="oa-camp-clicks-err"><span class="oa-err" id="oa-camp-clicks-err" hidden></span></label>
        </div>
        <p class="oa-help">Conversiile și veniturile atribuite se recalculează singure la fiecare 30 de minute, din vizitele venite cu parametrii UTM ai campaniei.</p>
      </fieldset>
      <label class="oa-check" id="oa-camp-active-f" hidden><input type="checkbox" id="oa-camp-active"><span>Campania este activă</span></label>
      <div class="oa-form-err" id="oa-camp-err" role="alert" hidden></div>
      <div class="oa-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="oa-camp-go"><span data-label>Adaugă campania</span></button></div>
    </form>
  </dialog>

  <dialog class="oa-dialog is-map" id="oa-map-d" aria-labelledby="oa-map-h" aria-describedby="oa-map-p">
    <div class="oa-d-inner">
      <div class="oa-d-head"><div><h2 class="oa-d-h" id="oa-map-h">Harta vizitatorilor</h2><p class="oa-d-p" id="oa-map-p"></p></div><?= $oaX ?></div>
      <div class="oa-map-body">
        <figure class="oa-map">
          <svg class="oa-map-svg" id="oa-map-svg" viewBox="0 0 694.7 510" role="img" aria-labelledby="oa-map-cap"><?= $oaMap ?><g id="oa-map-dots"></g></svg>
          <figcaption class="oa-map-cap" id="oa-map-cap"></figcaption>
        </figure>
        <div class="oa-map-side">
          <p class="oa-map-sum" id="oa-map-sum"></p>
          <ol class="oa-rank is-map" id="oa-map-list"></ol>
          <p class="oa-help" id="oa-map-note"></p>
        </div>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';

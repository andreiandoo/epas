<?php
/**
 * Organizer promo codes: /organizator/promo (promo.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: the figures (active codes, uses, discount
 * given, order value), search and the state filter, the code cards (state, code with copy, discount, activity, uses and
 * limit, expiry, the admin badge) with the "new code" tile, and the create / edit window (code with a generator,
 * percentage or fixed amount, activity, ticket types, total and per-customer limits, start and end dates).
 * org-promo.js reads /organizer/promo-codes, /organizer/events and each activity's ticket types through the proxy.
 *
 * Fixed on the way:
 * - editing sent the code, discount and activity, which core ignores on update: they are shown locked, with why;
 * - "Venituri generate" was always 0 and "Reduceri acordate" multiplied uses by the value (wrong for percentages):
 *   both now add up each code's /stats (discount given, order value);
 * - a code "valid until" a day stopped at 03:00 that day (core keeps UTC): days are sent as Bucharest 00:00-23:59:59;
 * - the "Dezactivate" filter matched nothing (core says inactive), expired / used-up / not-yet-started codes looked
 *   active, a limit of 0 was accepted by the form and refused by core;
 * - new: pause and reactivate a code, the orders that used it, minimum order / maximum discount / minimum tickets;
 * - errors came as browser alerts or English API text, deleting asked with confirm().
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Promo codes');
$pageDescription = v2_t('The promo codes of an operator on Viaqui: creating them, limits, periods and uses.');
$canonicalUrl = SITE_URL . '/organizator/promo';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-promo.css'];
$v2Scripts = ['organizer.js', 'org-promo.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$opStat = function (string $key, string $label, string $icon, string $note = '') {
    return '<article class="op-stat"><span class="op-stat-ic">' . v2_ic($icon) . '</span><div><p class="op-stat-k">' . $label . '</p>'
        . '<p class="op-stat-v" id="op-s-' . $key . '"><span class="org-skel op-sk"></span></p>'
        . ($note !== '' ? '<p class="op-stat-p">' . $note . '</p>' : '') . '</div></article>';
};
$opX = '<button class="op-x" type="button" data-close aria-label="' . v2_te('Close') . '">' . v2_ic('x') . '</button>';
$opErr = function (string $id) { return '<span class="op-err" id="' . $id . '-err" hidden></span>'; };

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('promo');
?>
<div class="op" id="op">
  <header class="op-head">
    <div class="op-head-t">
      <p class="org-k"><?= v2_te('Marketing') ?></p>
      <h1 class="op-h"><?= v2_te('Promo codes') ?></h1>
      <p class="op-lead"><?= v2_te('Create and manage promo codes for your experiences.') ?></p>
    </div>
    <div class="op-actions"><button class="btn btn-primary" type="button" id="op-add" disabled><?= v2_ic('plus') ?><?= v2_te('New code') ?></button></div>
  </header>

  <section class="op-stats" aria-label="<?= v2_te('At a glance') ?>">
    <?= $opStat('active', v2_te('Active codes'), 'tag', v2_te('Can be used now')) ?>
    <?= $opStat('uses', v2_te('Uses'), 'check-circle', v2_te('Orders with a code, in total')) ?>
    <?= $opStat('discount', v2_te('Discounts given'), 'receipt', v2_te('The amount taken off orders')) ?>
    <?= $opStat('revenue', v2_te('Revenue generated'), 'chart-line-up', v2_te('The value of orders with a code')) ?>
  </section>
  <p class="op-note" id="op-s-note" hidden></p>

  <div class="org-empty is-error" id="op-load-err" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the codes') ?></b>
    <p><?= v2_te('Check your connection and try again.') ?></p>
    <div class="op-empty-cta"><button class="btn btn-primary" type="button" id="op-retry"><?= v2_te('Try again') ?></button></div>
  </div>

  <section class="org-panel op-panel" id="op-panel" aria-labelledby="op-list-h">
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Codes') ?></p><h2 class="org-panel-h" id="op-list-h"><?= v2_te('Your codes') ?></h2><p class="org-panel-p" id="op-list-p"></p></div>
      <div class="op-filters">
        <label class="op-search"><?= v2_ic('magnifying-glass') ?><input id="op-q" type="search" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Search codes…') ?>" aria-label="<?= v2_te('Search by code or experience') ?>" aria-controls="op-grid"></label>
        <span class="op-select"><select id="op-status" aria-label="<?= v2_te('Show codes by state') ?>" aria-controls="op-grid">
          <option value=""><?= v2_te('All') ?></option>
          <option value="active"><?= v2_te('Active') ?></option>
          <option value="scheduled"><?= v2_te('Scheduled') ?></option>
          <option value="expired"><?= v2_te('Expired') ?></option>
          <option value="exhausted"><?= v2_te('Used up') ?></option>
          <option value="inactive"><?= v2_te('Paused') ?></option>
        </select><?= v2_ic('caret-down') ?></span>
      </div>
    </div>
    <ul class="op-grid" id="op-grid" aria-live="polite">
      <li class="op-sk-card"><span class="org-skel"></span></li><li class="op-sk-card"><span class="org-skel"></span></li><li class="op-sk-card"><span class="org-skel"></span></li>
    </ul>
  </section>

  <dialog class="op-dialog is-wide" id="op-code-d" aria-labelledby="op-code-h">
    <form class="op-d-inner" id="op-code-form" novalidate>
      <div class="op-d-head"><div><h2 class="op-d-h" id="op-code-h"><?= v2_te('New promo code') ?></h2><p class="op-d-p" id="op-code-p"></p></div><?= $opX ?></div>

      <fieldset class="op-lock" id="op-fixed">
        <legend class="op-sr"><?= v2_te('The code and the discount') ?></legend>
        <div class="op-grid2">
          <div class="op-f op-wide">
            <label class="op-f-l" for="op-code"><?= v2_te('Promo code') ?></label>
            <span class="op-codefield">
              <input id="op-code" type="text" maxlength="50" autocomplete="off" spellcheck="false" autocapitalize="characters" placeholder="<?= v2_te('e.g. SUMMER2026') ?>" aria-describedby="op-code-help op-code-err">
              <button class="op-gen" type="button" id="op-gen"><?= v2_ic('arrow-counter-clockwise') ?><?= v2_te('Generate') ?></button>
            </span>
            <span class="op-help" id="op-code-help"><?= v2_te('3–50 characters: unaccented letters, digits, - or _.') ?></span>
            <?= $opErr('op-code') ?>
          </div>
          <fieldset class="op-fs op-wide" id="op-type">
            <legend><?= v2_te('Discount type') ?></legend>
            <div class="op-types">
              <label class="op-type"><input type="radio" name="op-type" value="percentage" checked><span><b><?= v2_te('Percentage') ?></b><small><?= v2_te('E.g. 10% off') ?></small></span></label>
              <label class="op-type"><input type="radio" name="op-type" value="fixed"><span><b><?= v2_te('Fixed amount') ?></b><small><?= v2_te('E.g. {amount} off', ['amount' => v2_money(10)]) ?></small></span></label>
            </div>
          </fieldset>
          <div class="op-f">
            <label class="op-f-l" for="op-value"><?= v2_te('Discount value') ?></label>
            <span class="op-suffix"><input id="op-value" type="number" inputmode="decimal" min="0.01" max="100" step="0.01" aria-describedby="op-value-err"><span id="op-value-suffix" aria-hidden="true">%</span></span>
            <?= $opErr('op-value') ?>
          </div>
          <div class="op-f">
            <label class="op-f-l" for="op-event"><?= v2_te('Experience') ?></label>
            <span class="op-select"><select id="op-event" aria-describedby="op-event-help op-event-err"><option value=""><?= v2_te('Choose an experience') ?></option></select><?= v2_ic('caret-down') ?></span>
            <span class="op-help" id="op-event-help"><?= v2_te('The code applies only to this experience.') ?></span>
            <?= $opErr('op-event') ?>
          </div>
        </div>
      </fieldset>

      <fieldset class="op-fs" id="op-tts">
        <legend><?= v2_te('Ticket types') ?></legend>
        <div class="op-tts-head">
          <span class="op-help" id="op-tts-n" aria-live="polite"><?= v2_te('Tick one or more. The code works only for the ticked types.') ?></span>
          <span class="op-tts-all" id="op-tts-all" hidden><button class="op-linkbtn" type="button" data-tts="all"><?= v2_te('All') ?></button><button class="op-linkbtn" type="button" data-tts="none"><?= v2_te('None') ?></button></span>
        </div>
        <ul class="op-tt-list" id="op-tt-list"><li class="op-msg"><?= v2_te('Choose the experience first.') ?></li></ul>
        <?= $opErr('op-tts') ?>
      </fieldset>

      <div class="op-grid2">
        <div class="op-f">
          <label class="op-f-l" for="op-limit"><?= v2_te('Total use limit') ?></label>
          <input id="op-limit" type="number" inputmode="numeric" min="1" step="1" placeholder="<?= v2_te('Unlimited') ?>" aria-describedby="op-limit-help op-limit-err">
          <span class="op-help" id="op-limit-help"><?= v2_te('How many orders can use the code, in total.') ?></span>
          <?= $opErr('op-limit') ?>
        </div>
        <div class="op-f">
          <label class="op-f-l" for="op-limit-cust"><?= v2_te('Limit per customer') ?></label>
          <input id="op-limit-cust" type="number" inputmode="numeric" min="1" step="1" placeholder="<?= v2_te('Unlimited') ?>" aria-describedby="op-limit-cust-help op-limit-cust-err">
          <span class="op-help" id="op-limit-cust-help"><?= v2_te('How many times the same customer can use it.') ?></span>
          <?= $opErr('op-limit-cust') ?>
        </div>
        <div class="op-f">
          <label class="op-f-l" for="op-start"><?= v2_te('Start date') ?></label>
          <input id="op-start" type="date" aria-describedby="op-start-help op-start-err">
          <span class="op-help" id="op-start-help"><?= v2_te('From 00:00.') ?></span>
          <?= $opErr('op-start') ?>
        </div>
        <div class="op-f">
          <label class="op-f-l" for="op-end"><?= v2_te('End date') ?></label>
          <input id="op-end" type="date" aria-describedby="op-end-help op-end-err">
          <span class="op-help" id="op-end-help"><?= v2_te('The code works until the end of this day.') ?></span>
          <?= $opErr('op-end') ?>
        </div>
      </div>

      <details class="op-more" id="op-more">
        <summary><?= v2_ic('caret-down') ?><?= v2_te('Optional conditions') ?></summary>
        <div class="op-grid2">
          <div class="op-f">
            <label class="op-f-l" for="op-min-amount"><?= v2_te('Minimum order') ?></label>
            <span class="op-suffix"><input id="op-min-amount" type="number" inputmode="decimal" min="0" step="0.01" placeholder="<?= v2_te('No minimum') ?>" aria-describedby="op-min-amount-err"><span class="op-cur" aria-hidden="true"><?= v2_e(defined('SITE_CURRENCY_SYMBOL') ? SITE_CURRENCY_SYMBOL : '€') ?></span></span>
            <?= $opErr('op-min-amount') ?>
          </div>
          <div class="op-f" id="op-max-f">
            <label class="op-f-l" for="op-max-disc"><?= v2_te('Maximum discount') ?></label>
            <span class="op-suffix"><input id="op-max-disc" type="number" inputmode="decimal" min="0" step="0.01" placeholder="<?= v2_te('No cap') ?>" aria-describedby="op-max-disc-help op-max-disc-err"><span class="op-cur" aria-hidden="true"><?= v2_e(defined('SITE_CURRENCY_SYMBOL') ? SITE_CURRENCY_SYMBOL : '€') ?></span></span>
            <span class="op-help" id="op-max-disc-help"><?= v2_te('The cap of the percentage discount, per order.') ?></span>
            <?= $opErr('op-max-disc') ?>
          </div>
          <div class="op-f">
            <label class="op-f-l" for="op-min-tickets"><?= v2_te('Minimum tickets in the order') ?></label>
            <input id="op-min-tickets" type="number" inputmode="numeric" min="1" step="1" placeholder="<?= v2_te('No minimum') ?>" aria-describedby="op-min-tickets-err">
            <?= $opErr('op-min-tickets') ?>
          </div>
        </div>
      </details>

      <div class="op-form-err" id="op-form-err" role="alert" hidden></div>
      <div class="op-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="op-code-go"><span data-label><?= v2_te('Create the code') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="op-dialog is-wide" id="op-usage-d" aria-labelledby="op-usage-h">
    <div class="op-d-inner">
      <div class="op-d-head"><div><h2 class="op-d-h" id="op-usage-h"><?= v2_te('Uses of the code') ?></h2><p class="op-d-p" id="op-usage-p"></p></div><?= $opX ?></div>
      <dl class="op-ustats">
        <div><dt><?= v2_te('Uses') ?></dt><dd id="op-u-uses">—</dd></div>
        <div><dt><?= v2_te('Unique customers') ?></dt><dd id="op-u-customers">—</dd></div>
        <div><dt><?= v2_te('Discount given') ?></dt><dd id="op-u-discount">—</dd></div>
        <div><dt><?= v2_te('Order value') ?></dt><dd id="op-u-orders">—</dd></div>
      </dl>
      <ul class="op-uses" id="op-uses" tabindex="-1" aria-live="polite"></ul>
      <div class="op-more-row"><button class="btn btn-ghost op-sm" type="button" id="op-usage-more" hidden><span data-label><?= v2_te('Load more') ?></span></button></div>
      <div class="op-d-act"><button class="btn btn-primary" type="button" data-close><?= v2_te('Close') ?></button></div>
    </div>
  </dialog>

  <dialog class="op-dialog is-small" id="op-del-d" aria-labelledby="op-del-h" aria-describedby="op-del-p">
    <div class="op-d-inner">
      <h2 class="op-d-h" id="op-del-h"><?= v2_te('Delete the code?') ?></h2>
      <p class="op-d-p" id="op-del-p"><?= v2_te('The code can no longer be used on new orders. Orders that used it stay as they are.') ?></p>
      <div class="op-form-err" id="op-del-err" role="alert" hidden></div>
      <div class="op-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Keep it') ?></button><button class="btn op-danger" type="button" id="op-del-go"><span data-label><?= v2_te('Delete the code') ?></span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';

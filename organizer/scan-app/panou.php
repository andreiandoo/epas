<?php
$scanPage      = 'panou';
$scanPageTitle = 'Panou';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Event hero (date + countdown badge + name + venue meta) -->
  <div class="scanapp-event-header" id="scanapp-event-header">
    <div class="scanapp-event-date-row">
      <div class="scanapp-event-date" id="scanapp-event-date">Selectează un eveniment</div>
      <div class="scanapp-countdown" id="scanapp-countdown" hidden>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span id="scanapp-countdown-text">—</span>
      </div>
    </div>
    <div class="scanapp-event-name" id="scanapp-event-name-hero">—</div>
    <div class="scanapp-event-meta" id="scanapp-event-venue-hero">—</div>
  </div>

  <!-- Reports-only banner (shown when event is in the past) -->
  <div class="scanapp-reports-banner" id="scanapp-reports-banner" hidden>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--scanapp-amber); flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div class="scanapp-reports-banner__text">Acest eveniment s-a încheiat. Doar rapoartele sunt disponibile.</div>
  </div>

  <!-- ========================================================================
       ADMIN VIEW (owner/admin role) — primary stat, 2x2 grid, quick actions,
       recent activity, close shift.
  ======================================================================== -->
  <div id="scanapp-admin-view" hidden>

    <!-- Reports-only mode: show 2x2 colored summary instead of live stats -->
    <div class="scanapp-reports-grid" id="scanapp-reports-grid" hidden>
      <div class="scanapp-reports-card" style="background: var(--scanapp-purple-bg); border-color: var(--scanapp-purple-border);">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-purple)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
        <div class="scanapp-reports-card__value" style="color: var(--scanapp-purple);" id="scanapp-ro-sold">0</div>
        <div class="scanapp-reports-card__label">Total Vândute</div>
      </div>
      <div class="scanapp-reports-card" style="background: var(--scanapp-green-bg); border-color: var(--scanapp-green-border);">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <div class="scanapp-reports-card__value" style="color: var(--scanapp-green);" id="scanapp-ro-checked">0</div>
        <div class="scanapp-reports-card__label">Intrați</div>
      </div>
      <div class="scanapp-reports-card" style="background: var(--scanapp-cyan-bg); border-color: var(--scanapp-cyan-border);">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-cyan)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        <div class="scanapp-reports-card__value" style="color: var(--scanapp-cyan);" id="scanapp-ro-revenue">0 lei</div>
        <div class="scanapp-reports-card__label">Venituri</div>
      </div>
      <div class="scanapp-reports-card" style="background: var(--scanapp-amber-bg); border-color: var(--scanapp-amber-border);">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-amber)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
        <div class="scanapp-reports-card__value" style="color: var(--scanapp-amber);" id="scanapp-ro-rate">0%</div>
        <div class="scanapp-reports-card__label">Rata Check-in</div>
      </div>
    </div>

    <!-- Live stats: mockup-driven 2×2 grid — Scanați / Vândute / Încasări
         / Disponibile. Every card is a button and reuses the existing
         data-modal handlers wired in panou.js (scanned → guest-list,
         sold → TicketSalesByType, revenue → SalesBreakdown,
         available → Remaining). Rate + progress bar moved off the
         primary card; the Capacitate section below keeps them. -->
    <div id="scanapp-live-stats">
      <div class="scanapp-stats-section">
      <div class="scanapp-mock-grid">
        <button type="button" class="scanapp-mock-card" data-modal="scanned">
          <div class="scanapp-mock-card__head">
            <span class="scanapp-mock-card__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span class="scanapp-mock-card__label">Scanați</span>
          </div>
          <div class="scanapp-mock-card__value" id="scanapp-stat-entered">0</div>
          <div class="scanapp-mock-card__unit">persoane</div>
        </button>

        <button type="button" class="scanapp-mock-card" data-modal="sold">
          <div class="scanapp-mock-card__head">
            <span class="scanapp-mock-card__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
            </span>
            <span class="scanapp-mock-card__label">Vândute</span>
          </div>
          <div class="scanapp-mock-card__value" id="scanapp-stat-sold">0</div>
          <div class="scanapp-mock-card__unit">bilete</div>
        </button>

        <button type="button" class="scanapp-mock-card" data-modal="revenue">
          <div class="scanapp-mock-card__head">
            <span class="scanapp-mock-card__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <span class="scanapp-mock-card__label">Încasări</span>
          </div>
          <div class="scanapp-mock-card__value" id="scanapp-stat-revenue">0 lei</div>
          <div class="scanapp-mock-card__unit">total</div>
        </button>

        <button type="button" class="scanapp-mock-card" data-modal="remaining">
          <div class="scanapp-mock-card__head">
            <span class="scanapp-mock-card__icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
            </span>
            <span class="scanapp-mock-card__label">Disponibile</span>
          </div>
          <div class="scanapp-mock-card__value" id="scanapp-stat-available">0</div>
          <div class="scanapp-mock-card__unit">bilete</div>
        </button>
      </div>

      <!-- Hidden legacy fields kept as no-op targets so panou.js's
           renderStats can still write to scanapp-stat-total / -remaining
           / -capacity / -checkedin-pct without a null-ref crash. The
           progress bar element is retained hidden too for the same
           reason — no display, no cost. -->
      <div hidden aria-hidden="true">
        <span id="scanapp-stat-total">0</span>
        <span id="scanapp-stat-remaining">0</span>
        <span id="scanapp-stat-capacity">0%</span>
        <span id="scanapp-checkedin-pct">0%</span>
        <span id="scanapp-entered-bar"></span>
      </div>
      </div><!-- /.scanapp-stats-section -->

      <!-- Capacitate — sold / total_capacity progress bar (semantic
           differs from the check-in bar above: this shows how full the
           event is regardless of who's already entered). -->
      <div class="scanapp-capacity-section" id="scanapp-capacity-section" hidden>
        <div class="scanapp-capacity-header">
          <span class="scanapp-capacity-title">Capacitate</span>
          <span class="scanapp-capacity-pct" id="scanapp-cap-pct">0%</span>
        </div>
        <div class="scanapp-capacity-bar-lg">
          <div class="scanapp-capacity-bar-lg__fill" id="scanapp-cap-fill" style="width: 0%"></div>
        </div>
        <div class="scanapp-capacity-sub">
          <span id="scanapp-cap-sold">0</span> / <span id="scanapp-cap-total">0</span> locuri
        </div>
      </div>

      <!-- Online vs. la ușă — stacked bar, click a segment to expand
           the per-ticket-type breakdown UNDER the bar. Second click on
           the same segment collapses. Populated by panou.js from
           stats.online_count / .door_count / .by_source_and_type. -->
      <div class="scanapp-online-door-section" id="scanapp-online-door-section" hidden>
        <div class="scanapp-section-title">Online vs. la ușă</div>
        <div class="scanapp-online-door-bar" id="scanapp-od-bar">
          <button type="button" class="scanapp-od-seg scanapp-od-seg--online" id="scanapp-od-online" style="flex-grow: 0;" hidden>
            <span class="scanapp-od-label">Online</span>
            <span class="scanapp-od-count"><span id="scanapp-online-cnt">0</span> (<span id="scanapp-online-pct">0</span>%)</span>
          </button>
          <button type="button" class="scanapp-od-seg scanapp-od-seg--door" id="scanapp-od-door" style="flex-grow: 0;" hidden>
            <span class="scanapp-od-label">La ușă</span>
            <span class="scanapp-od-count"><span id="scanapp-door-cnt">0</span> (<span id="scanapp-door-pct">0</span>%)</span>
          </button>
        </div>
        <div class="scanapp-od-expand" id="scanapp-od-expand" hidden>
          <div class="scanapp-od-expand-title" id="scanapp-od-expand-title">Tipuri de bilete</div>
          <div id="scanapp-od-expand-list"></div>
        </div>
      </div>

      <!-- Quick actions (4 column) -->
      <div class="scanapp-quick-actions-section">
      <div class="scanapp-section-title">Acțiuni Rapide</div>
      <div class="scanapp-quick-grid">
        <a class="scanapp-quick-action scanapp-quick-action--purple" href="/organizator/scan/scanare">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><line x1="21" y1="14" x2="21" y2="14.01"/><line x1="21" y1="21" x2="21" y2="21.01"/><line x1="17" y1="21" x2="17" y2="21.01"/></svg>
          Scanare
        </a>
        <a class="scanapp-quick-action scanapp-quick-action--green" href="/organizator/scan/vanzare">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          Vânzare
        </a>
        <a class="scanapp-quick-action scanapp-quick-action--cyan" href="/organizator/scan/guest-list">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          Listă Invitați
        </a>
        <a class="scanapp-quick-action scanapp-quick-action--amber" href="/organizator/scan/asignare-personal">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Echipă
        </a>
      </div>
      </div><!-- /.scanapp-quick-actions-section -->

      <!-- Recent activity -->
      <div class="scanapp-recent-section">
      <div class="scanapp-section-title">Activitate Recentă</div>
      <div id="scanapp-recent-activity">
        <div class="scanapp-recent-empty">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--scanapp-text-quaternary);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <div class="scanapp-recent-empty__text">Nicio activitate recentă</div>
        </div>
      </div>
      </div><!-- /.scanapp-recent-section -->

      <!-- Close shift (admin) -->
      <button type="button" class="scanapp-close-shift" id="scanapp-action-close-shift">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-red)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        Închide Tura
      </button>

      <!-- Pull-to-refresh hint. Data already refreshes on a 30s poll
           and via Reverb when connected, so this is purely a visual
           reminder that the user CAN also pull down for an on-demand
           refresh. Kept low-contrast so it doesn't compete with the
           real stats above. -->
      <div class="scanapp-pull-hint">↓ Trage în jos pentru refresh</div>
    </div>
  </div>

  <!-- ========================================================================
       SCANNER VIEW (staff/manager role) — turnover, my stats, big actions.
  ======================================================================== -->
  <div id="scanapp-scanner-view" hidden>

    <!-- Turnover card (Cash + Card) -->
    <div class="scanapp-turnover-card">
      <h3 class="scanapp-turnover-title">Încasări</h3>
      <div class="scanapp-turnover-row">
        <div class="scanapp-turnover-item">
          <div class="scanapp-turnover-icon-wrap" style="background: var(--scanapp-green-bg); color: var(--scanapp-green);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <div>
            <div class="scanapp-turnover-label">Numerar</div>
            <div class="scanapp-turnover-amount" style="color: var(--scanapp-green);" id="scanapp-shift-cash">0 lei</div>
          </div>
        </div>
        <div class="scanapp-turnover-divider"></div>
        <div class="scanapp-turnover-item">
          <div class="scanapp-turnover-icon-wrap" style="background: var(--scanapp-cyan-bg); color: var(--scanapp-cyan);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          </div>
          <div>
            <div class="scanapp-turnover-label">Card</div>
            <div class="scanapp-turnover-amount" style="color: var(--scanapp-cyan);" id="scanapp-shift-card">0 lei</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3-column scanner stats -->
    <div class="scanapp-scanner-stats">
      <div class="scanapp-scanner-stat scanapp-scanner-stat--purple">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-purple)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
        <div class="scanapp-scanner-stat__value" style="color: var(--scanapp-purple);" id="scanapp-shift-scans">0</div>
        <div class="scanapp-scanner-stat__label">Scanări</div>
      </div>
      <div class="scanapp-scanner-stat scanapp-scanner-stat--green">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <div class="scanapp-scanner-stat__value" style="color: var(--scanapp-green);" id="scanapp-shift-sales">0</div>
        <div class="scanapp-scanner-stat__label">Vânzări</div>
      </div>
      <div class="scanapp-scanner-stat scanapp-scanner-stat--cyan">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-cyan)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div class="scanapp-scanner-stat__value" style="color: var(--scanapp-cyan);" id="scanapp-shift-duration">—</div>
        <div class="scanapp-scanner-stat__label">Durată tură</div>
      </div>
    </div>

    <!-- Big action buttons -->
    <div class="scanapp-big-actions">
      <a class="scanapp-big-action scanapp-big-action--purple" href="/organizator/scan/scanare">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/></svg>
        Începe Scanarea
      </a>
      <a class="scanapp-big-action scanapp-big-action--green" href="/organizator/scan/vanzare">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Începe Vânzarea
      </a>
    </div>

    <button type="button" class="scanapp-close-shift" id="scanapp-scanner-close-shift">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-red)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      Închide Tura
    </button>
  </div>
</section>

<!-- Generic bottom-sheet container (used by stat-card modals) -->
<div class="scanapp-sheet-backdrop" id="scanapp-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title" id="scanapp-sheet-title">—</h2>
    <div id="scanapp-sheet-body"></div>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-sheet-close">Închide</button>
  </div>
</div>

<?php $scanPageScript = 'panou.js'; require __DIR__ . '/_layout_end.php'; ?>

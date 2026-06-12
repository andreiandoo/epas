  </main>

  <nav class="scanapp-tabbar" role="navigation" aria-label="Tab-uri principale">
<?php foreach ($tabs as $key => $tab):
    $isActive = ($scanPage === $key);
?>
    <a class="scanapp-tabbar__tab<?= $isActive ? ' scanapp-tabbar__tab--active' : '' ?>"
       href="<?= htmlspecialchars($tab['href']) ?>"
       aria-current="<?= $isActive ? 'page' : 'false' ?>">
      <span class="scanapp-tabbar__icon">
<?php switch ($key):
    case 'panou': ?>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"></rect>
          <rect x="14" y="3" width="7" height="7"></rect>
          <rect x="14" y="14" width="7" height="7"></rect>
          <rect x="3" y="14" width="7" height="7"></rect>
        </svg>
<?php break; case 'scanare': ?>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"></path>
          <circle cx="12" cy="13" r="4"></circle>
        </svg>
<?php break; case 'vanzare': ?>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="9" cy="21" r="1"></circle>
          <circle cx="20" cy="21" r="1"></circle>
          <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"></path>
        </svg>
<?php break; case 'rapoarte': ?>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 20V10M12 20V4M6 20v-6"></path>
        </svg>
<?php break; case 'setari-scan': ?>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"></path>
        </svg>
<?php break; endswitch; ?>
      </span>
      <span class="scanapp-tabbar__label"><?= htmlspecialchars($tab['label']) ?></span>
    </a>
<?php endforeach; ?>
  </nav>

  <!-- Toast container (used by JS for in-app feedback) -->
  <div class="scanapp-toasts" id="scanapp-toasts" aria-live="polite" aria-atomic="true"></div>

  <!-- Core scan-app JS bundle. Order matters: auth → contexts → app init.
       Page-specific scripts can listen for ScanApp.toast / EventContext.subscribe(). -->
  <script src="/assets/js/scan-app/auth.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/auth.js') ?>" defer></script>
  <script src="/assets/js/scan-app/app-context.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/app-context.js') ?>" defer></script>
  <script src="/assets/js/scan-app/event-context.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/event-context.js') ?>" defer></script>
  <script src="/assets/js/scan-app/app.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/app.js') ?>" defer></script>
</body>
</html>

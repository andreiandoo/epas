<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$pageTitle = ORG_NAME . ' — Bilete';

$orgData = api_cached('wl_org_' . ORG_SLUG, function () {
    return api_get('/marketplace-events/organizers/' . urlencode(ORG_SLUG));
}, 300);

$events = $orgData['data']['upcomingEvents'] ?? [];
$orgLocation = $orgData['data']['location'] ?? '';
$orgTagline = $orgData['data']['about'] ?? '';
$bp = BASE_PATH;

// Split events: first 3 = featured, rest = list
$featured = array_slice($events, 0, 3);
$upcoming = array_slice($events, 3);

// Helper: format date
function fmtDate($d) { return $d ? date('d M', strtotime($d)) : ''; }
function fmtDay($d) { return $d ? date('d', strtotime($d)) : ''; }
function fmtMonth($d) {
    $m = ['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Nov','Dec'];
    return $d ? $m[(int)date('m', strtotime($d)) - 1] : '';
}
function fmtWeekday($d) {
    $w = ['Dum','Lun','Mar','Mie','Joi','Vin','Sâm'];
    return $d ? $w[(int)date('w', strtotime($d))] : '';
}
function fmtTime($t) { return $t && $t !== '00:00' ? $t : ''; }

require_once __DIR__ . '/includes/head.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-pattern"></div>
  <div class="hero-spotlight"></div>
  <div class="hero-content">
    <div class="hero-eyebrow"><?= htmlspecialchars(ORG_NAME) ?></div>
    <h1>Alege spectacolul.<br>Ia-ți <em>biletul.</em></h1>
    <div class="hero-meta">
      <span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= count($events) ?> spectacole disponibile
      </span>
      <?php if ($orgLocation): ?>
      <span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <?= htmlspecialchars($orgLocation) ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- CALENDAR STRIP -->
<div class="cal-strip">
  <div class="cal-label">Alege data</div>
  <div class="cal-days" id="calDays"></div>
</div>

<!-- FEATURED EVENTS -->
<?php if (!empty($featured)): ?>
<section class="section">
  <div class="section-header">
    <h2 class="section-title">Spectacole <em>în evidență</em></h2>
  </div>
  <div class="events-grid featured">
    <?php foreach ($featured as $i => $ev):
        $img = $ev['poster_url'] ?? $ev['image'] ?? '';
        $title = $ev['title'] ?? '';
        $date = fmtDate($ev['event_date'] ?? '');
        $time = fmtTime($ev['start_time'] ?? '');
        $venue = $ev['venue_name'] ?? '';
        $city = $ev['venue_city'] ?? '';
        $price = $ev['price'] ?? null;
        $slug = $ev['slug'] ?? '';
        $sold = $ev['is_sold_out'] ?? false;
    ?>
    <a href="<?= $bp ?>/<?= htmlspecialchars($slug) ?>" class="card">
      <div class="card-img"<?= $i > 0 ? ' style="aspect-ratio:16/7;"' : '' ?>>
        <?php if ($img): ?>
        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($title) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
        <?php else: ?>
        <div class="card-img-placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.89L15 14M3 8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/></svg>
        </div>
        <?php endif; ?>
        <?php if ($sold): ?>
        <div class="card-sold-out"><span>Sold out</span></div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="card-meta"><?= $date ?><?= $time ? ' <span class="card-meta-sep">·</span> ' . $time : '' ?></div>
        <h3 class="card-title"><?= htmlspecialchars($title) ?></h3>
        <?php if ($venue): ?>
        <div class="card-venue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= htmlspecialchars($venue) ?><?= $city ? ', ' . htmlspecialchars($city) : '' ?>
        </div>
        <?php endif; ?>
        <div class="card-footer">
          <div class="card-price">
            <?php if ($price !== null && !$sold): ?>
            de la <strong><?= number_format($price, 0) ?> lei</strong>
            <?php else: ?>
            &nbsp;
            <?php endif; ?>
          </div>
          <span class="card-cta">
            Ia bilet
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ALL EVENTS LIST -->
<?php if (!empty($upcoming)): ?>
<section class="section" style="padding-top: 0;">
  <div class="section-header">
    <h2 class="section-title">Toate spectacolele</h2>
  </div>
  <div class="events-list">
    <?php foreach ($upcoming as $ev):
        $title = $ev['title'] ?? '';
        $date = $ev['event_date'] ?? '';
        $time = $ev['start_time'] ?? '';
        $venue = $ev['venue_name'] ?? '';
        $city = $ev['venue_city'] ?? '';
        $price = $ev['price'] ?? null;
        $slug = $ev['slug'] ?? '';
        $sold = $ev['is_sold_out'] ?? false;
    ?>
    <a href="<?= $bp ?>/<?= htmlspecialchars($slug) ?>" class="list-item">
      <div class="list-date">
        <div class="day"><?= fmtDay($date) ?></div>
        <div class="month"><?= fmtMonth($date) ?></div>
      </div>
      <div class="list-info">
        <div class="list-time"><?= $time ? $time . ' · ' . fmtWeekday($date) : fmtWeekday($date) ?></div>
        <div class="list-title"><?= htmlspecialchars($title) ?></div>
        <div class="list-venue"><?= htmlspecialchars($venue) ?><?= $city ? ', ' . htmlspecialchars($city) : '' ?></div>
      </div>
      <div class="list-right">
        <div class="list-price"><?= $sold ? '—' : ($price !== null ? number_format($price, 0) . ' lei' : '') ?></div>
        <?php if ($sold): ?>
        <div class="list-avail low">Sold out</div>
        <?php else: ?>
        <span class="list-cta">Ia bilet</span>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Empty state -->
<?php if (empty($events)): ?>
<section class="section" style="text-align:center;padding:80px 40px;">
  <h2 class="section-title" style="margin-bottom:12px;">Momentan nu sunt<br>spectacole <em>disponibile</em></h2>
  <p style="color:var(--text-muted);font-size:15px;">Revino curând!</p>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Calendar strip — generate 28 days with event highlighting
(function(){
  var days=['Dum','Lun','Mar','Mie','Joi','Vin','Sâm'];
  var months=['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Nov','Dec'];
  var eventDates = <?= json_encode(array_map(function($e) {
      return !empty($e['event_date']) ? date('Y-m-d', strtotime($e['event_date'])) : '';
  }, $events)) ?>;
  var today = new Date();
  var container = document.getElementById('calDays');
  if (!container) return;
  for (var i = 0; i < 28; i++) {
    var d = new Date(today); d.setDate(today.getDate() + i);
    var ds = d.getFullYear()+'-'+(''+(d.getMonth()+1)).padStart(2,'0')+'-'+(''+(d.getDate())).padStart(2,'0');
    var hasEv = eventDates.indexOf(ds) !== -1;
    var div = document.createElement('div');
    div.className = 'cal-day' + (i === 0 ? ' active' : '') + (hasEv ? ' has-events' : '');
    div.innerHTML = '<div class="cal-wd">' + days[d.getDay()] + '</div><div class="cal-dm">' + d.getDate() + '</div><div style="font-size:9px;color:var(--text-dim);letter-spacing:.06em">' + months[d.getMonth()] + '</div>';
    div.addEventListener('click', function(){ document.querySelectorAll('.cal-day').forEach(function(x){x.classList.remove('active')}); this.classList.add('active'); });
    container.appendChild(div);
  }
})();
</script>

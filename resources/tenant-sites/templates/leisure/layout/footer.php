<?php
/** Subsolul template-ului leisure (Nordvale) + bara de cookies + închiderea documentului. */
$footer  = (array) site('footer', []);
$navItems = (array) site('nav', []);
$socials = array_filter([
    'IG' => $footer['instagram'] ?? '', 'FB' => $footer['facebook'] ?? '',
    'TT' => $footer['tiktok'] ?? '', 'YT' => $footer['youtube'] ?? '',
]);
$hours     = trim((string) site('settings.today_hours', ''));
$lastEntry = trim((string) site('settings.last_entry', ''));
$headerCta = (array) site('header_cta', []);
$half = (int) ceil(count($navItems) / 2);
?>
<style>
  .nvf{background:rgb(var(--c-pine-950));color:#fff;padding:64px 16px 32px;font-family:var(--f-body)}
  @media(min-width:640px){.nvf{padding-left:24px;padding-right:24px}}
  @media(min-width:1024px){.nvf{padding-left:40px;padding-right:40px}}
  .nvf-inner{max-width:1460px;margin:0 auto}
  .nvf-grid{display:grid;gap:40px;padding-bottom:52px;border-bottom:1px solid rgba(255,255,255,.1)}
  @media(min-width:900px){.nvf-grid{grid-template-columns:1.25fr .75fr .75fr .75fr}}
  .nvf-name{font:600 34px/1 var(--f-display)}
  .nvf-blurb{margin-top:16px;max-width:24rem;font-size:14px;line-height:1.7;color:rgba(255,255,255,.48)}
  .nvf-social{margin-top:22px;display:flex;gap:8px}
  .nvf-social a{width:40px;height:40px;display:grid;place-items:center;border-radius:999px;border:1px solid rgba(255,255,255,.12);color:#fff;font-size:12px;text-decoration:none}
  .nvf-col h4{margin:0 0 18px;font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgb(var(--c-primary))}
  .nvf-col a{display:block;margin-bottom:12px;font-size:14px;color:rgba(255,255,255,.58);text-decoration:none;transition:color .2s ease}
  .nvf-col a:hover{color:#fff}
  .nvf-col p{margin:0 0 8px;font-size:14px;color:rgba(255,255,255,.58)}
  .nvf-col .nvf-cta{display:inline-block;margin-top:18px;border-radius:999px;background:rgb(var(--c-primary));color:rgb(var(--c-pine-950));padding:12px 16px;font-weight:800;text-decoration:none}
  .nvf-bottom{display:flex;flex-direction:column;gap:16px;padding-top:24px;font-size:12px;color:rgba(255,255,255,.38)}
  @media(min-width:640px){.nvf-bottom{flex-direction:row;align-items:center;justify-content:space-between}}
  .nvf-bottom .nvf-links{display:flex;flex-wrap:wrap;gap:20px}
  .nvf-bottom a{color:rgba(255,255,255,.38);text-decoration:none}
  .nvf-bottom a:hover{color:#fff}
  .nvf-bottom .nvf-tix{color:rgb(var(--c-primary));font-weight:700;text-decoration:none}
</style>
<footer class="nvf">
  <div class="nvf-inner">
    <div class="nvf-grid">
      <div>
        <div class="nvf-name"><?= e(SITE_NAME) ?></div>
        <?php if (!empty($footer['text'])): ?><p class="nvf-blurb"><?= ts_md($footer['text']) ?></p><?php endif; ?>
        <?php if ($socials): ?><div class="nvf-social"><?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><?= e($label) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <div class="nvf-col"><h4>Explorează</h4><?php foreach (array_slice($navItems, 0, $half) as $i): ?><a href="<?= e(ts_href($i['url'] ?? '')) ?>"><?= e($i['label'] ?? '') ?></a><?php endforeach; ?></div>
      <div class="nvf-col"><h4>Vizită</h4><?php foreach (array_slice($navItems, $half) as $i): ?><a href="<?= e(ts_href($i['url'] ?? '')) ?>"><?= e($i['label'] ?? '') ?></a><?php endforeach; ?><?php if (!empty($footer['email'])): ?><a href="mailto:<?= e($footer['email']) ?>"><?= e($footer['email']) ?></a><?php endif; ?><?php if (!empty($footer['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>"><?= e($footer['phone']) ?></a><?php endif; ?></div>
      <div class="nvf-col"><h4>Astăzi</h4><?php if ($hours !== ''): ?><p><?= e($hours) ?></p><?php endif; ?><?php if ($lastEntry !== ''): ?><p>Ultima intrare: <?= e($lastEntry) ?></p><?php endif; ?><?php if (!empty($headerCta['label']) && !empty($headerCta['url'])): ?><a class="nvf-cta" href="<?= e(ts_href($headerCta['url'])) ?>"><?= e($headerCta['label']) ?></a><?php endif; ?></div>
    </div>
    <div class="nvf-bottom">
      <p>© <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
      <div class="nvf-links"><a href="/termeni">Termeni</a><a href="/confidentialitate">Confidențialitate</a><a href="/cookies">Cookies</a><a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false">Setări cookies</a><span>Ticketing by <a class="nvf-tix" href="https://tixello.ro">tixello</a></span></div>
    </div>
  </div>
</footer>
<?= ts_cookie_bar() ?>
<?php if (!empty($GLOBALS['lvDrawer'])) { include __DIR__ . '/booking-drawer.php'; } ?>
<?php if (!empty($GLOBALS['lvSections'])): ?><script type="module" src="<?= e(ts_asset('home.js')) ?>"></script><?php endif; ?>
<?php if (!defined('TS_SHARED_PAGE')): ?>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="fixed bottom-6 left-1/2 z-[160] -translate-x-1/2 rounded-full bg-pine-950 px-5 py-3 text-sm font-semibold text-white shadow-xl" x-text="msg" role="status"></div>
<?php endif; ?>
</body>
</html>

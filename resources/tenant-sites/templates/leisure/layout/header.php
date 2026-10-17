<?php
/**
 * Antetul template-ului leisure (Nordvale): bara plutitoare + meniul lateral.
 * Meniul, butonul și identitatea vin din core; programul zilei din „Setări template”.
 * Opțional: $nvNav (cheie / cale activă), $nvNoSpacer, $bodyAttrs.
 */
$navItems  = (array) site('nav', []);
$headerCta = (array) site('header_cta', []);
$logoUrl   = site('branding.logo');
$current   = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$hours     = trim((string) site('settings.today_hours', ''));
$lastEntry = trim((string) site('settings.last_entry', ''));
$isOn = function (string $url) use ($current): bool {
    return $url !== '/' && ($current === $url || str_starts_with($current, rtrim($url, '/') . '/'));
};
?>
<body <?= $bodyAttrs ?? 'class="antialiased"' ?>>
<?php if (SITE_PREVIEW): ?>
<div style="position:fixed;left:16px;bottom:16px;z-index:300;background:#fbbf24;color:#000;border-radius:999px;padding:8px 16px;font:700 12px/1 system-ui">Previzualizare ciornă · <a href="/?preview=0" style="color:#000;text-decoration:underline">ieși</a></div>
<?php endif; ?>
<style>
  .nvh-wrap{position:fixed;inset-inline:0;top:0;z-index:100;padding:max(10px,env(safe-area-inset-top)) 10px 0}
  @media(min-width:640px){.nvh-wrap{padding-left:16px;padding-right:16px}}
  @media(min-width:1024px){.nvh-wrap{padding-top:16px;padding-left:24px;padding-right:24px}}
  .nvh{max-width:1540px;margin:0 auto;min-height:62px;display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:10px 12px;border-radius:20px;border:1px solid rgba(255,255,255,.12);
    background:rgb(var(--c-pine-950) / .82);box-shadow:0 24px 70px -42px rgba(0,0,0,.9);
    -webkit-backdrop-filter:blur(22px) saturate(130%);backdrop-filter:blur(22px) saturate(130%);
    transition:box-shadow .4s ease,background .4s ease;font-family:var(--f-body)}
  @media(min-width:640px){.nvh{padding:10px 16px}}
  .nvh.nvh-scrolled{background:rgb(var(--c-pine-950) / .92);box-shadow:0 30px 80px -40px rgba(0,0,0,.95)}
  .nvh-brand{display:flex;min-width:0;align-items:center;gap:10px;color:#fff;text-decoration:none}
  .nvh-mark{width:42px;height:42px;flex:0 0 auto;display:grid;place-items:center;border-radius:14px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1)}
  .nvh-name{display:block;font:600 20px/1 var(--f-display)}
  .nvh-sub{display:none;margin-top:5px;font-size:8px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:rgba(255,255,255,.52)}
  @media(min-width:410px){.nvh-sub{display:block}}
  .nvh-links{display:none;align-items:center;gap:26px}
  @media(min-width:1160px){.nvh-links{display:flex}}
  .nvh-links a{color:rgba(255,255,255,.72);font-size:14px;font-weight:600;text-decoration:none;transition:color .2s ease}
  .nvh-links a:hover,.nvh-links a.nvh-on{color:rgb(var(--c-primary))}
  .nvh-actions{flex:0 0 auto;display:flex;align-items:center;gap:8px}
  .nvh-account{display:none;white-space:nowrap;padding:10px 16px;border-radius:999px;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.82);font-size:14px;font-weight:600;text-decoration:none;transition:background .2s ease}
  .nvh-account:hover{background:rgba(255,255,255,.1)}
  @media(min-width:1024px){.nvh-account{display:inline-flex}}
  .nvh-cta{display:inline-flex;align-items:center;gap:7px;white-space:nowrap;min-height:40px;padding:0 16px;border-radius:999px;background:rgb(var(--c-primary));color:rgb(var(--c-pine-950));font-size:13px;font-weight:800;text-decoration:none;box-shadow:0 18px 54px -24px rgb(var(--c-primary) / .58);transition:transform .2s ease}
  .nvh-cta:hover{transform:translateY(-2px)}
  .nvh-cart{position:relative;width:40px;height:40px;flex:0 0 auto;display:grid;place-items:center;border:1px solid rgba(255,255,255,.15);border-radius:999px;color:#fff;text-decoration:none;transition:background .2s ease}
  .nvh-cart:hover{background:rgba(255,255,255,.1)}
  .nvh-cart b{position:absolute;top:-4px;right:-4px;min-width:19px;height:19px;padding:0 5px;border-radius:999px;background:rgb(var(--c-primary));color:rgb(var(--c-pine-950));font:800 11px/19px var(--f-body);text-align:center}
  .nvh-cart b[hidden]{display:none}
  .nvh-burger{width:40px;height:40px;flex:0 0 auto;display:grid;place-items:center;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:transparent;color:#fff;cursor:pointer}
  @media(min-width:1160px){.nvh-burger{display:none}}
  .nvh-drawer{position:fixed;inset:0;z-index:130;visibility:hidden;pointer-events:none}
  .nvh-drawer.nvh-open{visibility:visible;pointer-events:auto}
  .nvh-scrim{position:absolute;inset:0;background:rgb(var(--c-pine-950) / .72);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);opacity:0;transition:opacity .35s ease}
  .nvh-drawer.nvh-open .nvh-scrim{opacity:1}
  .nvh-panel{position:absolute;right:0;top:0;height:100%;width:min(88vw,430px);display:flex;flex-direction:column;padding:22px;background:rgb(var(--c-canvas));transform:translateX(100%);transition:transform .38s cubic-bezier(.22,.8,.2,1)}
  .nvh-drawer.nvh-open .nvh-panel{transform:translateX(0)}
  .nvh-panel-top{display:flex;align-items:center;justify-content:space-between}
  .nvh-panel-brand{font:600 24px/1 var(--f-display);color:rgb(var(--c-pine-950))}
  .nvh-x{width:44px;height:44px;display:grid;place-items:center;border:1px solid rgb(var(--c-pine-900) / .15);border-radius:999px;background:transparent;color:rgb(var(--c-pine-900));cursor:pointer}
  .nvh-panel-nav{margin-top:34px;display:flex;flex-direction:column}
  .nvh-panel-nav a{display:flex;align-items:center;justify-content:space-between;padding:16px 2px;border-bottom:1px solid rgb(var(--c-pine-900) / .1);font:600 26px/1.08 var(--f-display);color:rgb(var(--c-pine-900));text-decoration:none}
  .nvh-panel-foot{margin-top:auto;border-radius:20px;background:rgb(var(--c-pine-900));color:#fff;padding:20px}
  .nvh-panel-foot a{display:block;margin-top:14px;text-align:center;border-radius:999px;background:rgb(var(--c-primary));color:rgb(var(--c-pine-950));padding:13px;font-weight:800;text-decoration:none}
  .nvh-spacer{height:78px}
  @media(min-width:1024px){.nvh-spacer{height:92px}}
</style>
<div class="nvh-wrap">
  <nav class="nvh" id="nvh-bar" aria-label="Navigație principală">
    <a class="nvh-brand" href="/" aria-label="<?= e(SITE_NAME) ?> — pagina principală">
      <?php if ($logoUrl): ?>
        <img src="<?= e($logoUrl) ?>" alt="" style="height:42px;width:auto;max-width:140px;object-fit:contain">
      <?php else: ?>
        <span class="nvh-mark" aria-hidden="true"><svg viewBox="0 0 48 48" width="30" height="30" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="rgb(var(--c-primary))"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="rgb(var(--c-canvas))"/></svg></span>
      <?php endif; ?>
      <span style="min-width:0"><span class="nvh-name"><?= e(SITE_NAME) ?></span><?php if (SITE_CITY !== ''): ?><span class="nvh-sub"><?= e(SITE_CITY) ?></span><?php endif; ?></span>
    </a>
    <div class="nvh-links">
      <?php foreach ($navItems as $item): $href = ts_href($item['url'] ?? ''); ?>
      <a href="<?= e($href) ?>" class="<?= $isOn($href) ? 'nvh-on' : '' ?>"><?= e($item['label'] ?? '') ?></a>
      <?php endforeach; ?>
    </div>
    <div class="nvh-actions">
      <a class="nvh-account" id="nvh-account" href="/autentificare">Contul meu</a>
      <?php if (!empty($headerCta['label']) && !empty($headerCta['url'])): ?>
      <a class="nvh-cta" href="<?= e(ts_href($headerCta['url'])) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H18l3 3v9.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5v-10Z"/><path d="M8 6V3m8 3V3M3 11h18"/></svg>
        <?= e($headerCta['label']) ?>
      </a>
      <?php endif; ?>
      <?php // Coșul: deschide sertarul comun (chrome.js), contorul îl actualizează tot chrome.js ?>
      <a class="nvh-cart" href="/cos" aria-label="Coșul meu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 7h12l1 13H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg><b data-ts-cart-count hidden>0</b></a>
      <button class="nvh-burger" id="nvh-burger" type="button" aria-label="Deschide meniul"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    </div>
  </nav>
</div>
<div class="nvh-drawer" id="nvh-drawer" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="nvh-scrim" data-nvh-close></div>
  <aside class="nvh-panel">
    <div class="nvh-panel-top">
      <span class="nvh-panel-brand"><?= e(SITE_NAME) ?></span>
      <button class="nvh-x" type="button" data-nvh-close aria-label="Închide meniul"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <nav class="nvh-panel-nav">
      <?php foreach ($navItems as $item): ?>
      <a href="<?= e(ts_href($item['url'] ?? '')) ?>"><?= e($item['label'] ?? '') ?> <span aria-hidden="true">→</span></a>
      <?php endforeach; ?>
      <a href="/cos">Coșul meu <span aria-hidden="true">→</span></a>
    </nav>
    <nav class="nvh-panel-nav" style="margin-top:6px">
      <a href="/cont" id="nvh-acc-link" hidden>Contul meu <span aria-hidden="true">→</span></a>
      <a href="/autentificare" id="nvh-login-link">Autentificare <span aria-hidden="true">→</span></a>
      <a href="#" id="nvh-logout-link" hidden>Deconectare <span aria-hidden="true">→</span></a>
    </nav>
    <?php if ($hours !== '' || (!empty($headerCta['label']) && !empty($headerCta['url']))): ?>
    <div class="nvh-panel-foot">
      <?php if ($hours !== ''): ?>
      <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:rgb(var(--c-primary))">Astăzi</span>
      <p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,.7)"><?= e($hours) ?><?= $lastEntry !== '' ? ' · ultima intrare ' . e($lastEntry) : '' ?></p>
      <?php endif; ?>
      <?php if (!empty($headerCta['label']) && !empty($headerCta['url'])): ?><a href="<?= e(ts_href($headerCta['url'])) ?>"><?= e($headerCta['label']) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
  </aside>
</div>
<?php if (empty($nvNoSpacer)): ?><div class="nvh-spacer" aria-hidden="true"></div><?php endif; ?>
<script>
(function(){
  var bar=document.getElementById('nvh-bar'), drawer=document.getElementById('nvh-drawer'), burger=document.getElementById('nvh-burger');
  function setDrawer(open){ if(!drawer)return; drawer.classList.toggle('nvh-open',open); drawer.setAttribute('aria-hidden',String(!open)); document.body.style.overflow=open?'hidden':''; }
  burger&&burger.addEventListener('click',function(){setDrawer(true);});
  drawer&&drawer.querySelectorAll('[data-nvh-close]').forEach(function(el){el.addEventListener('click',function(){setDrawer(false);});});
  drawer&&drawer.querySelectorAll('.nvh-panel-nav a').forEach(function(a){a.addEventListener('click',function(){setDrawer(false);});});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')setDrawer(false);});
  function onScroll(){ if(bar) bar.classList.toggle('nvh-scrolled', window.scrollY>18); }
  window.addEventListener('scroll',onScroll,{passive:true}); onScroll();
  var token=null;
  try{
    var a=JSON.parse(localStorage.getItem('site_auth')||'null'), acc=document.getElementById('nvh-account');
    var accLink=document.getElementById('nvh-acc-link'), loginLink=document.getElementById('nvh-login-link'), logoutLink=document.getElementById('nvh-logout-link');
    if(a&&a.token){
      token=a.token;
      var u=a.user||{}, fn=((u.first_name||(u.name||'').trim().split(/\s+/)[0])||'').trim();
      if(acc){ acc.setAttribute('href','/cont'); acc.textContent=fn?('Salut, '+fn):'Contul meu'; }
      if(accLink) accLink.hidden=false;
      if(logoutLink) logoutLink.hidden=false;
      if(loginLink) loginLink.hidden=true;
    }
    if(logoutLink){ logoutLink.addEventListener('click',function(ev){
      ev.preventDefault();
      try{ fetch('/api/proxy.php?action=logout',{method:'POST',headers:token?{'Authorization':'Bearer '+token}:{}}); }catch(e){}
      try{ localStorage.removeItem('site_auth'); }catch(e){}
      window.location.href='/';
    }); }
  }catch(e){}
})();
</script>

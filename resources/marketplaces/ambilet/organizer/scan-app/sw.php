<?php
/**
 * Service Worker for Aplicație Scan PWA.
 *
 * Served via /organizator/scan/sw.js so its scope covers all scan-app pages.
 * Strategy:
 *   - shell (HTML pages of scan-app): network-first, cache fallback (so updates
 *     ship immediately but offline keeps working)
 *   - static assets (CSS, JS of scan-app, jsdelivr CDN scripts): stale-while-
 *     revalidate (instant load from cache, refresh in background)
 *   - API calls (/api/marketplace-client/*): network-only (NEVER cache —
 *     stale tickets would be a nightmare)
 *
 * Versioned by the cache name; bumping CACHE_VERSION at the top forces a fresh
 * install + cleanup of older caches.
 */

header('Content-Type: application/javascript; charset=utf-8');
// Service workers MUST NOT be cached for long — clients re-fetch SW on each
// page load and a stale SW would block updates from being seen.
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Service-Worker-Allowed: /organizator/scan/');
?>
/* eslint-disable */
const CACHE_VERSION = 'scanapp-v1-<?= date('YmdHi') ?>';
const SHELL_CACHE   = CACHE_VERSION + '-shell';
const ASSETS_CACHE  = CACHE_VERSION + '-assets';

const SHELL_URLS = [
  '/organizator/scan/panou',
  '/organizator/scan/scanare',
  '/organizator/scan/vanzare',
  '/organizator/scan/rapoarte',
  '/organizator/scan/setari-scan',
];

const ASSET_URLS = [
  '/assets/css/scan-app.css',
  '/assets/js/scan-app/auth.js',
  '/assets/js/scan-app/api.js',
  '/assets/js/scan-app/app-context.js',
  '/assets/js/scan-app/event-context.js',
  '/assets/js/scan-app/app.js',
  '/assets/js/scan-app/scanner.js',
  '/assets/js/scan-app/pages/panou.js',
  '/assets/js/scan-app/pages/scanare.js',
  '/assets/js/scan-app/pages/vanzare.js',
  '/assets/js/scan-app/pages/rapoarte.js',
  '/assets/js/scan-app/pages/setari-scan.js',
  '/assets/js/scan-app/pages/porti.js',
  '/assets/js/scan-app/pages/asignare-personal.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const shell  = await caches.open(SHELL_CACHE);
    const assets = await caches.open(ASSETS_CACHE);
    // Best-effort precache — don't fail install if a single URL 404s.
    await Promise.allSettled(SHELL_URLS.map(u => shell.add(new Request(u, { credentials: 'include' }))));
    await Promise.allSettled(ASSET_URLS.map(u => assets.add(u)));
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => {
      // Drop any cache that doesn't match the current version prefix.
      if (k.indexOf(CACHE_VERSION) !== 0) return caches.delete(k);
      return Promise.resolve();
    }));
    await self.clients.claim();
  })());
});

function isApiRequest(url) {
  return /\/api\/marketplace-client\//.test(url);
}
function isCdnAsset(url) {
  return /^https:\/\/cdn\.jsdelivr\.net\//.test(url);
}
function isScanAssetPath(url) {
  return /\/assets\/(css\/scan-app\.css|js\/scan-app\/)/.test(url);
}
function isScanShellPath(url) {
  return /\/organizator\/scan(\/|$|\?)/.test(url);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = req.url;

  // API: network-only. We deliberately don't cache responses to avoid serving
  // stale ticket / event data.
  if (isApiRequest(url)) return;

  // Scan app pages: network-first, fall back to cache.
  if (isScanShellPath(url)) {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(req);
        const cache = await caches.open(SHELL_CACHE);
        cache.put(req, fresh.clone());
        return fresh;
      } catch (e) {
        const cached = await caches.match(req);
        if (cached) return cached;
        return new Response('Conexiune indisponibilă. Reîncearcă când rețeaua revine.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' }
        });
      }
    })());
    return;
  }

  // Scan app static assets + jsdelivr CDN: stale-while-revalidate.
  if (isScanAssetPath(url) || isCdnAsset(url)) {
    event.respondWith((async () => {
      const cache = await caches.open(ASSETS_CACHE);
      const cached = await cache.match(req);
      const networkPromise = fetch(req).then(resp => {
        if (resp && resp.ok) cache.put(req, resp.clone());
        return resp;
      }).catch(() => null);
      return cached || networkPromise || new Response('', { status: 504 });
    })());
    return;
  }

  // Default: pass-through (no service-worker involvement).
});

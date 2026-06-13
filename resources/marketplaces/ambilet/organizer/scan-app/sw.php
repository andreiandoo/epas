<?php
/**
 * Service Worker for Aplicație Scan PWA.
 *
 * Served via /organizator/scan/sw.js so its scope covers all scan-app pages.
 *
 * Strategy (intentionally minimal — NO precache on install):
 *   - shell (HTML pages of scan-app): network-first, cache fallback (so
 *     updates ship immediately but offline keeps working). Cached lazily on
 *     first successful visit.
 *   - static assets (CSS, JS of scan-app, jsdelivr CDN scripts): stale-while-
 *     revalidate (instant load from cache after first visit, refresh in
 *     background).
 *   - API calls (/api/marketplace-client/*): network-only (NEVER cache —
 *     stale tickets would be a nightmare).
 *
 * Why no precache: aggressive install-time precache fires a flurry of
 * parallel requests on the first visit which competes for bandwidth with
 * the actual page the user is trying to load. Lazy caching via the fetch
 * handler delivers the same offline benefit with no install cost — the
 * page the user opens first IS the one that gets cached first.
 *
 * Versioned by the cache name; bumping CACHE_VERSION (or letting the build
 * timestamp drift in the header) forces a fresh install + cleanup of older
 * caches.
 */

header('Content-Type: application/javascript; charset=utf-8');
// Service workers MUST NOT be cached for long — clients re-fetch SW on each
// page load and a stale SW would block updates from being seen.
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Service-Worker-Allowed: /organizator/scan/');
?>
/* eslint-disable */
const CACHE_VERSION = 'scanapp-v2-<?= date('YmdHi') ?>';
const SHELL_CACHE   = CACHE_VERSION + '-shell';
const ASSETS_CACHE  = CACHE_VERSION + '-assets';

self.addEventListener('install', (event) => {
  // No precache. Skip waiting so the new SW activates immediately on update.
  event.waitUntil(self.skipWaiting());
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
  return /\/api\/marketplace-client\//.test(url) || /\/api\/proxy\.php/.test(url);
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
function isIconRequest(url) {
  return /\/organizator\/scan\/icon\.php/.test(url) || /\/organizator\/scan\/manifest\.webmanifest/.test(url);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = req.url;

  // API: network-only. We deliberately don't cache responses to avoid serving
  // stale ticket / event data.
  if (isApiRequest(url)) return;

  // Icons + manifest: cache-first (immutable for a year per their headers).
  if (isIconRequest(url)) {
    event.respondWith((async () => {
      const cache = await caches.open(ASSETS_CACHE);
      const cached = await cache.match(req);
      if (cached) return cached;
      try {
        const resp = await fetch(req);
        if (resp && resp.ok) cache.put(req, resp.clone());
        return resp;
      } catch (e) {
        return new Response('', { status: 504 });
      }
    })());
    return;
  }

  // Scan app pages: network-first, fall back to cache.
  if (isScanShellPath(url)) {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(req);
        if (fresh && fresh.ok) {
          const cache = await caches.open(SHELL_CACHE);
          cache.put(req, fresh.clone());
        }
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

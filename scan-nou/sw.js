/* AmBilet Scan (web) service worker. Written by scripts/build-web.js;
 * The version, base path and precache list are filled in at build time.
 *
 * - App files (hashed JS chunks, icons, index.html) are cached at install,
 *   so the app opens with no signal and starts fast.
 * - index.html: network first, cached copy when offline.
 * - Hashed files under _expo/ and assets/: cache first (they never change).
 * - Everything else (the API proxy, core, CDNs): not touched here. Offline
 *   scans and sales are queued by the app itself.
 * - A new version waits until the app asks for it (banner "Versiune nouă"),
 *   so nobody gets reloaded in the middle of a sale.
 */
const VERSION = "2.2.0-dev.17-1793710877005";
const BASE = "/organizator/scan-nou";
const CACHE = 'ambilet-scan-' + VERSION;
const PRECACHE = [
  "/organizator/scan-nou/index.html",
  "/organizator/scan-nou/_expo/static/js/web/__expo-metro-runtime-37844bfe9405ed99797bdb43820a0459.js",
  "/organizator/scan-nou/_expo/static/js/web/__common-f0d9688aff4dad6914d9f09d958f2e53.js",
  "/organizator/scan-nou/_expo/static/js/web/CheckInScreen-7dae44654dfc833a0204c52f1faf7fb0.js",
  "/organizator/scan-nou/_expo/static/js/web/DashboardScreen-451c3446b8adb41282b8b9aa347703b4.js",
  "/organizator/scan-nou/_expo/static/js/web/index-43be1dec8bd5ef96db407c023195dbd8.js",
  "/organizator/scan-nou/_expo/static/js/web/index-95a1f34e22b1705187e4e6f9e82fa1c6.js",
  "/organizator/scan-nou/_expo/static/js/web/ReportsScreen-6f05dca0ed50bfafc84f88748b3f789d.js",
  "/organizator/scan-nou/_expo/static/js/web/SettingsScreen-9ce55e1a753233b92a141e2a3a88249f.js",
  "/organizator/scan-nou/_expo/static/js/web/VenueEventsScreen-8d3351a48ad92919ad9effed2a8e9966.js",
  "/organizator/scan-nou/_expo/static/js/web/SalesScreen-b64131cf146af683d10fd88c945c4392.js",
  "/organizator/scan-nou/_expo/static/js/web/VenueEventDetailScreen-97bbb2d92701240a4e65baed6c67ee7a.js",
  "/organizator/scan-nou/_expo/static/js/web/VenueTicketDetailScreen-6e9b399dd1ddd00cdac3cd4f637164c0.js",
  "/organizator/scan-nou/assets/assets/sounds/error.b2d7c7bd5fa2c2fe2de503bfbc11de3b.wav",
  "/organizator/scan-nou/assets/assets/sounds/success.6fc8e5768e8a95704c7f33a55836191a.wav",
  "/organizator/scan-nou/assets/assets/sounds/warning.b1fe4cf7def40298a7f6ecfd44bc23e5.wav",
  "/organizator/scan-nou/assets/assets/logo-header.18d301b542a48dc3386cf4aae94cc30b.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/back-icon-mask.0a328cd9c1afd0afe8e3b1ec5165b1b4.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/back-icon.35ba0eaec5a4f5ed12ca16fabeae451d.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/clear-icon.c94f6478e7ae0cdd9f15de1fcb9e5e55@4x.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/clear-icon.c94f6478e7ae0cdd9f15de1fcb9e5e55@2x.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/close-icon.808e1b1b9b53114ec2838071a7e6daa7.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/clear-icon.c94f6478e7ae0cdd9f15de1fcb9e5e55.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/close-icon.808e1b1b9b53114ec2838071a7e6daa7@3x.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/clear-icon.c94f6478e7ae0cdd9f15de1fcb9e5e55@3x.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/close-icon.808e1b1b9b53114ec2838071a7e6daa7@2x.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/search-icon.286d67d3f74808a60a78d3ebf1a5fb57.png",
  "/organizator/scan-nou/assets/node_modules/@react-navigation/elements/lib/module/assets/close-icon.808e1b1b9b53114ec2838071a7e6daa7@4x.png",
  "/organizator/scan-nou/apple-touch-icon.png",
  "/organizator/scan-nou/icon-192.png",
  "/organizator/scan-nou/icon-512.png"
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k.startsWith('ambilet-scan-') && k !== CACHE).map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  if (event.data === 'skip-waiting') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith(BASE + '/')) return;
  if (url.pathname === BASE + '/sw.js') return;

  if (req.mode === 'navigate' || url.pathname === BASE + '/' || url.pathname === BASE + '/index.html') {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(req);
        const c = await caches.open(CACHE);
        c.put(BASE + '/index.html', fresh.clone());
        return fresh;
      } catch {
        const cached = await caches.match(BASE + '/index.html');
        return cached || Response.error();
      }
    })());
    return;
  }

  event.respondWith((async () => {
    const cached = await caches.match(req);
    if (cached) return cached;
    const res = await fetch(req);
    if (res.ok) {
      const c = await caches.open(CACHE);
      c.put(req, res.clone());
    }
    return res;
  })());
});

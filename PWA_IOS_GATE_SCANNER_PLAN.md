# PWA iOS pentru app-ul de scanner Tixello/Ambilet

## Concluzie scurtă

**85% din ce face APK-ul există deja în `gate-scanner.blade.php`.** Lipsesc 5 lucruri pentru a-l face echivalentul iOS app:

1. Service Worker pentru **offline + cache**
2. Meta-tags iOS specifice (status bar, apple-touch-icon, splash)
3. Manifest dedicat scanner-ului (alt brand decât publicul)
4. **Coadă offline de check-in** în IndexedDB (UX-ul critic la festival fără semnal)
5. Wake Lock + push notifications (iOS 16.4+)

**NFC wristband nu se poate replica pe iOS.** Opțiunea pragmatică: la account creation pe iOS arăți „funcția cashless nu e disponibilă pe iPhone, folosește scanerul QR sau cere brățara cu QR în loc de NFC".

---

## Pas 1 — Manifest dedicat scanner-ului

Creează `public/scanner-manifest.webmanifest`:

```json
{
  "name": "Tixello Gate Scanner",
  "short_name": "Gate",
  "description": "Scanner bilete pentru organizatori",
  "start_url": "/gate",
  "scope": "/gate",
  "display": "standalone",
  "orientation": "portrait-primary",
  "background_color": "#030712",
  "theme_color": "#1e40af",
  "icons": [
    {
      "src": "/icons/scanner-192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/icons/scanner-512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],
  "categories": ["business", "productivity"],
  "lang": "ro",
  "dir": "ltr",
  "prefer_related_applications": false
}
```

Notă: `scope: /gate` izolează SW-ul scanner-ului de restul site-ului public.

---

## Pas 2 — Meta tags iOS în `<head>` din `gate-scanner.blade.php`

Adaugă imediat după `<title>`:

```html
<!-- PWA standard -->
<link rel="manifest" href="/scanner-manifest.webmanifest">
<meta name="theme-color" content="#030712">

<!-- iOS specific: full-screen "app" feeling -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Gate Scanner">
<link rel="apple-touch-icon" href="/icons/scanner-180.png">
<link rel="apple-touch-icon" sizes="152x152" href="/icons/scanner-152.png">
<link rel="apple-touch-icon" sizes="167x167" href="/icons/scanner-167.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/scanner-180.png">

<!-- iOS splash screens (opțional, dar dă feel de app real) -->
<link rel="apple-touch-startup-image" href="/icons/splash-1170x2532.png"
      media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)">

<!-- Previne zoom on input focus (iOS) -->
<meta name="format-detection" content="telephone=no">
```

**Important pentru iOS:** `viewport` deja are `maximum-scale=1.0, user-scalable=no` în fișier — asta previne pinch-zoom care ar strica scanner-ul. Lasă-l așa.

---

## Pas 3 — Service Worker (offline + cache)

Creează `public/scanner-sw.js`:

```javascript
const CACHE_VERSION = 'gate-v1';
const ASSETS = [
  '/gate',
  'https://cdn.tailwindcss.com/3.4.16',
  'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
  '/icons/scanner-192.png',
  '/icons/scanner-512.png',
];

// Install: pre-cache shell-ul
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

// Activate: curăță cache-uri vechi
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch strategy:
//   - GET requests către shell/static = cache-first
//   - POST/PUT/DELETE către API = network-only (handled by IndexedDB queue în app)
//   - GET API = network-first cu fallback la cache
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Nu intercepta requesturi non-GET — sunt handled de coada offline din JS
  if (request.method !== 'GET') return;

  // Static assets — cache first
  if (ASSETS.some((a) => url.pathname.endsWith(a) || request.url === a)) {
    event.respondWith(
      caches.match(request).then((r) => r || fetch(request))
    );
    return;
  }

  // API GET — network first, cache fallback
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then((c) => c.put(request, copy));
          return res;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // Pagina /gate — network first cu fallback offline
  event.respondWith(
    fetch(request).catch(() => caches.match('/gate'))
  );
});

// Background Sync (Android Chrome only — iOS nu suportă încă)
self.addEventListener('sync', (event) => {
  if (event.tag === 'flush-checkin-queue') {
    event.waitUntil(self.clients.matchAll().then((clients) => {
      clients.forEach((c) => c.postMessage({ type: 'flush-queue' }));
    }));
  }
});

// Push notifications (iOS 16.4+ în standalone mode)
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'Gate Scanner', {
      body: data.body || '',
      icon: '/icons/scanner-192.png',
      badge: '/icons/scanner-96.png',
      tag: data.tag || 'gate',
      data: data.url || '/gate',
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(self.clients.openWindow(event.notification.data || '/gate'));
});
```

---

## Pas 4 — Înregistrare SW în `gate-scanner.blade.php`

În `<script>`, înainte de `if (token) showScanner();`:

```javascript
// Register Service Worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/scanner-sw.js', { scope: '/gate' })
    .then((reg) => {
      console.log('SW registered', reg.scope);
      // Mesaje de la SW (background sync trigger)
      navigator.serviceWorker.addEventListener('message', (e) => {
        if (e.data?.type === 'flush-queue') flushQueue();
      });
    })
    .catch((e) => console.error('SW registration failed', e));
}

// Wake Lock (împiedică ecranul să se stingă în timpul scan-ului)
let wakeLock = null;
async function acquireWakeLock() {
  try {
    if ('wakeLock' in navigator) {
      wakeLock = await navigator.wakeLock.request('screen');
    }
  } catch (e) { console.warn('WakeLock failed', e); }
}
async function releaseWakeLock() {
  if (wakeLock) { await wakeLock.release(); wakeLock = null; }
}
// Re-acquire la vizibilitate (iOS pierde lock când e în background)
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && scanning) acquireWakeLock();
});
```

În `startScanner()`, după `scanning = true;`, adaugă: `acquireWakeLock();`
În `stopScanner()`, după `scanning = false;`, adaugă: `releaseWakeLock();`

---

## Pas 5 — Coadă offline de check-in (IndexedDB)

**Asta e diferentiator-ul real față de app-ul Android curent.** La festival, semnalul cade. Dacă coada e locală, scanezi în continuare iar la revenire flush automat la server.

Adaugă în `<script>`:

```javascript
// ============ OFFLINE QUEUE (IndexedDB) ============
const DB_NAME = 'gate-scanner';
const DB_VERSION = 1;
const STORE = 'pending-checkins';

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function queueCheckin(payload) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).add({ ...payload, queued_at: Date.now() });
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

async function getQueue() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readonly');
    const req = tx.objectStore(STORE).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function removeQueueItem(id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).delete(id);
    tx.oncomplete = () => resolve();
  });
}

async function flushQueue() {
  if (!navigator.onLine || !token) return;
  const items = await getQueue();
  for (const item of items) {
    try {
      await apiFetch('/organizer/participants/checkin', 'POST',
        { ticket_code: item.code, offline_scanned_at: item.queued_at });
      await removeQueueItem(item.id);
      updateQueueBadge();
    } catch (e) {
      // Dacă ticketul e dublu-scanat offline: marcăm rezolvat oricum
      if (e.message?.includes('already')) await removeQueueItem(item.id);
      else break; // Network re-failed — încearcă mai târziu
    }
  }
}

async function updateQueueBadge() {
  const items = await getQueue();
  const badge = document.getElementById('queue-badge');
  if (badge) {
    badge.textContent = items.length;
    badge.classList.toggle('hidden', items.length === 0);
  }
}

// Trigger flush când revine online
window.addEventListener('online', flushQueue);
setInterval(() => { if (navigator.onLine) flushQueue(); }, 30000); // every 30s safety net
```

**Modifică `checkinTicket(code)` ca să folosească coada când offline:**

```javascript
async function checkinTicket(code) {
  // Optimistic UI: arată succes imediat
  if (!navigator.onLine) {
    await queueCheckin({ code });
    showCheckinSuccess(code, { data: { customer: { name: '(offline)' }, ticket: {} } });
    addRecentScan(code, 'success', code + ' (offline)');
    updateQueueBadge();
    return;
  }
  try {
    const res = await apiFetch('/organizer/participants/checkin', 'POST', { ticket_code: code });
    showCheckinSuccess(code, res);
  } catch (e) {
    const msg = e.message || '';
    if (msg.includes('already checked in') || msg.includes('deja')) showAlreadyUsed(code, msg);
    else if (msg.includes('cancelled') || msg.includes('refunded')) showInvalid(code, msg);
    else if (msg.includes('Failed to fetch') || msg.includes('NetworkError')) {
      // Network died mid-request — pune în coadă
      await queueCheckin({ code });
      showCheckinSuccess(code, { data: { customer: { name: '(offline)' }, ticket: {} } });
      updateQueueBadge();
    }
    else showInvalid(code, msg || 'Bilet negasit');
  }
}
```

**Adaugă badge UI în header (lângă „Deconectare"):**

```html
<button onclick="doLogout()" class="text-gray-400 hover:text-white text-sm flex items-center gap-2">
    <span id="queue-badge" class="hidden bg-amber-500 text-black text-xs font-bold px-2 py-0.5 rounded-full">0</span>
    Deconectare
</button>
```

---

## Pas 6 — Banner „Adaugă la Home Screen" pentru iOS

iOS nu are prompt-ul `beforeinstallprompt` ca Android. Trebuie banner manual cu instrucțiuni:

```javascript
// Detect iOS Safari + nu deja instalat
const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
  || window.navigator.standalone;

if (isIOS && !isStandalone && !localStorage.getItem('a2hs-dismissed')) {
  setTimeout(() => {
    const banner = document.createElement('div');
    banner.className = 'fixed bottom-4 left-4 right-4 bg-blue-600 text-white p-4 rounded-2xl shadow-2xl z-50';
    banner.innerHTML = `
      <div class="flex items-start gap-3">
        <div class="flex-1 text-sm">
          <strong>Instalează aplicația</strong><br>
          Apasă <span class="inline-block">⎋</span> apoi <strong>"Add to Home Screen"</strong> pentru a folosi ca app.
        </div>
        <button onclick="this.parentElement.parentElement.remove(); localStorage.setItem('a2hs-dismissed', '1')"
                class="text-white text-xl">&times;</button>
      </div>`;
    document.body.appendChild(banner);
  }, 5000);
}
```

---

## Pas 7 — Push notifications (iOS 16.4+, doar după instalare)

iOS permite Push doar dacă PWA e instalată. După instalare, prima oară când se loghează:

```javascript
async function requestPushPermission() {
  if (!isStandalone) return; // iOS: doar în standalone
  if (!('Notification' in window) || !('PushManager' in window)) return;
  if (Notification.permission === 'default') {
    const perm = await Notification.requestPermission();
    if (perm === 'granted') await subscribeUserToPush();
  }
}

async function subscribeUserToPush() {
  const reg = await navigator.serviceWorker.ready;
  const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: 'YOUR_VAPID_PUBLIC_KEY_BASE64',
  });
  // Trimite la backend
  await apiFetch('/organizer/push-subscriptions', 'POST', {
    endpoint: sub.endpoint,
    keys: sub.toJSON().keys,
    platform: 'ios-pwa',
  });
}

// Apelează după login reușit:
// requestPushPermission();
```

**Backend:** ai nevoie de un endpoint nou `/api/marketplace-client/organizer/push-subscriptions` care salvează subscripția într-o tabelă `organizer_push_subscriptions` și un job care trimite push-uri Web (folosește `minishlink/web-push` PHP package).

---

## Pas 8 — Distribuție / Onboarding iOS

Modifică `mobile-app.php` să detecteze iOS și să afișeze instrucțiuni de instalare PWA în loc de buton App Store gol:

```php
<?php
$isIOS = preg_match('/iPad|iPhone|iPod/', $_SERVER['HTTP_USER_AGENT'] ?? '');
?>
<?php if ($isIOS): ?>
  <a href="/gate" class="...buton iOS specific...">
    Deschide pe iPhone
    <small>Apoi: Share → Add to Home Screen</small>
  </a>
<?php else: ?>
  <a href="/android" class="...buton Android...">Descarcă APK</a>
<?php endif; ?>
```

Sau, mai elegant, o pagină nouă `/install-ios` cu video screencast 15-secunde care arată exact pașii de instalare.

---

## Pas 9 — Token persistence (iOS îți poate șterge localStorage)

**Bug ascuns iOS:** Safari șterge localStorage după **7 zile fără folosire** (Intelligent Tracking Prevention). În standalone (PWA instalată), păstrează mai mult, dar nu garantat.

Migrează token-ul în IndexedDB:

```javascript
async function saveToken(t) {
  const db = await openDB();
  const tx = db.transaction(STORE, 'readwrite');
  // Folosește același store sau creează unul dedicat „auth"
  // (mai curat: adaugă un store „kv" în openDB onupgradeneeded)
  // ...
}
```

Sau, mai simplu pentru MVP: lasă în localStorage + adaugă re-login automat dacă token-ul e expirat (deja are `if (res.status === 401) doLogout()`).

---

## Limitări iOS pe care trebuie să le accepți (sau să le dai workaround)

| Funcție Android | iOS PWA — verdict | Workaround |
|---|---|---|
| **NFC wristband read/write** | ❌ Imposibil | Brățară cu QR code în loc de NFC, sau scanezi QR-ul printat pe brățară |
| **Background sync** automat la reconnect | ❌ (parțial) | Polling la 30s + flush la `online` event (deja în plan) |
| **Auto-start cameră** la deschidere | ❌ Necesită tap user | Buton mare „Pornește cameră" (deja există) |
| **Bluetooth printer** (bilete pe loc) | ❌ Imposibil | Email/SMS bilet + show QR pe ecranul telefonului fanului |
| **Biometric login** (Face ID auto) | ❌ Imposibil | Token persistent 30 zile + PIN local |
| **Push notifications** | ✅ doar iOS 16.4+ și doar după A2HS | Deja inclus în plan |
| **Vibrație** | ⚠️ iOS 16.4+ în PWA, înainte nu | Fallback: flash vizual + sunet |
| **Wake Lock** | ✅ iOS 16.4+ | Deja inclus în plan |
| **Cache offline complet** | ✅ Service Worker funcționează | Deja inclus în plan |

---

## Rezumat: ce de făcut acum, în ordine

| Pas | Effort | Câștig |
|---|---|---|
| 1. Manifest + iOS meta tags | 1 oră | „Add to Home Screen" funcționează |
| 2. Service Worker (cache shell) | 2 ore | App pornește offline, stilurile încărcate |
| 3. IndexedDB queue check-in | 4 ore | **Diferentiator** — funcționează la festival fără semnal |
| 4. Wake Lock | 30 min | Ecranul nu se stinge în timpul scanării |
| 5. Banner A2HS pentru iOS | 30 min | Conversie instalare ~3x |
| 6. Push notifications | 4 ore + backend | Notif update / event nou |
| 7. Pagina `/install-ios` cu video | 2 ore | Reduce friction onboarding |
| 8. Migrare token IndexedDB | 1 oră | Token nu mai dispare după 7 zile |

**Total: 1.5–2 zile de muncă** pentru paritate iOS cu Android (minus NFC, care e imposibil).

---

## Bonus — pacheteare comercială

După ce ai PWA-ul iOS funcțional, vinzi exact aceeași poveste ca pe Android:

- **„Disponibil pe iPhone și Android"** — diferentiator vs. iaBilet/Bilete.ro care au doar Android
- **„Funcționează offline la festival"** (datorită cozii IndexedDB) — diferentiator vs. **toți** competitorii
- **„Zero install pe iOS — direct din browser"** — argument „we move faster than App Store review"

Schimbi pagina din `mobile-app.php` și re-livrezi link-ul către clienți (Ambilet + clientul al doilea) — au cumpărat platforma, le dai feature nou gratis și pozezi bine.

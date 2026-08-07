# Shorts — stratul mobil (Faza 2)

Ce s-a livrat în `tics-app/mobile` pentru redarea feed-ului vertical, cum e cablat
la API-ul din Faza 1 și ce mai trebuie făcut de owner.

> Plan sursă: `docs/plans/shorts.md` §5 (API) și §7 (redare mobil).

---

## Fișiere

| Fișier | Rol |
|---|---|
| `src/api/shorts.ts` | Client tipat peste endpoint-urile Shorts: feed cursor, detaliu, telemetrie batched, toggle like/save. |
| `src/features/client/shorts/useShortsFeed.ts` | Paginare cursor + prefetch cu 3 short-uri înainte de capăt + patch optimist. |
| `src/features/client/shorts/useShortTelemetry.ts` | Coadă de evenimente, flush la 5s / 25 evenimente / trecere în fundal / demontare. |
| `src/features/client/shorts/ShortVideo.tsx` | `<video>` + hls.js: autoplay muted, pauză la ieșire, preload, `prefers-reduced-motion`. |
| `src/features/client/shorts/LiveShorts.tsx` | Pager vertical cu `IntersectionObserver`, overlay, rail, CTA, share. |
| `src/features/client/screens/Shorts.tsx` | Ecranul „Pe val": feed live, cu feed-ul din prototip ca fallback. |
| `src/design/client.css` | Regulile în plus pentru `<video>`, butonul de play, `.sr-only`, focus vizibil. |

Dependență nouă: **`hls.js`** — import dinamic, deci ajunge într-un chunk separat
(`hls-*.js`, ~162 kB gzip) încărcat doar la deschiderea feed-ului, nu în bundle-ul de
pornire.

---

## Cum e cablat la API

```
GET  /api/tenant-client/shorts?feed=for_you&cursor=&limit=10
POST /api/tenant-client/shorts/events
POST /api/marketplace-client/customer/shorts/{id}/like
POST /api/marketplace-client/customer/shorts/{id}/save
```

`API_ROOT` se reia din `src/api/tenantClient.ts` (`VITE_API_BASE`, implicit
`https://core.tixello.com/api`) — o singură sursă de adevăr pentru baza de API.

**Citirile sunt publice.** Feed-ul se parcurge fără cont; payload-ul vine îmbogățit cu
`viewer.liked/saved` doar când există token. Like/save cer cont și, fără el, afișează
un toast în loc să eșueze tăcut.

---

## Deciziile de redare

**Un singur `<video>` viu ± un vecin.** Doar short-ul activ și vecinii imediați
(`PRELOAD_RADIUS = 1`) montează un element media. Fără asta, un minut de derulare lasă
zeci de `<video>` vii în memorie — pe Android WebView asta înseamnă blocaje reale.

**hls.js doar unde e nevoie.** iOS (WKWebView/Safari) redă HLS nativ; acolo hls.js e
inutil și chiar dăunător. Verdictul se ia cu `video.canPlayType('application/vnd.apple.mpegurl')`,
nu prin sniffing de user agent.

**Buffer mic.** `maxBufferLength: 12` + `capLevelToPlayerSize` — într-un feed pe care
utilizatorul îl poate părăsi în două secunde, un buffer generos e bandă plătită degeaba
(și se leagă direct de guardrail-urile de cost din D8).

**Activul se determină cu `IntersectionObserver`** (prag 0.7), nu din calcule de scroll:
rămâne corect și când utilizatorul aruncă degetul peste trei ecrane.

**Autoplay muted, un singur „unmuted".** Sunetul e o stare unică la nivel de feed, nu
per card. Tap pe video = mute/unmute. Când politica de autoplay a browserului refuză,
apare butonul de play în loc de un eșec tăcut.

**`prefers-reduced-motion`** dezactivează autoplay-ul și arată poster + buton de play
(D10, parțial — restul accesibilității vine în Faza 3).

---

## Telemetria emisă

| Eveniment | Când |
|---|---|
| `impression` | short-ul intră în ecran (o singură dată per short) |
| `view` | ≥2s **sau** ≥25% văzut — în timpul redării sau la ieșire |
| `skip` | short părăsit sub prag |
| `complete` | clipul a ajuns la final |
| `share` / `cta_click` | la apăsarea butonului |
| `like` / `unlike` / `save` / `unsave` | scrise de server la toggle, nu de client |

`watch_ms` și `watch_ratio` însoțesc `view`/`skip`. Pragurile din client le oglindesc pe
cele din `config/shorts.php` — clientul nu are voie să fie mai permisiv decât serverul,
care oricum revalidează.

Loturile pleacă pe `sendBeacon` când pagina moare, altfel pe `fetch` cu `keepalive`.
Fără asta s-ar pierde exact evenimentele care contează cel mai mult: raportul de ieșire.

---

## Ce urmează / TODO(owner)

1. **Token de client.** App-ul rulează încă pe identitate mock (`api/client.ts`,
   `USE_MOCK = true`). Când apare loginul real de `MarketplaceCustomer`, cheamă
   `setShortsToken()` din același loc în care se cheamă `setToken()`.
   Până atunci feed-ul merge ca guest — corect prin design.
2. **Tab dedicat „Shorts".** Ecranul e deja în stivă („Pe val"); planul recomandă un
   tab principal de discovery cu segmented control (For You / Following / Nearby /
   Event). „Following" și „Nearby" cer graful de urmărire și geo — vin în Faza 5.
3. **Short-uri externe (embed).** `LiveShorts` afișează posterul pentru sursele
   externe; randarea `embed_html` per platformă rămâne de făcut împreună cu ingestia
   (Faza 6).
4. **Deep-link `tixello://shorts/{id}`** + landing web — Faza 3 (D1).
5. **Gesturi.** Double-tap = like și swipe-up pe CTA nu sunt încă implementate;
   tap = mute/unmute este.
6. **Prefetch de postere + primul segment HLS**, blurhash/LQIP, data-saver — Faza 3 (D9).

---

## Verificare locală

```bash
cd tics-app/mobile
npm install
npm run build          # tsc --noEmit && vite build
```

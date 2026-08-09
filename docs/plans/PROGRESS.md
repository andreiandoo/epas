# PROGRESS.md — Shorts

Branch: `claude/shorts` (pornit din `origin/core`).
Plan: `docs/plans/shorts.md` · Mandat: `docs/plans/shorts-START-PROMPT.md` · Decizii: `DECISIONS.md`

---

## Rezumat pentru owner

**Toate cele 12 faze din `shorts-START-PROMPT.md` sunt livrate și push-uite pe
`claude/shorts`.** 168 de teste, 437 aserțiuni — verzi **atât pe SQLite, cât și pe
PostgreSQL 16**, motorul de producție. 18 migrații aplicate curat pe Postgres.
12 comenzi programate, 34 de rute. App-ul mobil se compilează (`tsc` + `vite build`).

**Fără conflicte cu `core`** (verificat cu `git merge-tree` după ce core a avansat cu
4 commit-uri; niciun fișier nu e atins de ambele părți).

| Fază | Ce a intrat |
|---|---|
| 1 | Fundație: migrații, model `Short`, `VideoProvider` + Bunny, resursă Filament „Core", feed cursor, telemetrie |
| 2 | Redare mobil: feed vertical HLS cu autoplay, preload, overlay, like/save/share |
| 3 | Val 1: share+referral+landing, remind/drop, blurhash + data-saver, gamification, accesibilitate |
| 4 | Shoppable: CTA cu bilet + promo, atribuire last-touch, CTR/CVR/venit |
| 5 | Following polimorf + ranker „For You" explicabil + geamăn tenant |
| 6 | Ingestie externă (YouTube/TikTok/Meta) + seed automat din canale de artiști |
| 7 | Val 2: trending pe velocitate, retenție, prune telemetrie, seen-store + explorare, nudge-uri |
| 8 | Auto-gen din media evenimentului, captions, analytics organizator |
| 9 | Colecții editoriale, stories efemere, igiena feed-ului |
| 10 | Val 3: promovate cu pacing, drepturi/licențiere, guardrails de cost, UGC verificat, A/B cover |
| 11 | **Reclame:** injectare reală în feed, advertiseri + credit prepaid, targetare, taxare CPM/CPC, panouri de aprobare |
| 12 | **Generare automată:** shorts din posterele evenimentelor, artiștilor și locațiilor + măturătoare nocturnă |

### ✅ Verificat pe PostgreSQL (nu mai e pe lista ta)

Am pornit un PostgreSQL 16 local — era instalat în imagine, doar nu rula — și am rulat
**toate cele 18 migrații Shorts + întreaga suită (168 de teste) pe motorul real de
producție.** Toate verzi pe Postgres **și** pe SQLite.

A meritat: au ieșit **7 bug-uri care treceau pe SQLite și ar fi picat pe Postgres**
(detalii în `DECISIONS.md`, addendum D-068…D-074). Cele mai serioase:

| Bug | Ce s-ar fi întâmplat în producție |
|---|---|
| `owner()->first()` pe un short editorial | **500 pe feed-ul principal** — `zero-length delimited identifier` |
| `LIKE` pe coloană `json` | filtrul de teritoriu arunca; `json` n-are operator `LIKE` în PG |
| `json` fără `jsonb` | `DISTINCT`/`GROUP BY` pe json → 500 în Filament (bug cu precedent în repo) |
| `tickets.checked_in` inexistent | **nimeni n-ar fi putut posta UGC, niciodată** (tăcut, prin fail-closed) |
| tabelele de favorite greșite | semnalul de personalizare mort permanent în ranker |
| insert în `points_transactions` | 4 coloane greșite; reparat prin serviciul care exista deja |
| oglindire în watchlist imposibilă | insert care nu putea reuși, înghițit de `catch` — șters |

Rulează și tu, oricând:

```bash
# porneste un PG local (ca in audit)
su postgres -c "/usr/lib/postgresql/16/bin/initdb -D /var/lib/postgresql/shortsdata -U postgres --auth=trust"
su postgres -c "/usr/lib/postgresql/16/bin/pg_ctl -D /var/lib/postgresql/shortsdata -o '-p 55432 -k /tmp' start"
createdb -h /tmp -p 55432 -U postgres shorts_pg

DB_CONNECTION=pgsql DB_HOST=/tmp DB_PORT=55432 DB_DATABASE=shorts_pg DB_USERNAME=postgres \
  SHORTS_TEST_PGSQL=1 php artisan test --filter=Shorts
```

> **De reținut:** o suită verde pe SQLite **nu dovedește** compatibilitate cu Postgres.
> Rulează `SHORTS_TEST_PGSQL=1` înainte de fiecare merge care atinge Shorts.

**Rămâne totuși de făcut de tine:** migrațiile sunt verificate pe schema **redusă**, nu pe
un dump complet de producție. Istoricul de 747 de migrații nu se poate reda aici (o migrație
preexistentă, `2025_10_31_200100_events_translatables`, face `DROP COLUMN` pe o coloană
indexată și nici pe Postgres nu e garantat curat). Un `migrate --pretend` pe un dump real
înainte de deploy rămâne pasul prudent — dar riscul e mult mai mic decât era.

### 📣 Reclame în shorts — ce merge acum (Faza 11)

Ai întrebat dacă am luat în calcul (1) promovarea evenimentelor/artiștilor și (2) reclame
în shorts. Parțial — și verificând am găsit că **D3 era construit, dar deconectat**:
`inject()` nu avea niciun apelant, deci *nicio reclamă nu ajungea vreodată în feed*;
`chargeClick()` nu era apelat niciodată; `targeting` se scria dar nu se citea; iar statusul
`pending` nu putea fi mutat pe `active`, fiindcă nu exista nicio interfață. Am marcat Faza 10
„completă" când jumătate din D3 lipsea. Reparat în Faza 11.

**Ce poți face acum:**

| | Cum |
|---|---|
| **Promovezi un eveniment sau un artist** | Panou tenant → „Promovare Shorts" → alegi short-ul, obiectivul, CPM/CPC, bugetul, perioada, targetarea. Pleacă în `pending`. |
| **Aprobi/respingi** | Core admin → „Ad campaigns". Are badge cu numărul celor în așteptare și tab „Pending review" ca prim ecran. |
| **Vinzi reclamă unui brand terț** | Core admin → „Ad advertisers" (tip `external`) → „Add credit" → apoi „Ad campaigns" → „New campaign" cu obiectiv `brand`. Nu are nevoie de cont de tenant. |
| **Cross-promovare proprie** | Advertiser de tip `house`: nu se facturează niciodată și umple doar sloturile pe care nu le-a vrut nicio campanie plătită. |

**Cum se comportă în feed:** un slot la fiecare 5 shorts organice, maximum 2 pe pagină
(`config/shorts.php` → `ads`). Sloturile se inserează **după** ranking — niciun buget nu poate
muta poziția unui short organic. `event` și `artist` nu poartă reclame (sunt pagina cuiva
anume). Eticheta e obligatorie și diferă: `Sponsorizat` / `Reclamă` / `Recomandat de Tixello`.

**Bani:** credit prepaid per advertiser, verificat înainte de fiecare afișare, cu registru
append-only (`short_advertiser_transactions`) ținut separat de telemetrie ca joburile de
prune să nu poată șterge o dovadă de facturare. CPM taxează la afișare, CPC la click — și
doar pe campania care chiar a servit acel click, nu după `short_id`.

**Rămâne la tine:** alimentarea creditului prin Stripe. Cusătura e gata —
`ShortAdvertiser::topUp($cents, $paymentIntentId)` — dar apelul cere `CheckoutController`,
pe care nu l-am atins (D-031). Până atunci, creditul se adaugă manual din core admin, cu
număr de factură ca referință.

### 🖼️ Generare automată din poster — ce merge acum (Faza 12)

Ai întrebat dacă se generează shorts automat pentru fiecare eveniment/artist/locație, și dacă
se folosește posterul când nu e video. Răspunsul era **nu** — și din exact același motiv ca la
reclame: `GenerateShortFromEventJob` era scris și testat din Faza 8, dar **nimic nu-l chema**
(fără scheduler, fără observer, fără comandă), deci nu s-a generat niciodată vreun short. Și
acoperea doar evenimente.

**Acum:**

| Sursă | Imagini folosite | CTA |
|---|---|---|
| **Eveniment** | `poster_url` → `hero_image_url` → `gallery` (max 5) | „Ia bilet" |
| **Artist** | `portrait_url` → `main_image_url` → `logo_url` | „Ia bilet" dacă are concert viitor, altfel „Vezi artistul" |
| **Locație** | `image_url` → `gallery` | „Vezi evenimentele" |

Fără renderer configurat (Shotstack), rezultatul e un **poster short**: cadrul fix, marcat
`ready` și servit ca un card în feed. Clientul mobil deja randa cazul ăsta. Cu renderer
configurat, se comandă un clip vertical real și posterul rămâne ca fallback dacă randarea pică.

**Ce le declanșează:** `shorts:generate`, programat nocturn la 02:30. Măturătoare, nu observer
— un eveniment se creează cu mult înainte să i se urce posterul, iar un observer ar porni pe o
înregistrare goală și nu s-ar mai uita niciodată. Idempotentă: sare peste ce are deja short.

```bash
php artisan shorts:generate --dry-run          # ce s-ar genera, fără să genereze
php artisan shorts:generate --type=venue        # doar locații
php artisan shorts:generate --limit=50          # peste plafonul din config
```

**Limite intenționate:** doar evenimente din următoarele 120 de zile (un feed cu afișe de anul
trecut face suprafața să pară abandonată), doar artiști cu concert viitor (un buton „Ia bilet"
fără nimic de vândut e o fundătură), maximum 200 pe rulare pe tip — o primă trecere peste
catalogul existent recuperează în câteva nopți în loc să pună zeci de mii de joburi în coadă
deodată. Tot în `config/shorts.php` → `autogen`.

**Două decizii care merită știute:**
1. **Intră publicate**, nu `draft` (invers față de UGC). Sunt pozele proprii ale
   organizatorului, deja publice pe pagina evenimentului — feed-ul nu adaugă nicio suprafață
   de moderare nouă. Alternativa nu e „mai sigur", ci „nimeni nu publică manual zece mii de
   rânduri". `SHORTS_AUTOGEN_PUBLISH=false` le trimite prin revizuire.
2. **Video real bate poster la egalitate** în ranker (`generated_penalty`, 0.5 față de 3.0 pe
   afinitate). Penalizarea dispare când short-ul capătă asset video.

### Ce trebuie făcut de tine înainte de producție

1. **Bunny Stream** — pașii din `shorts.md` §C0 + cheile în `.env`. Fără ele containerul
   cade pe `NullVideoProvider`: dev/CI pornesc, feed-ul servește shorts externe și
   self-hosted, dar upload-ul nativ răspunde `503`. Confirmă și schema exactă de token
   pe pull zone (`TODO(owner)` în `BunnyStreamProvider::sign()`).
2. **Ultimul pas al atribuirii de conversii** — `CheckoutController` trebuie să treacă
   `source_short_id` / `source_feed` din payload în `Order::create()`. Coloanele sunt
   `fillable` și observerul e gata; n-am atins controllerul (1700+ linii de flux de
   plată real) fără să pot rula un checkout end-to-end (D-031).
3. **Layer de push** — `PushSender` are azi doar `LogPushSender` (fiecare notificare e
   logată cu payload complet, deci logica de declanșare e verificabilă). D2 și D12 devin
   reale în clipa în care legi un transport FCM/APNs.
4. **Puntea `MarketplaceCustomer ↔ Customer`** (`friends-social.md` §0) — mai puțin urgentă
   decât credeam: XP-ul curge deja prin `ExperienceService::awardActionXpForMarketplace`,
   care nu are nevoie de punte (D-073). Doar `points_transactions` (side-ul `Customer`) o
   cere. **De configurat însă `ExperienceAction` pentru `short_watch` / `short_share` /
   `short_create`**, altfel XP-ul rămâne no-op.
5. **Decizie pe `.claude/settings.json`.** L-am adus pe branch la cererea ta, dar merge-ul
   în `core` îl aplică tuturor sesiunilor viitoare din repo: `defaultMode: acceptEdits` +
   un allowlist larg de comenzi. Dacă a fost doar pentru rularea autonomă de acum, scoate-l
   din merge (`git rm --cached .claude/settings.json`) sau mută-l în `.claude/settings.local.json`,
   care e per-mașină.
6. **Alimentarea creditului de reclamă prin Stripe** — același blocaj ca punctul 2:
   cusătura există (`ShortAdvertiser::topUp($cents, $paymentIntentId)`), dar apelul e în
   fluxul de plată. Până atunci creditul se adaugă manual din core admin → „Ad advertisers"
   → „Add credit", cu numărul de factură ca referință; registrul rămâne corect oricum.
7. **Opțional:** chei Shotstack (altfel auto-generarea produce „poster shorts"), token
   Meta oEmbed (altfel IG/FB raportează „neconectat"), driver de transcriere pentru
   captions, partiționarea lunară a `short_events` (D-048).

### Convenție obligatorie

Orice migrație Shorts nouă se numește `<timestamp>_shorts_<descriere>.php`. Sufixul
`_shorts_` e cum le descoperă suita de teste — fără el, migrația nu intră în schema
de test și testele trec fals (D-002).

### Cum rulezi local

```bash
composer install
npm install && npm run build            # manifestul Vite — altfel panoul Filament nu bootează
cp .env.example .env && php artisan key:generate

./scripts/shorts-dev-migrate.sh --reset # schemă de dev redusă
php artisan test --filter=Shorts        # 168 de teste (SQLite)
./vendor/bin/pint
```

Deciziile luate autonom sunt în `DECISIONS.md` (67 de intrări + addendumul de audit pe
Postgres, D-068…D-074, care corectează trei dintre ele).

---

## Faza 1 — Fundație (Partea A + Partea C) ✅

- [x] Migrație `shorts` (owner polimorf, umbrelă tenant/marketplace/event, sursă+pipeline,
      prezentare, commerce, publicare, agregate)
- [x] Migrații `short_likes` / `short_saves` (unic pe short+customer → toggle idempotent)
- [x] Migrație `short_events` (append-only, `created_at` fără `updated_at`, coloană `meta`
      pregătită pentru `poster_variant_id`/`promotion_id` din B10/D3)
- [x] Model `Short` — relații, scope `published()`/`forEvent()`/`forOwner()`/`featured()`,
      accessori `is_external`/`playback_url`/`poster_url`/`aspect`. **Fără** `SecureTenantScoping`
- [x] Modele `ShortEvent`, `ShortLike`, `ShortSave`
- [x] Interfața `VideoProvider` (+ `ingestFromUrl` pentru B3, `parseWebhook` pentru webhook-uri)
- [x] `BunnyStreamProvider` — TUS direct upload, ingest din URL, `getPlayback`, URL-uri
      semnate HLS/poster/preview, delete, verificare webhook
- [x] `NullVideoProvider` + `VideoServiceProvider` (fallback pe config placeholder)
- [x] `config/services.php`: `video.driver`, `bunny.*` · `config/shorts.php`: feed, ranker,
      telemetrie, deep-links · `.env.example` cu placeholdere
- [x] `ShortFeedService` + `ShortFeedCursor` (keyset, featured-first) + `ShortPayload`
- [x] `ShortTelemetryService` (validare, praguri de credibilitate, eșantionare impresii)
- [x] `ShortsController` public (feed, detaliu, per event/artist, telemetrie)
- [x] `ShortsController` customer (feed, saved, toggle like/save)
- [x] `ShortUploadController` (`POST tenant/shorts/upload-url`)
- [x] `VideoWebhookController` (`POST webhooks/video/{provider}`)
- [x] `SyncShortPlaybackJob`, `AggregateShortStatsJob`
- [x] Resursa Filament `ShortResource` (grup „Core") + pagini + bulk publish/archive/feature
- [x] Rute în `routes/api.php`
- [x] Teste: 25 (fundație, API, provider Bunny, resursă Filament)
- [x] `pint` aplicat

### Endpoint-uri livrate

```
GET  api/tenant-client/shorts?feed=&cursor=&limit=
GET  api/tenant-client/shorts/{id}
GET  api/tenant-client/events/{slug}/shorts
GET  api/tenant-client/artists/{slug}/shorts
POST api/tenant-client/shorts/events              (batched, acceptă guest)
GET  api/marketplace-client/customer/shorts/feed
GET  api/marketplace-client/customer/shorts/saved
POST api/marketplace-client/customer/shorts/{id}/like
POST api/marketplace-client/customer/shorts/{id}/save
POST api/tenant/shorts/upload-url
POST api/webhooks/video/{provider}
```

---

## Faza 2 — Redare mobil ✅

App-ul mobil **este** în repo: `tics-app/mobile` (React 18 + Capacitor 6 + Vite).
Detalii complete: `docs/plans/shorts-mobile.md`.

- [x] `src/api/shorts.ts` — client tipat peste endpoint-urile din Faza 1
- [x] `useShortsFeed` — paginare cursor + prefetch cu 3 short-uri înainte de capăt
- [x] `useShortTelemetry` — coadă batched, flush la 5s / 25 evenimente / fundal / demontare,
      `sendBeacon` la moartea paginii
- [x] `ShortVideo` — hls.js pe Android/WebView (import dinamic → chunk separat), HLS nativ
      pe iOS, buffer mic (12s) + `capLevelToPlayerSize`
- [x] Autoplay muted la intrare în viewport, pauză la ieșire, un singur „unmuted" pe feed,
      fallback pe buton de play când politica de autoplay refuză
- [x] Preload ±1 ecran; restul nu montează `<video>`
- [x] `IntersectionObserver` (prag 0.7) pentru short-ul activ
- [x] Overlay: owner, titlu, caption, hashtags, music credit, CTA · rail like/save/share
- [x] Telemetrie: impression / view / skip / complete / share / cta_click cu
      `watch_ms` + `watch_ratio`, praguri identice cu serverul
- [x] `prefers-reduced-motion` → fără autoplay, poster + play (parte din D10)
- [x] Feed live cu fallback pe feed-ul din prototip (convenția din `tenantClient.ts`)
- [x] `tsc --noEmit` + `vite build` verzi

**TODO(owner) rămas pe mobil:** tokenul de client (app-ul e încă pe identitate mock),
tab dedicat cu segmented control, randarea `embed_html` pentru sursele externe (Faza 6),
double-tap = like și swipe-up pe CTA.

## Faza 3 — Val 1 (creștere ieftină) ✅

**D1 — share + referral + landing**
- [x] `short_shares` (token, canal, clicks/installs/conversions) + `shorts.share_card_path`
- [x] `ShortShareService` — mintește tokenul, bagă codul de referral existent în link
- [x] `POST customer/shorts/{id}/share` → `{url, card_url, deep_link, points}`
- [x] Landing web `GET /s/{id}` — OG tags, „Deschide în aplicație", fallback store,
      cookie de referral 30 zile, atribuirea click-ului
- [x] `GenerateShareCardJob` — card 1200×630 din poster + scrim + titlu (GD, fără render service)
- [x] `ShortDeepLink` — `tixello://shorts/{id}`

**D2 — remind / drop**
- [x] `short_reminders` (unic pe customer+short, index pe `remind_at`/`notified_at`)
- [x] `ShortReminderService` — fereastra de vânzare din `TicketType.sales_start_at`,
      `remind_at` **copiat** la creare (nu rezolvat la fire time), oglindit în watchlist
- [x] `POST`/`DELETE customer/shorts/{id}/remind` — refuză când biletele sunt deja la vânzare
- [x] `FireDropRemindersJob` — programat **la minut**; nu retrimite niciodată
- [x] Payload-ul de feed marchează `cta.pending` + `cta.on_sale_at`
- [x] Client: `DropCountdown` — countdown + „Amintește-mi" în locul unui buton mort

**D9 — UX player**
- [x] `shorts.blurhash` + `GenerateBlurhashJob` (grilă 2×3 de culori medii, GD)
- [x] Client `Blurhash` — gradient sub video până urcă posterul
- [x] `usePlaybackPreferences` — autoplay (mereu/Wi-Fi/niciodată), data-saver, tip de rețea
      via `@capacitor/network`
- [x] Data-saver: buffer 6s, `startLevel: 0`, prefetch 0 (fără vecini montați)

**D11 — gamification**
- [x] `short_streaks` + `ShortGamificationService` (watch zilnic, share, UGC)
- [x] Plafon zilnic de puncte (anti-farming) + bonus de streak plafonat
- [x] `GET customer/shorts/streak`, `POST customer/shorts/watched`
- [x] Client: ping o dată pe sesiune după primul view credibil + toast „+X puncte"

**D10 — accesibilitate**
- [x] `prefers-reduced-motion` → fără autoplay, poster + buton de play
- [x] Setare explicită de autoplay, independentă de cont (preferință de dispozitiv)
- [x] Ținte de atingere ≥44px, focus vizibil, `aria-pressed` pe toggle-uri
- [x] Regiune `aria-live` care anunță schimbarea short-ului
- [x] Avertisment de conținut pentru `flashing` (din `shorts.content_flags`)
- [x] Niciodată autoplay cu sunet

**Dependențe lipsă, puse ca stub (funcționale, cu `TODO(owner)`)**
- `PushSender` + `LogPushSender` — EPAS nu are layer de push; fiecare notificare e
  logată cu payload complet, deci logica de declanșare e verificabilă end-to-end
- `IdentityBridge` — puntea `MarketplaceCustomer ↔ Customer` lipsește
  (`friends-social.md` §0); punctele stau marketplace-side până apare coloana de legătură

## Faza 4 — Shoppable (B1) ✅

- [x] `shorts.conversions` / `revenue_cents` / `revenue_currency`
- [x] `orders.source_short_id` / `source_feed` / `short_attributed_at` (aditiv, nullable)
- [x] `ShortAttributionService` — credit + reversare, idempotent prin
      `short_attributed_at`; agregatele nu pot deveni negative
- [x] `ShortAttributionOrderObserver` — pe tranziția de status, nu pe eveniment
      (comenzile ajung „paid" pe mai multe căi; doar modelul le vede pe toate);
      nu aruncă niciodată — o atribuire eșuată nu are voie să dea înapoi o comandă plătită
- [x] `POST tenant-client/shorts/{id}/cta-click` — trimis imediat, nu batched;
      întoarce oferta de onorat (event, ticket type, promo, `source_short_id`, `source_feed`)
- [x] Client: `reportCtaClick` + `sourceShortId`/`sourceFeed` propagate spre checkout
- [x] Admin: coloane CTR, Sales, CVR, Revenue — derivate, nu stocate
- [x] Teste: 9 (click CTA, credit unic, retry-uri de webhook, refund, floor la zero,
      comenzi fără short, short șters, rate)

> **TODO(owner):** ultimul pas al buclei rămâne de făcut în checkout —
> `CheckoutController` trebuie să treacă `source_short_id` / `source_feed` din payload
> în `Order::create()`. Coloanele sunt `fillable`, deci e o linie; nu am atins
> controllerul de checkout (1700+ linii, fluxuri de plată reale) fără să pot rula
> un test de checkout end-to-end pe schema completă.

## Faza 5 — Following + ranker For You (B2) ✅

**Graf de urmărire**
- [x] `marketplace_follows` (polimorf: Artist / Tenant / Venue, unic pe triplet)
- [x] `MarketplaceFollow` — API vorbește tokenuri scurte („artist"), nu nume de clase
- [x] `GET`/`POST marketplace-client/customer/follows` (toggle, 404 pe țintă inexistentă)
- [x] Un follow invalidează imediat cache-ul de profil — apare în pagina următoare

**Ranker „For You"**
- [x] `ShortFeedRanker` — scor explicabil pe termeni numiți, cu ponderi din config:
      afinitate (follow / favorit / eveniment cumpărat), popularitate (velocitate,
      comprimată logaritmic), watch ratio, geo, prospețime (decay cu half-life),
      featured, penalizare pentru „deja văzut"
- [x] `ShortAffinityProfile` — gustul spectatorului, construit o dată per pagină și
      cache-uit 5 minute; citește ce există deja (follows, favorite, comenzi, oraș)
- [x] Diversitate: niciodată două short-uri consecutive de la același owner, **fără**
      să scurteze pagina (cele amânate merg la coadă, nu la gunoi)
- [x] Cursorul avansă pe keyset-ul de recență, nu pe ordinea rankată — altfel
      re-scorarea între pagini ar sări sau ar repeta rânduri
- [x] Cold start: fără semnale → featured + prospețime + popularitate

**Segmente noi de feed**
- [x] `following` — doar owner-i urmăriți; gol onest când nu urmărești pe nimeni
- [x] `nearby` — orașul vine de pe **venue** (`events` n-are coloană `city`);
      `?city=` din client bate orașul din profil

**Geamăn tenant Filament**
- [x] `App\Filament\Tenant\Resources\ShortResource` — scoped pe `tenant_id`
- [x] Short-urile organizatorului intră în `pending_review`, nu direct în feed;
      editarea unuia publicat îl trimite înapoi la review
- [x] Coloane de performanță: views, watch %, CTA, Sales, Revenue

- [x] Teste: 13 (follow toggle, segmente, ranker, diversitate, cursor, geo, resursă)

## Faza 6 — Ingestie externă + seed YouTube (B4) ✅

> **Regula de aur respectată:** metadate + thumbnail + cod de embed. Fișierul video
> NU e descărcat și NU e re-hostat niciodată — ar încălca ToS-ul și dreptul de autor
> la toate cele patru platforme.

- [x] `ShortIngestService` — detecție de platformă + ingestie normalizată
  - **YouTube**: `YouTubeService::extractVideoId()` (prinde deja `/shorts/`) +
    `getVideosStats` (cache 6h → respectă cota Data API); embed `youtube-nocookie`
  - **TikTok**: oEmbed public, fără cheie și fără app review; cache 24h
  - **Meta (IG/FB)**: oEmbed Read — întoarce `null` cât timp tokenul lipsește,
    ca adminul să vadă „platformă neconectată", nu un short pe jumătate completat
- [x] `IngestShortJob` — completează embed/titlu/durată, **cache-uiește thumbnail-ul
      local** (URL-urile de CDN ale platformelor expiră), nu suprascrie niciodată un
      titlu scris de mână, lasă short-ul în `draft` (ingestia nu e curatoriere)
- [x] `PullChannelShortsJob` — seed din canalul YouTube al unui artist; filtrează
      strict la ≤60s (un live set de 40 de minute n-are ce căuta într-un feed vertical),
      dedup pe `(source, source_video_id)`, leagă de următorul eveniment al artistului
- [x] Programat săptămânal, doar pentru artiștii **cu evenimente viitoare** — cota
      Data API e zilnică și n-are rost arsă pe short-uri pe care nu le curatorează nimeni
- [x] Acțiuni Filament „Fetch from link" (per rând + bulk)
- [x] `services.meta.oembed_token` + `META_OEMBED_TOKEN` placeholder
- [x] Teste: 9 (detecție, YouTube, TikTok, Meta fără token, platforme necunoscute,
      durate ISO-8601, job de ingestie, titlu păstrat, link inutilizabil)

**Fix colateral:** `YouTubeService::__construct` arunca `TypeError` când
`YOUTUBE_API_KEY` lipsea (`config()` întoarce `null`, proprietatea e tipată `string`).
Nu se vedea cât timp serviciul era construit doar cu `new`; `ShortIngestService` îl
rezolvă din container, deci devenea o eroare de boot pe orice mediu fără cheie.

## Faza 7 — Val 2 (măsurare & scalare) ✅

**D4 — retenție, atribuire pe segment, trending**
- [x] `short_retention` + `AggregateShortRetentionJob` — curba de drop-off în decile;
      re-rularea înlocuiește ziua, deci nu poate dubla
- [x] `shorts.trending_score` + `ComputeTrendingJob` la 15 min — **velocitate raportată
      la baseline**, nu totaluri: un short cu un milion de vizionări istorice nu e
      „trending"; ce nu mai are engagement în fereastră decade la zero
- [x] Atribuirea pe segment de feed (`orders.source_feed`) — livrată în Faza 4
- [x] Termen `trending` în ranker, cu pondere proprie în config

**D6 — scalarea telemetriei**
- [x] `PruneShortEventsJob` zilnic — șterge în bucăți de 5000 (un DELETE nemărginit
      peste luni de rânduri blochează tabelul și expiră pe worker)
- [x] Eșantionarea impresiilor și pragurile de credibilitate — livrate în Faza 1
- [ ] Partiționare pe lună — `TODO(owner)`: e o decizie de DBA pe Postgres
      (partiționare declarativă + migrarea datelor existente), nu ceva de făcut
      orbește fără acces la volumul real

**D5 — evoluția rankerului**
- [x] `short_impressions` + `SyncShortImpressionsJob` — seen-store distilat, care
      **supraviețuiește prune-ului**; fără el, un short reapare în feed după 90 de zile
      doar pentru că dovada a fost ștearsă
- [x] Explorare epsilon-greedy — o felie din fiecare pagină e rezervată short-urilor
      cu prea puține impresii ca să fi apucat să câștige un scor; fără asta rankerul
      e rich-get-richer și un short nou nu poate ieși niciodată la suprafață
- [x] Explorarea deplasează de la **coada** paginii, nu din vârf

**D12 — notificări comportamentale**
- [x] `notification_preferences` — absența unui rând înseamnă „folosește default-ul
      tipului", deci tabelul crește doar când cineva chiar schimbă ceva
- [x] `EvaluateBehaviouralTriggersJob` — trigger-ul „a văzut N short-uri pentru
      același eveniment și n-a cumpărat", cu: opt-in (default **oprit** pentru acest
      tip), quiet hours, cooldown de 14 zile per (user, eveniment), și verificarea
      că nu a cumpărat deja
- [ ] Înrolarea în `AutomationWorkflow` — `TODO(owner)`: evaluarea trigger-ului și
      gardurile sunt gata; ce lipsește sunt șabloanele de workflow prin care
      marketingul editează copy-ul și cadența fără deploy

- [x] Teste: 13 (trending, decay, retenție, idempotență, prune, seen-store,
      supraviețuire la prune, explorare, prag de intenție, deja-cumpărat, opt-in,
      cooldown, default-uri de preferințe)

## Faza 8 — Auto-gen + captions + analytics organizator ✅

**B3 — auto-generare din media existentă**
- [x] Contract `VideoRenderer` + `ShotstackRenderer` (timeline 1080×1920, Ken-Burns,
      titlu) + `NullVideoRenderer`
- [x] `GenerateShortFromEventJob` — două moduri, decise de existența unui renderer:
      clip vertical real, sau **„poster short"** (imagine redată ca un card în feed)
- [x] Un renderer picat cade automat pe poster short — o pană de render nu are voie
      să lase un short neredabil în coadă
- [x] `render_job_id` — o re-rulare nu poate cere un al doilea render pentru același short
- [x] Sare peste evenimentele care au deja un short; nu creează nimic fără imagini

**B6 — captions**
- [x] `short_captions` + `ShortCaption` (unic pe short+limbă)
- [x] `GenerateCaptionsJob` — întâi ce are deja providerul video (gratis), apoi un
      driver de transcriere, apoi nimic: subtitrările sunt un plus, niciodată un
      blocaj la publicare
- [x] Track-urile ajung în payload-ul de feed doar când relația e eager-loaded
      (fără N+1 ascuns în serializator)
- [x] Acțiune Filament „Generate captions"

**B5 — analytics organizator**
- [x] `short_analytics_daily` + `AggregateShortAnalyticsJob` — telemetrie + conversii
      într-un singur rând pe zi; **stocat**, nu interogat live, fiindcă rândurile brute
      din care e construit sunt tăiate de retenție
- [x] Pagină Filament tenant `ShortsAnalytics`: pâlnie (afișare → vizionare → CTA →
      vânzare), curbă de retenție pe decile, top shorts cu venit
- [x] Rate calculate, nu stocate

- [x] Teste: 13 (poster short, render job, fallback la pană, skip, fără imagini,
      fallback de container, captions ×4, rollup zilnic, idempotență, webhook render)

**TODO(owner):** cheile Shotstack lipsesc, deci calea de render real e neatinsă în
practică — containerul leagă `NullVideoRenderer` și rulează poster short. Driverul de
transcriere pentru captions e tot un `TODO(owner)`.

## Faza 9 — Collections + Stories ✅

**B7 — colecții editoriale**
- [x] `short_collections` + `short_collection_items` (ordine în pivot) + `ShortCollection`
- [x] Slug derivat din titlu când lipsește
- [x] Scop: o colecție fără `marketplace_client_id` e editorială și se vede pe orice
      marketplace; una cu client se vede doar acolo
- [x] `GET tenant-client/short-collections` (cu preview per colecție, ca ecranul de
      discovery să randeze rail-uri într-un singur drum) + `GET .../{slug}`
- [x] Doar short-uri publicate intră în colecție
- [x] Resursă Filament în core admin (colecțiile traversează tenanți prin definiție)

**B8 — stories efemere**
- [x] `shorts.is_story` — un story **este** un short cu expirare; un tabel separat ar
      fi însemnat duplicarea redării, telemetriei și moderării odată cu el
- [x] `scopeStories()` cere expirare validă: un story fără `expires_at` nu e story
- [x] `GET tenant-client/stories` — grupat pe owner, cu numărul de segmente, exact
      cum randează tava
- [x] Story-urile sunt **excluse** din feed-ul principal — tava se joacă tap-through,
      nu în infinite scroll

**Igienă (§14)**
- [x] `CheckShortHealthJob` orar — arhivează ce a trecut de `expires_at` (inclusiv
      stories) și embed-urile externe al căror video a fost șters
- [x] O eroare de rețea NU e tratată ca „video mort" — un blip nu are voie să
      arhiveze un short sănătos

- [x] Teste: 9 (slug + ordine, doar publicate, scoping, grupare stories, excluderea
      din feed, expirare, embed mort, blip de rețea, resursă Filament)

> **Bug prins de teste:** filtrul de stories nu se aplicase pe `baseQuery()` —
> o modificare de fază anterioară mutase linia pe care se baza patch-ul, iar
> story-urile ar fi apărut în feed. Fără testul „stories stay out of the main feed"
> ar fi ajuns în producție tăcut.

## Faza 10 — Val 3 (bani & compliance) ✅

**D3 — shorts promovate**
- [x] `short_promotions` + `short_promotion_events` (dovada de facturare stă separat de
      `short_events`, care e tăiat de retenție)
- [x] CPM (bid/1000 per impresie) și CPC; bugetul epuizat oprește imediat difuzarea
- [x] **Pacing** — un flight înaintea curbei sale stă pe bară, ca să nu ardă bugetul
      unei săptămâni în prima oră
- [x] **Frequency capping** — 3 afișări/zi/utilizator per promoție
- [x] Injectare pe slot fix, nu în scor, și **întotdeauna etichetat „Sponsorizat"**:
      banii n-au voie să devină tăcut relevanță

**D7 — drepturi & licențiere**
- [x] `rights_holder`, `license_type`, `usage_expires_at`, `territories`, `age_rating`
- [x] `ShortRightsGuard` — fereastră de licență (când expiră dreptul **nostru** de a
      arăta, nu expirarea short-ului), teritoriu allow/deny, age gate pe dată de naștere
      verificată
- [x] Aplicat ca **constrângeri de query**, nu post-filtru: altfel paginile ies scurte
      și cursorul se strică
- [x] Locație necunoscută ≠ „oriunde e ok" — se servesc doar short-urile nerestricționate

**D8 — guardrails de cost**
- [x] `CostGuardService` + `PollBunnyUsageJob` la 6h
- [x] Proiecție la sfârșit de lună, nu consum curent („60% pe 5" e problema, „60% pe 28"
      nu e)
- [x] Peste prag → data-saver global (max 480p, prefetch 0), plus kill switch manual
- [x] Fără plafon configurat = fără guardrail (nu presupune limite pe care nimeni nu le-a cerut)

**B9 — UGC verificat + moderare**
- [x] Poți posta doar pentru un eveniment la care **ai fost**, dovedit cu un bilet
      scanat pe care îl deții — asta face din UGC o buclă de creștere, nu o suprafață de spam
- [x] Verificarea eșuată = **fără** permisiune (fail-closed)
- [x] Rate limit zilnic per (user, eveniment); tot ce se încarcă intră în `pending_review`
- [x] `short_reports` + auto-ascundere la N rapoarte: a ascunde câteva ore ceva bun costă
      mult mai puțin decât a lăsa sus ceva dăunător
- [x] Raportarea rămâne deschisă și pentru guest

**B10 — A/B pe cover**
- [x] `short_poster_variants` + `PickPosterWinnerJob` zilnic
- [x] Câștigătorul se declară doar când **fiecare** variantă are eșantion suficient —
      a decide la 100 de impresii înseamnă a alege zgomot și a arunca definitiv varianta
      care poate era mai bună
- [x] O singură variantă nu e test

- [x] Teste: 17 (injectare + etichetă, pacing, buget epuizat, CPM, frequency cap,
      licență expirată, age gate ×3, teritorii ambele sensuri, proiecție, data-saver,
      fără plafon, eligibilitate UGC ×4, auto-hide, motiv normalizat, A/B ×2)

---

## Cum rulezi local

```bash
composer install
npm install && npm run build          # Vite manifest — altfel panoul Filament nu bootează
cp .env.example .env && php artisan key:generate

./scripts/shorts-dev-migrate.sh --reset   # schemă de dev redusă (vezi DECISIONS.md D-002)
php artisan test --filter=Shorts
./vendor/bin/pint
```

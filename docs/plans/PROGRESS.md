# PROGRESS.md — Shorts

Branch: `claude/shorts` (pornit din `origin/core`).
Plan: `docs/plans/shorts.md` · Mandat: `docs/plans/shorts-START-PROMPT.md` · Decizii: `DECISIONS.md`

---

## Rezumat pentru owner

**Gata.**

- **Faza 1** — fundația completă: migrațiile `shorts` / `short_likes` / `short_saves` /
  `short_events`, modelul `Short` (polimorf, fără tenant scope global), abstracția
  `VideoProvider` + `BunnyStreamProvider` (Partea C) cu chei placeholder, resursa Filament
  centrală în grupul „Core", API-ul de feed cu paginare cursor, telemetria batched și
  toggle-urile like/save. **25 de teste, 81 de aserțiuni, toate verzi.**
- **Faza 2** — redarea pe mobil, în `tics-app/mobile` (app-ul **este** în repo): feed
  vertical real cu HLS, autoplay, preload, overlay, like/save/share și telemetrie batched,
  cu fallback pe feed-ul din prototip. `tsc` + `vite build` verzi.
  Detalii: `docs/plans/shorts-mobile.md`.
- **Faza 3 (Val 1)** — creștere ieftină: share cu referral + landing web (D1), remind/drop
  cu countdown (D2), UX de player cu blurhash/data-saver (D9), gamification cu plafon
  anti-abuz (D11), accesibilitate (D10).
- **Faza 4 (Shoppable, B1)** — CTA cu bilet + promo, `orders.source_short_id`/`source_feed`,
  atribuire last-touch idempotentă cu reversare la refund, CTR/CVR/venit în admin.
- **Faza 5 (B2)** — graf de urmărire polimorf, ranker „For You" explicabil cu ponderi în
  config, segmentele `following` / `nearby`, geamăn tenant în Filament cu moderare.
- **Faza 6** — ingestie externă prin link (YouTube / TikTok / Meta) + seed automat din
  canalele YouTube ale artiștilor, cu regula „niciodată re-host" respectată.
- **Faza 7 (Val 2)** — trending pe velocitate, curba de retenție, prune de telemetrie,
  seen-store durabil + explorare în ranker, nudge-uri comportamentale opt-in.
  **81 de teste, 216 aserțiuni, toate verzi.**

**Urmează.** Faza 8 — auto-gen din media (B3), captions (B6), analytics organizator (B5).

**Blocaje / de știut.**

1. **Migrațiile nu pot fi verificate pe schema completă.** Istoricul de 747 de migrații
   nu se poate reda pe SQLite (o migrație preexistentă, `2025_10_31_200100_events_translatables`,
   face `DROP COLUMN` pe o coloană indexată — SQLite refuză), iar containerul nu are
   PostgreSQL, MySQL sau daemon Docker. Migrațiile Shorts sunt verificate izolat, pe o
   schemă redusă (vezi D-002). → **`TODO(owner)`: rulează migrațiile Shorts o dată pe un
   dump de dev PostgreSQL înainte de deploy.**
2. **Cheile Bunny sunt placeholder** (gard: fără chei reale). Containerul cade pe
   `NullVideoProvider` când nu sunt configurate — dev/CI pornesc, feed-ul servește shorts
   externe și self-hosted. → `TODO(owner)`: pașii din `shorts.md` §C0 în dashboard-ul Bunny.
3. **Schema exactă a token-ului Bunny** trebuie confirmată pe pull zone când apar cheile
   reale (`TODO(owner)` marcat în `BunnyStreamProvider::sign()`).
4. **Convenție obligatorie:** orice migrație Shorts nouă se numește
   `<timestamp>_shorts_<descriere>.php` — altfel nu intră în schema de test (vezi D-002).

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

## Faza 8 — Auto-gen + captions + analytics organizator ⏳

- [ ] B3 `VideoRenderer` + `GenerateShortFromEventJob` (MVP „poster short")
- [ ] B6 `short_captions` + `GenerateCaptionsJob`
- [ ] B5 `short_analytics_daily` + pagină Filament tenant

## Faza 9 — Collections + Stories ⏳

- [ ] B7 `short_collections` + items + API
- [ ] B8 `is_story` + `GET tenant-client/stories` + `CheckShortHealthJob`

## Faza 10 — Val 3 (bani & compliance) ⏳

- [ ] D3 shorts promovate (CPM/CPC, pacing, frequency capping)
- [ ] D7 drepturi / licențiere / age-gating / geo
- [ ] D8 guardrails de cost Bunny
- [ ] B9 UGC verificat + moderare · B10 A/B pe cover

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

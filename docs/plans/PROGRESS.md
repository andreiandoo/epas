# PROGRESS.md — Shorts

Branch: `claude/shorts` (pornit din `origin/core`).
Plan: `docs/plans/shorts.md` · Mandat: `docs/plans/shorts-START-PROMPT.md` · Decizii: `DECISIONS.md`

---

## Rezumat pentru owner

**Gata.** Faza 1 — fundația completă: migrațiile `shorts` / `short_likes` / `short_saves` /
`short_events`, modelul `Short` (polimorf, fără tenant scope global), abstracția
`VideoProvider` + `BunnyStreamProvider` (Partea C) cu chei placeholder, resursa Filament
centrală în grupul „Core", API-ul de feed cu paginare cursor, telemetria batched și
toggle-urile like/save. **25 de teste, 81 de aserțiuni, toate verzi.**

**Urmează.** Faza 2 (modul client de redare verticală), apoi Valul 1 (D1/D2/D9/D11/D10).

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

## Faza 2 — Redare mobil ⏳

- [ ] Pager vertical + HLS (hls.js pe Android/WebView, nativ pe iOS)
- [ ] Autoplay la intrare în viewport, pauză la ieșire, un singur „unmuted"
- [ ] Preload N+1/N+2, dezmontarea celor îndepărtate
- [ ] Overlay: caption, hashtags, owner, like/save/share, CTA
- [ ] Telemetrie batched din client
- [ ] Documentație de integrare (app-ul mobil nu e în acest repo)

## Faza 3 — Val 1 (creștere ieftină) ⏳

- [ ] D1 share + referral + landing web
- [ ] D2 remind/drop countdown
- [ ] D9 UX player (blurhash, prefetch, data-saver)
- [ ] D11 gamification
- [ ] D10 accesibilitate

## Faza 4 — Shoppable (B1) ⏳

- [ ] `shorts.conversions` / `revenue_cents` · `orders.source_short_id`
- [ ] Propagarea atribuirii last-touch · listener pe „order paid" / refund
- [ ] CTR / CVR în admin

## Faza 5 — Following + ranker For You (B2) ⏳

- [ ] `marketplace_follows` + API
- [ ] `ShortFeedRanker` (afinitate, popularitate, watch, geo, prospețime, diversitate)
- [ ] Geamăn tenant `ShortResource` (scoped pe `tenant_id`)

## Faza 6 — Ingestie externă + seed YouTube (B4) ⏳

- [ ] `ShortIngestService` (YouTube → TikTok → Meta)
- [ ] `IngestShortJob`, `PullChannelShortsJob`
- [ ] Acțiuni Filament „Preia din link" / „Importă Shorts YouTube"

## Faza 7 — Val 2 (măsurare & scalare) ⏳

- [ ] D4 retenție / atribuire pe segment / trending
- [ ] D6 partiționare, rollup, prune, sampling
- [ ] D5 evoluția rankerului (explorare, seen-store, diversitate)
- [ ] D12 notificări comportamentale prin `AutomationWorkflow`

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

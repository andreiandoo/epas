# Plan detaliat — Shorts (video vertical scurt), MOBILE-ONLY

> Feature: feed de „shorts" (video vertical, tip Reels/TikTok/YT Shorts) folosit **exclusiv în aplicația mobilă Tixello**. Curatoriabil central în core admin și atașabil la orice eveniment/umbrelă.
> Status codebase: feature inexistentă (nu există model short/reel azi).
> Branch de referință pentru fișierele citate: `core`.

---

## 0. Scope: „mobile-only" — ce implică

Pentru că shorts trăiesc **doar** în app-ul mobil (Capacitor + React), nu în paginile web publice:
- **Nu** e nevoie de blocuri page-builder / randare pe site → ignorăm `VideoBlock`/`EmbedBlock` pentru afișare.
- API-ul servește **doar** clientul mobil (`tenant-client` public + token `MarketplaceCustomer` pentru personalizare/interacțiuni).
- Prioritatea #1 devine **redarea fluidă în feed** (autoplay, scroll vertical, preload) — asta dictează decizia de pipeline video de la §1.
- Interacțiunile (like/save/share/CTA), analytics și personalizarea sunt **in-app**, nu pe web.
- Deep-linking din share → deschide app-ul (fallback web minimal doar pentru „open in app").

---

## 1. Decizia #1 (structurantă): pipeline-ul video

Există două tipuri de conținut, cu implicații foarte diferite:

| Tip | Sursă | Redare în feed | Autoplay/UX | ToS/host |
|---|---|---|---|---|
| **Native (upload)** | organizatorul urcă un `.mp4` vertical | **HLS adaptiv** în `<video>` | ✅ perfect (autoplay muted, preload, scrub) | îl deții tu |
| **Extern (embed)** | link YT/TikTok/IG/FB | iframe/embed platformă | ⚠️ inconsistent (branding, fără autoplay fiabil, greu de preîncărcat) | doar embed, nu re-host |

**Recomandare (importantă):** pentru un feed fluid tip TikTok, **conținutul principal trebuie să fie native/HLS**. Externele rămân un tip **secundar** — card „link-out" sau embed best-effort — pentru că nu pot livra autoplay seamless în feed și au constrângeri de ToS/branding.

**Nu ai `ffmpeg`/transcodare în stack** (confirmat: fără ffmpeg/yt-dlp, queue = `database`). Deci pentru upload native alege un **serviciu video managed** care face transcode→HLS, thumbnails, ABR și playback semnat, fără să întreții infra:
- **Cloudflare Stream** sau **Bunny Stream** — cost mic, HLS + thumbnails + direct upload. (recomandate)
- **Mux** — DX excelent, webhooks bogate, mai scump.

Abstracție ca să fie schimbabil:
```php
// app/Services/Video/VideoProvider.php  (interface)
interface VideoProvider {
    public function createDirectUpload(array $meta): array;   // ['upload_url','asset_id']
    public function getPlayback(string $assetId): array;      // ['hls_url','poster_url','duration','width','height']
    public function delete(string $assetId): void;
    public function verifyWebhook(Request $r): bool;
}
// implementări: CloudflareStreamProvider, BunnyStreamProvider, MuxProvider
// config('services.video.driver') alege providerul
```
**Flux upload native (fără a trece fișierul prin Laravel):**
1. Admin/organizator cere un „direct upload URL" → `POST .../shorts/upload-url` → `VideoProvider::createDirectUpload()`.
2. Clientul urcă fișierul **direct** la provider.
3. Providerul trimite webhook „asset ready" → `POST webhooks/video/{provider}` → job `SyncShortPlaybackJob` completează `hls_url`, `poster_url`, `duration`, `ready=true`, `status` rămâne `draft` până la publicare.

> MVP fără provider: poți începe cu `.mp4` mic pe disk `public` redat în `<video>` (fără ABR) ca să validezi feed-ul, apoi comuți pe managed prin abstracția de mai sus. Marchează asta ca datorie tehnică.

---

## 2. Model de date

### 2.1 Migrație `create_shorts_table`
```php
Schema::create('shorts', function (Blueprint $t) {
    $t->id();

    // Owner polimorf (nullable = short editorial)
    $t->nullableMorphs('owner');                 // owner_type / owner_id (Event|Activity|Attraction|Artist|Tenant)

    // Umbrelă (ca MediaLibrary — FĂRĂ global tenant scope pe model)
    $t->foreignId('tenant_id')->nullable()->index();
    $t->foreignId('marketplace_client_id')->nullable()->index();
    $t->foreignId('event_id')->nullable()->index();   // denormalizat pt query dominant

    // Sursă & pipeline
    $t->enum('source', ['upload','youtube','tiktok','instagram','facebook'])->default('upload');
    $t->text('source_url')->nullable();
    $t->string('source_video_id')->nullable();
    $t->longText('embed_html')->nullable();           // doar extern

    // Native / managed video
    $t->string('video_provider')->nullable();         // cloudflare|bunny|mux|self
    $t->string('provider_asset_id')->nullable();
    $t->text('hls_url')->nullable();
    $t->boolean('ready')->default(false);             // asset transcodat & gata
    $t->string('disk')->nullable();                   // fallback self-host
    $t->string('path')->nullable();
    $t->string('mime_type')->nullable();
    $t->unsignedInteger('duration')->nullable();      // secunde
    $t->unsignedInteger('width')->nullable();
    $t->unsignedInteger('height')->nullable();

    // Prezentare / feed
    $t->string('poster_path')->nullable();            // thumbnail/cover
    $t->string('title')->nullable();
    $t->text('caption')->nullable();
    $t->json('hashtags')->nullable();
    $t->string('language', 8)->nullable();
    $t->string('music_credit')->nullable();

    // Commerce (shoppable — vezi §11)
    $t->enum('cta_type', ['none','buy_tickets','open_event','open_artist','external'])->default('none');
    $t->string('cta_label')->nullable();
    $t->string('cta_url')->nullable();
    $t->foreignId('cta_ticket_type_id')->nullable();  // bilet specific
    $t->string('promo_code')->nullable();

    // Publicare & moderare
    $t->enum('status', ['draft','pending_review','published','archived','rejected'])->default('draft');
    $t->boolean('is_featured')->default(false);        // pt feed editorial global
    $t->unsignedInteger('sort')->default(0);
    $t->timestamp('published_at')->nullable();
    $t->timestamp('expires_at')->nullable();

    // Stats denormalizate (agregate din short_events)
    $t->unsignedBigInteger('impressions')->default(0);
    $t->unsignedBigInteger('views')->default(0);       // ≥ prag watch
    $t->unsignedBigInteger('completions')->default(0);
    $t->unsignedBigInteger('likes')->default(0);
    $t->unsignedBigInteger('saves')->default(0);
    $t->unsignedBigInteger('shares')->default(0);
    $t->unsignedBigInteger('cta_clicks')->default(0);
    $t->decimal('avg_watch_ratio', 4, 3)->default(0);  // 0..1

    $t->timestamps();
    $t->softDeletes();

    $t->index(['status','published_at']);
    $t->index(['owner_type','owner_id']);
    $t->index(['is_featured','status']);
});
```

### 2.2 Interacțiuni & analytics
```php
// like/save ca stare (idempotent, per user)
Schema::create('short_likes', function (Blueprint $t) {
    $t->id();
    $t->foreignId('short_id')->constrained()->cascadeOnDelete();
    $t->foreignId('marketplace_customer_id')->constrained('marketplace_customers')->cascadeOnDelete();
    $t->timestamps();
    $t->unique(['short_id','marketplace_customer_id']);
});
// (identic short_saves)

// eveniment de analytics (flux brut → agregat prin job)
Schema::create('short_events', function (Blueprint $t) {
    $t->id();
    $t->foreignId('short_id')->constrained()->cascadeOnDelete();
    $t->foreignId('marketplace_customer_id')->nullable()->index();
    $t->string('session_id', 64)->nullable();
    $t->enum('type', ['impression','view','complete','like','unlike','save','share','cta_click','skip']);
    $t->unsignedInteger('watch_ms')->nullable();
    $t->decimal('watch_ratio', 4, 3)->nullable();
    $t->string('feed', 32)->nullable();     // for_you|following|nearby|event|artist
    $t->timestamp('created_at')->useCurrent();
    $t->index(['short_id','type']);
    $t->index(['created_at']);
});

// atribuire conversie (vezi §11)
// ALTER orders ADD COLUMN source_short_id BIGINT NULL INDEX
```

### 2.3 Model `app/Models/Short.php`
- Relații: `morphTo owner()`, `belongsTo Event`, `belongsTo Tenant`, `belongsTo MarketplaceClient`, `belongsTo TicketType (cta_ticket_type_id)`, `hasMany short_events`, `hasMany short_likes`.
- **NU** `SecureTenantScoping` (ca `MediaLibrary`) — core admin vede tot; scoparea în resurse.
- Accessors: `is_external` (`source!=='upload'`), `playback_url` (native→`hls_url`; self→`Storage::disk($disk)->url($path)`; extern→`source_url`), `poster_url`.
- Scopes: `published()` (`status=published`, `published_at<=now`, `(expires_at null||>now)`, `ready` pt native), `forEvent()`, `forOwner()`, `featured()`.
- Casts: `hashtags array`, datetimes, `avg_watch_ratio decimal`.

---

## 3. Admin (Filament v4.1)

### 3.1 Central — `app/Filament/Resources/Shorts/ShortResource.php`
- Namespace `App\Filament\Resources\Shorts`; `$navigationGroup = 'Core'`; **fără** `getEloquentQuery()` (vede tot). Auto-descoperit de `AdminPanelProvider`.
- Form (`Filament\Schemas\Schema`, `Filament\Schemas\Components as SC`):
  - **Sursă**: `Select source`; dacă extern → `TextInput source_url` + acțiune „Preia din link" (dispatch `IngestShortJob`, vezi `docs/plans/social-video-ingestion.md`); dacă upload → widget „Încarcă video" care cheamă `createDirectUpload()` (upload direct la provider) + progres.
  - **Owner**: `MorphToSelect owner` (Event/Activity/Attraction/Artist/Tenant) + `Select event_id` opțional.
  - **Prezentare**: `FileUpload poster` (disk `public`), `title`, `caption`, `TagsInput hashtags`, `language`, `music_credit`.
  - **Commerce**: `cta_type`, `cta_label`, `Select cta_ticket_type_id` (relatie de event), `promo_code`, `cta_url`.
  - **Publicare**: `status`, `is_featured`, `published_at`, `expires_at`, `sort`.
- Table: preview `poster` (`ImageColumn->disk('public')`), `title`, `source` badge, `owner`, `status`, `ready` icon, `views`, `avg_watch_ratio`, `cta_clicks`.
- Bulk: publish/archive/feature. Pattern din `app/Filament/Resources/PriceTierResource.php`.

### 3.2 Per-tenant — `app/Filament/Tenant/Resources/ShortResource.php`
- `getEloquentQuery()` scoped pe `tenant_id` (tiparul din `EventResource.php:82-97`). Organizatorul își gestionează shorts pe evenimentele lui; `status` pornește `pending_review` dacă vrei moderare centrală înainte de feed-ul global (vezi §14).

---

## 4. Ingestie externă (referință)

Vezi `docs/plans/social-video-ingestion.md`. Pe scurt: link → `IngestShortJob` (șablon `FetchArtistSocialStats`) → `source_video_id` + `embed_html` + thumbnail (descărcat pe `public`). YouTube parsează deja `/shorts/` (`YouTubeService.extractVideoId()`).

---

## 5. API (consumat doar de app-ul mobil)

Sub `Route::prefix('tenant-client')` (public read + CORS, `routes/api.php:60`) + `marketplace-client/customer/*` (auth `sanctum` pt interacțiuni):

```
# Feed & citire (public sau cu token pt personalizare)
GET  tenant-client/shorts?feed=for_you|nearby|featured&cursor=...   // feed vertical paginat (cursor)
GET  tenant-client/events/{slug}/shorts
GET  tenant-client/artists/{slug}/shorts
GET  tenant-client/shorts/{id}                                      // detaliu (deep-link/share)

# Interacțiuni (auth: sanctum + marketplace.auth)
POST marketplace-client/customer/shorts/{id}/like        // toggle
POST marketplace-client/customer/shorts/{id}/save        // toggle
GET  marketplace-client/customer/shorts/feed?feed=following|for_you
GET  marketplace-client/customer/shorts/saved

# Telemetrie (batched, fire-and-forget; poate accepta și guest prin session_id)
POST tenant-client/shorts/events        // {events:[{short_id,type,watch_ms,watch_ratio,feed}]}

# Upload native (admin/organizer, auth)
POST tenant/shorts/upload-url           // → {upload_url, asset_id}
POST webhooks/video/{provider}          // provider → ready
```

**Payload feed (per short):**
```json
{
  "id": 123, "source": "upload",
  "playback": { "hls_url": "https://.../playlist.m3u8", "poster_url": "https://..." },
  "embed_html": null, "duration": 18, "aspect": "9:16",
  "title": "...", "caption": "...", "hashtags": ["untold"], "music_credit": "...",
  "owner": { "type": "event", "slug": "untold-2026", "name": "UNTOLD 2026" },
  "event": { "slug": "untold-2026", "title": "UNTOLD 2026", "date": "2026-08-06", "price_from": 350, "currency": "RON" },
  "cta": { "type": "buy_tickets", "label": "Ia bilet", "ticket_type_id": 55, "promo_code": "SHORT10" },
  "stats": { "likes": 1240, "views": 88000 },
  "viewer": { "liked": false, "saved": false }
}
```
- **Paginare cursor** (nu offset) — feed-ul e infinit-scroll.
- Controller în stilul `TenantClientController`/`PublicDataController`.

---

## 6. Feed „For You" — ranking & personalizare

Semnale (ai deja sursele: `MarketplaceCustomer.favoriteEvents/favoriteArtists`, preferințe de gen din onboarding, `orders`, oraș):
- **Afinitate**: artist/venue/gen favorit sau cumpărat înainte.
- **Popularitate**: viteza de views/completions/likes (fereastră 48h).
- **Watch signals**: `avg_watch_ratio`, completions (semnal puternic de calitate).
- **Geo**: evenimente în orașul/aria userului.
- **Prospețime**: decay pe `published_at`.
- **Diversitate**: nu două short-uri consecutive de la același event/artist.
- **Cold start**: featured + geo + genurile din onboarding.

Implementare pragmatică: **scored query** (SQL/PHP) cu ponderi configurabile (reutilizează tiparul de config din `GamificationConfig`), evoluează spre un `ShortFeedRanker` service. Ține-l explicabil (loghează scorul în dev). Segmente de feed: `for_you`, `following` (artiști/organizatori urmăriți — necesită follow, vezi planul friends/urmărire), `nearby`, `featured`, `event`, `artist`.

---

## 7. Redare în mobil (React + Capacitor)

Arhitectură feed vertical „TikTok":
- **Pager vertical** full-screen, un short/ecran, `scroll-snap-type: y mandatory` + `IntersectionObserver` (threshold ~0.7) ca să știi care e „activ".
- **Native/HLS**: `<video playsinline muted loop preload="auto">`; redă cu **hls.js** (Android/webview) — iOS Safari/WKWebView redă HLS nativ. Autoplay la intrarea în viewport, pauză la ieșire. Un singur video „unmuted" la un moment dat.
- **Preload**: montează short-ul curent + următorul (N=1..2); dezmontează cele îndepărtate ca să nu ții zeci de `<video>`.
- **Extern (embed)**: randează `embed_html`/iframe doar când e activ (lazy) — acceptă că autoplay/branding-ul sunt limitate; ideal marchezi vizual „sursă: YouTube/TikTok".
- **Overlay UI**: caption, hashtags, autor/owner, butoane like/save/share pe dreapta, **CTA jos** („Ia bilet") → deep-link către eveniment/checkout.
- **Gesturi**: tap = mute/unmute; double-tap = like; swipe-up pe CTA = deschide checkout.
- **Telemetrie**: trimite `impression` la intrare, `view` la prag (ex. 2s sau 50%), `complete` la final/loop, `watch_ms`/`watch_ratio` la ieșire — **batched** prin `POST shorts/events`.
- **Prefetch offline** (opțional): pre-descarcă poster-ele + primul segment HLS al următoarelor N pentru scroll fluid pe rețele slabe.

Feed-ul poate fi și un **tab principal** de discovery în app (recomandare, §16), nu doar un rail pe Home.

---

## 8. Interacțiuni & analytics

- **Like/Save**: idempotent prin `short_likes`/`short_saves` (unic pe user+short); update denormalizat pe `shorts.likes/saves` prin observer sau job.
- **Share**: generează deep-link `tixello://shorts/{id}` (+ fallback web „open in app"); logează `share`.
- **Agregare**: `AggregateShortStatsJob` (programat, ex. la 5 min) rulează peste `short_events` → actualizează `impressions/views/completions/avg_watch_ratio/cta_clicks` pe `shorts`. Reutilizează tiparul `EventAnalyticsDaily/Hourly/Weekly/Monthly` deja din cod pentru rapoarte pe termen lung.

---

## 9. ÎMBOGĂȚIRE — Shorts „shoppable" (comerț) ⭐

Cea mai mare valoare comercială pentru mobile-only.
- Atașezi unui short un **tip de bilet** (`cta_ticket_type_id`) și opțional un **cod promo** (`promo_code`) → butonul „Ia bilet" deschide checkout-ul cu biletul preselectat + discount aplicat.
- **Atribuire conversie**: `orders.source_short_id` (nullable) setat când comanda pornește dintr-un short → raportezi „bilete/venit generate de short X".
- Metrici: `cta_clicks`, conversion rate, revenue attributed — per short și per owner.
- „Swipe-up to buy" ca gest nativ în feed.

---

## 10. ÎMBOGĂȚIRE — Generare automată de shorts din media existentă ⭐

Multe evenimente n-au video vertical. Umple feed-ul automat:
- `GenerateShortFromEventJob`: ia `poster_url`/`hero_image_url`/`gallery` + titlu + un music bed → produce un short vertical (6–10s, Ken-Burns + text).
- Fără ffmpeg → folosește un serviciu de render managed (ex. **Shotstack** sau **Cloudinary video**) prin aceeași abstracție `VideoProvider`/un `VideoRenderer`.
- MVP fără render: „poster short" = card imagine verticală + text în feed (tip `source=upload`, dar imagine), până adaugi generarea video.

---

## 11. ÎMBOGĂȚIRE — Seed automat din artiști (YouTube) ⭐

`Artist` are `youtube_id`/`youtube_videos`/handle-uri + `YouTubeService`. `PullChannelShortsJob` (vezi planul de ingestie) creează `Short` (owner = Artist, `event_id` dacă e legat) în `draft` pentru curatoriere. Umple feed-ul „following/for_you" fără efort manual pentru evenimente cu artiști cunoscuți.

---

## 12. ÎMBOGĂȚIRE — Analytics pentru organizator

Pagină/widget Filament (panoul tenant): per short → views, avg watch %, completion, CTR la „Ia bilet", conversii, venit atribuit. Reutilizează modelele `EventAnalytics*` + `short_events`. Dă organizatorilor motiv să posteze.

---

## 13. ÎMBOGĂȚIRE — Alte idei (prioritizate)

| Prioritate | Idee | Note |
|---|---|---|
| 🔴 | **Tab „Shorts" de discovery** în app (For You / Nearby / Following / Event) | mobile-only → shorts pot fi suprafața principală de descoperire, nu doar un rail |
| 🔴 | **Shoppable + atribuire** (§9) | cel mai mare ROI |
| 🔴 | **Managed video/HLS** (§1) | condiție pentru feed fluid |
| 🟡 | **Auto-gen din poster/gallery** (§10) | acoperire feed |
| 🟡 | **Seed din YouTube artiști** (§11) | acoperire fără efort |
| 🟡 | **Captions/subtitrări auto** + limbă | accesibilitate + i18n (ai deja en/ro/de/fr/es) |
| 🟡 | **Collections/playlists editoriale** („Weekend în București") | curatoriere în core admin |
| 🟡 | **Stories efemere (24h)** pentru promo organizatori | `expires_at` există deja |
| 🟢 | **UGC de la participanți verificați** (postezi short de la eveniment dacă ai bilet scanat) | buclă de creștere; necesită moderare |
| 🟢 | **A/B pe cover/thumbnail** | optimizare CTR |
| 🟢 | **Duet/stitch, efecte** | departe, doar dacă devine platformă de creație |

---

## 14. Moderare & ciclu de viață

- Flux `draft → pending_review → published → archived/rejected`. Short-urile organizatorilor pot intra `pending_review` înainte de feed-ul global (moderare în core admin).
- **Auto-archive**: `expires_at` trecut, sau embed extern mort (job periodic `CheckShortHealthJob` verifică 404/removed → `archived`).
- **Raportare user**: `POST tenant-client/shorts/{id}/report {reason}` → coadă de review în core admin.

---

## 15. Config & storage
- `config/services.php`: `video.driver` (cloudflare|bunny|mux), chei provider, `video.webhook_secret`.
- Postere/thumbnails: disk `public` (sau `s3` prod), ca `MediaLibrary`.
- HLS: servit de provider (CDN-ul lor) → zero egress din infra ta.

---

## 16. Plan pe faze

1. **Fundație**: model + migrații (`shorts`, `short_events`, `short_likes/saves`) + `Short.php`; resursă centrală Filament (upload native prin `VideoProvider`); API feed `tenant-client/shorts` (cursor) + telemetrie.
2. **Redare mobil**: pager vertical + HLS (hls.js) + autoplay/preload + overlay + like/save/share + telemetrie batched. Tab „Shorts" de discovery.
3. **Shoppable** (§9): CTA bilet + promo + `orders.source_short_id` + atribuire.
4. **Ingestie externă** (plan dedicat): YouTube → TikTok → Meta; geamăn tenant pentru self-service.
5. **For You ranker** (§6) + `following` (necesită follow artist/organizer) + `AggregateShortStatsJob`.
6. **Îmbogățiri**: auto-gen din media (§10), seed artiști (§11), analytics organizator (§12), moderare/health (§14), captions/collections/stories.

## 17. Decizii deschise (de confirmat înainte de start)
1. ✅ **REZOLVAT — provider video = Bunny Stream** (cel mai ieftin pt bibliotecă mereu-disponibilă + trafic mobil low-bitrate). Implementarea concretă: **Partea C**.
2. Shorts = **tab principal** de discovery sau doar rail pe Home? (recomand tab, fiind mobile-only).
3. Moderare: shorts organizatori merg direct în feed sau prin `pending_review`?
4. `following` are nevoie de „urmărire artist/organizator" — îl construim odată cu shorts sau reutilizăm `favoriteArtists` ca proxy la început?
5. Generare auto (§10): adăugăm un render service (Shotstack/Cloudinary) sau începem cu „poster shorts" (imagine)?

---

# PARTEA B — Îmbogățiri: implementare completă

Fiecare secțiune e autonomă (migrații + model + servicii/joburi + API + admin + mobil). Toate presupun fundația din Partea A (`shorts`, `short_events`, `short_likes/saves`).

---

## B1. Shoppable shorts + atribuire de conversie ⭐

**Obiectiv:** un short vinde direct — „Ia bilet" deschide checkout cu biletul preselectat + cod promo aplicat; măsori bilete și venit generate de fiecare short.

### Model de date
```php
// ALTER shorts: (unele există deja din Partea A)
//   cta_ticket_type_id, promo_code, cta_type, cta_label  ✔ (Partea A)
// adaugă agregate de conversie:
Schema::table('shorts', function (Blueprint $t) {
    $t->unsignedBigInteger('conversions')->default(0);
    $t->unsignedBigInteger('revenue_cents')->default(0);
    $t->string('revenue_currency', 3)->nullable();
});
// ALTER orders: atribuire (last-touch)
Schema::table('orders', function (Blueprint $t) {
    $t->foreignId('source_short_id')->nullable()->index();
});
```
> Verifică numele tabelului de comenzi și FK-ul de bilet: `Order` (`app/Models/Order.php`, `marketplace_customer_id`), `OrderItem`, tipul de bilet (`TicketType`/`PriceTier` — vezi `app/Filament/Resources/PriceTierResource.php`). Folosește sistemul de cupoane existent (`app/Models/Coupon/*`, `docs/PROMO_CODES.md`) pentru `promo_code`.

### Flux
1. Feed livrează `cta:{type:'buy_tickets', ticket_type_id, promo_code, label}`.
2. Tap CTA → deep-link în app la checkout cu `ticket_type` preselectat + `promo_code` pre-aplicat + `source_short_id` în context.
3. La `POST` coș/comandă, propagi `source_short_id` pe `Order` (last-touch în sesiune).
4. La plata confirmată (webhook Stripe existent), un listener incrementează `shorts.conversions` + `shorts.revenue_cents`.

### API
```
POST tenant-client/shorts/{id}/cta-click            // telemetrie (short_events type=cta_click)
POST marketplace-client/customer/cart {..., source_short_id}   // propagă atribuirea
```
Validarea promo → reutilizează serviciul de cupoane existent (nu reimplementa).

### Aggregation & metrics
- `cta_clicks` din `short_events`; `conversions`/`revenue_cents` din `orders.source_short_id` (listener la „order paid").
- CTR = `cta_clicks / views`; CVR = `conversions / cta_clicks`.

### Admin
- În `ShortResource` (table + infolist): `cta_clicks`, `conversions`, `revenue`, CTR, CVR.

### Edge cases
- **Fereastră de atribuire**: last-touch în sesiune; dacă userul revine peste zile, nu atribui (evită supra-creditarea).
- Bilet epuizat între click și checkout → checkout arată starea reală (nu bloca CTA-ul, dar semnalează).
- Refund → scade `conversions`/`revenue_cents` (listener pe refund).

---

## B2. Tab „Shorts" de discovery + „Following" (model de urmărire) ⭐

**Obiectiv:** shorts ca suprafață principală de descoperire, cu segmente `For You / Following / Nearby / Event`. „Following" cere un model de urmărire.

### Model de urmărire (polimorf: Artist, Tenant/organizator, Venue)
```php
Schema::create('marketplace_follows', function (Blueprint $t) {
    $t->id();
    $t->foreignId('marketplace_customer_id')->constrained('marketplace_customers')->cascadeOnDelete();
    $t->nullableMorphs('followable');   // followable_type / followable_id (Artist|Tenant|Venue)
    $t->timestamps();
    $t->unique(['marketplace_customer_id','followable_type','followable_id'], 'mp_follow_unique');
    $t->index(['followable_type','followable_id']);
});
```
Model `MarketplaceFollow` + pe `MarketplaceCustomer`: `follows()`, `follow($model)`, `unfollow($model)`, `isFollowing($model)`.
> La început poți folosi `favoriteArtists` ca proxy pentru „following" (există deja), dar modelul dedicat acoperă și organizatori/venue-uri — recomand să-l construiești direct.

### API
```
POST   marketplace-client/customer/follows        // {type, id} toggle
GET    marketplace-client/customer/follows
GET    tenant-client/shorts?feed=following         // (auth) owner ∈ urmăriți
GET    tenant-client/shorts?feed=nearby&lat=&lng=  // sau city
GET    tenant-client/shorts?feed=featured          // is_featured
```

### Ranker „For You" (query scored, explicabil)
```php
// app/Services/Shorts/ShortFeedRanker.php  — pseudo-scor
$score =
    W_AFFINITY   * affinity($short, $customer)          // artist/venue/gen favorit/urmărit/cumpărat
  + W_POPULARITY * popularity48h($short)                // views/completions/likes velocity
  + W_WATCH      * $short->avg_watch_ratio
  + W_GEO        * geoProximity($short->event, $customer->city)
  + W_FRESH      * recencyDecay($short->published_at)
  - W_SEEN       * seenPenalty($short, $customer);       // deja văzute
// + diversitate: nu 2 consecutive de la același owner (post-process)
```
Ponderi în config (tiparul `GamificationConfig`). Cold start: `featured` + `nearby` + genuri din onboarding. Loghează scorul în dev (explicabilitate).

### Mobil (React/Capacitor)
- Tab „Shorts" cu segmented control (For You / Following / Nearby / Event).
- Fiecare segment = feed cursor separat (§5). „Nearby" trimite `lat/lng` (Capacitor Geolocation) sau `city` din profil.
- Buton „Urmărește" pe overlay-ul owner-ului.

---

## B3. Generare automată de shorts din media existentă ⭐

**Obiectiv:** umple feed-ul pentru evenimente fără video vertical, din poster/galerie + text + music bed.

### Pipeline de render (fără ffmpeg → serviciu managed)
```php
// app/Services/Video/VideoRenderer.php (interface)
interface VideoRenderer {
    public function render(string $template, array $payload): string;  // → render_job_id
    public function verifyWebhook(Request $r): bool;                   // → mp4 gata
}
// implementări: ShotstackRenderer (JSON timeline) | CloudinaryRenderer | CreatomateRenderer
```
Șablon vertical 1080×1920: Ken-Burns pe poster/hero/gallery (max N imagini), overlay titlu + dată + logo, `music_credit`, end-card CTA.

### Job
```php
// app/Jobs/GenerateShortFromEventJob.php
// 1. selectează imagini (poster_url/hero_image_url/gallery[])
// 2. build payload render (template + text din Event)
// 3. VideoRenderer::render() → render_job_id (salvat pe un Short draft)
// 4. webhook render „ready" → mp4 → trece prin VideoProvider (upload managed → HLS)
//    → completează Short(source=upload, ready=true, status=draft)
```

### Trigger
- Acțiune Filament pe `Event`: „Generează short".
- Bulk/scheduled pentru evenimente viitoare **fără** short (`whereDoesntHave('shorts')`).

### MVP fără render service
- „Poster short" = imagine verticală + text în feed (`source=upload`, dar imagine, `duration=null`) → randat ca `<img>` cu text overlay. Migrezi la video când adaugi rendererul.

### Config & licențiere
- `config/services.render.driver` + cheie.
- **Muzică**: doar bibliotecă royalty-free; stochează `music_credit`. Nu folosi muzică comercială nelicențiată.

---

## B4. Seed automat din YouTube (artiști) ⭐

**Obiectiv:** feed populat automat din Shorts-urile artiștilor cunoscuți.

### Job
```php
// app/Jobs/PullChannelShortsJob.php  (per Artist; șablon: FetchArtistSocialStats)
// 1. $ytId = $artist->youtube_id ?? YouTubeService::resolveChannel($artist->youtube_url)
// 2. $items = YouTubeService::latestShorts($ytId, limit: 10)  // Data API: uploads playlist + duration<=60s + vertical
// 3. foreach: firstOrCreate Short by (source='youtube', source_video_id=$id)
//      owner = $artist; event_id = eveniment viitor al artistului (dacă există relația EventArtist)
//      status='draft' (curatoriere)
```
- Extinde `YouTubeService` cu `latestShorts()` (parsează `/shorts/` există deja).
- **Dedup**: unic `(source, source_video_id)` pe `shorts`.
- **Legare la eveniment**: dacă există relația artist↔eveniment (verifică `EventArtist`/pivot), setează `event_id` pentru evenimentele viitoare.

### Trigger
- Buton Filament pe `Artist`/`Event`: „Importă Shorts YouTube".
- Scheduled săptămânal pentru artiști cu evenimente viitoare. Respectă cota Data API (cache 6–24h).

---

## B5. Analytics pentru organizator

**Obiectiv:** organizatorul vede performanța fiecărui short și motivația să posteze.

### Agregare (reutilizează tiparul `EventAnalytics*`)
```php
Schema::create('short_analytics_daily', function (Blueprint $t) {
    $t->id();
    $t->foreignId('short_id')->constrained()->cascadeOnDelete();
    $t->date('date');
    $t->unsignedBigInteger('impressions')->default(0);
    $t->unsignedBigInteger('views')->default(0);
    $t->unsignedBigInteger('completions')->default(0);
    $t->unsignedInteger('unique_viewers')->default(0);
    $t->decimal('avg_watch_ratio', 4, 3)->default(0);
    $t->unsignedBigInteger('likes')->default(0);
    $t->unsignedBigInteger('saves')->default(0);
    $t->unsignedBigInteger('shares')->default(0);
    $t->unsignedBigInteger('cta_clicks')->default(0);
    $t->unsignedBigInteger('conversions')->default(0);
    $t->unsignedBigInteger('revenue_cents')->default(0);
    $t->unique(['short_id','date']);
});
```
`AggregateShortAnalyticsJob` (scheduled zilnic) rulează peste `short_events` + `orders` → populează tabelul.

### Admin (panoul tenant)
- Pagină Filament `ShortsAnalytics` (widget-uri): top shorts, **funnel** (impression → view → cta_click → conversion), watch-ratio, venit atribuit, serie temporală.

### API (app organizator — are deja tab Rapoarte)
```
GET tenant/shorts/{id}/analytics
GET tenant/shorts/analytics?event={id}
```

---

## B6. Captions / subtitrări auto + i18n

**Obiectiv:** accesibilitate + limbi (ai deja en/ro/de/fr/es).

### Model
```php
Schema::create('short_captions', function (Blueprint $t) {
    $t->id();
    $t->foreignId('short_id')->constrained()->cascadeOnDelete();
    $t->string('language', 8);
    $t->string('vtt_path');            // WebVTT pe disk public
    $t->boolean('auto_generated')->default(true);
    $t->timestamps();
    $t->unique(['short_id','language']);
});
```

### Generare
- `GenerateCaptionsJob` după ce asset-ul e `ready`:
  - dacă providerul video are auto-captions (Cloudflare Stream / Mux) → preia VTT-ul;
  - altfel transcriere (Whisper / AssemblyAI) → VTT.
- Traducere opțională în locale-urile app-ului (serviciu de traducere) → mai multe rânduri `short_captions`.

### Mobil
- `<track kind="subtitles" srclang="ro" src="...vtt">` pe `<video>`; toggle CC; limbă implicită din locale-ul app-ului.

---

## B7. Collections / playlists editoriale

**Obiectiv:** curatoriere („Weekend în București", „Top festivaluri").

### Model
```php
Schema::create('short_collections', function (Blueprint $t) {
    $t->id();
    $t->string('title'); $t->string('slug')->unique();
    $t->text('description')->nullable();
    $t->string('cover_path')->nullable();
    $t->foreignId('marketplace_client_id')->nullable();   // null = global
    $t->boolean('is_active')->default(true);
    $t->unsignedInteger('sort')->default(0);
    $t->timestamps();
});
Schema::create('short_collection_items', function (Blueprint $t) {
    $t->id();
    $t->foreignId('short_collection_id')->constrained()->cascadeOnDelete();
    $t->foreignId('short_id')->constrained()->cascadeOnDelete();
    $t->unsignedInteger('sort')->default(0);
    $t->unique(['short_collection_id','short_id']);
});
```

### Admin
- Resursă core admin `ShortCollectionResource` (grup „Core") cu `Repeater`/relation-manager pentru itemi (drag-sort).

### API & mobil
```
GET tenant-client/short-collections
GET tenant-client/short-collections/{slug}
```
- Mobil: rail-uri pe discovery + segment „Collections".

---

## B8. Stories efemere (24h)

**Obiectiv:** promo time-limited de la organizatori/artiști, în tavă de stories deasupra feed-ului.

### Model
- Reutilizează `shorts` + `expires_at`; adaugă `is_story` boolean.
- Story = short cu `is_story=true`, `expires_at = published_at + 24h`.
- `CheckShortHealthJob` (scheduled) → `archived` la expirare.

### API & mobil
```
GET tenant-client/stories        // grupate pe owner, doar active (expires_at>now)
```
- Mobil: tavă cu avatare circulare (owner) sus; tap → **story viewer** (tap-through, cu progres pe segmente), nu infinite-scroll.
- Marchează „văzut" per user (`short_events type=view` filtrat pe `is_story`).

---

## B9. UGC de la participanți verificați

**Obiectiv:** participanții postează short-uri de la eveniment (dacă au bilet scanat) — buclă de creștere. Necesită moderare strictă.

### Model
```php
Schema::table('shorts', function (Blueprint $t) {
    $t->boolean('is_ugc')->default(false);
    $t->foreignId('author_marketplace_customer_id')->nullable()->index();
    // status folosește 'pending_review' (există în enum din Partea A)
});
```

### Eligibilitate & flux
- Poți posta pentru `Event X` doar dacă ai un `Ticket` cu `checked_in` + `current_owner_customer_id = tu` (verifică `app/Models/Ticket.php`).
- Flux: înregistrezi/încarci în app → `upload-url` → `Short(source=upload, is_ugc=true, owner=Event, author=tu, status=pending_review)` → coadă de moderare în core admin → `published`.

### Moderare & siguranță
- Verificări auto: durată max, moderare conținut (provider video moderation sau serviciu extern: nuditate/violență).
- Rate-limit per user; raportare/block; takedown din core admin.
- **Recompensă**: puncte via `Gamification` la UGC aprobat (leagă de puntea de identitate din planul friends).

### API
```
POST marketplace-client/customer/shorts            // {event_id, asset_id, caption} → pending_review
POST tenant-client/shorts/{id}/report {reason}
```

---

## B10. A/B pe cover/thumbnail

**Obiectiv:** maximizează CTR-ul alegând automat cel mai bun cover.

### Model
```php
Schema::create('short_poster_variants', function (Blueprint $t) {
    $t->id();
    $t->foreignId('short_id')->constrained()->cascadeOnDelete();
    $t->string('poster_path');
    $t->string('label')->nullable();
    $t->unsignedBigInteger('impressions')->default(0);
    $t->unsignedBigInteger('clicks')->default(0);   // view după impression
    $t->boolean('is_winner')->default(false);
    $t->timestamps();
});
```

### Mecanism
- La livrarea în feed, alegi varianta prin `hash(user_id . short_id) % n` (bucketing stabil).
- `short_events` primește un câmp `poster_variant_id` (adaugă în migrația `short_events` sau într-un câmp `meta` JSON).
- `PickPosterWinnerJob` (scheduled) după prag de impresii → CTR pe variantă → setează `is_winner` → servești doar câștigătoarea.

---

## B11. Cross-cutting (necesare pentru toate)

- **Config nou** (`config/services.php`): `video.driver` + chei (provider playback), `render.driver` + cheie, `captions.driver`, `video.webhook_secret`.
- **Joburi (queue = database)**: `SyncShortPlaybackJob`, `IngestShortJob`, `PullChannelShortsJob`, `GenerateShortFromEventJob`, `GenerateCaptionsJob`, `AggregateShortStatsJob`, `AggregateShortAnalyticsJob`, `CheckShortHealthJob`, `PickPosterWinnerJob`. Toate după tiparul `app/Jobs/FetchArtistSocialStats.php`.
- **Webhooks**: `webhooks/video/{provider}` (asset ready), `webhooks/render/{provider}` (mp4 gata) — semnate.
- **Permisiuni** (`spatie/laravel-permission`): `shorts.manage` (core admin), `shorts.manage.own` (tenant), moderare UGC = permisiune separată.
- **Push** (când există layer-ul): „artist urmărit a postat un short", „short-ul tău a fost aprobat".
- **Deep-links**: `tixello://shorts/{id}`, `tixello://shorts/collection/{slug}` + fallback web „open in app".

---

## B12. Plan de faze (cu îmbogățiri)

1. **Fundație** (Partea A): model + feed + redare native/HLS + telemetrie + tab Shorts (B2 fără „following").
2. **Shoppable** (B1) + atribuire conversie.
3. **Following + ranker For You** (B2 complet).
4. **Ingestie externă** (plan dedicat) + **seed YouTube** (B4).
5. **Auto-gen din media** (B3) — MVP „poster short" apoi render.
6. **Analytics organizator** (B5) + **captions** (B6).
7. **Collections** (B7) + **Stories** (B8).
8. **UGC verificat** (B9) + moderare + **A/B cover** (B10).

---

# PARTEA C — Bunny Stream: implementare `VideoProvider`

Provider ales pentru upload native → HLS. Bunny Stream encodează, generează thumbnails și livrează HLS prin Bunny CDN. Externele (YT/TikTok/IG/FB) **nu** trec pe aici (rămân embed).

## C0. Precondiții în dashboard-ul Bunny (o singură dată)
1. Creează un **Video Library** (Stream) → notează **Library ID** și **API Key** (din setările library-ului).
2. Library-ul are automat o **Pull Zone** cu hostname `vz-xxxxxxxx-xxx.b-cdn.net` → notează hostname-ul.
3. Activează **Token Authentication** pe pull zone (ca URL-urile brute cu `guid` să nu fie publice) → notează **Token Security Key**.
4. (Opțional) setează rezoluțiile encodate (ex. 240/360/480/720/1080) și „MP4 fallback" pe library.
5. Setează **Webhook URL** pe library → `https://api.tixello.ro/webhooks/video/bunny?secret=...`.

## C1. Config
```php
// config/services.php
'bunny' => [
    'stream_library_id'     => env('BUNNY_STREAM_LIBRARY_ID'),
    'stream_api_key'        => env('BUNNY_STREAM_API_KEY'),
    'stream_pull_zone'      => env('BUNNY_STREAM_PULL_ZONE'),      // ex. vz-abc123-def.b-cdn.net
    'stream_token_key'      => env('BUNNY_STREAM_TOKEN_KEY'),      // pull zone token auth key
    'stream_webhook_secret' => env('BUNNY_STREAM_WEBHOOK_SECRET'),
],
// config('services.video.driver') = 'bunny'
```
Pe `Short`: `video_provider='bunny'`, `provider_asset_id = {guid}`. **NU** stoca `hls_url`/`poster_url` statice — se semnează la runtime, per cerere (TTL scurt). `ready` vine din webhook.

## C2. `app/Services/Video/BunnyStreamProvider.php` (implements `VideoProvider`)
```php
use Illuminate\Support\Facades\Http;

class BunnyStreamProvider implements VideoProvider
{
    private string $api = 'https://video.bunnycdn.com';
    public function __construct(
        private string $lib,       // config services.bunny.stream_library_id
        private string $key,       // stream_api_key
        private string $pullZone,  // stream_pull_zone (host)
        private string $tokenKey,  // stream_token_key
    ) {}

    /** Upload direct din app (TUS resumable) — fișierul NU trece prin Laravel. */
    public function createDirectUpload(array $meta): array
    {
        // 1) creează obiectul video → primești guid
        $guid = Http::withHeaders(['AccessKey' => $this->key, 'accept' => 'application/json'])
            ->post("{$this->api}/library/{$this->lib}/videos", ['title' => $meta['title'] ?? 'short'])
            ->json('guid');

        // 2) presemnează sesiunea TUS (client urcă direct la Bunny)
        $expire = now()->addHour()->timestamp;
        $signature = hash('sha256', $this->lib . $this->key . $expire . $guid);

        return [
            'asset_id'    => $guid,
            'upload_url'  => 'https://video.bunnycdn.com/tusupload',
            'tus_headers' => [
                'AuthorizationSignature' => $signature,
                'AuthorizationExpire'    => (string) $expire,
                'LibraryId'              => $this->lib,
                'VideoId'                => $guid,
            ],
        ];
    }

    /** Ingest server-side dintr-un URL (folosit de B3 auto-gen: mp4 randat → Bunny). */
    public function ingestFromUrl(string $url, ?string $title = null): string
    {
        $guid = Http::withHeaders(['AccessKey' => $this->key])
            ->post("{$this->api}/library/{$this->lib}/videos", ['title' => $title ?? 'short'])
            ->json('guid');
        Http::withHeaders(['AccessKey' => $this->key])
            ->post("{$this->api}/library/{$this->lib}/videos/{$guid}/fetch", ['url' => $url]);
        return $guid;
    }

    /** Stare + metadate (apelat din webhook/job). status 4 = Finished. */
    public function getPlayback(string $guid): array
    {
        $v = Http::withHeaders(['AccessKey' => $this->key, 'accept' => 'application/json'])
            ->get("{$this->api}/library/{$this->lib}/videos/{$guid}")->json();
        return [
            'ready'    => (int)($v['status'] ?? 0) === 4,
            'duration' => (int)($v['length'] ?? 0),
            'width'    => $v['width']  ?? null,
            'height'   => $v['height'] ?? null,
        ];
    }

    /** URL-uri semnate pt redare (token auth pe pull zone), TTL scurt, per request. */
    public function signedHls(string $guid, int $ttl = 3600): string
    {
        return $this->sign("/{$guid}/playlist.m3u8", $ttl);
    }
    public function signedPoster(string $guid, int $ttl = 3600): string
    {
        return $this->sign("/{$guid}/thumbnail.jpg", $ttl);
    }
    private function sign(string $path, int $ttl): string
    {
        $expires = time() + $ttl;
        // Bunny CDN Token Authentication (confirmă schema exactă în setările pull zone-ului:
        // SHA256(security_key + path + expires), base64 url-safe fără padding):
        $hash  = hash('sha256', $this->tokenKey . $path . $expires, true);
        $token = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
        return "https://{$this->pullZone}{$path}?token={$token}&expires={$expires}";
    }

    public function delete(string $guid): void
    {
        Http::withHeaders(['AccessKey' => $this->key])
            ->delete("{$this->api}/library/{$this->lib}/videos/{$guid}");
    }

    public function verifyWebhook(\Illuminate\Http\Request $r): bool
    {
        // Webhook-ul Bunny Stream nu e semnat implicit → strategie defensivă:
        //  (a) verifică ?secret= din URL == config stream_webhook_secret
        //  (b) tratează webhook-ul doar ca declanșator; RE-CITEȘTE starea via getPlayback() (autoritativ)
        return hash_equals(config('services.bunny.stream_webhook_secret'), (string) $r->query('secret'));
    }
}
```
> Notă: URL HLS Bunny = `https://{pullZone}/{guid}/playlist.m3u8`; thumbnail = `/{guid}/thumbnail.jpg`, preview = `/{guid}/preview.webp`. Confirmă schema exactă de token la momentul implementării (setările pull zone-ului).

## C3. Endpoint upload-url (app cere sesiune de upload)
```
POST tenant/shorts/upload-url            // (auth organizator) sau marketplace-client/customer/shorts/upload-url (UGC)
// controller:
$out = app(VideoProvider::class)->createDirectUpload(['title' => $request->title]);
$short = Short::create([
    'video_provider'    => 'bunny',
    'provider_asset_id' => $out['asset_id'],
    'source'            => 'upload',
    'owner_type'/'id'   => ...,   // event/artist/etc
    'status'            => 'draft',
    'ready'             => false,
]);
return ['short_id' => $short->id] + $out;   // upload_url + tus_headers
```

## C4. Upload din mobil (TUS resumable, direct la Bunny)
```ts
// React/Capacitor — tus-js-client
import * as tus from 'tus-js-client'
const { upload_url, tus_headers, short_id } = await api.post('shorts/upload-url', { title })
new tus.Upload(file, {
  endpoint: upload_url,
  headers: tus_headers,                       // AuthorizationSignature, AuthorizationExpire, LibraryId, VideoId
  metadata: { filetype: file.type, title },
  onSuccess: () => { /* rămâne 'draft' până la webhook 'ready' */ },
}).start()
```
Fișierul merge **direct** la Bunny (nu prin Laravel) → zero egress/CPU pe serverul tău.

## C5. Webhook „ready" + job
```
POST webhooks/video/bunny?secret=...        // fără CSRF; VerifyBunnyWebhook middleware
// body: { "VideoLibraryId": <int>, "VideoGuid": "<guid>", "Status": <int> }  (4 = Finished, 5/6 = error)
```
```php
// Controller
if (! app(VideoProvider::class)->verifyWebhook($request)) abort(403);
$guid = $request->input('VideoGuid');
if ((int) $request->input('Status') === 4) {
    SyncShortPlaybackJob::dispatch($guid);       // autoritativ: re-citește via getPlayback()
} elseif (in_array((int)$request->input('Status'), [5,6])) {
    Short::where('provider_asset_id',$guid)->update(['status'=>'rejected']);
}
return response()->noContent();
```
```php
// app/Jobs/SyncShortPlaybackJob.php  (queue: database; șablon FetchArtistSocialStats)
$p = app(VideoProvider::class)->getPlayback($guid);
Short::where('provider_asset_id',$guid)->update([
    'ready'    => $p['ready'],
    'duration' => $p['duration'],
    'width'    => $p['width'],
    'height'   => $p['height'],
]);   // status rămâne 'draft'/'pending_review' → publicare separată
```

## C6. Servirea în feed (URL-uri semnate la runtime)
La construirea payload-ului de feed (§5), pentru short-urile Bunny gata (`ready`):
```php
$provider = app(VideoProvider::class);
$playback = [
    'hls_url'    => $provider->signedHls($short->provider_asset_id, ttl: 3600),
    'poster_url' => $provider->signedPoster($short->provider_asset_id, ttl: 3600),
];
```
- **Nu** stoca URL-uri semnate (expiră). Generează-le per cerere, TTL 1–6h.
- Mobil redă `hls_url` cu **hls.js** (Android/WebView) / HLS nativ (iOS). Poster = `poster_url`.

## C7. Integrare cu îmbogățirile
- **B3 (auto-gen)**: rendererul produce un `mp4` → `ingestFromUrl($mp4Url)` → guid → același flux webhook/`ready`.
- **B6 (captions)**: Bunny Stream poate genera/urca subtitrări pe video (`.../videos/{guid}/captions/{srclang}`); `GenerateCaptionsJob` le atașează sau le urcă (VTT). Confirmă suportul de auto-captions curent din Bunny.
- **B9 (UGC)**: upload-url pe ruta de customer, `is_ugc=true`, `status='pending_review'` → moderare înainte de publicare.
- **A/B poster (B10)**: Bunny permite setarea unui thumbnail custom (`.../videos/{guid}/thumbnail`) — încarci variante și le testezi.

## C8. Checklist de mediu (.env)
```
BUNNY_STREAM_LIBRARY_ID=
BUNNY_STREAM_API_KEY=
BUNNY_STREAM_PULL_ZONE=vz-xxxxxxxx-xxx.b-cdn.net
BUNNY_STREAM_TOKEN_KEY=
BUNNY_STREAM_WEBHOOK_SECRET=
```

## C9. Costuri — reglaje care contează
- Limitează rezoluțiile encodate la ce are sens pe mobil vertical (ex. max 720p sau 1080p) → mai puțină stocare și bandwidth.
- Activează „MP4 fallback" doar dacă îți trebuie (altfel dublezi stocarea).
- Token auth cu TTL scurt (anti-hotlinking → nu-ți irosești bandwidth-ul pe emb-eduri externe).
- Bunny **volume tier** (dacă traficul e mare) reduce la ~$0.005/GB.

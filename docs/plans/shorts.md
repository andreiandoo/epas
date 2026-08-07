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
1. **Provider video** managed: Cloudflare Stream / Bunny / Mux? (recomand Cloudflare sau Bunny pt cost).
2. Shorts = **tab principal** de discovery sau doar rail pe Home? (recomand tab, fiind mobile-only).
3. Moderare: shorts organizatori merg direct în feed sau prin `pending_review`?
4. `following` are nevoie de „urmărire artist/organizator" — îl construim odată cu shorts sau reutilizăm `favoriteArtists` ca proxy la început?
5. Generare auto (§10): adăugăm un render service (Shotstack/Cloudinary) sau începem cu „poster shorts" (imagine)?

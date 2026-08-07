# Plan — Shorts (video vertical scurt) în core.tixello.com

> Feature: feed de „shorts" (video vertical, tip Reels/TikTok/YT Shorts) pentru aplicația mobilă, curatoriabil central și atașabil la orice eveniment/umbrelă.
> Status codebase: **feature inexistentă** — nu există niciun model short/reel/story azi.
> Branch de referință pentru fișierele citate: `core`.

---

## 1. Decizia de arhitectură: centralizat **și** per-eveniment (polimorf)

Un singur model central `Short`, gestionat într-o resursă Filament în **core admin** (grup „Core"), dar **atașabil polimorf** la orice owner (Event, Activity, Attraction, Artist, Tenant) sau standalone (editorial). Astfel:
- **core admin** curatoriază feed-ul global / marketplace și materialele oricui;
- **organizatorul** (panoul tenant) își adaugă singur shorts pe evenimentele lui;
- funcționează pe toate umbrelele (marketplace / tenant / leisure) fără modele separate.

Acest tipar există deja și e validat de `MediaLibrary` — replicăm exact convențiile lui.

### Precedent în cod (a nu reinventa)
- `app/Models/MediaLibrary.php` — model central **polimorf** (`model_type`/`model_id`, `morphTo model()`), `collection` string, `marketplace_client_id` nullable, **fără** `SecureTenantScoping` (deci vizibil din core admin peste tot). Recunoaște deja video prin mime: `getIsVideoAttribute()` / `scopeVideos()` (`mime_type LIKE 'video/%'`).
- `app/Filament/Resources/MediaLibrary/MediaLibraryResource.php` — resursă **centrală** (grup `Core`, fără `getEloquentQuery` scoping → vede tot).
- `app/Filament/Marketplace/Resources/MediaLibraryResource.php` — geamăn **scoped** (`->where('marketplace_client_id', $marketplace?->id)`).
- Umbrele pe `Event` (pentru `owner`/denormalizare): `tenant_id`, `marketplace_client_id` + `marketplace_organizer_id`, `display_template === 'leisure_venue'` (trait `app/Concerns/IsLeisureVenue.php`). Modele leisure separate: `Activity`, `Attraction`, `BoatRental`.

---

## 2. Model de date

### Migrație `create_shorts_table`
```php
Schema::create('shorts', function (Blueprint $t) {
    $t->id();

    // Owner polimorf (nullable = short editorial fără owner)
    $t->nullableMorphs('owner');            // owner_type / owner_id

    // Umbrelă (ca MediaLibrary — fără global tenant scope pe model)
    $t->foreignId('tenant_id')->nullable()->index();
    $t->foreignId('marketplace_client_id')->nullable()->index();

    // Denormalizare pentru cel mai frecvent query „shorts pt event X" (optional)
    $t->foreignId('event_id')->nullable()->index();

    // Sursă
    $t->enum('source', ['upload','youtube','tiktok','instagram','facebook'])->default('upload');
    $t->text('source_url')->nullable();     // linkul original
    $t->string('source_video_id')->nullable();
    $t->longText('embed_html')->nullable(); // oEmbed (extern)

    // Fișier (doar pentru upload nativ)
    $t->string('disk')->nullable();
    $t->string('path')->nullable();
    $t->string('mime_type')->nullable();
    $t->unsignedInteger('duration')->nullable();   // secunde
    $t->unsignedInteger('width')->nullable();
    $t->unsignedInteger('height')->nullable();

    // Prezentare / feed
    $t->string('thumbnail_path')->nullable();
    $t->string('title')->nullable();
    $t->text('caption')->nullable();
    $t->enum('cta_type', ['none','buy_tickets','open_event','open_artist','external'])->default('none');
    $t->string('cta_target_id')->nullable();
    $t->unsignedInteger('sort')->default(0);
    $t->enum('status', ['draft','published','archived'])->default('draft');
    $t->timestamp('published_at')->nullable();
    $t->timestamp('expires_at')->nullable();

    // Stats (denormalizate; sau tabel short_events mai târziu)
    $t->unsignedBigInteger('views')->default(0);
    $t->unsignedBigInteger('likes')->default(0);
    $t->unsignedBigInteger('shares')->default(0);

    $t->timestamps();
    $t->softDeletes();

    $t->index(['status','published_at']);
    $t->index(['owner_type','owner_id']);
});
```

### Model `app/Models/Short.php`
- `morphTo owner()`, `belongsTo Event` (denormalizat), `belongsTo Tenant`, `belongsTo MarketplaceClient`.
- **NU** folosi `SecureTenantScoping` (ca `MediaLibrary`) — core admin trebuie să vadă tot; scoparea se face în resursele per-panou.
- Accessors utile: `getIsExternalAttribute()` (`source !== 'upload'`), `getUrlAttribute()` (upload → `Storage::disk($disk)->url($path)`; extern → `source_url`), `getThumbnailUrlAttribute()`.
- Scopes: `scopePublished()`, `scopeForEvent($id)`, `scopeForOwner($model)`.
- Cast: `published_at`/`expires_at` datetime.

---

## 3. Admin (Filament v4.1)

### 3.1 Resursă centrală — `app/Filament/Resources/Shorts/ShortResource.php`
- Namespace `App\Filament\Resources\Shorts`; auto-descoperit de `AdminPanelProvider` (`app_path('Filament/Resources')`).
- `protected static string|\UnitEnum|null $navigationGroup = 'Core';`
- **Fără** `getEloquentQuery()` override → vede toate short-urile (toți tenants/marketplaces).
- Form (Filament v4 = `Filament\Schemas\Schema`, `Filament\Schemas\Components as SC`):
  - Secțiune „Sursă": `Select source` + `TextInput source_url` (paste link) + buton/acțiune „Preia din link" (dispatch `IngestShortJob`, vezi `docs/plans/social-video-ingestion.md`).
  - Secțiune „Owner": `MorphToSelect owner` (Event/Activity/Attraction/Artist/Tenant) + `Select event_id` opțional.
  - Secțiune „Prezentare": `title`, `caption`, `cta_type`, `cta_target_id`, `FileUpload thumbnail` (disk `public`), `FileUpload path` (doar `source=upload`, accept `video/*`).
  - Secțiune „Publicare": `status`, `published_at`, `expires_at`, `sort`.
- Table: preview thumbnail (`ImageColumn->disk('public')` ca în MediaLibraryResource), `title`, `source` badge, `owner` (morph), `status`, `views`.
- Pages: `index/create/edit` (folosește șablonul din `app/Filament/Resources/PriceTierResource.php`).

### 3.2 Geamăn per-tenant — `app/Filament/Tenant/Resources/ShortResource.php`
- Namespace `App\Filament\Tenant\Resources`; scoped în `getEloquentQuery()` pe `tenant_id` (vezi tiparul din `app/Filament/Tenant/Resources/EventResource.php:82-97`).
- Organizatorul adaugă/gestionează doar shorts pe evenimentele lui.
- (Opțional) geamăn marketplace scoped pe `marketplace_client_id`, ca `app/Filament/Marketplace/Resources/MediaLibraryResource.php`.

---

## 4. API (feed mobil)

Adaugă în `routes/api.php`, grupul `Route::prefix('tenant-client')` (`:60`, public read + CORS) — și/sau `marketplace-client`:

```
GET  tenant-client/shorts                 // feed global paginat (published, ne-expirat)
GET  tenant-client/shorts?event={slug}    // filtrare pe eveniment
GET  tenant-client/events/{slug}/shorts   // shorts pentru un eveniment
POST tenant-client/shorts/{id}/view       // increment views (fire-and-forget)
POST marketplace-client/customer/shorts/{id}/like   // (auth: sanctum) like/unlike
```
- Controller în stilul `App\Http\Controllers\Api\TenantClientController` / `PublicDataController`.
- Payload feed: `{ id, source, embed_html|video_url, thumbnail_url, title, caption, cta:{type,target}, owner:{type,slug,name}, event:{slug,title,date,price_from} }`.
- Personalizare (fază 2): ordonează după `MarketplaceCustomer.favoriteEvents/favoriteArtists` + preferințe existente.

---

## 5. Redare în aplicația mobilă (Capacitor/React)

- **Upload nativ** (`source=upload`): `<video>` HTML5 din `video_url`, autoplay muted, loop, vertical.
- **Extern** (`source=youtube|tiktok|instagram|facebook`): randează `embed_html` (oEmbed) sau iframe; pentru YouTube Shorts, iframe `youtube-nocookie.com/embed/{id}`.
- Feed vertical „swipe" (ca prototipul client — secțiunea Shorts din `client-app.html`, funcțiile `openShorts`, `lb*`).
- CTA overlay → „Cumpără bilete" (deep-link către eveniment) folosind `cta_type`/`cta_target_id`.

> **Legal:** pentru surse externe se redau **doar embed-uri** (nu se descarcă/re-hostează fișierul). Vezi `docs/plans/social-video-ingestion.md`.

---

## 6. Faze de implementare

1. **Model + migrație** `shorts` + `Short.php` (fără tenant global scope).
2. **Resursă centrală** `ShortResource` (core admin, grup „Core") cu upload nativ + owner polimorf.
3. **API feed** `tenant-client/shorts` + per-eveniment; randare în mobil (upload nativ întâi).
4. **Geamăn tenant** (+ marketplace) pentru self-service organizatori.
5. **Ingestie externă prin link** (vezi planul dedicat) — YouTube întâi.
6. **Personalizare feed** + stats (views/likes) + `short_events` dacă e nevoie de analytics fin.

## 7. Decizii deschise
- `event_id` denormalizat în plus față de owner polimorf? (recomandat DA, pentru query rapid pe cazul dominant).
- Stats inline pe `shorts` vs. tabel separat `short_events` (recomandat: inline la început, tabel când vrei analytics per-user).
- Moderare: shorts adăugate de organizatori necesită aprobare în core admin înainte de feed-ul global? (recomandat: `status=draft` → review → `published`).

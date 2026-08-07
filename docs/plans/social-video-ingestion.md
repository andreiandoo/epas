# Plan — Ingestie automată de video social (Facebook / YouTube / TikTok / Instagram) prin link

> Feature: din linkul unei postări video, populează automat un „Short" (metadate + thumbnail + embed). Alimentează feed-ul din `docs/plans/shorts.md`.
> Branch de referință: `core`.

---

## 0. Regula de aur (legal + tehnic)

**Preiei metadate + thumbnail + cod de embed. NU descarci și NU re-hostezi fișierul video.** Descărcarea/re-hostarea încalcă ToS-ul și dreptul de autor la toate cele patru platforme.
- Sursele externe (YT/TikTok/IG/FB) se **redau prin embed** în feed.
- Redare **nativă** (fișier `.mp4`) doar pentru clipurile **urcate direct** în Tixello de organizator.
- Thumbnail-urile pot fi descărcate/cache-uite pe disk-ul `public` (uz uzual, acceptat).

Acest model se aliniază cu ce există deja: `app/PageBuilder/Blocks/VideoBlock.php` + `EmbedBlock.php` (embed prin URL), `YouTubeService`.

---

## 1. Ce există deja (a reutiliza)

- **`app/Services/YouTubeService.php`** — `Http` (Guzzle) pe YouTube Data API v3, `Cache::remember` 6h. **`extractVideoId()` parsează deja `youtube.com/shorts/{id}`** + URL standard/embed. Cheia din `Setting::current()->youtube_api_key` sau `config('services.youtube.api_key')`.
- **`app/Services/TikTokService.php`**, **`app/Services/FacebookService.php`**, `SpotifyService.php` — wrappere HTTP cu `isConfigured()`, `clearCache()`, `extractUsername()`/`extractPageId()`.
- **`app/Jobs/FetchArtistSocialStats.php`** — job pe coadă (queue = `database`), `ShouldQueue`, `$tries=3`, `$backoff`, `$timeout`, `failed()`, `retryUntil()`. **Șablon direct pentru `IngestShortJob`.**
- **`Artist`** are deja `youtube_id`, `youtube_videos` (JSON), `tiktok_url`, `instagram_url`, `facebook_url` + follower counts → sursă de seed automat.
- Storage: disk `public` (default) sau `s3` (`config/filesystems.php`).

---

## 2. Fezabilitate pe platformă

| Platformă | Din link | Mecanism | „Ultimele de la un cont" |
|---|---|---|---|
| **YouTube (+ Shorts)** | ✅ Curat | Data API v3 (cheie în `Setting`); `YouTubeService.extractVideoId()` prinde `/shorts/`; embed `youtube-nocookie.com/embed/{id}` | ✅ Da — listare ultimele Shorts pe canal (Data API `search.list`/`playlistItems`) |
| **TikTok** | ✅ Parțial | **oEmbed public**: `GET https://www.tiktok.com/oembed?url={url}` (fără auth) → `title`, `author_name`, `thumbnail_url`, `html` (embed) | ⚠️ Doar cu Display API (OAuth + app review) |
| **Instagram (Reels)** | ⚠️ Limitat | Meta **oEmbed Read** (app FB + app access token + review; doar postări publice) → embed + thumbnail | ⚠️ Graph API + review |
| **Facebook (video/Reels)** | ⚠️ Limitat | Același Meta **oEmbed Read** (token de app + review) | ⚠️ Ca IG |

> Politicile Meta pentru oEmbed s-au schimbat de câteva ori (Instagram Basic Display scos în dec. 2024). Verifică statusul produsului „oEmbed Read" la momentul implementării părții IG/FB. TikTok oEmbed e public și stabil. YouTube Data API e cel mai solid.

**Concluzie de nivel de efort:** YouTube = automat (inclusiv pull pe canal); TikTok = paste-link (oEmbed) fără auth; IG/FB = paste-link dar necesită app Meta + review pentru oEmbed. Începe cu YouTube + TikTok.

---

## 3. Componente de construit

### 3.1 `app/Services/Shorts/ShortIngestService.php`
```php
class ShortIngestService {
    public function detectPlatform(string $url): string;          // youtube|tiktok|instagram|facebook|unknown
    public function ingest(string $url): array;                   // → payload normalizat (vezi mai jos)
    // intern:
    //  youtube()  → YouTubeService::extractVideoId() + videos.list (snippet,contentDetails)
    //  tiktok()   → Http::get('https://www.tiktok.com/oembed', ['url'=>$url])
    //  meta()     → Graph oEmbed Read (token app) pentru IG/FB
}
```
Payload normalizat returnat:
```php
[
  'source' => 'youtube', 'source_url' => $url, 'source_video_id' => $id,
  'embed_html' => '<iframe ...>', 'title' => '...', 'duration' => 34,
  'thumbnail_remote' => 'https://...jpg', 'author' => '...',
]
```

### 3.2 `app/Jobs/IngestShortJob.php` (șablon: `FetchArtistSocialStats`)
- Input: `Short $short` (creat cu `source_url` + `status=draft`) **sau** `(string $url, array $ownerRef)`.
- Pași: `detectPlatform` → `ingest` → completează câmpurile pe `Short` → **descarcă thumbnail-ul** în `storage/app/public/shorts/thumbnails/` (disk `public`, ca `MediaLibrary`) → set `thumbnail_path` → salvează.
- `$tries=3`, `$backoff=[30,120,300]`, `failed()` marchează `status='draft'` + loghează.
- Coadă: `database` (default). Dev runner are `queue:listen` (`composer.json`).

### 3.3 Refresh periodic (opțional)
- `Short.embed_html`/`thumbnail` pot fi reîmprospătate la un interval (comandă `shorts:refresh` + schedule) — util pentru thumbnail-uri expirate. Metadatele externe se pot cache-ui 6–24h (ca `YouTubeService`).

### 3.4 Pull automat pe cont (fază 2, doar YouTube întâi)
- `app/Jobs/PullChannelShortsJob.php`: pentru un `Artist` cu `youtube_id`/handle → `YouTubeService` listează ultimele Shorts → creează `Short` (owner = Artist, `event_id` dacă e legat de un eveniment) în `status=draft` pentru curatoriere.
- IG/FB/TikTok „pe cont" necesită API-urile oficiale + review — amână.

---

## 4. UX Admin

- În `ShortResource` (vezi `docs/plans/shorts.md`): câmp `source_url` + acțiune **„Preia din link"** → dispatch `IngestShortJob` → completează `title`, `source_video_id`, `embed_html`, `duration`, `thumbnail`. Rămâne `draft` pentru revizuire → `published`.
- (Fază 2) buton pe `Artist`/`Event`: „Importă ultimele Shorts YouTube" → `PullChannelShortsJob`.

---

## 5. Config necesar

- `config/services.php`: `youtube.api_key` (există via `Setting`), `meta.oembed_token` (nou — app access token FB pentru IG/FB oEmbed). TikTok oEmbed nu cere cheie.
- Rate limiting / cache: respectă cotele Data API v3 (cache 6h ca acum). oEmbed TikTok — cache 24h.

---

## 6. Faze

1. **YouTube**: `ShortIngestService::youtube()` + `IngestShortJob` + acțiunea „Preia din link" (reutilizează `YouTubeService`).
2. **TikTok**: adaugă `tiktok()` (oEmbed public, fără auth).
3. **Meta (IG + FB)**: creează app Meta + `oembed_read`; adaugă `meta()`; token în config.
4. **Pull pe canal YouTube** din artiști (`PullChannelShortsJob`) + seed automat.
5. **Refresh periodic** thumbnail/embed + comandă programată.

## 7. Riscuri / decizii deschise
- Meta oEmbed necesită app review — planifică-l ca task separat cu lead time.
- Dacă vrei redare fără dependență de embed-urile platformelor (fiabilitate feed), singura cale conformă e **upload nativ** de către organizatori — păstrează asta ca opțiune „premium" pentru materiale proprii.
- Nu stoca niciodată fișierul video extern; dacă un embed devine indisponibil (video șters), marchează `Short` `archived` automat (verificare periodică).

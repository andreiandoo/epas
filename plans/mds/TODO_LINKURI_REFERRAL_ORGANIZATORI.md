# TODO: Linkuri de referral pentru organizatori (punctul 4.1)

**Stare:** analiză făcută, neimplementat. Pe 2026-09-15 userul a decis „îl facem mai încolo”.

**Punct de plecare:** branch `core`, commit `300e9acfa` (2026-09-15). Numerele de linie de mai jos sunt aproximative și se pot decala; caută după numele funcției sau fișierului.

---

## 1. Cerința

Organizatorul își poate crea mai multe linkuri de referral, câte unul pentru fiecare partener, influencer sau site. Pentru fiecare link vede câte vizite și câte vânzări a adus.

---

## 2. Ce există azi

### 2.1 Tracking de vizite

**`resources/marketplaces/ambilet/assets/js/tracking.js`**
- Se încarcă pe toate paginile AmBilet (`includes/scripts.php`).
- La fiecare eveniment trimite:
  - `page_url` complet, cu query string;
  - `document.referrer`;
  - `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`;
  - `gclid`, `fbclid`, `ttclid`.
- `visitor_id` e salvat în localStorage (`epas_visitor_id`).
- `session_id` e salvat în sessionStorage: o sesiune per tab, cu expirare după 30 de minute de inactivitate.

**Endpoint-ul din core**
- `App\Http\Controllers\Api\MarketplaceTrackingController::track` și `trackBatch`.
- Rutele sunt sub prefixul `marketplace-tracking`, cu middleware-ul `marketplace.auth`.
- Pe AmBilet trece prin proxy-ul `api/tracking.php`.

**Tabele**
- `core_customer_events`:
  - `page_url` și `referrer` sunt de tip TEXT, trunchiate la 2048 de caractere;
  - index pe `(marketplace_event_id, event_type, created_at)`.
- `core_sessions`: `landing_page`, `referrer`, UTM-uri și click id-uri, luate din primul eveniment al sesiunii.

**Vizualizări de eveniment**
- Fluxul: `event-single.js` → proxy `event.track-view` → `MarketplaceEventsController::trackView`.
- Scrie un rând `page_view` în care `page_url` e de obicei null.

**Reparat pe 2026-09-15**
- IP-ul real al vizitatorului ajunge acum prin antetul `X-Visitor-IP` (`App\Support\VisitorIp`).
- `api.js` trimite `X-Visitor-ID`, iar proxy-ul îl transmite mai departe.

**Retenție**
- Tabela `data_retention_policies`, plus un job zilnic (`routes/console.php`, în jurul liniilor 622–649).
- Valorile reale există doar în baza de producție și nu au fost verificate.

### 2.2 Legătura dintre tracking și comenzi

**Comenzile NU păstrează nicio atribuire.**
- `CheckoutController::checkout` (`Order::create`, în jurul liniilor 1081–1142) salvează în `meta` doar: `promo_code`, IP, UA, `cart_id`, `seated_items`.

**Singura legătură existentă**
- Evenimentul `purchase`, trimis de pagina de mulțumire (`thank-you.js`, în jurul liniilor 218–243), are `order_id`. Din el se ajunge la `session_id`.
- Legătura se pierde dacă:
  - pagina de mulțumire nu se încarcă;
  - cumpărătorul a deschis un tab nou;
  - sesiunea a expirat.

**Cod care citește date inexistente**
- `OrderObserver` (în jurul liniilor 546–568) și `MilestoneAttributionService` (în jurul liniilor 114–121) citesc `meta.utm_*`, dar nimeni nu scrie aceste chei.

**Bug**
- Fallback-ul din `MilestoneAttributionService` (în jurul liniilor 126–140) ia ultimul `page_view` etichetat al **evenimentului**, nu al cumpărătorului.

**Model bun de urmat**
- Fluxul de atribuire pentru newsletter:
  - `newsletter-attribution.js`;
  - apoi `checkout-page.js` (în jurul liniei 938);
  - apoi `orders.newsletter_attribution_id` și `attribution_method` (`CheckoutController`, în jurul liniilor 1066–1089).

### 2.3 Sisteme existente care NU se potrivesc direct

**Afiliați**
- Componente: `App\Services\AffiliateTrackingService` și tabelele `affiliates`, `affiliate_links`, `affiliate_clicks`, `affiliate_conversions`.
- Sunt construite pentru tenanți (`Tenant::find`). Coloana `marketplace_client_id` a fost adăugată ulterior.
- În Filament Marketplace (`AffiliateResource`) e un program la nivel de marketplace, cu comisioane și retrageri. Nu există la nivel de organizator sau de eveniment.
- Checkout-ul marketplace nu apelează acest sistem.
- De preluat doar ideile:
  - prioritatea codului de cupon;
  - fereastra de atribuire;
  - deduplicarea per comandă;
  - anularea atribuirii la rambursare.

**`marketplace_referral_codes`**
- E referral de la client la client, cu puncte.
- **Folosește deja parametrul `?ref=` pe AmBilet** (`assets/js/auth.js`, în jurul liniei 709; `user/referrals.php`, în jurul liniei 205).
- ⇒ Pentru linkurile organizatorilor NU folosim `ref`.

**`mkt_promo_codes` (`MarketplaceOrganizerPromoCode`)**
- Au scop de organizator sau de eveniment și se salvează în `orders.meta.promo_code`.
- Sunt complementare linkurilor, pentru că funcționează și când clientul cumpără de pe alt dispozitiv.

**`EventMilestone`**
- Campanii cu UTM-uri, fereastră de atribuire și ROI, plus un modal pe pagina de analytics.
- E conceptul cel mai apropiat, dar atribuirea nu se face per vizitator (vezi bug-ul de la 2.2).

### 2.4 Deja livrat (punctul 4, 2026-09-15)

**Endpoint**
- `GET /organizer/events/{event}/analytics/sources` → `Organizer\EventsController::analyticsSources`.
- Funcții ajutătoare: `sourceLink`, `sourceDomain`, `sourceHost`.

**Pagina**
- Cardul „Linkuri și site-uri sursă” în `resources/marketplaces/ambilet/organizer/analytics.php`, cu funcțiile JS `loadSources`, `renderSources`, `setSourcesTab`.
- Grupează vizitele în două feluri:
  - pe linkul de intrare (parametri sortați, click id-uri mascate);
  - pe domeniul referrer.
- Comenzile sunt atribuite prin sesiunea evenimentului `purchase`.
- **Limită:** acoperă doar comenzile la care s-a încărcat pagina de mulțumire. Pasul 4 din planul de mai jos (atribuirea salvată pe comandă) rezolvă și această problemă.

---

## 3. Design recomandat

### 3.1 Model de date (migrații noi)

**`mkt_tracking_links`**
- `id`, `marketplace_client_id`, `marketplace_organizer_id`;
- `event_id`: nullable; null înseamnă toate evenimentele organizatorului;
- `code`: unic per marketplace, format `[a-z0-9-]{3,40}`;
- `label`;
- `partner_type`: influencer / site / partener / altul;
- `promo_code_id`: nullable, cheie străină spre `mkt_promo_codes`;
- `is_active`;
- `clicks_count`: contor brut, opțional;
- `timestamps`, `softDeletes`.

**`mkt_tracking_link_visits`**
- Coloane: `id`, `tracking_link_id`, `session_id`, `visitor_id`, `event_id`, `landing_url`, `referrer`, `created_at`.
- `UNIQUE(tracking_link_id, session_id)`: fiecare sesiune se numără o singură dată.

**`mkt_tracking_link_daily`**
- Rollup zilnic cu: link, zi, clicks, sessions, visitors.
- Rămâne și după ce retenția șterge rândurile din `core_customer_events`.

**Comenzi**
- `orders.tracking_link_id`: nullable, cu index.
- `orders.meta.attribution`, cu structura `{ first: {url, referrer, utm, click_ids, at}, last: {...}, visitor_id, session_id, method }`.

### 3.2 Captura

**Linkul scurt `https://ambilet.ro/l/{code}`**
1. Un rewrite în `.htaccess` trimite cererea spre o pagină PHP mică (`l.php`).
2. Pagina anunță click-ul în core:
   - `POST /tracking-links/{code}/click`;
   - trimite `X-Visitor-IP` și User-Agent.
3. Face redirect 302 spre pagina evenimentului (sau spre pagina organizatorului, pentru linkurile „toate evenimentele”):
   - adaugă `?tl={code}`;
   - păstrează eventualele UTM-uri.
4. Răspunsul trimite `Cache-Control: no-store` și nu trebuie să treacă prin `page-cache.php`.
5. Filtrul de boți nu numără click-urile de la crawlerele de preview: `facebookexternalhit`, `WhatsApp`, `Slackbot`, `TelegramBot`, `Twitterbot`, `LinkedInBot`, `Googlebot`, `Discordbot`.

**`tracking.js`**
- Dacă URL-ul conține `tl`, salvează în localStorage cheia `epas_tl`, cu forma `{code, first_code, first_at, last_at}`.
- Regula de atribuire: ultimul click câștigă, primul se păstrează separat.
- Expiră după 30 de zile.
- Trimite `tl` în `track()`. Core scrie un rând în `mkt_tracking_link_visits`, cu deduplicare per sesiune.

### 3.3 Atribuirea vânzărilor

**Frontend**
- `checkout-page.js` trimite:
  - `tracking_link_code`, luat din `epas_tl` dacă nu a expirat;
  - atribuirea first/last touch, pe modelul newsletter-attribution.

**Backend (`CheckoutController`)**
- Validează codul:
  - aparține aceluiași `marketplace_client`;
  - e activ;
  - organizatorul sau evenimentul linkului se află în coș.
- Setează `orders.tracking_link_id` și `meta.attribution`.

**Priorități și calcul**
- Ordinea priorităților:
  1. codul promo legat de un link, dacă a fost folosit;
  2. ultimul click din fereastra de atribuire.
- **Coș cu mai multe evenimente:** creditează doar liniile evenimentului sau organizatorului linkului. Venitul e suma acelor linii.
- Statisticile de vânzări se calculează la citire din `orders`, cu status `paid` sau `completed`. Astfel rambursările ies automat, fără contoare de vânzări stocate.

### 3.4 Interfața pentru organizator

**Pagina linkurilor**
- Pagină nouă `/organizator/linkuri` (sau un tab în analytics). Trebuie adăugată și în `.htaccess` și în sidebar.
- Operații:
  - creare: label, eveniment sau „toate”, cod, cod promo opțional;
  - copierea linkului;
  - cod QR, generat în browser;
  - activare / dezactivare.
- Tabel cu:
  - click-uri, sesiuni, vizitatori;
  - comenzi, bilete, venit;
  - rata de conversie.
- Filtre: perioadă și eveniment.
- Export CSV.

**Alte suprafețe**
- Un card în analytics-ul fiecărui eveniment, cu top linkuri pentru evenimentul respectiv.
- În Filament Marketplace, o listă doar pentru citire pentru admin.

### 3.5 Consimțământ (GDPR / ePrivacy, Legea 506/2004)

- **Cu acord:** păstrarea codului 30 de zile în localStorage e stocare neesențială, deci cere acord pentru analytics/marketing.
- **Fără acord:** codul se atașează doar coșului de pe server, pe durata vizitei (`cart_id` există deja în meta comenzii), fără să fie salvat pe dispozitiv.
- **Risc care există deja și trebuie reparat separat:**
  - `tracking.js` și cookie-urile `_fbp`/`_fbc` rulează fără să verifice `ambilet_cookie_consent`;
  - Consent Mode din `includes/head.php` (în jurul liniilor 330–349) acoperă doar gtag.

### 3.6 Cazuri limită

- **Alt dispozitiv și browserele din aplicații** (Instagram, TikTok) pierd localStorage. Atenuare: un cod promo legat de link.
- **Link pentru evenimentul A, cumpărare la evenimentul B:** se atribuie doar dacă linkul e de tip „toate evenimentele organizatorului”.
- **Rambursări parțiale:** venitul se recalculează din liniile rămase.
- **POS, invitații, comenzi de test:** nu pot fi atribuite și apar ca excluse.
- **Whitelabel și bilete.online** au propriul `tracking.js`, deci au nevoie de port separat.
- **Crawlerele de preview** umflă click-urile. Atenuare: filtru pe UA, iar sesiunile reale se numără separat.

### 3.7 Limitări

- **Numărătoarea va ieși mai mică decât realitatea:**
  - aplicațiile și Referrer-Policy taie referrerul;
  - unele browsere blochează storage-ul;
  - unii vizitatori refuză acordul.
- **Istoric:** comenzile dinainte de implementare nu vor avea link.

---

## 4. Plan și efort (~6–8 zile de dezvoltare)

| # | Pas | Efort |
|---|---|---|
| 1 | Migrații, modele, listă Filament doar pentru citire | 0,5 z |
| 2 | Endpoint pentru click, pagina `/l/{code}`, `.htaccess`, filtru de boți | 0,5 z |
| 3 | `tracking.js` și `track` în core: parametrul `tl`, deduplicarea vizitelor, rollup zilnic | 1 z |
| 4 | Checkout: atribuirea salvată pe comandă (`tracking_link_id`, `meta.attribution` first/last touch), plus validări. **Rezolvă și atribuirea din cardul „Linkuri și site-uri sursă”.** | 1 z |
| 5 | API pentru organizator: CRUD linkuri, statistici, CSV | 1 z |
| 6 | Interfața organizatorului: pagina de linkuri, QR, card în analytics, sidebar | 1,5–2 z |
| 7 | Consimțământ: condiționare pe acord, cu fallback pe coșul de pe server | 0,5 z |
| 8 | QA și teste: deduplicare, fereastră de atribuire, coș cu mai multe evenimente, rambursări | 1 z |

---

## 5. Întrebări deschise pentru user (de clarificat înainte de implementare)

1. Linkurile sunt per eveniment, per organizator sau ambele?
2. Fereastra de atribuire e de 30 de zile? Regula e „ultimul click câștigă”?
3. Legăm opțional un cod promo de fiecare link?
4. Doar statistici, sau și comisioane sau plăți pentru parteneri (ca la afiliați)?
5. Apar numele partenerilor în rapoartele sau deconturile organizatorului?
6. Formatul linkului scurt: `ambilet.ro/l/{code}`?
7. Adminul AmBilet poate crea linkuri în numele organizatorului?

---

## 6. Fișiere de atins (estimare)

**Core**
- migrații pentru `mkt_tracking_links`, `mkt_tracking_link_visits`, `mkt_tracking_link_daily` și coloana `orders.tracking_link_id`;
- `app/Models/MarketplaceTrackingLink.php`, plus modelele pentru vizite și rollup;
- `app/Http/Controllers/Api/MarketplaceClient/Organizer/TrackingLinksController.php` (nou): CRUD și statistici;
- endpoint-ul de click (în `MarketplaceTrackingController` sau într-un controller nou);
- `app/Http/Controllers/Api/MarketplaceTrackingController.php`: parametrul `tl`, deduplicarea vizitelor;
- `app/Http/Controllers/Api/MarketplaceClient/Customer/CheckoutController.php`: atribuirea pe comandă;
- `routes/api.php`;
- Filament Marketplace: resursă doar pentru citire.

**AmBilet**
- `.htaccess`: rewrite pentru `/l/{code}` și `/organizator/linkuri`;
- `l.php` (nou);
- `assets/js/tracking.js`;
- `assets/js/pages/checkout-page.js`;
- `api/proxy.php` și maparea din `assets/js/api.js`, pentru acțiunile noi;
- `organizer/linkuri.php` (nou) și `includes/organizer-sidebar.php`;
- `organizer/analytics.php`: cardul cu top linkuri.

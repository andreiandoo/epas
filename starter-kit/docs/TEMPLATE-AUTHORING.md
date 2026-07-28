# Arhitectura de template-uri — spec pentru sesiunea care produce template-uri

> **Vrei doar să produci un template?** Pasează `docs/BRIEF-NEW-TEMPLATE.md` —
> e varianta autonomă, pas-cu-pas, a acestui document. Cel de față rămâne
> referința de arhitectură din spate.

> **Cui i se adresează:** unei sesiuni (Claude sau om) care produce **template-uri**
> peste `starter-kit`. Citind acest document trebuie să știi exact **ce** fișiere
> produci, **cum** le compui și **ce reguli** respecti, astfel încât rezultatul să
> fie **plug&play**: `php tools/build.php <slug>` → site funcțional, fără cablare
> suplimentară.
>
> Context complet de framework: `docs/STARTER-KIT.md`. Catalog de componente:
> `docs/COMPONENTS.md`. Documentul de față e stratul „cum produc template-uri”.

---

## 0. Modelul mental (citește asta întâi)

Un **template** NU conține logică de date și NU conține markup de carduri/liste.
Un template este:

1. **Identitate + date** → `site.config.php` (nume, profil, chei API/tenant, meniu, URL-uri).
2. **Aspect** → `theme.css` (suprascrii variabile `--kit-*`; ATÂT).
3. **Compoziție** → `pages/*.php` care **cheamă componente** din kit.
4. **Rutare** → `routes.php` (URL curat → nume de pagină).

**Nu scrii niciodată:** `curl`/`api_get` brut, normalizare de răspuns API,
markup de `event-card`/grid/hero, culori hard-codate. Toate există în `kit/`.

Dacă te trezești copiind HTML de card sau punând `#A51C30` într-o pagină,
**greșești** — folosește o componentă și un token.

---

## 1. Ce ai voie și ce NU ai voie să atingi

| Poți edita | NU atinge |
|---|---|
| `templates/<slug>/site.config.php` | `kit/**` (framework — comun tuturor site-urilor) |
| `templates/<slug>/theme.css` | forma canonică / adaptoarele |
| `templates/<slug>/pages/*.php` | `tokens.css` (doar îl suprascrii din theme.css) |
| `templates/<slug>/routes.php` | componentele (le CHEMI, nu le editezi) |
| `templates/<slug>/includes/bootstrap.php` (rar) | |

Dacă ai nevoie de o componentă care nu există: **nu o improviza în pagină**.
Semnaleaz-o ca „componentă lipsă” (vezi §7) — o adaugă sesiunea de framework
după rețeta din STARTER-KIT.md §5.1. Doar așa rămâne plug&play și refolosibilă.

---

## 2. Profile ȘI kind (două layere de tip)

**Profile** = modelul de auth/API:

| | `tenant` | `marketplace` |
|---|---|---|
| Cine | un singur organizator | agregator multi-organizator |
| Config cheie | `tenant_id` | `api_key` |
| Starter | `_starter-tenant` | `_starter-marketplace` |

**Kind** = sub-tipul unui `tenant` (`teatru`, `filarmonica`, `agentie`,
`leisure`, `artist`, `organizator`). Un kind aduce automat meniul, vocabularul
(`kit_term`), capabilitățile (`kit_feature`), URL-urile și setul de pagini
recomandat. **Detalii complete: `docs/TENANT-KINDS.md`.**

În pagini, fă-le kind-aware în loc să hard-codezi:
```php
<h1><?= e(kit_term('events_cap', 'Evenimente')) ?></h1>   <!-- „Spectacole” / „Activități” -->
<?php if (kit_feature('seating')) component('seat-map', ['event'=>$event]);
      else component('ticket-selector', ['event'=>$event]); ?>
```

**Paginile sunt identice ca structură între profile și kind-uri.** Nu pune
`if (profil)` / `if (kind)` în pagini pentru DATE — doar `kit_feature()` pentru
a afișa/ascunde zone specifice, și `kit_term()` pentru text.

---

## 3. Pasul cu pasul — producerea unui template

```bash
# 1. schelet — dă un KIND (recomandat) sau un profile brut
php tools/create-template.php <kind|profile> <slug> "Nume Site"
#   kind:    teatru | filarmonica | agentie | leisure | artist | organizator
#   profile: tenant | marketplace   (fără kind)
# Cu un KIND, generatorul produce TOATE paginile tipului (wrappere peste
# kit/pagesets/*) + routes.php. Site-ul e funcțional imediat.

# 2. IDENTITATE  → editează templates/<slug>/site.config.php
#    - tenant_id (kind) / api_key (marketplace)
#    - site_name, site_city, logo_text, currency, locale
#    - fonts_href (Google Fonts pt. fonturile temei)
#    - (menu/url/cta vin din kind; suprascrie-le aici DOAR dacă e nevoie)

# 3. ASPECT  → editează templates/<slug>/theme.css   (vezi §5)

# 4. RUTE  → editează templates/<slug>/routes.php     (vezi §6)

# 5. PAGINI  → creează templates/<slug>/pages/*.php   (vezi §4, blueprint-uri)

# 6. verifică
KIT_FIXTURES="$PWD/fixtures" php -S 127.0.0.1:8899 tools/dev-router.php  # KIT_SITE=<slug>
php tools/build.php <slug>                                              # → build/<slug>
```

**Definiția lui „gata” (Definition of Done) pentru un template:**
- [ ] `site.config.php` completat (fără `TODO`, fără `mpc_TODO`/`tenant_id=0`)
- [ ] `theme.css` cu paleta de brand + fonturi (nu mai arată ca default-ul)
- [ ] paginile obligatorii există și randează (§4)
- [ ] fiecare intrare din `menu[]` are pagină + rută
- [ ] `*_url_pattern` ≡ `routes.php` (link-urile de card duc unde trebuie)
- [ ] `php -l` clean pe toate paginile; randare OK cu fixtures
- [ ] `php tools/build.php <slug>` produce `build/<slug>` fără erori

---

## 4. Blueprint-uri de pagini (copiază structura)

Fiecare pagină = `require bootstrap` → `kit_*` pt. date → `layout(...)` cu
componente. Sub fiecare tip: **data function** + **componentele** de folosit.

### Pagini publice OBLIGATORII (orice site)

| Pagină | Rută | Data fn | Componente |
|---|---|---|---|
| Homepage | `/` | `kit_events()` | `hero`, `event-grid` (+ `cta`) |
| Listare evenimente | `/evenimente` \| `/repertoriu` | `kit_events($filters)` | `filters`, `event-grid`, `pagination` |
| Eveniment (single) | `/bilete/{slug}` \| `/spectacol/{slug}` | `kit_event($slug)` | `event-hero` + `ticket-selector` **sau** `seat-map`, `breadcrumb`, `artist-card`, `event-grid` (related) |
| 404 | (fallback) | — | `empty-state` |

### Pagini publice frecvente

| Pagină | Data fn | Componente |
|---|---|---|
| Program/calendar | `kit_events(['per_page'=>100])` | `calendar` |
| Abonamente | `kit_subscriptions()` | `subscription-card`, `cta` |
| Listă artiști/trupă | `kit_artists()` | `artist-card` |
| Artist (single) | `kit_artist($slug)` | `event-hero`-like + `event-grid` |
| Listă locații | (venues endpoint) | `venue-card` |
| Căutare | `kit_events(['q'=>...])` | `search-bar`, `event-grid` |
| Coș | client (`KitCart`) | `cart-line`, `order-summary`, `step-indicator` |
| Checkout | `kit_api_get` + client | `step-indicator`, `order-summary`, `cart-line` |

### Zona de cont (`layout('account')`, JS-hydrated, doar tenant de regulă)

| Pagină | Proxy action | Componente |
|---|---|---|
| Panou | `acc-stats`, `acc-tickets` | `stat-tile`, `ticket-card`, `qr-modal` |
| Bilete | `acc-tickets` | `ticket-card`, `qr-modal` |
| Comenzi | `acc-orders` | `order-summary` |
| Recenzii | `acc-reviews` | `review-card` |

### Schelet canonic — pagină publică
```php
<?php
require __DIR__ . '/../includes/bootstrap.php';
$events = kit_events(['per_page' => 12]);              // DATE (canonice, cache inclus)
layout('public', ['title' => '… — '.kit_cfg('site_name'), 'nav' => 'events'],
  function () use ($events) { ?>
    <section class="kit-section"><div class="kit-container">
      <h1 class="kit-display">Evenimente</h1>
      <?php component('event-grid', ['events' => $events]); ?>
    </div></section>
<?php });
```

### Schelet canonic — pagină de cont
```php
<?php
require __DIR__ . '/../includes/bootstrap.php';
layout('account', ['title' => 'Bilete', 'nav' => 'tickets'], function () { ?>
  <div x-data="{ items:[], async load(){ const r=await KitProxy('acc-tickets'); this.items=(r&&r.data)||[]; } }" x-init="load()">
    <template x-for="t in items" :key="t.code"> … folosește markup .kit-ticket … </template>
  </div>
<?php });
```

> Pentru zone dinamice/hidratate (cont, seating, coș): datele vin din `KitProxy`
> pe client, iar componentele SSR oferă doar scheletul. Vezi paginile exemplu
> `templates/teatru/pages/cont-index.php` și componenta `seat-map`.

---

## 5. Contractul de temă (`theme.css`) — aici trăiește TOT aspectul

Suprascrii doar tokenii pe care vrei să-i schimbi; restul moștenesc default-ul
din `tokens.css`. Regula: **niciun `--kit-*` important nelăsat pe default dacă
brandul îl definește**. Nu adăuga reguli CSS pe clase Tailwind sau selectori noi
în pagini — pune totul aici, pe tokeni.

**Setul minim de branding (completează-le mereu):**
```css
:root {
  /* culori */
  --kit-primary: #…;  --kit-primary-dark: #…;  --kit-on-primary: #…;  --kit-accent: #…;
  /* suprafețe (setează-le pe TOATE dacă tema e dark) */
  --kit-bg: #…;  --kit-surface: #…;  --kit-surface-2: #…;  --kit-text: #…;  --kit-text-muted: #…;  --kit-border: #…;
  /* tipografie */
  --kit-font-body: '…', sans-serif;  --kit-font-display: '…', serif;  --kit-font-weight-display: 700;
  /* formă */
  --kit-radius: …;  --kit-radius-sm: …;
}
```
Listă completă de tokeni cu default-uri: `kit/tokens/tokens.css` (secțiunea `:root`).
Seating are tokeni proprii: `--kit-seat-free/held/sold/selected`.

**Fonturi:** pune URL-ul Google Fonts în `site.config.php → fonts_href`, apoi
referă familia în `--kit-font-*`.

**Flourish-uri opționale:** poți adăuga în `theme.css` reguli pe clasele `.kit-*`
existente (ex. `.kit-btn--primary:hover{…}`) — dar NU redefini structura, doar
accente. Exemplu real: `templates/teatru/theme.css`.

**Un template = o temă.** Dacă vrei „dark + gold” vs „light + crimson”, e doar
alt `theme.css`. Vezi diferența teatru (dark) vs ambilet (light) — aceleași
componente, `theme.css` diferit.

---

## 6. Rutare (`routes.php`)

```php
return [
  'exact'   => ['/' => 'index', '/evenimente' => 'events', '/cont' => 'cont-index'],
  'capture' => ['bilete' => 'show', 'artist' => 'artist'],   // /bilete/{slug} → show.php?slug=
];
```
- `exact`: URL complet → nume fișier din `pages/`.
- `capture`: prefix → pagină; ultimul segment devine `$_GET['slug']`.
- **Sincronizează** cu `*_url_pattern` din `site.config.php`: dacă
  `event_url_pattern = '/bilete/{slug}'`, trebuie `capture['bilete'] => 'show'`.

---

## 7. Reguli stricte (respectă-le sau strici plug&play-ul)

1. **Nu edita `kit/`.** E comun tuturor site-urilor. Ai nevoie de ceva nou →
   raportează, nu improviza local.
2. **Datele doar prin `kit_*` / `KitProxy`.** Zero `curl`/`api_get` în pagini.
3. **Markup repetat doar prin componente.** Nu rescrie un card/hero/grid.
4. **Stil doar prin tokeni în `theme.css`.** Zero culori/fonturi hard-codate în
   pagini; zero `<style>` cu valori de brand în pagini.
5. **Escape:** conținutul dinamic scris manual trece prin `e()`.
6. **Fără `if (profile)` în pagini.** Diferențele de profil sunt în data layer.
7. **Verifică cu fixtures** înainte de a declara gata (randare + `php -l`).
8. **Nu inventa chei de view-model.** Folosește câmpurile canonice
   (`docs/COMPONENTS.md` / `viewmodel.php`). Lipsește un câmp → raportează.

Când respecți 1–8, orice pagină nouă e „doar compoziție”, iar un template nou
înseamnă `site.config.php` + `theme.css` + câteva pagini scurte.

---

## 8. Ce livrezi la final (per template)

```
templates/<slug>/
  site.config.php     # completat, fără TODO
  theme.css           # brandul
  routes.php          # rutele
  includes/bootstrap.php
  pages/
    index.php  events.php  show.php  404.php        # minim
    schedule.php  subscriptions.php  artists.php …  # după meniu
    cont-index.php  cont-bilete.php …               # dacă are cont
```
Plus, în mesajul de predare: lista paginilor produse, componentele folosite, și
orice **componentă lipsă** semnalată (nume + input propus + unde ar fi folosită).

---

## 9. Referință rapidă — paleta de componente

26 componente. Input complet în `docs/COMPONENTS.md`. Cele mai folosite:

`event-card` · `event-grid` · `event-hero` · `ticket-selector` · `seat-map` ·
`schedule-row` · `calendar` · `artist-card` · `venue-card` · `category-card` ·
`subscription-card` · `cart-line` · `order-summary` · `ticket-card` · `qr-modal` ·
`review-card` · `filters` · `pagination` · `breadcrumb` · `search-bar` ·
`auth-widget` · `step-indicator` · `hero` · `cta` · `stat-tile` · `empty-state`.

Layout-uri: `public` (site) · `account` (cont/*).

Exemple complete de citit înainte să începi:
`templates/teatru/pages/{index,repertoire,schedule,subscriptions,show,cont-index}.php`
și `templates/ambilet/pages/events.php`.

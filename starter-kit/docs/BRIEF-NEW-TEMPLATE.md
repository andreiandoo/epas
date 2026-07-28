# BRIEF — produ un template nou peste `starter-kit`

> **Acesta e documentul care ți se pasează ca sarcină.** E autonom: dacă îl
> citești integral și îl urmezi, rezultatul e plug&play — `php tools/build.php
> <slug>` scoate un site funcțional, fără cablare suplimentară din partea
> nimănui.
>
> Referințe (citește-le DOAR dacă ai nevoie de detaliu):
> `CLAUDE.md` (orientare) · `docs/STARTER-KIT.md` (framework) ·
> `docs/COMPONENTS.md` (input-ul fiecărei componente) ·
> `docs/TENANT-KINDS.md` (stratul kind) · `docs/I18N.md` (traduceri).

---

## 0. Ce ești și ce NU ești

Ești **compozitor**, nu inginer de framework. Un template e:

| Livrezi | Conține |
|---|---|
| `site.config.php` | identitate + credențiale (tenant_id / api_key) |
| `theme.css` | **tot** aspectul, exclusiv prin tokeni `--kit-*` |
| `routes.php` | URL curat → nume de pagină |
| `pages/*.php` | compoziție: date prin `kit_*`, HTML prin `component()` |
| `includes/bootstrap.php` | generat, rar îl atingi |

**Nu scrii niciodată:** markup de card/grid/hero, `curl`/`api_get`, normalizare
de răspuns API, culori sau fonturi hard-codate, copy hard-codat, `if (profile)`.
Toate există deja în `kit/`.

Dacă te trezești copiind HTML de card sau scriind `#A51C30` într-o pagină,
**greșești** — folosește o componentă și un token.

**NU edita `kit/`.** E comun tuturor site-urilor. Îți lipsește o componentă sau
un câmp canonic → **raportează** (§8), nu improviza local.

---

## 1. Cele 3 straturi (modelul mental)

```
profile   marketplace | tenant       → model de auth + API
  kind    teatru | filarmonica | agentie | leisure | artist | organizator
                                     → meniu, vocabular, features, URL-uri, set de pagini
  site    site.config.php + theme.css → identitate + brand
```
Se contopesc la boot: `defaults ← kind ← site`.

De aici vine ieftinătatea: **10 teatre = același kind + 10 × `theme.css`.**

**Profile** decide auth-ul: `tenant` → `tenant_id` (`?tenant=ID`);
`marketplace` → `api_key` (`X-API-Key`). Un kind îl setează automat.

---

## 2. Setup (rulează-le înainte de orice)

```bash
cd starter-kit
bash tools/verify.sh          # TREBUIE să fie verde ÎNAINTE să atingi ceva
```

Preview cu date reale (recomandat):
```bash
KIT_SITE=<slug> php -S 127.0.0.1:8899 tools/dev-router.php
```

Preview offline (fără backend):
```bash
KIT_SITE=<slug> KIT_FIXTURES="$PWD/fixtures" php -S 127.0.0.1:8899 tools/dev-router.php
```

> **Windows/XAMPP:** dacă apelurile HTTPS pică cu „SSL certificate problem",
> pornește PHP cu bundle-ul de CA:
> `php -d curl.cainfo="I:\xampp\apache\bin\curl-ca-bundle.crt" -S 127.0.0.1:8899 tools/dev-router.php`

Deschide `/styleguide` în dev-router: vezi **toate cele 29 de componente**
randate în tema activă. Fă asta înainte să compui prima pagină.

---

## 3. Pașii, în ordine

### Pas 1 — schelet
```bash
php tools/create-template.php <kind> <slug> "Nume Site"
#   kind: teatru | filarmonica | agentie | leisure | artist | organizator
#   (sau un profile brut: tenant | marketplace)
```
Generatorul scoate **24–27 de pagini funcționale** + `routes.php` + `theme.css`
+ `site.config.php`. Site-ul merge din prima. Tu îl personalizezi — nu îl
reconstruiești.

### Pas 2 — identitate (`site.config.php`)
| Cheie | Obligatoriu | Notă |
|---|---|---|
| `tenant_id` / `api_key` | ✅ | fără el nu vin date |
| `site_name`, `site_city`, `logo_text` | ✅ | |
| `site_url` | ✅ | **nu e cosmetic**: din el se derivă `hostname`-ul pentru auth-ul tenant |
| `locale`, `locales`, `currency` | ✅ | `locales` doar dacă e multilingv |
| `fonts_href` | ✅ | URL Google Fonts pentru fonturile temei |
| `description`, `og_image`, `social` | recomandat | SEO + footer + JSON-LD |
| `ga4_id`, `gtm_id`, `meta_pixel_id` | opțional | se încarcă doar după consimțământ |
| `menu`, `*_url_pattern`, `cta_*` | opțional | vin din kind; suprascrie DOAR dacă e nevoie |

⚠️ `fixtures` rămâne `null`. E un switch de dev prin `KIT_FIXTURES`; build-ul de
producție **refuză** un config care îl setează.

### Pas 3 — aspect (`theme.css`)
Aici trăiește **tot** designul. Setul minim, completează-l mereu:
```css
:root {
  --kit-primary: #…;  --kit-primary-dark: #…;  --kit-on-primary: #…;  --kit-accent: #…;
  --kit-bg: #…;  --kit-surface: #…;  --kit-surface-2: #…;
  --kit-text: #…;  --kit-text-muted: #…;  --kit-border: #…;
  --kit-font-body: '…', sans-serif;  --kit-font-display: '…', serif;
  --kit-font-weight-display: 700;
  --kit-radius: …;  --kit-radius-sm: …;
}
```
- Dacă tema e **dark**, setează-le pe TOATE cele de suprafață — altfel moștenești light.
- Seating are tokeni proprii: `--kit-seat-free/held/sold/selected`.
- Lista completă cu default-uri: `kit/tokens/tokens.css`, secțiunea `:root`.
- Ai voie să adaugi accente pe clase `.kit-*` existente (ex. `.kit-btn--primary:hover`),
  dar **nu** redefini structura și **nu** inventa selectori noi.

Exemple de citit: `templates/teatru/theme.css` (dark, editorial) vs
`templates/ambilet/theme.css` (light, comercial) — aceleași componente.

### Pas 4 — rute (`routes.php`)
```php
return [
  'exact'   => ['/' => 'index', '/evenimente' => 'events', '/cont' => 'cont-index'],
  'capture' => ['bilete' => 'show', 'artist' => 'artist'],  // /bilete/{slug} → show.php?slug=
];
```
**Sincronizează obligatoriu** cu `*_url_pattern`: dacă
`event_url_pattern = '/bilete/{slug}'`, trebuie `capture['bilete'] => 'show'`,
altfel fiecare card duce în 404.

### Pas 5 — pagini (`pages/*.php`)
Vezi §4 pentru schelete și §5 pentru ce pagini sunt obligatorii.

### Pas 6 — verifică
```bash
bash tools/verify.sh              # lint + i18n + randare + build pe toate kind-urile
php tools/build.php <slug>        # → build/<slug>
```

---

## 4. Scheletele canonice (copiază-le)

### Pagină publică
```php
<?php
require __DIR__ . '/../includes/bootstrap.php';

$events = kit_events(['per_page' => 12]);      // DATE — canonice, cache inclus

layout('public', [
    'title' => kit_term('events_cap') . ' — ' . kit_cfg('site_name'),
    'nav'   => 'events',                        // marchează item-ul activ din meniu
], function () use ($events) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display"><?= e(kit_term('events_cap')) ?></h1>
    <?php component('event-grid', ['events' => $events]); ?>
  </div></section>
<?php });
```

### Pagină de cont (hidratată pe client)
```php
<?php
require __DIR__ . '/../includes/bootstrap.php';

layout('account', ['title' => t('account.tickets'), 'nav' => 'tickets'], function () { ?>
  <div x-data="{ items:[], loading:true,
       async load(){ const r = await KitProxy('acc-tickets');
                     this.items = (r && r.data) || []; this.loading = false; } }"
       x-init="load()">
    <template x-for="t in items" :key="t.code">
      … markup .kit-ticket …
    </template>
  </div>
<?php });
```
`nav` pentru `layout('account')`: `dashboard` · `tickets` · `orders` ·
`favorites` · `subscriptions` · `giftcards` · `settings`.

### API-ul pe care ai voie să-l chemi
| Funcție | Ce întoarce |
|---|---|
| `kit_events($params)` | listă canonică. Params: `per_page`, `page`, `q`, `category`, `city` |
| `kit_featured_events($limit)` | listă canonică |
| `kit_event($slug)` | un eveniment canonic sau `null` |
| `kit_artists()` / `kit_artist($slug)` | artiști canonici |
| `kit_venues()` | locații canonice |
| `kit_categories()` | taxonomii canonice |
| `kit_subscriptions()` | planuri (doar tenant) |
| `kit_page($slug)` | `['title','html','description']` |
| `kit_posts()` / `kit_post($slug)` | articole de blog |
| `KitProxy('<action>', params, opts)` | **client**, pentru zone dinamice |

Helperi de randare: `component($name, $data)` · `layout($name, $vars, $body)` ·
`e($str)` (escape — obligatoriu pe orice text dinamic scris manual) ·
`t('key')` (string UI) · `kit_term('event')` (substantiv per kind) ·
`kit_feature('seating')` (capabilitate) · `kit_cfg('site_name')` ·
`vm_date($iso)` (**folosește-l mereu** pentru date — evită driftul de fus orar).

---

## 5. Pagini + componente

### Obligatorii (orice site)
| Pagină | Rută | Date | Componente |
|---|---|---|---|
| Homepage | `/` | `kit_events()` | `hero`, `event-grid`, `cta` |
| Listare | `/evenimente` \| `/repertoriu` | `kit_events($f)` | `filters`, `event-grid`, `pagination` |
| Single | `/bilete/{slug}` | `kit_event($slug)` | `event-hero` + `ticket-selector` **sau** `seat-map`, `breadcrumb`, `artist-card`, `event-grid` (related) |
| Coș | `/cos` | client (`KitCart`) | `cart-line`, `order-summary`, `step-indicator` |
| Checkout | `/finalizare` | client | `step-indicator`, `order-summary` |
| Confirmare | `/confirmare` | `KitProxy('order-summary')` | `order-summary`, `ticket-card`, `qr-modal` |
| Legal | `/termeni`, `/confidentialitate` | `kit_page($slug)` | — |
| 404 | fallback | — | `empty-state` |

### Frecvente
Program (`calendar`, `schedule-row`) · Abonamente (`subscription-card`) ·
Artiști (`artist-card`) · Locații (`venue-card`) · Căutare (`search-bar`,
`event-grid`) · Blog (`kit_posts`) · Despre / Contact.

### Cont (`layout('account')`)
Panou (`stat-tile`, `ticket-card`) · Bilete (`ticket-card`, `qr-modal`) ·
Comenzi (`order-summary`) · Favorite (`favorite-button`) · Setări.

### Cele 29 de componente
`event-card` · `event-grid` · `event-hero` · `ticket-selector` · `seat-map` ·
`schedule-row` · `calendar` · `artist-card` · `venue-card` · `category-card` ·
`subscription-card` · `cart-line` · `order-summary` · `ticket-card` ·
`qr-modal` · `review-card` · `filters` · `pagination` · `breadcrumb` ·
`search-bar` · `auth-widget` · `locale-switcher` · `favorite-button` ·
`step-indicator` · `hero` · `cta` · `stat-tile` · `empty-state` ·
`cookie-consent`

**Input-ul exact al fiecăreia: `docs/COMPONENTS.md`.** Nu ghici cheile.

---

## 6. Kind-aware, nu hard-codat

```php
<h1><?= e(kit_term('events_cap')) ?></h1>          <!-- „Spectacole” / „Activități” -->

<?php if (kit_feature('seating')) component('seat-map', ['event' => $event]);
      else                        component('ticket-selector', ['event' => $event]); ?>

<?php if (kit_feature('subscriptions')): ?> … zona de abonamente … <?php endif; ?>
```

- `kit_term()` pentru **substantive** (diferă per kind).
- `t()` pentru **stringuri UI** (diferă per limbă) — vezi `docs/I18N.md`.
- `kit_feature()` pentru **zone** care se aprind/sting.
- **Zero** `if (kind)` / `if (profile)` pentru DATE. Diferențele de API sunt deja
  rezolvate în stratul de date; dacă un răspuns nu se potrivește, **raportează**.

Dacă adaugi o cheie `t()` nouă, adaug-o în **toate** dicționarele
(`kit/lang/{ro,en,hu}.php`) — altfel `php tools/i18n-audit.php` pică și verify e roșu.

---

## 7. Definition of Done

- [ ] `site.config.php` complet — fără `TODO`, fără `mpc_TODO`, fără `tenant_id => 0`
- [ ] `fixtures => null`
- [ ] `theme.css` cu paleta + fonturile brandului (nu mai arată a default)
- [ ] Toate paginile din §5 „obligatorii" există și randează
- [ ] Fiecare intrare din `menu[]` are pagină **și** rută
- [ ] `*_url_pattern` ≡ `routes.php`
- [ ] Zero culori/fonturi hard-codate în `pages/` (verify le prinde)
- [ ] Zero copy hard-codat — totul prin `t()` / `kit_term()`
- [ ] `bash tools/verify.sh` **verde**
- [ ] `php tools/build.php <slug>` trece fără erori
- [ ] Verificat vizual în dev-router: homepage, listare, single, coș, checkout, cont

---

## 8. Ce raportezi la predare

1. Lista paginilor produse.
2. Componentele folosite.
3. **Componente lipsă** — nume propus + input propus + unde ar fi fost folosită.
4. **Câmpuri canonice lipsă** — ce ai avut nevoie și nu exista în `viewmodel.php`.
5. Orice loc unde ai fost tentat să încalci o regulă și de ce.

Punctele 3–5 sunt cele mai valoroase: ele cresc framework-ul. Le rezolvă sesiunea
de framework, nu tu — dar dacă nu le semnalezi, următorul template se lovește de
aceleași lipsuri.

---

## 9. Cele 7 reguli de aur (dacă reții doar atât)

1. Zero culori/fonturi/radius hard-codate — doar tokeni `--kit-*`.
2. Paginile nu cheamă API-ul direct — doar `kit_*` (server) / `KitProxy` (client).
3. Fără `if (profile)` / `if (kind)` pentru date — `kit_feature()` + `kit_term()`.
4. Componentele nu iau date și nu emit `<style>`. Le CHEMI, nu le editezi.
5. Atenție la capcana docblock: `*/` într-un comentariu PHP îl închide devreme.
6. Date: `vm_date()`, întotdeauna.
7. Ordinea scripturilor din layout e fixă (`window.KIT` → `kit.js` → Alpine).
   Nu o rearanja.

---

## 10. Exemple de citit înainte să începi

```
templates/teatru/pages/{index,repertoire,schedule,subscriptions,show,cont-index}.php
templates/ambilet/pages/events.php
templates/teatru/theme.css        (dark)
templates/ambilet/theme.css       (light)
kit/kinds/teatru.php              (cum arată un kind complet)
```

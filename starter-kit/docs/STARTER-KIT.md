# Starter Kit — ghid complet de operare

> Document de referință pentru dezvoltatori **și pentru o instanță Claude** care
> continuă implementarea. Explică de ce există kit-ul, cum e construit, și
> **rețete exacte** pentru fiecare tip de modificare. Citește-l întreg înainte
> să adaugi cod.

Locație: `starter-kit/` pe branch-ul `starter-kit`.

---

## 1. Problema pe care o rezolvă

Platforma are două „skin-uri” PHP hand-authored, deployate ca site-uri statice
pe subdomenii separate:

- **marketplace** (`resources/marketplaces/ambilet`) — agregator, API
  `/api/marketplace-client/*`, auth cu `X-API-Key`, formă `event` **plată**.
- **tenant** (`resources/tenant-demos/teatru`) — un singur organizator, API
  `/api/tenant-client/*`, scopare `?tenant=ID`, formă `event` **bogată/nested**.

Fiecare pagină din ele **fuzionează trei preocupări**: (1) aducerea datelor,
(2) markup-ul, (3) stilul. Un template nou le reface pe toate trei → lent.
Cele două skin-uri **nu împart niciun rând de cod** (aceleași fișiere
`includes/{config,api,head,header,footer}` rescrise separat).

**Soluția (acest kit):** separă cele trei preocupări.
- Datele + normalizarea trăiesc o singură dată în **kit core** (adaptoare →
  view-model canonic).
- Markup-ul trăiește o singură dată în **componente**.
- Stilul e **doar tokens** (variabile CSS) suprascrise de `theme.css`-ul
  fiecărui template.

Un template nou = **`site.config.php` + `theme.css`** (două fișiere) plus pagini
scurte care **cheamă componente**. Zero re-cablare de date.

**Un singur kit, două profile.** Profilul (`marketplace`/`tenant`) se alege în
`site.config.php`; schimbă doar adaptorul de date și lista de acțiuni din proxy.
Componentele sunt identice.

---

## 2. Harta fișierelor

```
starter-kit/
  kit/                         # FRAMEWORK-ul partajat (nu conține nimic site-specific)
    core/
      config.php     # Kit::boot(), kit_cfg(), kit_asset_url()
      http.php       # kit_api_get/post/request + cache SWR + mod FIXTURES
      data.php       # kit_events(), kit_event(), kit_artists()... → view-model canonic
      view.php       # component(), component_html(), layout(), e(), kit_price()
      viewmodel.php  # forma canonică Event/Venue/Artist/Taxonomy + vm_* helpers
      adapters/
        tenant.php       # raw tenant-client  → canonic
        marketplace.php  # raw marketplace-client → canonic
    components/        # partial-uri pure: date in → HTML out (vezi docs/COMPONENTS.md)
    layouts/public.php # <head> + header + footer, token-driven
    tokens/tokens.css  # CONTRACTUL de variabile + stilurile de bază .kit-*
    js/kit.js          # runtime client: cart, Alpine ticket-selector, hydrate seat-map, img fallback
    proxy.php          # gateway client unic, allow-list de acțiuni per profil
    deploy/
      index.php        # front controller (clean URLs → pages/)
      htaccess         # rewrite rules (devine .htaccess la build)
  templates/
    _starter-tenant/       # schelet copiat de generator
    _starter-marketplace/
    teatru/                # exemplu real, profil tenant
    ambilet/               # exemplu real, profil marketplace
      site.config.php  # IDENTITATEA + credentialele (singurul fișier cu secrete)
      theme.css        # ÎNTREAGA identitate vizuală
      routes.php       # clean-URL map
      includes/bootstrap.php
      pages/*.php      # pagini scurte care cheamă componente
  tools/
    dev-router.php     # router pentru `php -S` (preview local)
    build.php          # asamblează un folder deployabil (kit + template + chrome)
    create-template.php# generator: schelet nou din _starter-*
  fixtures/            # răspunsuri API JSON pentru randare offline (dev/CI)
  docs/
    STARTER-KIT.md     # acest fișier
    COMPONENTS.md      # catalogul de componente + contractele lor
```

---

## 3. Fluxul de date (o cerere, cap-coadă)

```
pagină  →  kit_events($params)            [data.php]
           └─ kit_api_get('/tenant-client/events', …)     [http.php]
                └─ cache SWR / fixtures / cURL → răspuns brut
           └─ kit_map_events()  → alege adaptorul profilului
                └─ tenant_adapt_event()  sau  marketplace_adapt_event()
                     → EVENT CANONIC (aceleași chei mereu)   [viewmodel.php]
pagină  →  component('event-grid', ['events'=>$events])   [view.php]
           └─ include kit/components/event-grid.php
                └─ component('event-card', ['event'=>$e]) pentru fiecare
pagină  →  layout('public', $vars, fn)  →  <head>+header+ $slot +footer
                └─ injectează tokens.css + theme.css (kit_theme_links())
```

**Invariant esențial:** o componentă nu apelează NICIODATĂ API-ul și nu vede
NICIODATĂ o formă brută. Primește doar view-model canonic. Asta e ce face
componentele reutilizabile între profile.

---

## 4. Contractul view-model-ului canonic (`kit/core/viewmodel.php`)

`vm_event_defaults()` definește forma. Chei garantate mereu prezente (deci
componentele nu fac `isset()`):

```
id, slug, title, category, category_slug, venue_name, city, country,
starts_at, date (Y-m-d), time (H:i), end_date, duration_mode,
poster_url, hero_image_url, price_from (float, unități MAJORE), currency,
is_sold_out, is_cancelled, is_postponed, is_promoted,
url (permalink pe ACEST site), short_description, description,
ticket_types[]{name,price,sale_price,currency}, artists[]{name,slug,image}, tags[]
```

**Regula de aur pentru un câmp nou:** adaugă-l în `vm_event_defaults()` o dată,
apoi învață AMBELE adaptoare să-l umple. Toate componentele îl primesc gratis.

---

## 5. Rețete (fă exact așa)

### 5.1 Adaugă o componentă nouă
1. Creează `kit/components/<nume>.php`. Semnătură: primește variabile din
   `$data`, are `$__cfg` disponibil, **echo-uie HTML**. Nicio aducere de date.
2. Pune stilurile în `kit/tokens/tokens.css` cu clase `.kit-<nume>*`, folosind
   **doar** `var(--kit-*)`. NU pune `<style>` în componentă (se dublează în
   bucle). Dacă ai nevoie de un „knob” nou de temă, adaugă un token cu default.
3. Documentează input-ul în header-ul fișierului + în `docs/COMPONENTS.md`.
4. Cheam-o: `component('<nume>', [...])` sau `component_html('<nume>', [...])`
   dacă vrei string-ul (ex. pentru un slot).

### 5.2 Adaugă o pagină într-un template
1. `templates/<site>/pages/<name>.php`:
   ```php
   require __DIR__ . '/../includes/bootstrap.php';
   $data = kit_events([...]);           // sau kit_event($slug), kit_artists()...
   layout('public', ['title'=>'…','nav'=>'…'], function () use ($data) {
       component('event-grid', ['events' => $data]);
   });
   ```
2. Adaugă ruta în `templates/<site>/routes.php` (`exact` sau `capture`).

### 5.3 Creează un template (site) nou — calea rapidă
```
php tools/create-template.php tenant opera-cluj "Opera Națională Cluj"
# editează templates/opera-cluj/site.config.php  (tenant_id/api_key, menu, url patterns)
# editează templates/opera-cluj/theme.css         (tokens de brand)
php tools/build.php opera-cluj                     # → build/opera-cluj (deployabil)
```
Atât. Pagini funcționale instant (starter-ul aduce home + 404; adaugi restul cu 5.2).

### 5.4 Portează o pagină din skin-ul legacy
1. Identifică datele: ce `api_get`/`tc_*` folosea → înlocuiește cu `kit_*`.
2. Identifică blocurile vizuale repetate → mapează-le pe componente existente
   (vezi `docs/COMPONENTS.md`). Lipsește una? Rețeta 5.1.
3. Copiază secțiunile unice de layout ca HTML în closure-ul `layout()`.
4. Mută culorile/fonturile în `theme.css` ca override de tokens.
5. Randează cu fixtures și compară vizual cu originalul.

### 5.5 Extinde un adaptor (câmp nou din API)
Editează AMBELE `kit/core/adapters/{tenant,marketplace}.php` + default-ul în
`viewmodel.php`. Testează cu un fixture care conține câmpul.

### 5.6 Adaugă o acțiune în proxy (pentru hidratare client)
Editează `$ACTIONS` în `kit/proxy.php`: `'nume' => [VERB, '/endpoint/{param}', ttl]`.
Placeholder-ele `{param}` se umplu din query. Cheam-o din JS cu
`KitProxy('nume', {param: …})`.

---

## 6. Convenții și capcane (IMPORTANT)

- **`*/` în comentarii PHP** închide blocul `/* */` prematur → parse error. Nu
  scrie `_starter-*/` sau alte secvențe cu `*/` în docblock-uri. (Ne-a lovit o
  dată; vezi commit-ul de fundație.)
- **Date fără drift de fus orar:** folosește `vm_date()` (ia partea `Y-m-d`
  literal). `strtotime()` pe un ISO cu `+03:00` mutat în UTC dă ziua greșită.
- **Stil doar în tokens.css.** Componentele nu au culori/fonturi hard-codate și
  nu emit `<style>`. Un template restilează totul din `theme.css`.
- **Escape:** orice text din API trece prin `e()`. Excepție conștientă:
  `$event['description']` e HTML de încredere din API (marcat în cod).
- **Preț în unități majore.** Adaptoarele normalizează la lei (nu bani).
  `kit_price()` formatează.
- **Profil-agnostic în pagini.** O pagină nu trebuie să știe profilul. Dacă
  scrii `if (profile==…)` într-o pagină, refactorizează în data layer/adaptor.

---

## 7. Verificare (fă asta după orice schimbare)

```bash
cd starter-kit
# 1. lint
find kit templates tools -name '*.php' -exec php -l {} \; | grep -v 'No syntax'
# 2. randare offline cu fixtures
export KIT_FIXTURES="$(pwd)/fixtures"
php templates/teatru/pages/repertoire.php  | head
php templates/ambilet/pages/events.php     | head
# 3. preview vizual (server local)
KIT_SITE=teatru KIT_FIXTURES="$(pwd)/fixtures" php -S 127.0.0.1:8899 tools/dev-router.php
#   → http://127.0.0.1:8899/repertoriu , /spectacol/hamlet
```
Fixtures live în `fixtures/` cu numele `<path-cu-__>.json` (ex.
`tenant-client__events.json`). Un lookup singular cade pe fixture-ul de colecție.

---

## 8. Deploy (cum ajunge pe subdomeniu)

Modelul existent: fiecare site e o branch git orfană push-uită la host-ul lui
(cPanel git deploy / webhook `_webhook-deploy.php`). Kit-ul se **vendoruiește**
în folderul deployabil:

```bash
php tools/build.php teatru            # → build/teatru/ conține tot ce trebuie:
#   index.php (front controller), .htaccess, site.config.php, routes.php,
#   includes/, pages/, kit/ (copie), theme/{tokens,theme}.css, api/proxy.php
```
`build/<site>/` este rădăcina site-ului. `.htaccess` servește fișierele reale
(`/theme`, `/kit`, `/api`) direct și trimite restul la `index.php`, care
rutează prin `routes.php` la `pages/<name>.php`.

Integrarea cu `deploy-*.bat` existente: în loc să copieze
`resources/.../<skin>`, copiază `build/<site>/`.

---

## 9. Starea curentă (ce e construit vs. ce urmează)

**Construit și verificat (randează cu fixtures + screenshot):**
- Core complet: config, http (+cache SWR +fixtures +forward Bearer), data,
  view, viewmodel, ambele adaptoare.
- Token contract + `layout('public')` + `layout('account')` (gated pe auth) +
  `kit.js` (cart, auth, ticket-selector, calendar, seat-map hydrate, QR modal,
  account shell, img fallback).
- **26 componente** — vezi `docs/COMPONENTS.md` pentru lista + input-uri.
- Proxy client cu allow-list (public + auth + account, forward Bearer).
- Exemple: teatru (`index`, `repertoire`, `schedule`, `subscriptions`, `show`,
  `cont-index`) + ambilet (`events`) — profile diferite, aceleași componente,
  look complet diferit doar din `theme.css`.
- Generator + build + front controller + `routes.php` — pipeline end-to-end verificat.

**De adăugat (aceeași rețetă, mecanic — nu necesită arhitectură nouă):**
- Portarea completă a paginilor rămase din fiecare skin (auth, cart/checkout,
  restul zonei `cont/*`, listări venue/artist).
- Extinderea `$ACTIONS` din proxy la întreaga suprafață (legacy: ~357 acțiuni
  marketplace, ~40 tenant) — de adăugat pe măsură ce portezi.
- Câteva componente de nișă rămase (ex. `nav-megamenu`), după `docs/COMPONENTS.md`.

**Producerea de template-uri** se face acum după `docs/TEMPLATE-AUTHORING.md`
(spec-ul de pasat sesiunii care face template-uri). Ordinea de continuare a
FRAMEWORK-ului: (1) portează un skin întreg ca dovadă de paritate, (2) extinde
proxy `$ACTIONS`, (3) integrează `build.php` în `deploy-*.bat`.

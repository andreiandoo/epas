# Tenant kinds — al treilea layer

> Peste `profile` (marketplace vs tenant) există `kind`: **sub-tipul unui tenant**.
> Un `kind` este un preset + manifest de capabilități, aplicat AUTOMAT la boot.
> Rezultat: „10 template-uri per tip” împart un `kind` și diferă doar prin
> `theme.css` + copy.

## 1. Cele trei layere

```
profile   marketplace | tenant                 → auth + API base (X-API-Key vs ?tenant=ID)
  kind    teatru | filarmonica | agentie |      → vocabular, meniu, features, URL-uri, set de pagini
          leisure | artist | organizator          (doar pentru profile=tenant)
  site    site.config.php + theme.css           → identitate (tenant_id, nume) + brand
```

**Ordinea de merge la boot** (`Kit::boot`): `defaults` ← `kind` ← `site`.
`terms` și `features` se merge-uiesc adânc (site poate suprascrie un singur
cuvânt/flag fără să rescrie harta). Deci un site poate oricând suprascrie orice
vine din kind.

## 2. Ce aduce un `kind`

Fișier: `kit/kinds/<kind>.php`, întoarce un manifest:

```php
return [
  'profile' => 'tenant',
  'label'   => 'Teatru',
  'terms'   => ['event'=>'spectacol','events_cap'=>'Spectacole','artists_cap'=>'Trupa', ...],
  'features'=> ['seating'=>true,'subscriptions'=>true,'gamification'=>true, ...],
  'event_url_pattern' => '/spectacol/{slug}',
  'menu'    => [ ['key'=>'repertoire','label'=>'Repertoriu','url'=>'/repertoriu'], ... ],
  'cta_label' => 'Cumpără bilete', 'cta_url' => '/program', 'cart_url' => '/cos',
  'pages'   => ['index','repertoire','schedule','subscriptions','troupe','about','show', ...],
];
```

## 3. Helper-e (folosește-le în pagini/componente)

```php
kit_kind()               // 'teatru' | ... | null
kit_feature('seating')   // bool — capabilitatea e activă pentru acest kind?
kit_term('events_cap')   // 'Spectacole' / 'Concerte' / 'Activități' — noun-ul kind-ului
kit_kinds()              // ['teatru'=>'Teatru', ...] — toate kind-urile
```

O pagină generică devine kind-aware fără `if`-uri:
```php
<h1><?= e(kit_term('events_cap', 'Evenimente')) ?></h1>
<?php if (kit_feature('seating')) component('seat-map', ['event'=>$event]);
      else component('ticket-selector', ['event'=>$event]); ?>
```

## 4. Matricea de capabilități (starea curentă)

| kind | event=noun | seating | subscriptions | gamification | reviews | gift_cards | tours | multi_venue | epk | booking | rentals | merch | fan_crm |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| **teatru** | spectacol | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| **filarmonica** | concert | ✓ | ✓ | | ✓ | ✓ | | | | | | | |
| **agentie** | eveniment | | | | ✓ | ✓ | ✓ | ✓ | | | | | |
| **leisure** | activitate | | | | ✓ | ✓ | | | | ✓ | ✓ | | |
| **artist** | concert | | | | | | ✓ | | ✓ | ✓ | | ✓ | ✓ |
| **organizator** | eveniment | | | | ✓ | ✓ | ✓ | ✓ | | | | | |

`features` sunt „switch-uri de capabilitate”. O componentă/pagină kind-specifică
(seat-map pentru teatru, rentals pentru leisure, epk pentru artist) se afișează
condiționat pe `kit_feature(...)`. Setul comun (event listing, single event,
carduri, coș, checkout) e identic pentru toate.

## 5. Cum generezi 10 template-uri pentru un tip

```bash
php tools/create-template.php teatru teatru-sibiu   "Teatrul Radu Stanca"
php tools/create-template.php filarmonica filarm-iasi "Filarmonica Moldova"
php tools/create-template.php leisure    salina-turda "Salina Turda"
php tools/create-template.php artist     the-motans   "The Motans"
```
Fiecare comandă generează un template **complet, funcțional**: toate paginile
tipului (din `manifest['pages']`), `routes.php` derivat automat, meniul,
vocabularul, features și URL-urile — gata. Rămâne să editezi DOAR:
1. `site.config.php` → `tenant_id`, `site_name/city/logo`, `fonts_href`
2. `theme.css` → paleta de brand

Zece teatre = același `kind: teatru`, zece perechi (config, theme) diferite.

### Cum arată paginile generate
Fiecare `pages/<name>.php` e un **wrapper subțire** peste un „pageset" din
`kit/pagesets/`:
```php
<?php
require __DIR__ . '/../includes/bootstrap.php';
$PAGE = ['nav' => 'repertoire'];
require KIT_DIR . '/pagesets/listing.php';
```
Pageset-urile sunt kind-aware (`kit_term`, `kit_feature`), deci același
`listing.php` afișează „Spectacole" pentru teatru și „Activități" pentru leisure,
iar `show.php` alege singur `seat-map` (dacă `feature('seating')`) sau
`ticket-selector`. Vrei să personalizezi o pagină? Înlocuiește wrapper-ul cu o
compoziție proprie de componente — pagesets sunt doar punctul de plecare.

### Pageset-uri disponibile (`kit/pagesets/`)
Publice: `home`, `listing`, `calendar`, `subscriptions`, `artists`, `venues`,
`show`, `cart`, `checkout`, `about`, `contact`, `tours`, `404` + `rentals`
(leisure), `epk`/`music`/`gallery` (artist), `giftcards`.
Comerț/cont (auto-adăugate pentru orice kind `tenant`): `login`, `register`,
`confirmare`, `account-dashboard`, `account-tickets`, `account-orders`,
`account-subscriptions` (dacă `feature('subscriptions')`), `account-giftcards`
(dacă `feature('gift_cards')`), `account-settings`.
Conținut (auto): `page` (legal — `/termeni`, `/confidentialitate` — din API
`/pages/{slug}`); `blog` + `post` dacă `feature('blog')` (din `/blog-articles`).
Pagini de eroare: `error` (403/500/503) + `404`.
Un `kind` mapează numele paginii la pageset în `manifest['pages']`:
`'repertoire' => ['set'=>'listing','nav'=>'repertoire']`.

### Fluxul de cumpărare + contul (end-to-end, live prin proxy)
Generatorul adaugă automat, pentru orice kind `tenant`, paginile de auth,
checkout/confirmare și zona `cont/*` (meniul de cont e feature-gated în
`layout('account')`). Fluxul:
```
show → seat-map (hold/release live + timer 10 min) SAU ticket-selector
     → cart (KitCart, localStorage)
     → /finalizare  checkout: payment-methods + POST proxy 'checkout'
     → /confirmare?order=ID  order-summary + bilete + QR
cont/* : login/register (proxy) → Bearer în localStorage → acc-* hidratate
```
Endpoint-urile din proxy (`kit/proxy.php`, allow-list): `seating/seats/hold/
release/seats-confirm`, `checkout`, `order-summary`, `payment-methods`,
`login/register/me/me-update/logout`, `acc-stats/tickets/orders/subscriptions/
giftcards`, `acc-giftcard-redeem`. Extinde lista pe măsură ce conectezi mai
mult din API.

Trei lucruri care NU sunt evidente din API (verificate contra controllerelor):
- **Seating cere id numeric de eveniment**, nu slug (`SeatingController` tipează
  `int $eventId`), iar hold/release iau `{event_seating_id, seat_uids[]}` — la
  plural. Sesiunea de hold-uri e ținută de proxy printr-un cookie first-party
  retrimis ca `X-Session-Id`; fără el fiecare apel ar cădea în altă sesiune și
  niciun loc n-ar mai putea fi eliberat sau confirmat.
- **Auth-ul tenant nu acceptă `?tenant=ID`.** `TenantClient\AuthController`
  rezolvă tenantul exclusiv din `?hostname=` / `X-Tenant-Domain` (restul
  endpoint-urilor tenant acceptă `?tenant=`). Proxy-ul trimite ambele.
- **`checkout` merge pe `/tenant-client/demo-checkout`** pentru că
  `/tenant-client/checkout/submit` e încă un stub în amonte (nu creează Order —
  vezi TODO-urile din `CheckoutController`). demo-checkout scrie comandă +
  bilete reale și întoarce URL-ul de plată; e ce folosește și skin-ul teatru
  live. Se mută pe endpoint-ul real când acesta e implementat.

### Leisure — fluxul de rezervare (kind `leisure`)

O locație de agrement nu vinde „un eveniment", ci **o zi și un interval**.
Modelul de date reflectă asta: activitatea rămâne un `Event`, dar lucrurile
vandabile sunt **ticket type-uri** care poartă modelul de preț leisure
(variante de durată, reguli pe zi de săptămână, sezoane, depășire).

```
/activitati            listing        → kit_events()
/inchirieri            rentals        → kit_rentals()  → rental-card
/activitate/{slug}     show           → booking-widget (când kit_feature('booking'))
     zi → interval → durată → nr. persoane
     → linie de coș cu capacity_id + visit_date + slot_time + duration_minutes
/cos → /finalizare     checkout       → demo-checkout ține locul sub row lock
/confirmare?order=ID   order-summary
```

Date (server, `kit/core/data.php`):
`kit_bookables($category)` · `kit_availability($ticketTypeId, $month)` ·
`kit_slots($ticketTypeId, $date)` · `kit_rentals()` · `kit_is_leisure()`.

Acțiuni proxy (client, pentru hidratarea calendarului):
`bookables` · `availability` · `slots` · `rentals`.

Backend: `/tenant-client/leisure/{bookables,availability,slots,rentals}`.
Toate întorc **404 dacă tenantul nu e `tenant_type=leisure`**, iar
`kit_is_leisure()` scurtcircuitează înainte de apel, deci un kind non-leisure nu
plătește nimic.

Trei lucruri de reținut:
- **Prețul vine întotdeauna de la server** (`LeisurePricingResolver`): zi de
  săptămână, sezon și multiplicatorul de durată sunt deja aplicate. Un template
  nu recalculează niciodată prețul — doar îl afișează.
- **Nu se ține nimic în timpul navigării.** Spre deosebire de seating (hold de
  10 min), locul leisure se ia abia la checkout, sub row lock, folosind
  `capacity_id` de pe linia de coș. De aceea linia TREBUIE să-l păstreze.
- **Statusurile zilei** sunt `available | limited | sold_out | closed |
  unavailable`. `closed` ≠ `sold_out`: prima înseamnă „în afara programului".

## 6. Cum adaugi un kind nou

1. `kit/kinds/<nume>.php` cu manifestul (profile, label, terms, features, menu,
   url patterns, și `pages` = map `name => ['set'=>pageset,'nav'=>key]`).
2. Refolosește pageset-urile existente pentru paginile comune. Dacă tipul are o
   pagină proprie (ca `rentals`/`epk`), adaug-o în `kit/pagesets/<nume>.php`
   (kind-aware, feature-gated) și referă-o în `pages`.
3. Blocuri vizuale noi → componente în `kit/components/` (rețeta `STARTER-KIT.md §5.1`),
   afișate condiționat pe `kit_feature('...')`.
4. Gata — apare automat în `kit_kinds()`, iar generatorul îi produce toate
   paginile + `routes.php`.

## 7. Reguli

- Un `kind` NU conține secrete/identitate (alea sunt în `site.config.php`).
- Setul COMUN de componente rămâne kind-agnostic; doar zonele specifice se
  gate-uiesc pe `kit_feature()`. Nu duplica o componentă „per kind” dacă diferă
  doar textul — folosește `kit_term()`.
- `marketplace` nu are `kind` (e agregatorul); kind-urile sunt pentru `tenant`.

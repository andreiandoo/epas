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
# 1 dată per tip: kind-ul există deja (kit/kinds/<kind>.php)
php tools/create-template.php teatru teatru-sibiu   "Teatrul Radu Stanca"
php tools/create-template.php teatru teatru-cluj     "Teatrul Național Cluj"
php tools/create-template.php filarmonica filarm-iasi "Filarmonica Moldova"
php tools/create-template.php leisure    salina-turda "Salina Turda"
php tools/create-template.php artist     the-motans   "The Motans"
# ...
```
Fiecare comandă îți dă un template cu meniul, vocabularul, features și URL-urile
tipului deja setate. Rămâne să editezi DOAR:
1. `site.config.php` → `tenant_id`, `site_name/city/logo`, `fonts_href`
2. `theme.css` → paleta de brand
3. paginile recomandate ale kind-ului (din `manifest['pages']`) — compuse din
   componente, după blueprint-urile din `TEMPLATE-AUTHORING.md §4`.

Zece teatre = același `kind: teatru`, zece perechi (config, theme) diferite.

## 6. Cum adaugi un kind nou

1. `kit/kinds/<nume>.php` cu manifestul (profile, label, terms, features, menu,
   url patterns, pages).
2. Dacă are pagini/blocuri proprii (ex. leisure „rentals”, artist „epk”), adaugă
   componentele lor în `kit/components/` (rețeta din `STARTER-KIT.md §5.1`) și
   afișează-le condiționat pe `kit_feature('...')`.
3. Gata — apare automat în `kit_kinds()` și în generator.

## 7. Reguli

- Un `kind` NU conține secrete/identitate (alea sunt în `site.config.php`).
- Setul COMUN de componente rămâne kind-agnostic; doar zonele specifice se
  gate-uiesc pe `kit_feature()`. Nu duplica o componentă „per kind” dacă diferă
  doar textul — folosește `kit_term()`.
- `marketplace` nu are `kind` (e agregatorul); kind-urile sunt pentru `tenant`.

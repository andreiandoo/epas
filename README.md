# Site-uri de tenant găzduite de Tixello

Un singur cod PHP servește site-urile tuturor tenanților fără domeniu sau fără
site propriu: `{nume}.tics.ro` și, mai târziu, `*.bilete-teatru.ro`, `*.cautbilet.ro`
și `*.teatru-copii.ro`. Site-ul află tenantul după host, cerând configurația de la
core (`GET /api/tenant-site/config?host=`). Template-ul și personalizarea se aleg
în core, la **Tenants → editare → Domains & Deployment → „Site (template)”**.

```
public/index.php          punctul de intrare (nginx: try_files … /index.php)
app/                      nucleul: config, client API, host → site, temă, router
shared/                   pagini comune tuturor template-urilor
  pages/                  coș, checkout, confirmare, autentificare, înregistrare, legale
  cont/                   contul clientului
  api/proxy.php           proxy browser → core (locuri, login, checkout, cont)
templates/{slug}/         câte un template
  template.json           manifestul: tema, rutele, meniul, paginile, secțiunile
  layout/                 head.php, header.php, footer.php
  pages/                  paginile fixe ale template-ului (program, spectacol …)
  sections/               câte un fișier per tip de secțiune
```

## Cum ajunge pe server

Un push pe `core` care atinge acest folder declanșează `.github/workflows/deploy-tenant-sites.yml`.
Workflow-ul copiază folderul în branch-ul `tenant-sites`, iar Ploi îl trage cu Quick deploy.

### Pregătirea serverului (o singură dată, pe domeniu)

1. **Cloudflare**, în zona domeniului: o înregistrare `A`, nume `*`, IP `89.44.137.26`, cu proxy activ.
   Înregistrările existente (`tics.ro`, `www`, `app`) au prioritate față de wildcard și rămân neatinse.
2. **Cloudflare → SSL/TLS**:
   - Origin Server → creează un certificat Origin pentru `*.tics.ro` și `tics.ro`;
   - modul SSL al zonei trebuie să fie Full (strict).
3. **Ploi → site nou** (de exemplu `sites.tics.ro`):
   - repository `andreiandoo/epas`, branch `tenant-sites`, web directory `/public`;
   - Quick deploy activ, fără comenzi de deploy.
4. **Ploi → nginx-ul site-ului**: `server_name sites.tics.ro *.tics.ro;`.
   Blocurile existente pentru `tics.ro` și `app.tics.ro` au nume exacte, deci câștigă în fața wildcard-ului.
5. **Ploi → SSL → certificat propriu**: lipește certificatul și cheia Origin de la pasul 2.
6. **Folderul `cache/`** de lângă `public/` trebuie să poată fi scris de userul PHP-FPM al site-ului.
   Se creează singur la prima cerere.
7. **În `.env` pe core**, pentru domeniile pregătite: `TENANT_SITES_TICS_ACTIVE=true` (implicit true).
   Celelalte domenii: `TENANT_SITES_CAUTBILET_ACTIVE=true` etc. Apoi `php artisan config:cache`.

Pentru alt domeniu se repetă pașii 1, 2, 4 (adaugi `*.domeniu.ro` la `server_name`) și 5.

### Configurare locală

`config.local.php`, lângă `public/`, nu e în git:

```php
<?php
define('API_BASE', 'https://stage.tixello.com/api');
define('DEBUG', true);
```

Test local: `php -S 127.0.0.1:8094 -t public public/index.php`, cu header-ul `Host` al unui domeniu de tenant.

## Cum scrii un template

1. Copiază `templates/teatru` în `templates/{slug-nou}`.
2. Editează `template.json`:
   - `name`, `description`: apar în core la alegerea template-ului.
   - `theme.colors` / `theme.fonts`: valorile implicite ale celor 7 culori (`canvas`, `surface`,
     `panel`, `fg`, `muted`, `primary`, `accent`) și ale fonturilor (`display`, `body`).
   - `routes`: paginile fixe ale template-ului, `"/spectacol/{slug}": "show.php"` (din `pages/`).
     Parametrii `{nume}` ajung în `$_GET`.
   - `links`: adresele template-ului pentru adresele standard pe care le folosesc paginile comune:
     `events`, `catalog`, `event`, `subscriptions`, `artists`, `artist`, `contact`.
     Standardul e `/program`, `/repertoriu`, `/spectacol/{slug}`, `/abonamente`, `/trupa`, `/artist/{id}`, `/contact`.
     Dacă template-ul folosește altă adresă, cea standard se redirecționează (301) spre ea.
   - `nav`, `header_cta`: meniul implicit (editabil în core).
   - `pages`: paginile construite din secțiuni (`path`, `title`, `sections` implicite).
     Core le arată ca tab-uri, cu un editor de secțiuni.
   - `sections`: tipurile de secțiuni și câmpurile lor. Core generează formularele din ele.
     Tipurile de câmp: `text`, `textarea`, `toggle`, `number`, `select` (`options`), `image`
     (încărcare sau link), `repeater` (`fields`, `max`).
3. **Fiecare secțiune** este `sections/{tip}.php` și primește datele în `$s`.
   Imaginile sosesc ca URL-uri complete.
   - `ts_md()` pentru texte scurte: `*italic*`, `**evidențiat**`, rânduri noi;
   - `ts_paragraphs()` pentru texte lungi;
   - `ts_href()` pentru linkuri;
   - `ts_link('event', ['slug' => …])` pentru paginile standard.
4. **Layout-ul** trebuie:
   - să cheme `ts_head_assets()` în `<head>`: aduce Tailwind, Alpine, fonturile și variabilele temei;
   - să folosească `site('nav')`, `site('header_cta')`, `site('footer.*')`, `SITE_NAME`,
     `SITE_LOGO_TEXT`, `SITE_CITY` și `site('branding.logo')`;
   - să afișeze banda de previzualizare când `SITE_PREVIEW` e true.
5. **Culorile se scriu doar prin token-uri**, niciodată fixe:
   - clase: `bg-canvas`, `bg-surface`, `text-fg/70`, `text-muted`, `bg-primary`, `text-on-primary`,
     `border-primary/20`, `from-accent`, `text-primary-light`, `bg-accent-dark`;
   - fonturi: `font-display`, `font-body`;
   - în CSS: `rgb(var(--c-primary) / .3)`, `var(--f-display)`.
6. **Paginile comune** (`shared/`) folosesc layout-ul template-ului pentru coș, checkout și
   autentificare, plus clasele `btn-primary`, `btn-outline`, `btn-accent`, `show-card`, `divider-ornate`,
   definite în `app/theme.php`. Un template le poate redefini în `<head>`, după `ts_head_assets()`.

7. **Câmpurile de catalog din manifest:**
   - `category`: grupa din select și din galerie;
   - `kind`: `events` / `leisure` / `competition`;
   - `status`: `draft` (nu apare în select) / `beta` / `ready`;
   - `visibility`: `public` sau `private` (template custom pentru un client, exclusiv cât îl folosește),
     cu `"tenants": [id, …]` = cine îl poate alege. Template-urile custom stau în `templates/custom-{client}`;
   - `preview_image`: fișier din `assets/`, folosit în galerie.
8. **Animațiile:** `"motion": ["lenis", "gsap"]` încarcă smooth scroll-ul și GSAP ScrollTrigger (versiuni
   fixate în `app/theme.php`), plus `public/assets/motion/ts-motion.js`. În markup:
   - `data-reveal` (sau `="left|right|scale|fade"`), `data-reveal-delay`;
   - `data-stagger`: copiii apar pe rând;
   - `data-parallax="0.15"`.

   Pe coș, checkout și cont nu se încarcă niciodată. Cu `prefers-reduced-motion` totul rămâne static.
   Resursele de UI permise: `plans/mds/UI_RESOURCES.md`.
9. **Fișierele proprii** (imagini, modele 3D, JS, fonturi) stau în `templates/{slug}/assets/` și se
   folosesc cu `ts_asset('img/hero.webp')`. La deploy ajung în `public/tpl/{slug}/`.

## Demo-uri live

În core, la **Tenants → Demo-uri site**, alegi template-ul, tenantul demo (datele) și adresa
`demo-{slug}.tics.ro`. Core creează domeniul și pornește checkout-ul demo pe tenant. Galeria cu toate
demo-urile listate e pe `demo.tics.ro`. Pe pagina „Site” a unui tenant apare „Vezi demo live”
lângă template.

Ce se schimbă per tenant fără cod:
- identitatea;
- culorile și fonturile;
- meniul și subsolul;
- secțiunile fiecărei pagini (care apar, în ce ordine, cu ce conținut).

Coșul, checkout-ul, confirmarea și contul clientului sunt aceleași pentru toți. Diferă doar culorile și fonturile.

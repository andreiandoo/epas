# bilete.online → GetYourGuide-style — Plan de dezvoltare

Obiectiv: aducem bilete.online cât mai aproape de getyourguide.com (descoperire, atracții, interese, traveler types, proximitate, review-uri, pagini-destinație, pagină operator), strict **non-breaking** și **gated** printr-un microserviciu nou `discovery-module` (ca `activities-module`).

Core-ul Laravel e în `epas/` (root). Marketplace standalone PHP în `epas/resources/marketplaces/bileteonline/`, care lovește core-ul prin `api/proxy.php` → `API_BASE_URL`.

## Ce există deja (fundație ~60%)
- Activități `/activitate/{slug}` cu JSON-LD, galerie, variante preț, FAQ, politică anulare, hartă (venue lat/lng), „related", „similare în oraș".
- Ghiduri `ghid.php` cu activități legate ca recomandări.
- Review-uri customer (`customer.reviews`) — dar pe evenimente și NEafișate pe pagina de activitate.
- Wishlist/favorite/recomandări. Profil operator `public.php`. Categorii/orașe/locații/regiuni.

## Gap-uri (ce construim)
- A. Atracții + tipuri de atracții (POI + taxonomie, legate de activități, landing pages)
- B. Interese (taxonomie tematică) + landing pages
- C. Traveler types (cupluri/familii/solo/grupuri) — badge + filtru + landing
- D. Motor Nearby (proximitate pe coordonate, Haversine)
- E. Review-uri pe pagina de activitate (agregat + listă)
- F. UX descoperire GYG (search facetat, pagini-destinație, badge-uri trust, calendar disponibilitate)

## Model de date nou (core)
Tabele: `attractions`, `attraction_types`, `interests`, `traveler_types` + pivots `activity_attraction`, `activity_interest`, `activity_traveler_type` (+ opțional `attraction_interest`). Coloane noi `latitude`/`longitude` pe `activities` + `attractions` (venue le are deja). Review polimorf atașabil la activități cu „verified purchase".

## Roadmap pe faze (ordine ROI/efort)
- **F0** — confirmare schemă core + microserviciu `discovery-module`.
- **F1** — Reviews pe activitate (agregat + endpoint + secțiune pe pagină). + implementare template v4 GYG single-activity.
- **F2** — Nearby (lat/lng pe activities + endpoint Haversine + secțiuni „în apropiere").
- **F3** — Interese + Traveler types (taxonomii + Filament + filtre + landing).
- **F4** — Atracții (entitate + tipuri + Filament + pagini + legare activități).
- **F5** — Pagini-destinație + search facetat + badge-uri trust.
- **F6** — Enrich ghiduri (rânduri activități random/pin) + pagină operator GYG-style.

## Împărțire
- Andrei: look/templating, ajustări vizuale, date de pe live la cerere.
- Claude: model de date, backend (migrări/modele/Filament/API), proxy + wiring template + JS.

## Status
- [x] F0 — schemă confirmată; microserviciu `discovery-module` creat (seeder auto-activează client 3).
- [x] F1 — Reviews pe activitate (agregat + endpoint + secțiune) + template v4 GYG single-activity (`single-activitate.php`, slug `/{oras}/{slug}`).
- [x] F2 — Nearby: `latitude`/`longitude` pe `activities` (backfill din venue) + Haversine în `detailPayload` (`activity.nearby`) + rail „În apropiere" cu distanță reală.
- [x] F3 — Interese + Traveler types: tabele + pivots + modele + API (payload + filtre `?interests=`/`?traveler_types=`) + Filament (Interest/TravelerType resources + multiselect pe Activitate) + filtre GYG pe `category.php` + badge-uri pe single-activity. Seeder `DiscoveryTaxonomiesSeeder`.
- [x] F4 — Atracții: `attractions` + `attraction_types` + pivot + modele + `AttractionsController` (`/attractions`, `/attractions/{slug}`) + Filament (Attraction/AttractionType) + secțiune „Atracții" pe `city.php` + „Atracții asociate" pe single-activity + landing `atractie.php` (`/atractie/{slug}`).
- [ ] F5 — Pagini-destinație + search facetat + badge-uri trust.
- [ ] F6 — Enrich ghiduri + pagină operator GYG-style.

### Deploy (de rulat pe core)
```bash
php artisan migrate
php artisan db:seed --class=DiscoveryModuleMicroserviceSeeder   # creează microserviciul + activează pt client 3
php artisan db:seed --class=DiscoveryTaxonomiesSeeder           # interese + traveler types + tipuri atracții
php artisan optimize:clear && sudo service php8.3-fpm reload
# apoi deploy-bilete.bat pentru template-urile frontend
```
Notă: gating-ul Filament (Interese/Traveler/Atracții) e prin `discovery-module`; resursele apar doar după activarea microserviciului (seeder-ul o face pentru client 3).

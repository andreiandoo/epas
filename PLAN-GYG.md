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
- [ ] F0
- [ ] F1 (în lucru)
- [ ] F2 — F6

# Reduceri la cantitate (`ticket_types.bulk_discounts`) — analiză & plan

Data: 2026-09-11. Analiză read-only, fără modificări de cod. Referințele `Fișier:linie` sunt la momentul analizei.

## 1. Starea actuală

- **Stocare:** `ticket_types.bulk_discounts` JSON (migrația `2025_11_01_000500_ticket_type_bulk_discounts_and_extras.php`). În `TicketType` e fillable și cast `array`. Nu are validare sau normalizare.
- **Configurare:** 3 formulare Filament cu aceeași schemă:
  - Marketplace `EventResource.php:4055-4125`;
  - Tenant `EventResource.php:1252-1275`;
  - Core `Resources/Events/EventResource.php:519-528`.

  Panoul organizatorului **nu** o expune.
- **ambilet.ro / bilete.online: zero efect pentru toate cele 4 reguli.**
  - API-ul public nu trimite regulile (`MarketplaceEventsController.php:~1146-1177`).
  - JS-ul nu calculează nimic.
  - Checkout-ul calculează prețul ca preț × cantitate (`CheckoutController.php:~497-504`).
- **Singura implementare** e fluxul tenant-client (`Router.ts` + `TenantClient/OrderController.php:105-265`), cu bug-uri (vezi §3).

| rule_type | Etichetă | Câmpuri | Tenant-client |
|---|---|---|---|
| `buy_x_get_y` | Cumperi X primești Y gratis | `buy_qty`, `get_qty` | grupare greșită (B5) |
| `buy_x_percent_off` | Cumperi X bilete → % reducere | `min_qty`, `percent_off` | nu se declanșează niciodată (B2) |
| `amount_off_per_ticket` | Reducere pe bilet (min cantitate) | `min_qty`, `amount_off` (lei) | de 100× prea mică (B4) |
| `bundle_price` | Preț pachet (X bilete la preț total) | `min_qty`, `bundle_total_price` | lipsă (B3) |

Probleme ale formularului:
- Câmpurile regulii nu sunt `required`. O regulă salvată cu `buy_qty` gol duce la împărțire la zero.
- Lipsesc validările încrucișate:
  - pachet ≤ N × preț;
  - `amount_off` < preț;
  - pragul ≤ `max_per_order` (implicit 10).
- Secțiunea apare și la evenimentele de tip leisure.

## 2. Ce atinge (unde se reflectă)

**Reprezentare actuală**
- `tickets.price` = prețul unitar la vânzare, înainte de codul promo; prețul redus e deja inclus.
- `tickets.meta.discount_amount` = partea de cod promo care revine biletului.
- `order.discount_amount` = doar codul promo.
- `Ticket::getEffectivePrice()` = preț − `meta.discount_amount`. Îl folosesc PDF-ul biletului, emailul, dashboardul organizatorului, exporturile și plățile.

**Bilete cu / fără loc pe hartă**
- Checkout-ul cere `count(seat_uids) === quantity`.
- La „cumperi X primești Y”, clientul trebuie să aleagă N bilete și N locuri. Biletele adăugate automat n-ar avea loc.

**Decont (`SalesBreakdownService`)**
- Pe grupa (comandă × tip) calculează prețul primului bilet × număr. Rezultatul e greșit dacă prețurile diferă în grupă (B10). Cu `splitByPrice=true` e corect.
- Pentru prețuri ≤ 0 există o rezervă care ia prețul de listă. Soluția propusă fie evită biletele de 0 lei, fie condiționează rezerva.
- Reducerea care nu e procentuală se împarte egal pe toate biletele comenzii (B11). Ăsta e motivul să **nu** trecem reducerea la cantitate în `order.discount_amount`.
- `buildPayoutSplitTable` etichetează „(redus)” doar la codurile promo, deci are nevoie de un marcaj „(cantitate)”.

**Coduri promo**
- Baza codului promo e `processedItems[].total`. Dacă reducerea la cantitate intră în `itemTotal`, promo se aplică automat peste prețul după reducere.
- Frontend-ul trimite totaluri client la validarea codului promo, deci trebuie să trimită totalurile după reducere.
- Biletele gratuite cu cod și invitațiile trebuie excluse mereu.

**Comision la checkout**
- Se calculează per item, pe `$unitPrice`. Trebuie trecut per bilet, pe prețul efectiv.
- Minimul de comision al organizatorului e aplicat doar biletelor cu preț > 0. Riscul: la reduceri mari, minimul poate depăși valoarea biletului.

**Documente fiscale**
- SeriesAllocator nu are nivel pentru reducerea la cantitate: prețurile nu pot fi pre-declarate în cererea de avizare.
- Declarația, pagina 2: rândurile non-reduse ale aceluiași tip pornesc toate de la `series_start`, deci seriile se suprapun (B14, de verificat pe date).
- Valoarea din `declaratie_impozite` = prețul de catalog curent × număr − Σ reduceri. Ignoră prețul real plătit (B13).
- PV-ul de distrugere are nevoie de prețul unitar al nivelului.

**Rambursări**
- Se folosește `ticket.price` cu raportul de reducere la nivel de comandă (B12).
- Dacă reducerea la cantitate stă în `tickets.price`, suma rambursată e automat corectă.
- Politica pentru ruperea unui pachet e decizia Q11.

**Rapoarte, analytics, extra**
- `SalesReportService`, dashboardul organizatorului, rapoartele leisure și FB CAPI urmează automat `tickets.price`.
- Asigurarea și suplimentul pentru card cultural sunt calculate de client și acceptate ca atare de server (B19). Baza lor trebuie să devină prețul după reducere.

**Stoc / min-max**
- Stocul crește atomic cu cantitatea.
- Un prag peste `max_per_order` nu poate fi atins; pasul `multiplier` poate sări pragul.
- Serverul nu validează min/max per comandă online.

**Facturare (SmartBill / Oblio / leisure):** sursa prețului nu e verificată.

## 3. Bug-uri găsite

| # | Sev. | Constatare |
|---|---|---|
| B1 | Mare | Regulile configurate în admin nu au niciun efect pe marketplace. |
| B2 | Mare | Tenant: formularul salvează `buy_x_percent_off`, codul verifică `percent_off`. |
| B3 | Medie | `bundle_price` nu e implementat nicăieri. |
| B4 | Mare | Tenant: `amount_off` e în lei, dar e scăzut din bani (cenți), deci de 100× prea mic. |
| B5 | Mare | Tenant: „buy X get Y” = ⌊qty/X⌋×Y din aceeași cantitate; poate da total negativ. |
| B6 | Medie | Câmpurile regulii nu sunt required, deci crash. |
| B7 | Medie | Tenant: biletele se creează fără preț; `discount_amount` e luat de la client. |
| B8 | Medie | Actualizarea din panoul organizatorului șterge și recreează tipurile de bilet, deci pierde `bulk_discounts` și câmpurile de admin. |
| B10 | Medie | Decont: preț primul bilet × număr pe grupă (când `splitByPrice=false`). |
| B11 | Medie | Codul promo fix se alocă egal pe toate biletele comenzii. |
| B12 | Medie | Rambursarea folosește raportul de reducere pe comandă, nu `meta.discount_amount`. |
| B13 | Medie | Valoarea de impozit = catalog curent × număr, nu prețul plătit. |
| B14 | Medie | Declarația pag. 2: serii suprapuse la același tip vândut la 2 prețuri (de verificat). |
| B15 | Mică | SeriesAllocator nu calculează `qty_sold` pentru nivelul RED. |
| B16 | **Mare** | POS Tixello încasa `$tt->price` = accessor de preț redus, deci 0 lei fără reducere activă. **REPARAT 2026-09-11.** |
| B17 | Medie | `sale_stock_sold` nu e incrementat nicăieri; plafonul „Stoc reducere” nu e aplicat. |
| B18 | Medie | Checkout-ul online nu aplică prețul suprascris pe reprezentație; coșul și POS-ul îl aplică. |
| B19 | Mică | Asigurarea și suplimentul card cultural sunt acceptate de la client. |

## 4. Design recomandat

- **Sursă unică:** `app/Services/Pricing/BulkDiscountCalculator.php`, un serviciu PHP pur, cu:
  - `normalize()` (aliasul `percent_off` → `buy_x_percent_off`);
  - `validate()` pentru formulare;
  - `quote(rules, unitPrice, qty)` → `line_total`, `per_ticket_prices[]` (în bani, suma exactă), `discount_total` și regula aplicată.

  Îl folosesc checkout-ul (autoritar), `summary()`, `CartController`, API-ul public și tenant-client. JS-ul oglindește calculul doar pentru afișare, cu vectori de test comuni PHP/JS.
- **Reprezentare:** reducerea la cantitate devine **prețul unitar al biletului**, la fel ca prețul redus de azi. Codul promo rămâne reducere peste el.
  - `tickets.price` = prețul după reducerea la cantitate;
  - `tickets.meta.bulk` = `{rule_type, list_price, discount, group}`;
  - `order_items.unit_price` = prețul de listă, iar `order_items.total` = totalul după reducere;
  - `order.subtotal` = suma după reducere;
  - `order.discount_amount` = doar codul promo;
  - `order.meta.bulk_discount_total` = reducerea la cantitate pe comandă.
- **Împărțirea prețului:** în fiecare grupă calificată, toate biletele primesc același preț, iar restul rămân la preț întreg. Exemple:
  - 3+1 la 100 lei, 7 bilete: 4×75 + 3×100;
  - pachet „4 la 300”, 6 bilete: 4×75 + 2×100.

  Fără bilete plătite de 0 lei.
- **Schimbări în aval, condiționate de `meta.bulk`** (istoricul rămâne identic):
  - decont cu preț și comision per bilet (B10);
  - split table cu marcajul „(cantitate)” / cod `CANTITATE`;
  - nivel SeriesAllocator `discount_source='bulk'` pentru fiecare preț;
  - căutare de preț pentru cerere, PV și declarație;
  - impozite din Σ prețul efectiv (B13).

## 5. Decizii (implicit recomandat în **bold**)

1. Q1 „Cumperi X primești Y”: **clientul alege N bilete/locuri; din fiecare grupă X+Y, Y sunt gratuite** / se adaugă automat Y.
2. Q2 Valoarea gratuitului: **împărțită egal în grupă** / bilete explicite de 0 lei.
3. Q3 Pachet: **pachete repetate de exact `min_qty`, restul la preț întreg** / preț T/N pe toate biletele de la N în sus.
4. Q4 Procent / sumă: **de la prag, pe toate biletele liniei** / doar peste prag.
5. Q5 Mai multe reguli: **cea mai bună pentru client, fără cumul** / cumul.
6. Q6 Ce se numără la prag: **tipul de bilet × reprezentație, în comandă** / pe tip, peste reprezentații.
7. Q7 Preț redus activ: **reducerea la cantitate se aplică peste prețul efectiv** / doar peste prețul de listă / dezactivată cât e reducere.
8. Q8 Cod promo: **cumulabil, calculat pe totalul după reducere** / exclusiv / flag „cumulabil” per cod.
9. Q9 Baza comisionului: **prețul după reducere; minimul per bilet plătit ca azi** / prețul de listă. Plus: plafonăm minimul la prețul efectiv?
10. Q10 Fiscal: **nivel de serie propriu (…-CANT-…) pre-declarat în avizare, rânduri separate în declarație/PV** / aceeași serie. **Necesită contabil.**
11. Q11 Rambursare parțială din pachet: **se rambursează cât s-a plătit pe acel bilet** / recalculare și recuperare / doar grupe întregi.
12. Q12 Canale: **doar online (ambilet, bilete.online, tenant) în v1; fără POS / leisure** / toate.
13. Q13 Cine configurează: **doar adminul în v1** / și organizatorul (necesită fix B8).
14. Q14 Excluderi: **invitații, bilete gratuite cu cod, comenzi de test**.
15. Q15 Afișare: **badge pe card, „Economisești X lei” live, preț de listă tăiat pe PDF/email**.
16. Q16 Lansare: **flag per marketplace, oprit implicit; pilot pe un eveniment**.
17. Q17 Baza asigurării procentuale: **suma după reducere, recalculată pe server**.
18. Q18 Prag peste `max_per_order` sau imposibil de atins cu `multiplier`: **blocare la salvare** / avertisment.

## 6. Plan

| # | Pas | Risc | Efort |
|---|---|---|---|
| 0 | Decizii Q1–Q18 (+ contabil pentru Q10) | — | — |
| 1 | `BulkDiscountCalculator` + teste unitare + vectori comuni | mic | 1 z |
| 2 | Formulare: required, integer, validare prin calculator, ascuns la leisure, flag marketplace | mic | 0,5 z |
| 3 | API public: `bulk_discounts` normalizate (când flag-ul e activ) | mic | 0,25 z |
| 4 | Checkout autoritar: preț per bilet, comision per bilet, meta, `summary()`, `CartController` (decizie B18 întâi) | **mare** (flag) | 1,5 z |
| 5 | Frontend ambilet + bilete.online: badge, totaluri live, coș, drawer, checkout, bază asigurare | mediu | 2 z |
| 6 | Decont: preț / comision per bilet la grupele cu `meta.bulk`, marcaj în split table, PDF | **mare** (teste snapshot) | 1 z |
| 7 | Fiscal: nivel SeriesAllocator, cerere / PV / declarație, impozite din Σ efectiv | **mare** (legal) | 1,5 z |
| 8 | Rambursări: politica Q11 (+ opțional fix B12) | mediu | 0,5–1 z |
| 9 | Rapoarte / exporturi / FB CAPI | mic | 0,5 z |
| 10 | Tenant-client: calculatorul pe server + reparat `Router.ts` | mediu | 1 z |
| 11 | (Q12) POS / leisure | mediu | 1 z |
| 12 | (Q13) Panou organizator + fix B8 | mediu | 1 z |

Total ≈ 8–11 zile pentru pașii 1–10 (+2 zile cu pașii 11–12).

Test-cheie: evenimentele fără reducere la cantitate trebuie să producă deconturi și documente fiscale **identice** înainte și după (snapshot).

### Vectori de test (preț 100)

**buy_x_get_y 3+1:**
- 3 → 300;
- 4 → 300 (4×75);
- 7 → 600;
- X=1, Y=2 cu 1 bilet → 100 (niciodată negativ);
- X=2, Y=1 cu 3 bilete → 200 (3×66,67, suma exactă).

**percent (min 5, 20%):**
- 4 → 400;
- 5 → 400 (5×80);
- cu cod promo de 10% → 360.

**amount_off (min 4, 15 lei):**
- 3 → 300;
- 4 → 340 (în lei, nu bani).

**bundle (4 la 300):**
- 3 → 300;
- 4 → 300;
- 6 → 500;
- 8 → 600.

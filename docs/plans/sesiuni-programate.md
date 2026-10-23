# Plan — Sesiuni programate: muzee, tururi ghidate, cinema

Vânzare de bilete pe **zi + interval orar**, cu capacitate pe slot și pe tip de bilet,
validare la scanare în fereastra corectă, și extensie până la cinema (mai multe săli,
filme în paralel, hartă de locuri per proiecție).

Document de proiectare. Nu conține cod scris încă — descrie ce se construiește, în ce
ordine, și de ce fiecare alegere e făcută așa.

---

## 1. Rezumat executiv

Cerința e, în esență, una singură: **unitatea vândută nu mai e „evenimentul", ci o
sesiune cu oră de început, capacitate proprie și fereastră de valabilitate.**

Un slot de muzeu la 10:00 și o proiecție de cinema la 21:30 sunt același lucru: o
sesiune rezervabilă. Diferă doar cum apar — muzeul le generează dintr-un program
recurent, cinematograful le creează una câte una, pe film și pe sală.

Repo-ul are deja jumătate din acest sistem, construit de două ori, în două locuri
diferite, ambele parțiale (§2). Planul **nu adaugă un al treilea**: promovează
`performances` — tabelul care deja e legat de bilete, comenzi, hărți de locuri și
rezervări de locuri — la rangul de entitate universală de sesiune, și adaugă în jurul
lui ce lipsește: un generator de recurență, o matrice de capacitate sesiune × tip de
bilet, ferestre de validitate pe bilet, și validare temporală la scanare.

Auditul a mai scos la iveală un defect care trebuie reparat înainte de orice
funcționalitate nouă, pentru că e deja în producție (§12.1): pe marketplace, capacitatea
pe slot se afișează dar **nu se consumă niciodată** la vânzare.

Onboardingul unui muzeu sau cinema **rămâne deliberat în mâna marketplace-ului** — nu e
self-service. Ce lipsește nu e un wizard pentru organizator, ci un flux de creare
decent pentru admin, în afara formularului de 6297 de linii (§12.2). După creare,
organizatorul își operează singur programul — precedentul există deja și funcționează
(`updateVenueConfig`).

**Ce lipsește azi, concret:**

| Cerință | Stare |
|---|---|
| Client alege zi din calendar | Parțial — există calendar, dar pe produs, nu pe eveniment |
| Client alege apoi slot orar | Invers — slotul e ascuns *în interiorul* unui produs, ales după |
| Client alege apoi tipurile de bilet | Nu — tipurile se aleg înaintea slotului |
| Bilet afișează data și ora la scanare | Nu — data stă în `tickets.meta`, nu ajunge pe dispozitiv |
| Bilet validat doar în data/segmentul lui | **Nu, deloc** — un bilet de 12 iulie ora 10:00 scanează verde pe 3 august la 19:00 |
| Capacitate pe interval orar | Modelată, dar globală pe slot — și neaplicată la vânzare |
| Capacitate pe tip de bilet, în cadrul slotului | Nu |
| Program tot anul / doar o perioadă | Da (`venue_config.seasons`), dar necalificat pe sesiuni |
| Cinema: săli, filme paralele, hartă/proiecție | Nu — modelul există, dar API-ul de locuri ignoră proiecția |
| Un admin de marketplace poate crea rezonabil o astfel de locație | **Cu greu** — doar prin formularul de 6297 de linii, bifând manual câmpuri ascunse |
| Capacitatea pe slot chiar se consumă la vânzare | **Nu pe marketplace** — `SlotBookingService::reserve()` nu are niciun apelant (pe tenant, da) |

---

## 2. Audit — ce există deja

Nimic din ce urmează nu se aruncă. Contează să știm exact peste ce construim.

### 2.1 Două lumi de evenimente

- **`Event`** (`events`) — folosit și de tenanți, și de marketplace (prin
  `marketplace_client_id` + `marketplace_organizer_id`). Ăsta e cel real, cel pe care
  rulează ambilet.
- **`MarketplaceEvent`** (`marketplace_events`) — model paralel, mai vechi.
  `MarketplaceEventDateCapacity` atârnă de el, dar
  `DateAvailabilityController` (`app/Http/Controllers/Api/MarketplaceClient/DateAvailabilityController.php:29`)
  interoghează `Event`, nu `MarketplaceEvent`. Adică tabelul de capacități pe zi
  **nu e pe calea fierbinte**.

> Planul se construiește exclusiv peste `Event`. `MarketplaceEvent` nu se atinge.

### 2.2 „Leisure venue" — prima implementare (marketplace)

- `Event.display_template = 'leisure_venue'` + `Event.venue_config` (JSON):
  `seasons[]` (`name`, `start` MM-DD, `end` MM-DD, `schedule` pe zi a săptămânii cu
  `{open, close}`, `last_entry`), `closed_dates[]`, `pricing_rules[]`.
- `app/Concerns/IsLeisureVenue.php` — `getSeasonForDate()`, `isDateOpen()`,
  `getOperatingHours()`, `isPastLastEntry()`, `getEffectivePrice()`. Gestionează
  corect sezoanele cu wrap-around (nov→mar).
- Sloturile trăiesc în `ticket_types.meta`, în **două forme concurente**:
  - `meta.slots_config` = `{enabled, first_slot, last_slot, interval_minutes, duration_minutes, capacity_per_slot, unit_pricing}`
  - `meta.has_tour_slots` + `meta.slot_times[]` + `meta.max_per_slot` (varianta mai veche)
- `leisure_slot_bookings` + `App\Services\Leisure\SlotBookingService` — contorizare
  per slot, cu `UNIQUE(event_id, ticket_type_id, visit_date, slot_time)` și
  `lockForUpdate`. Serviciul e corect scris și gata de concurență, **dar
  `reserve()` nu are niciun apelant** — se folosește doar `getBookingsMap()`, la
  citire. Vezi B-1 în §12.1: capacitatea se afișează, nu se consumă.
- `GET /events/{identifier}/date-availability?date=|month=` — calendarul public.
- Coșul și checkout-ul poartă `visit_date` și `tour_slot_time` prin item, apoi în
  `tickets.meta`.

### 2.3 „Leisure tenant" — a doua implementare, paralelă

Aceleași concepte, altă modelare: `ticket_types.leisure_seasons`,
`leisure_schedule_open_time/close_time/days`, `leisure_slot_duration_minutes`,
`App\Models\Leisure\TicketTypeCapacity` (`capacity_date`, `time_slot_start/end`,
`capacity/sold/reserved`), `App\Services\Leisure\CapacityAvailabilityService`.

Din `TicketTypeCapacity` merită păstrat modelul de disponibilitate:
`remaining = capacity − sold − reserved` plus o etichetă grosieră
(`closed|sold_out|limited|available`). Îl reluăm în §5.

### 2.4 `performances` — sesiunea, deja pe jumătate construită

Ăsta e descoperitul important. `performances` are deja:

- `event_id`, `season_id`, `starts_at`, `ends_at`, `door_time`, `status`, `label`,
  `ticket_overrides` (JSON: `[{ticket_type_id, price_cents, quota}]`),
  `capacity_override`.
- **Legături deja existente în toată aplicația:**
  - `tickets.performance_id` (`app/Models/Ticket.php:14`)
  - `order_items.performance_id`
  - `event_seating_layouts.performance_id` — hartă de locuri **per proiecție**
  - `seat_holds.performance_id`
  - (migrația `2026_03_24_100000_add_performance_id_to_order_items_and_seating.php`)
- **Expus deja public:** `MarketplaceEventsController.php:999` returnează
  `performances[]` cu `date`, `start_time`, `end_time`, `door_time`, `label`,
  `ticket_overrides` mapate pe preț și cotă.
- **Acceptat deja în coș:** `CartController.php:55` validează `performance_id`,
  aplică `Performance::getEffectivePrice()` și cheia de coș devine
  `event_ticketType_performance` (`CartController.php:120`).
- **Selectat deja în frontend:** `event-single.js:1271-1288` filtrează proiecțiile
  trecute și autoselectează prima viitoare.

Ce **nu** face: `Performance::getEffectiveQuota()` (`app/Models/Performance.php:88`)
nu e apelat nicăieri în calea de vânzare. Cota per sesiune e decorativă.

### 2.5 Hărțile de locuri

`seating_layouts → seating_sections → seating_rows → seating_seats` (șablon per
locație), clonate în `event_seating_layouts → event_seats` (inventar per eveniment),
cu `seat_holds` pe sesiune de browser și TTL.

**Defect care blochează cinema-ul:**
`app/Http/Controllers/Api/PublicApi/SeatingController.php:41` și `:93` rezolvă
snapshot-ul **doar** pe `event_id`, ignorând `performance_id`, deși coloana există.
Șase proiecții pe zi în aceeași sală ar împărți același inventar de locuri →
suprarezervare garantată.

### 2.6 Scanarea

- `tickets.checked_in_at`, `scanned_at`, `status`.
- `ticket_scans` — jurnal offline-first, idempotent după `client_scan_id`,
  „primul câștigă" pe `scanned_at` normalizat. Bine gândit (vezi comentariul din
  migrația `2026_08_09_100200`).
- `tics-app/mobile/src/offline/scanEngine.ts` — decizie locală:
  `unknown | wrong-event | void | duplicate | valid`.
- Server: `TixelloApp\OrganizerController::scans()` aplică aceleași reguli.

**Lipsa centrală a întregii cerințe:** nicăieri, nici pe server nici pe dispozitiv,
nu se compară momentul scanării cu vreo fereastră de valabilitate. Pachetul trimis
pe telefon (`OrganizerController.php:146`) conține doar
`code / eventId / status / seat / usedAt` — dispozitivul **nu are cum** să valideze
o oră pe care n-o primește.

### 2.7 Adminul

`app/Filament/Marketplace/Resources/EventResource.php` — **6297 de linii**, un
singur formular, cu câmpurile de leisure ascunse prin zeci de
`->visible(fn ($get) => $get('display_template') === 'leisure_venue')`, iar
configurarea de sloturi îngropată în `meta.slots_config.*` într-un Repeater.

Consecință directă pentru plan: **funcționalitatea nouă nu intră în acest formular.**
Intră în pagini dedicate, după modelul deja folosit la linia 2148 (un `Placeholder`
care trimite către un ecran separat).

---

## 3. Decizii de arhitectură

### D-1 — `performances` devine entitatea universală de sesiune

**Context.** Trebuie o entitate pentru „10:00, muzeu, 30 locuri" și „21:30, Sala 2,
Dune 3, hartă de locuri".

**Alegere.** Extindem `performances` aditiv, cu un discriminator `session_type`
(`slot | tour | showtime | performance`; NULL = reprezentație de teatru moștenită).

**Alternative.** (a) Tabel nou `event_sessions` — ar duplica `tickets.performance_id`,
`order_items.performance_id`, `event_seating_layouts.performance_id`,
`seat_holds.performance_id` și tot lanțul coș→checkout care deja funcționează. Patru
chei străine și un frontend de rescris, pentru un tabel identic ca formă.
(b) Extinderea `leisure_slot_bookings` — e un contor, n-are identitate: nu poate purta
preț, sală, hartă de locuri, și nu poate fi referit de un bilet.

**Impact.** Câștigăm gratuit: prețul per sesiune (`ticket_overrides`), selecția în
coș, expunerea publică, harta per sesiune. Costul: numele `performances` e nefericit
pentru un slot de muzeu. Rămâne — o redenumire ar atinge 20+ fișiere fără câștig
funcțional. Numele de domeniu în cod nou și în UI e **sesiune**.

### D-2 — Sesiuni materializate, generate din reguli

**Context.** Un muzeu deschis tot anul, 8 sloturi/zi, înseamnă ~2900 sesiuni/an. Se
calculează la cerere sau se scriu în DB?

**Alegere.** **Se scriu.** Un generator (`performance_rules`) le materializează pe un
orizont rulant (implicit 120 de zile), zilnic, idempotent.

**De ce nu calcul on-the-fly:**
1. Biletul trebuie să pointeze la ceva stabil ca să fie validat la scanare. Un slot
   calculat n-are id.
2. Capacitatea per slot are nevoie de un rând pe care să pui `SELECT … FOR UPDATE`.
3. Organizatorul trebuie să poată anula **un singur** slot („marți 14:00 e închis
   pentru filmare") fără să atingă regula.
4. Hărțile de locuri și rapoartele au nevoie de chei străine.

**Impact.** Un job zilnic în plus și o regulă de regenerare care nu are voie să
distrugă sesiuni vândute (§4.2). În schimb, tot restul sistemului tratează un slot de
muzeu exact ca pe o reprezentație de teatru.

### D-3 — Capacitatea e un plafon partajat cu sub-cote

**Context.** „Capacitate maximă pe un interval orar **și** pe un tip de bilet."

**Alegere.** Două niveluri:
- `performances.capacity_total` — plafonul sesiunii, partajat de toate tipurile.
- `performance_ticket_type_quotas.quota` — sub-cotă per tip, opțională (NULL = fără
  limită proprie, doar plafonul sesiunii).

Disponibilul pentru (sesiune S, tip T) e minimul dintre cele două, intersectat cu
stocul global al tipului de bilet:

```
disponibil(S,T) = min(
    quota(S,T)      − sold(S,T) − held(S,T),     -- dacă quota nu e NULL
    capacity(S)     − sold(S)   − held(S),       -- dacă capacity nu e NULL
    available_quantity(T)                        -- stocul global existent
)
```

Un muzeu cu 30 locuri/slot din care maximum 10 reduse se exprimă:
`capacity_total = 30`, `quota(redus) = 10`, `quota(întreg) = NULL`.

**Alternative.** Doar cote per tip, fără plafon — nu poți exprima „30 de oameni în
sală, indiferent cum se împart". Doar plafon, fără cote — nu poți limita reducerile.

### D-4 — Contoare denormalizate, nu `COUNT(*)` pe bilete

`sold`/`held` se țin ca întregi pe rândul de sesiune și pe rândul de cotă. În seara
dinaintea unui blockbuster, un `COUNT(*)` cu lock pe `tickets` blochează totul; un
lock pe un singur rând e ieftin.

Riscul de derivă se acoperă cu o comandă de reconciliere orară — repo-ul are deja
precedentul exact (commit `94b58b61`, „Hourly quota_sold reconciliation with email
notification on drift"). Reluăm același tipar, inclusiv notificarea pe derivă.

### D-5 — Fereastra de valabilitate e **pe bilet**, ca două coloane

**Context.** Dispozitivul de la poartă lucrează **offline**. Nu poate face join-uri.

**Alegere.** `tickets.valid_from` și `tickets.valid_until` (timestamp), calculate la
emitere, plus `session_starts_at` pentru afișare.

**De ce coloane și nu `meta`:**
1. Pachetul offline se descarcă printr-un `SELECT` simplu; `meta->>'visit_date'` ar
   trebui parsat pe telefon.
2. Rapoartele „ce bilete sunt valabile azi" se pot indexa.
3. Validarea devine o comparație de timestamp-uri, nu manipulare de JSON.

`meta.visit_date` rămâne scris în paralel, pentru retrocompatibilitate cu
`TicketVariableService` și rapoartele existente (§9).

### D-6 — `too-early` și `expired` **nu** sunt uși închise

Un vizitator care a ratat slotul cu 10 minute nu trebuie trimis acasă de un algoritm.
Scanarea în afara ferestrei returnează un rezultat distinct, afișat cu motivul
(„valabil 10:00–11:00"), plus un buton de suprascriere pentru operatorul cu
permisiunea potrivită. Suprascrierea se jurnalizează în `ticket_scans`.

Fără asta sistemul e de nefolosit în practică, iar personalul va cere dezactivarea
validării temporale cu totul — adică exact pierderea funcționalității cerute.

### D-7 — Hărțile de locuri pentru cinema se materializează leneș

O sală de 200 de locuri × 6 proiecții × 30 de zile = **360.000** de rânduri
`event_seats` pe lună, pe sală. Inacceptabil dacă se creează la generarea proiecției.

Snapshot-ul se creează la **prima cerere a hărții** pentru acea proiecție (sau la
prima vânzare), sub lock pe rândul de sesiune. Proiecțiile nevizitate nu ocupă nimic.
O comandă arhivează `event_seats` pentru sesiuni mai vechi de N zile.

### D-8 — Săli: tabel nou `venue_halls`, nu `stages`

`stages` există, dar e legat de festivaluri (`FestivalLineupSlot`), e tenant-scoped
și n-are `seating_layout_id`, `turnaround_minutes` sau exclusivitate. Semantica
„scenă de festival" și „sală de cinema" diverge suficient. `stages` rămâne
neschimbat.

---

## 4. Modelul de date

Toate migrațiile sunt **aditive**. Producția e PostgreSQL, dev/CI pe SQLite; se
folosesc coloane `string` cu constante pe model, nu `enum` — vezi D-001 din
`DECISIONS.md` pentru motivul detaliat.

Convenție de nume: `<timestamp>_sessions_<descriere>.php`.

### 4.1 `performances` — coloane noi

| Coloană | Tip | Rol |
|---|---|---|
| `session_type` | string(24) null | `slot`, `tour`, `showtime`, `performance` |
| `venue_hall_id` | FK null → `venue_halls` | sala |
| `capacity_total` | int null | plafonul sesiunii (NULL = din hartă sau din eveniment) |
| `capacity_sold` | int default 0 | contor live |
| `capacity_held` | int default 0 | contor live pentru rezervări în curs |
| `sales_start_at` / `sales_end_at` | timestamp null | fereastra de vânzare a sesiunii |
| `sales_cutoff_minutes` | int null | oprire vânzări cu N min înainte de start |
| `entry_window_before_minutes` | int null | de câte min înainte e valid biletul |
| `entry_window_after_minutes` | int null | câte min după start rămâne valid |
| `is_seated` | bool default false | folosește hartă de locuri |
| `generated_from_rule_id` | FK null → `performance_rules` | provenienţa |
| `generated_signature` | string(64) null | hash al parametrilor regulii, pentru regenerare idempotentă |
| `is_manually_edited` | bool default false | protejează de regenerare |
| `language` / `format` | string(16) null | cinema: `ro`/`sub`/`dub`, `2D`/`3D`/`IMAX` |
| `attributes` | jsonb null | extensibil |
| `external_ref` | string(64) null | import din sisteme de cinema |

Indici noi: `(venue_hall_id, starts_at)`, `(generated_from_rule_id)`,
`(event_id, session_type, starts_at)`.

Fără `UNIQUE(venue_hall_id, starts_at)`: două tururi ghidate pot porni legal
simultan în aceeași aripă de muzeu. Exclusivitatea e o **regulă de business**
verificată la scriere, activabilă per sală (§4.4).

### 4.2 `performance_rules` — generatorul

Aici se rezolvă „organizator cu program tot anul sau doar într-o perioadă".

```
performance_rules
  id
  event_id                     FK → events, cascade
  name                         'Program vară', 'Tur ghidat weekend'
  session_type                 'slot' | 'tour' | 'showtime'
  venue_hall_id                FK null → venue_halls
  valid_from                   date           -- perioada în care regula produce sesiuni
  valid_until                  date null      -- NULL = nedefinit, orizont rulant
  weekdays                     jsonb          -- [1..7], ISO-8601, 1 = Luni
  first_slot                   time           -- '10:00'
  last_slot                    time           -- '17:00'
  interval_minutes             int            -- 30
  duration_minutes             int            -- 60 → ends_at
  capacity_per_slot            int null
  entry_window_before_minutes  int null
  entry_window_after_minutes   int null
  sales_cutoff_minutes         int null
  label_template               string null    -- 'Tur {time}'
  ticket_type_quotas           jsonb null     -- [{ticket_type_id, quota, price_cents}]
  status                       'active' | 'paused'
  priority                     int default 0  -- când 2 reguli acoperă aceeași zi
  timestamps
```

„Tot anul" = `valid_from = azi`, `valid_until = NULL`.
„Doar vara" = `valid_from = 2026-06-01`, `valid_until = 2026-09-15`.
Mai multe reguli cu perioade diferite modelează sezoane cu program diferit; la
suprapunere câștigă `priority` mai mare, apoi `id` mai mic (determinist).

### 4.3 `performance_rule_exceptions` — zilele speciale

```
performance_rule_exceptions
  id
  event_id                FK → events
  performance_rule_id     FK null   -- NULL = se aplică tuturor regulilor evenimentului
  exception_date          date
  type                    'closed' | 'custom_hours' | 'capacity_override'
  first_slot / last_slot / interval_minutes   null
  capacity_per_slot       int null
  reason                  string null
  unique(event_id, performance_rule_id, exception_date)
```

Preia `venue_config.closed_dates[]` din sistemul actual, dar cu două tipuri în plus:
program special (25 decembrie: 10:00–14:00) și capacitate redusă pe o zi.

### 4.4 `venue_halls` — sălile

```
venue_halls
  id
  venue_id                    FK → venues
  tenant_id                   null
  marketplace_client_id       null
  name                        'Sala 1', 'Sala IMAX'
  slug
  capacity                    int null
  seating_layout_id           FK null → seating_layouts   -- harta implicită
  hall_type                   'cinema' | 'theatre' | 'room' | 'area'
  enforce_exclusive_booking   bool default true
  turnaround_minutes          int default 0               -- curățenie între spectacole
  attributes                  jsonb   -- {'screen':'IMAX','sound':'Dolby Atmos'}
  is_active                   bool
  sort_order                  int
```

`enforce_exclusive_booking` + `turnaround_minutes` alimentează
`HallConflictChecker`: la salvarea unei sesiuni se verifică suprapunerea cu
`[starts_at − turnaround, ends_at + turnaround]` pe aceeași sală. Conflictul e o
eroare de validare în admin, nu o constrângere de bază de date (organizatorul are
uneori motive legitime să forțeze).

### 4.5 `performance_ticket_type_quotas` — matricea

```
performance_ticket_type_quotas
  id
  performance_id   FK → performances, cascade delete
  ticket_type_id   FK → ticket_types, cascade delete
  quota            int null      -- NULL = doar plafonul sesiunii
  sold             int default 0
  held             int default 0
  price_cents      int null      -- override de preț pe sesiune
  is_available     bool default true
  timestamps
  unique(performance_id, ticket_type_id)
  index(performance_id)
```

Înlocuiește `performances.ticket_overrides` (JSON) ca sursă de adevăr — JSON-ul nu
poate ține contoare sub lock. `ticket_overrides` rămâne populat pentru
retrocompatibilitate cu `MarketplaceEventsController.php:1016` până la F8.

### 4.6 `tickets` — validitatea temporală

| Coloană | Tip | Rol |
|---|---|---|
| `valid_from` | timestamp null | începutul ferestrei de intrare |
| `valid_until` | timestamp null | sfârșitul ferestrei |
| `session_starts_at` | timestamp null | ora tipărită pe bilet |
| `venue_hall_id` | FK null | sala (scanare la ușa sălii) |

Indici: `(performance_id, status)`, `(valid_from, valid_until)`.

Calculul la emitere:

```
valid_from  = starts_at − COALESCE(entry_window_before_minutes, config('sessions.default_entry_before', 15))
valid_until = starts_at + COALESCE(entry_window_after_minutes, duration_minutes + config('sessions.default_grace_after', 30))
```

Pentru bilete fără sesiune (muzeu cu intrare liberă pe zi, doar dată aleasă):
`valid_from` = data la ora de deschidere, `valid_until` = data la ora de închidere +
toleranță. Adică ziua întreagă — exact „validate în data lor" din cerință, când nu
există segment orar.

### 4.7 `ticket_scans` — rezultate noi

`result` primește: `too-early`, `expired`, `wrong-session`, `override`.
Coloane noi: `override_by` (null), `override_reason` (null),
`session_id` (null, care sesiune era selectată la poartă).

### 4.8 `events` — un singur câmp nou

`scheduling_mode` string(16), implicit `single`:
- `single` — o dată fixă (comportament actual)
- `sessions` — sesiuni programate (calendar → slot → bilete)
- `open` — doar zi, fără oră (muzeu cu intrare continuă)

E comutatorul care decide ce citește API-ul public și ce ecran de admin se deschide.
Nimic nu se schimbă pentru evenimentele existente, care rămân pe `single`.

---

## 5. Motorul de disponibilitate

`App\Services\Sessions\AvailabilityService`.

### 5.1 Calendarul lunii

```
GET /events/{identifier}/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD
```

O singură interogare agregată peste `performances` (join `performance_ticket_type_quotas`),
grupată pe zi. **Fără N+1 pe zile** — greșeala clasică la calendare.

Per zi se întoarce:
`status` ∈ `available | limited | sold_out | closed | past`, `sessions_count`,
`min_price`, `first_session`, `last_session`.

`limited` se declanșează sub 20% rămas din capacitatea zilei — pragul din
`TicketTypeCapacity::getStatusAttribute()` (`app/Models/Leisure/TicketTypeCapacity.php:77`),
păstrat ca să nu avem două definiții de „aproape plin".

Cache 60s per `(event, lună)`, invalidat la vânzare. `EventStatsCache` există deja.

### 5.2 Sesiunile unei zile

```
GET /events/{identifier}/sessions?date=YYYY-MM-DD
```

```json
{
  "date": "2026-09-14",
  "is_open": true,
  "opening_hours": { "open": "10:00", "close": "18:00", "last_entry": "17:00" },
  "sessions": [
    { "id": 4412, "starts_at": "2026-09-14T10:00:00+03:00", "ends_at": "...",
      "label": "Tur ghidat 10:00", "hall": {"id": 3, "name": "Sala Mare"},
      "language": null, "format": null, "is_seated": false,
      "capacity_remaining": 12, "status": "available", "sales_open": true,
      "min_price": 25.00,
      "ticket_types": [
        { "id": 88, "name": "Întreg", "price": 40.00, "currency": "RON",
          "remaining": 12, "min_per_order": 1, "max_per_order": 10 },
        { "id": 89, "name": "Redus",  "price": 25.00, "currency": "RON",
          "remaining": 4,  "min_per_order": 1, "max_per_order": 10 }
      ] } ]
}
```

`remaining` per tip e deja minimul din D-3 — frontendul nu recalculează nimic și nu
poate desincroniza regula.

Când ziua e închisă: `is_open: false` + `reason` ∈
`closed | before_season | after_season | past | no_sessions`, exact vocabularul deja
folosit de `DateAvailabilityController.php:88`.

---

## 6. Rezervare și concurență

`App\Services\Sessions\SessionBookingService`.

### 6.1 Ordinea de blocare — fixă

**Întotdeauna:** rândul de sesiune → apoi rândurile de cotă, în ordinea crescătoare a
`ticket_type_id`. Ordine fixă = fără deadlock între două checkout-uri concurente care
cumpără aceleași tipuri în ordine diferită.

### 6.2 Ciclul de viață

```
hold(session, [{ticket_type_id, qty}], holder)  → held += qty       (TTL 10 min)
  ↓ plata reușește
confirm(hold)                                   → held -= qty; sold += qty
  ↓ sau
release(hold)                                   → held -= qty       (expirare / abandon)
  ↓ sau, mai târziu
cancel(tickets)                                 → sold -= qty       (refund / anulare)
```

TTL-ul de 10 minute e aliniat cu `seat_holds` — un coș care ține locuri **și** cote
trebuie să le elibereze simultan, altfel rămân cote blocate pentru locuri deja libere.

Expirarea: comandă schedulată la fiecare minut, plus verificare leneșă la citire
(`held` care a depășit `expires_at` nu se contabilizează). Dubla apărare, ca la
`seat_holds`.

### 6.3 Anulările trebuie să elibereze

Lecția din comentariul lui `LeisureSlotBooking` („Fără asta, slot-ul rămâne consumat
în DB") se aplică la fel: fiecare cale de anulare — refund, anulare de comandă,
ștergere de bilet din POS, expirare de comandă neplătită — trebuie să apeleze
`cancel()`. Se acoperă cu un observer pe `Ticket` (tranziție `status → cancelled`),
nu cu apeluri împrăștiate prin controlere, ca să nu se poată uita una.

### 6.4 Reconciliere

`sessions:reconcile-counters` — orar. Recalculează `sold` din `tickets` (grupat pe
`performance_id` + `ticket_type_id`, `status = valid`) și compară cu contoarele.
Pe derivă: corectează + trimite notificare, exact ca `quota_sold` (commit `94b58b61`).

---

## 7. Biletul

### 7.1 Ce poartă

- `performance_id` — sesiunea (deja existent)
- `valid_from` / `valid_until` — fereastra
- `session_starts_at` — ora tipărită
- `venue_hall_id` — sala
- `meta.visit_date` — păstrat, pentru compatibilitate

### 7.2 Ce se vede la scanare

Ecranul de la poartă afișează, pe verde sau pe roșu:

```
  ✓ VALID
  Muzeul Antipa · Tur ghidat 10:00
  Sâmbătă, 14 septembrie 2026
  Valabil 09:45 – 11:30
  Întreg · Andrei Ionescu
```

sau

```
  ✗ ÎN AFARA INTERVALULUI
  Bilet pentru 14 septembrie, 10:00
  A fost valabil 09:45 – 11:30
  Acum: 14 septembrie, 13:22
  [ Permite intrarea ]     ← doar cu permisiunea scan.override_window
```

### 7.3 Variabile de șablon

`TicketVariableService` capătă, lângă `visit_date` / `visit_date_raw` /
`visit_day_name` deja existente (`app/Services/TicketCustomizer/TicketVariableService.php:370`):

`session_time` („10:00"), `session_end_time`, `session_label` („Tur ghidat"),
`valid_window` („09:45 – 11:30"), `hall_name`, `seat_full` („Sala 2 · Rând F · Loc 12").

Toate goale pe evenimentele fără sesiuni — zero regresie pe șabloanele existente.

### 7.4 Codul QR

Nu se schimbă. QR-ul rămâne `code`/`barcode`. Fereastra de validitate **nu** se
codifică în QR: ar face imposibilă prelungirea unui slot de către organizator după
emitere, iar dispozitivul o primește oricum în pachetul offline.

---

## 8. Scanarea

### 8.1 Ordinea de decizie (identică pe server și pe dispozitiv)

1. bilet inexistent → `unknown`
2. `status != 'valid'` → `void`
3. eveniment greșit → `wrong-event`
4. poarta e legată de o sesiune și biletul e pentru alta → `wrong-session`
5. `now < valid_from − toleranță` → `too-early`
6. `now > valid_until + toleranță` → `expired`
7. `checked_in_at` există → `duplicate`
8. altfel → `valid`

Ordinea contează: `duplicate` **după** verificarea ferestrei, ca operatorul să vadă
motivul real. Un bilet expirat și deja scanat e, în primul rând, expirat.

`toleranță` = `config('sessions.scan_clock_tolerance_seconds', 120)`.

### 8.2 Offline

`scanEngine.ts` are nevoie de datele pe dispozitiv. Pachetul
`GET /org/events/{event}/tickets` (`OrganizerController.php:146`) se extinde cu
`validFrom`, `validUntil`, `sessionStartsAt`, `sessionLabel`, `hall`, plus parametri
`?session_id=`, `?from=`, `?to=` — un muzeu nu poate descărca biletele pe un an
întreg la fiecare deschidere a aplicației.

Ceasul nesigur: `clock.ts` marchează deja `trusted`. Când `trusted === false`,
fereastra se lărgește cu `UNTRUSTED_CLOCK_SLACK_MS` (implicit 15 min) și rezultatul
se marchează `low_confidence`. Un telefon cu ceasul umblat nu are voie să respingă
bilete bune; reconcilierea de pe server, care are ora corectă, va corecta jurnalul.

### 8.3 Puncte de aplicare

Aceeași regulă, un singur loc de implementare (`App\Services\Sessions\ScanValidator`),
apelat din toate cele patru intrări:

| Intrare | Fișier |
|---|---|
| App organizator (loturi offline) | `Api/TixelloApp/OrganizerController::scans()` |
| Panou proprietar de locație | `Api/MarketplaceClient/VenueOwner/CheckInController` |
| Panou operator (Filament) | `Filament/Operator/Pages/CheckIn.php:51` |
| Check-in manual organizator | `Organizer/Leisure/LeisureController::manualCheckin()` |

Azi fiecare are propria variantă de „e deja scanat?". Se unifică — altfel validarea
temporală va exista în trei locuri din patru, ceea ce e mai rău decât în niciunul.

---

## 9. Fluxul public (muzeu)

Ordinea cerută: **zi → slot → tipuri de bilet.**

**Pas 1 — Calendar.** Luna curentă, zile colorate din `/calendar`. Zilele închise
dezactivate, cu motivul în tooltip („Închis lunea"). Navigarea între luni limitată la
intervalul acoperit de reguli — nu are sens să răsfoiești în gol.

**Pas 2 — Slot.** Grilă de butoane: oră + locuri rămase („10:00 · 12 locuri").
Sold-out gri și inactiv. Peste 12 sloturi se grupează pe Dimineață / După-amiază /
Seară.

**Pas 3 — Tipuri de bilet.** Stepper per tip, cu maximul live =
`min(cotă tip, capacitate rămasă sesiune, max_per_order)`. Când plafonul partajat se
atinge, celelalte steppere se plafonează vizibil, cu explicație („au mai rămas 3
locuri în acest interval") — nu tăcut.

**Pas 4 — Locuri** (doar `is_seated`, cinema). Harta sălii pentru sesiunea aleasă.

**Hold.** La „Adaugă în coș": `POST /sessions/{id}/hold`, TTL 10 min, numărătoare
inversă vizibilă. La expirare, itemul iese din coș și capacitatea se eliberează.

**Deep-link.** `?date=2026-09-14&session=4412` — ca să poți trimite un link direct
dintr-un email de remarketing.

**Unde trăiește.** Pagina actuală `leisure-venue.php` (2797 linii) rămâne pentru
organizatorii nemigrați. Fluxul nou e o componentă separată, activată de
`scheduling_mode === 'sessions'`. Migrarea se face organizator cu organizator, nu
printr-un big-bang.

---

## 10. Cinema

Fără concepte noi — doar combinația celor de mai sus.

| Noțiune cinema | Modelare |
|---|---|
| Film | `Event` cu `display_template = 'cinema_movie'`, `scheduling_mode = 'sessions'`; `range_start_date`/`range_end_date` = perioada de rulare |
| Operă reutilizabilă între cinematografe | `Repertoire` (există; `repertoire_id` pe `Event`) |
| Cinematograf | `Venue` |
| Sală | `venue_halls` |
| Proiecție | `performances` cu `session_type='showtime'`, `venue_hall_id`, `language`, `format`, `is_seated=true` |
| Filme în paralel | Două `Event`-uri, fiecare cu proiecții la aceeași oră în săli diferite |
| Hartă de locuri per proiecție | `event_seating_layouts.performance_id` (coloana există deja) |

**Ce trebuie construit efectiv:**

1. **Repararea `SeatingController`** (§2.5) — rezolvare pe `performance_id` când e
   prezent. Fără asta, cinema-ul suprarezervă din prima zi. E și un defect real în
   codul actual pentru evenimentele de teatru cu mai multe reprezentații.
2. **Snapshot leneș** (D-7) — `SeatingSnapshotService::ensureForSession($performance)`,
   sub lock, clonând `venue_halls.seating_layout_id`.
3. **Grila de program** — `/cinema/{venue-slug}?date=`: toate proiecțiile zilei din
   toate sălile, grupate pe film, cu ora, sala, formatul și limba. O interogare:
   `performances` join `events` unde `venue_hall_id ∈ sălile locației`.
4. **Detecția de conflict** — `HallConflictChecker` (§4.4), la salvarea proiecției.
5. **Cutoff de vânzare** — `sales_cutoff_minutes` (tipic 15 min după începere; unele
   cinematografe vând și după).

Costul real al cinema-ului, odată ce §4–§9 există, e mic: e același sistem cu
`is_seated = true` și o sală.

---

## 11. Adminul

**Principiu: nimic nou în `EventResource.php`.** Are 6297 de linii; a mai adăuga
douăzeci de câmpuri condiționale l-ar face nementenabil. Doar un `Select`
(`scheduling_mode`) și un link, exact ca tiparul de la linia 2148.

Adminul capătă două ecrane noi, distincte ca rol: **crearea** unei locații cu program
(§12.2 — un wizard, folosit o dată per organizator) și **întreținerea** programului ei
(§11.1 — folosit constant).

### 11.1 Pagină nouă: `EventResource/Pages/Sessions.php`

Patru sub-taburi:

**Reguli.** Repeater peste `performance_rules`: perioadă (`valid_from`/`valid_until`,
cu „tot anul" ca opțiune care lasă `valid_until` gol), zilele săptămânii, primul și
ultimul slot, interval, durată, capacitate/slot, ferestrele de intrare. Un buton
**Previzualizează** care arată câte sesiuni ar genera și primele 20 — nimeni nu
trebuie să genereze 3000 de rânduri pe încredere. Apoi **Generează**.

**Excepții.** Calendar pe 12 luni; click pe o zi → închis / program special /
capacitate redusă. Preia `venue_config.closed_dates` la migrare.

**Sesiuni.** Tabel filtrabil pe dată și sală. Editare inline a capacității, anulare
de sesiune (cu notificarea celor care au bilete), duplicare de zi, acțiuni în masă pe
interval („crește capacitatea cu 20% în tot august").

**Cote.** Matricea sesiune × tip de bilet, cu editare rapidă și aplicare în masă
(„aplică pe toate sloturile de weekend din septembrie").

Toate scrierile de capacitate trec prin serviciu — niciodată `update()` direct — ca
să nu se poată coborî capacitatea sub `sold`.

### 11.2 Săli

`VenueResource` primește un RelationManager `Halls`: nume, capacitate, tip, hartă de
locuri implicită, turnaround, exclusivitate.

### 11.3 Panoul organizatorului

Organizatorul nu are Filament — are panoul de la `/organizator`. Primește un ecran
**Program** cu capabilitățile operaționale din §12.3, peste
`Organizer\SessionsController` (aceleași servicii ca adminul, altă prezentare).

Nu e o funcționalitate nouă ca principiu: `LeisureController::updateVenueConfig()`
lasă deja organizatorul să-și editeze `seasons` și `closed_dates` fără să treacă prin
Filament sau prin aprobare. Ecranul de Program continuă exact acel precedent, peste
noul model. Fără el, fiecare zi închisă ajunge la echipa de suport.

---

## 12. Modelul de marketplace — onboardingul unui organizator

Cerința nu e doar tehnică. Un marketplace trebuie să poată **înrola** un organizator
care vinde astfel de sesiuni și să-l poată deconta ca pe oricare altul.

Două decizii de produs delimitează capitolul:

- **Onboardingul e asistat, nu self-service.** Un muzeu sau un cinema e creat de
  marketplace, nu de organizator. Operarea de zi cu zi rămâne a organizatorului.
- **Deconturile funcționează exact ca la leisure azi.** Nicio bază de calcul nouă,
  niciun câmp nou, niciun comportament nou (§12.6).

### 12.1 Un defect de reparat întâi, și o constatare

**B-1 — Pe marketplace, capacitatea pe slot nu se consumă niciodată.**
`SlotBookingService::reserve()` și `::release()` **nu au niciun apelant** în tot
repo-ul. Singura metodă folosită e `getBookingsMap()` (citire), din
`DateAvailabilityController` și `LeisureAvailabilityController`. Adică: sloturile
*afișează* „12 locuri rămase", dar contorul nu scade niciodată la vânzare. Un slot de
muzeu vândut pe ambilet poate fi suprarezervat la nesfârșit, azi, în producție.

Serviciul e scris corect (tranzacție, `lockForUpdate`, unique constraint) — pur și
simplu nu e conectat la checkout și la POS. Comentariul din `LeisureSlotBooking`
descrie flow-ul corect ca și cum ar exista.

**Nuanța care contează:** pe partea de tenant, capacitatea *se* consumă — prin
`CapacityAvailabilityService::reserve()`, apelat din `TenantClient\OperatorController`,
`DemoCheckoutController` și `OrderObserver`, peste `Leisure\TicketTypeCapacity`.
Deci cele două implementări paralele din §2.2–2.3 au divergit până acolo încât una
aplică regula și cealaltă doar o afișează. Checkout-ul de marketplace nu atinge
niciuna dintre ele.

E cel mai bun argument pentru unificare din tot documentul: nu e o preferință de
design, e o regulă de business care se pierde la granița dintre două implementări ale
aceluiași lucru.

> Consecință pentru plan: F3 nu e „adăugăm rezervarea", ci „conectăm rezervarea
> pentru prima dată". Iar migrarea din §13 nu poate recupera contoare reale din
> `leisure_slot_bookings` — tabelul e, foarte probabil, gol sau nereprezentativ.
> Migrarea recalculează `sold` din bilete, nu din el.

**C-1 — Crearea trece obligatoriu printr-un admin, și e neplăcută.**
`Organizer\EventsController::store()` acceptă `duration_mode` doar în
`single_day, range, multi_day`, nu setează niciodată `display_template` sau
`venue_config`. `Leisure\LeisureController` se activează **doar** pentru evenimente
deja marcate `leisure_venue` (linia 58) — configurează, nu creează.

**Asta nu e un defect: e modelul dorit.** Un muzeu sau un cinema nu se înrolează
singur; marketplace-ul îl creează, cu contract, comision și verificare de identitate
în spate. Constatarea reală e alta — drumul actual al adminului trece prin
`EventResource.php` (6297 de linii), bifând manual câmpuri ascunse în spatele a zeci
de condiții `display_template === 'leisure_venue'`. Funcționează, dar nu e un flux de
onboarding; e arheologie.

Ce lipsește e un ecran de creare dedicat pentru admin (§12.2), nu un wizard pentru
organizator.

### 12.2 Crearea: un ecran de admin, nu un wizard de organizator

Marketplace-ul creează locația. Fluxul e asistat, ca la orice organizator care intră
cu contract negociat — dar trebuie să fie un flux, nu o vânătoare prin 6297 de linii.

**Ecran dedicat în Filament: „Locație nouă cu program".** Un wizard cu pași, separat
de `EventResource`, care produce la final un `Event` complet configurat plus regulile
lui. Pașii:

1. **Organizatorul** — se alege dintre cei existenți, sau se creează pe loc.
2. **Identitate** — nume, descriere, categorie, oraș, imagini.
3. **Locația** — `venue_id`. La cinema, aici se declară și sălile (`venue_halls`).
4. **Programul** — perioada (tot anul / interval), zilele săptămânii, orele, dacă se
   vinde pe intervale orare și la ce pas, capacitatea pe slot. Devine un
   `performance_rule`.
5. **Biletele** — tipurile, prețurile, cotele opționale per slot, societatea emitentă
   (`issuing_company`) — contează direct la decont (§12.6).
6. **Comisionul** — rata și modul, ca la orice eveniment de marketplace.
7. **Previzualizare** — câte sesiuni se generează, primele 20, și prima lună de
   calendar așa cum o va vedea clientul. Nimeni nu publică 3000 de sesiuni pe
   încredere.

Fiind creat de admin, evenimentul se poate publica direct — nu trece prin
`submitted_at` / aprobare. Porțile de organizator (cont activ, contract semnat) rămân
verificate, dar ca precondiții afișate în wizard, nu ca blocaje asincrone.

**Cinema:** același ecran, cu `sessions-seated` activ. Un film nou într-un cinematograf
deja configurat nu reia wizardul — e „Adaugă film" peste sălile existente, cu grila de
proiecții.

### 12.3 Operarea: organizatorul își conduce singur programul

Onboardingul e asistat; **operarea nu are cum să fie**. Un muzeu care trebuie să sune
marketplace-ul ca să închidă ziua de Crăciun nu e un produs.

Precedentul există deja și funcționează:
`Leisure\LeisureController::updateVenueConfig()` (linia 362) lasă organizatorul să
editeze `seasons`, `closed_dates` și `pricing_rules` direct, printr-un endpoint
dedicat, **fără să treacă prin `pending_changes`**. Comentariul din cod o spune
explicit: „Organizatorul rezervației poate modifica fără să intre în Filament admin".

**Decizia D-9 — două suprafețe, formalizarea unui precedent.**

Nu inventăm o excepție nouă; extindem regula care e deja în producție.

| Se revizuiește (`pending_changes`) | Operațional — organizatorul, live |
|---|---|
| Nume, descriere, imagini, categorie | Zile închise și excepții de program |
| Locație și săli | Capacitatea unei sesiuni sau a unui slot |
| Tipurile de bilet și **prețurile** | Anularea unei sesiuni individuale |
| Comisionul și societatea emitentă | Extinderea orizontului de generare |
| Termenii biletului | Deschiderea/închiderea vânzărilor pe o sesiune |

Regula: **ce e comercial se aprobă, ce e operațional nu.** Prețul rămâne comercial,
inclusiv un `price_cents` de sesiune — altfel aprobarea de preț s-ar putea ocoli prin
suprascrieri.

Se implementează ca o listă albă explicită de câmpuri operaționale, în același loc cu
`updateVenueConfig`, nu ca excepții împrăștiate prin controlere.

### 12.4 Activarea funcționalității (microservicii)

Repo-ul are deja mecanismul: `Microservice` + pivotul `marketplace_client_microservices`,
cu `MarketplaceClient::hasMicroservice($slug)`. Familia `leisure-*` e deja semănată
(`leisure-core`, `leisure-pos`, `leisure-rentals`, `leisure-multi-society`,
`leisure-embed`).

Se adaugă două:

- **`sessions-scheduling`** — calendar, sloturi, reguli, capacitate. Muzee, tururi.
- **`sessions-seated`** — săli, hărți per sesiune, cinema. Depinde de prima.

Gating în trei locuri, toate obligatorii: ecranul de creare acceptă arhetipul; paginile
de program (admin și organizator) sunt vizibile; API-ul public răspunde. Un gate doar
în UI nu e gate.

**Un gol de acoperit:** gating-ul e azi **per marketplace client**, nu per organizator.
Un marketplace care vrea să dea sesiuni doar unor organizatori are nevoie de un pivot
`marketplace_organizer_microservices`, simetric cu cel existent. Se adaugă în F1 —
altfel activarea e totul-sau-nimic pe tot marketplace-ul.

### 12.5 Comisionul

`SalesBreakdownService` — sursa de adevăr pentru bani — citește **prețul efectiv
plătit per bilet** (`tickets.price`), nu prețul din catalogul tipului de bilet.
Comentariul din serviciu o spune explicit: reducerile de perioadă se reflectă pentru
că se citește ce s-a plătit, nu ce scria în catalog.

Cascada de comision e deja completă:
`event.commission_rate → organizer → tenant → marketplace client`, cu modul
`event.commission_mode → organizer.default_commission_mode → client`, plus pragul
opțional al organizatorului (`commission_use_floor` + `fixed_commission_default`).

**Deci sesiunile nu cer nicio schimbare aici** — cu o singură condiție, care devine
cerință de acceptanță în F3/F4:

> Prețul efectiv al sesiunii (suprascrierea din `performance_ticket_type_quotas`,
> peste `pricing_rules`, peste prețul de bază) trebuie să ajungă în `tickets.price`
> la emitere. Dacă ajunge acolo, comisionul, breakdown-ul de vânzări și decontul sunt
> corecte prin construcție.

Un singur punct de rezolvare a prețului — `SessionPriceResolver`, cu cascada
`suprascriere de sesiune → pricing rules → preț de bază` — folosit identic de coș,
checkout și POS. Nu trei implementări care pot devia.

### 12.6 Deconturile: identice cu leisure, fără excepții

**Decizia D-10.** Decontul unei locații cu sesiuni funcționează **exact** ca decontul
unui leisure venue azi. Nicio bază de calcul nouă, niciun câmp nou, niciun
comportament nou. Concret, pipeline-ul care rămâne neatins:

| Element | Comportament, neschimbat |
|---|---|
| Sursa cifrelor | `SalesBreakdownService::build()` — preț real plătit per bilet |
| Data de grupare | `paid_at` — data încasării, nu data vizitei |
| Vânzările POS | Excluse (`excludePos: true`), facturate separat |
| Împărțirea pe societăți | Pe `issuing_company` de pe tipul de bilet (`primary` / `secondary`) |
| Idempotența | Cel mult un decont activ per (eveniment, societate, `period_start`) |
| Rambursările | **Nu** se scad automat; operatorul le leagă ulterior pe pagina decontului |
| Rezultatul | `MarketplacePayout` cu status `approved`, serie auto, PDF din `MarketplaceTaxTemplate` (`decont_inclus` / `decont_ontop`) |

**Ce trebuie construit pentru decont: nimic.** Condiția e cea din §12.5 — biletul să
poarte prețul efectiv. `LeisureSocietyDecontService` filtrează pe
`isLeisureVenue()`; se extinde condiția ca să prindă și `scheduling_mode === 'sessions'`,
și atât. O linie, nu un flux nou.

> Expunerea la vânzarea în avans (bani încasați în decembrie pentru o vizită în
> iulie) rămâne **exact cum e azi** pentru orice leisure venue. Nu se schimbă în
> niciun sens prin această lucrare.

### 12.7 Rambursările trebuie să elibereze capacitatea

Azi nu eliberează nimic, pentru că nimic nu se rezervă (B-1). Odată conectat,
fiecare cale de ieșire trebuie să apeleze `SessionBookingService::cancel()`:

| Cale | Unde |
|---|---|
| Cerere de rambursare aprobată | `MarketplaceRefundRequest` → tranziție de status |
| Anulare de comandă | observer pe `Order` |
| Ștergere de bilet din POS | `LeisureOrderDeletionLog` există deja ca jurnal |
| Comandă neplătită expirată | jobul existent de expirare |
| Anularea unei sesiuni de către organizator | eliberează tot și notifică deținătorii |

Se prinde cu **un singur observer** pe tranziția de status a biletului, nu cu apeluri
în cinci controlere — lecția din comentariul lui `LeisureSlotBooking` („fără asta,
slotul rămâne consumat în DB") aplicată preventiv.

Transferul de bilet (`MarketplaceTicketTransfer`) **nu** eliberează nimic: se schimbă
proprietarul, nu ocuparea.

### 12.8 Echipa organizatorului

`MarketplaceOrganizerTeamMember` are deja `role`, `leisure_role` (nouă valori,
inclusiv `check_in`, `pos_cashier`, `kiosk_selfcheckin`), `permissions[]`, `gate_id`
și un whitelist per eveniment. E granularitatea potrivită — nu e nevoie de whitelist
per sesiune.

Se adaugă o singură permisiune: **`schedule.manage`** — cine poate edita regulile,
excepțiile și capacitățile. Implicit doar `admin`. Un casier POS nu trebuie să poată
închide o zi.

Opțional, util la cinema: legarea unui scanner de o sală (`gate_id` → `venue_hall_id`),
ca scanarea la ușa Sălii 2 să respingă biletele pentru Sala 1 cu `wrong-session`.

### 12.9 Scoping multi-marketplace

Fiecare tabel nou primește `marketplace_client_id` **de la început**:
`performance_rules`, `performance_rule_exceptions`, `venue_halls`.

Nu e teoretic: `event_seating_layouts` a primit coloana abia ulterior, prin migrația
`2026_01_20_110000_add_marketplace_client_id_to_event_seating_layouts.php`. Nu
repetăm.

`performances` **nu** primește coloana — moștenește scope-ul prin `event_id`, iar
generatorul și fiecare interogare de admin filtrează prin eveniment.

### 12.10 Descoperirea publică: o locație nu e un eveniment cu dată

Un muzeu deschis tot anul n-are `event_date`. Trei consecințe:

**Agregatele de intent funcționează deja.** `RefreshEventIntentAggregatesCommand`
citește deja `performances` pentru `next_session_at` și `has_session_today` /
`_tomorrow` / `_this_weekend`. Filtrele „azi", „mâine", „weekend" merg fără cod nou.
**Dar** comanda face `with('performances')` fără limită — la 2900 de sesiuni pe an, pe
locație, memoria explodează. Eager-load-ul trebuie constrâns la următoarele 8 zile.
Reparație de o linie, obligatorie în F1.

**Cardurile trebuie să arate altceva.** „14 sept – 14 sept" e greșit pentru o locație
permanentă; „Deschis azi 10:00–18:00" e corect. `data-transformer.js` ramifică deja pe
`duration_mode` — capătă o ramură `sessions`.

**SEO: o locație e un loc, nu o dată.** Trebuie să apară pe „muzee în București", nu
pe „evenimente 14 septembrie". Sitemap-ul, paginile de oraș și URL-urile vanity au
nevoie de acest tratament — altfel un muzeu concurează cu concertele pe interogări
datate și pierde.

### 12.11 Vânzarea la ghișeu (POS)

`leisure-pos` + `LeisureController::posSale()`. Un muzeu vinde majoritatea biletelor
la intrare, **simultan** cu vânzarea online — deci POS-ul e locul cel mai probabil de
suprarezervare.

`posSale()` trebuie să treacă prin exact același `SessionBookingService`, cu aceleași
lock-uri. Un flux paralel „pentru că e la ghișeu și e mai simplu" garantează
suprarezervarea. La ghișeu, slotul implicit e cel curent sau următorul, dar casierul
îl poate schimba.

### 12.12 Fiscal și documente

- **Emitentul** nu se schimbă: `issuing_company` / `tenant_tax_registry_id` rămân pe
  tipul de bilet.
- **`MarketplaceTaxTemplate`** tratează special evenimentele `multi_day`, citind
  `multi_slots` (liniile 755 și 850). Are nevoie de o ramură `sessions` — altfel
  cererile de avizare pentru un muzeu vor lista date greșite.
- **Momentul TVA** pentru o vânzare în avans e aceeași discuție ca §12.6, și se
  rezolvă odată cu ea.

### 12.13 Ce mai urmează firesc

Nu în primele faze, dar decurg direct din model și merită proiectate ca posibile:

- **Listă de așteptare** pe slot epuizat — `Waitlist` / `WaitlistEntry` există deja.
- **Coduri promo pe data vizitei** — azi restricțiile sunt pe data cumpărării.
  „20% la vizitele din ianuarie" cere o restricție pe sesiune.
- **Widget embed** (`leisure-embed` + `TixelloWidgetToken`) — muzeul vrea calendarul
  pe propriul site. API-ul din §5 e deja forma potrivită.
- **Raport de ocupare per sesiune** — „cât de plin e slotul de 14:00 marțea" e prima
  întrebare pe care o pune un organizator după prima săptămână.
- **Notificarea deținătorilor** la anularea unei sesiuni — `organizer_notifications`
  există pe `Event`.

---

## 13. Migrarea sistemului existent

Nimic nu se rescrie din mers. `scheduling_mode` e comutatorul; implicit toate
evenimentele rămân pe `single` și se comportă identic.

**`sessions:migrate-leisure {event} [--dry-run]`**, `--dry-run` implicit:

1. `venue_config.seasons[]` → câte un `performance_rule` per sezon (`valid_from`/
   `valid_until` derivate din MM-DD peste anul curent și următorul; sezoanele cu
   wrap-around nov→mar produc două intervale).
2. `venue_config.closed_dates[]` → `performance_rule_exceptions` de tip `closed`.
3. `ticket_types.meta.slots_config` și `meta.has_tour_slots` + `meta.slot_times[]` →
   reguli cu `session_type = 'tour'` și cotele corespunzătoare.
4. Sesiunile trecute cu vânzări se materializează din **bilete**, nu din
   `leisure_slot_bookings` — vezi B-1 (§12.1): contorul acela n-a fost niciodată
   populat de vânzări, deci nu e o sursă de adevăr.
5. Biletele existente cu `meta.visit_date` → backfill `valid_from`/`valid_until` pe
   ziua respectivă (fereastră = ziua întreagă, în lipsa unei ore); cu
   `meta.tour_slot_time` prezent → fereastră pe slot.

Raportează diferențele înainte de scriere.

**Compatibilitate în timpul tranziției:**
- `DateAvailabilityController` rămâne funcțional; citește din sesiuni **doar** când
  `scheduling_mode === 'sessions'`.
- `SlotBookingService` devine un adaptor peste `SessionBookingService` (aceleași
  semnături, delegare). Se șterge în F8, după ce nu mai are apelanți.
- `performances.ticket_overrides` continuă să fie populat din
  `performance_ticket_type_quotas`, ca `MarketplaceEventsController.php:1016` și
  `event-single.js` să funcționeze nemodificate.

---

## 14. Faze de livrare

Fiecare fază se poate opri fără să lase sistemul într-o stare intermediară.

### F0 — Fundație
Migrațiile din §4, modelele, relațiile, `HallConflictChecker`. `marketplace_client_id`
pe toate tabelele noi de la început (§12.9).
**Gata când:** migrațiile rulează curat pe SQLite și pe un dump de dev PostgreSQL;
modelele au teste de relații; nimic din comportamentul existent nu se schimbă.

### F1 — Generator + activare
`SessionGenerator`, comanda `sessions:generate`, orizontul rulant, semnătura de
idempotență, protecția sesiunilor vândute. Microserviciile `sessions-scheduling` /
`sessions-seated` + pivotul per organizator (§12.4). Constrângerea eager-load-ului în
`RefreshEventIntentAggregatesCommand` (§12.10).
**Gata când:** o regulă „tot anul, marți–duminică, 10:00–17:00 din 30 în 30" produce
setul corect; a doua rulare nu schimbă nimic; o sesiune cu `sold > 0` supraviețuiește
regenerării; comanda de agregate rulează pe o locație cu 3000 de sesiuni fără să
depășească memoria.

### F2 — Disponibilitate + API public
`AvailabilityService`, `/calendar`, `/sessions`, caching.
**Gata când:** calendarul unei luni se rezolvă într-o interogare; testele acoperă zi
închisă, sezon expirat, sold-out, cotă epuizată pe un tip dar nu pe altul.

### F3 — Rezervare, coș, checkout, POS
`SessionBookingService` — **prima conectare reală a rezervării** (B-1).
`SessionPriceResolver` (§12.5), `session_id` obligatoriu în coș, observer de anulare
(§12.7), reconciliere. `posSale()` pe același serviciu (§12.11).
**Gata când:** două checkout-uri concurente pe ultimul loc — unul reușește, unul
primește 409; o vânzare POS și una online simultane pe ultimul loc se serializează
corect; comisionul se calculează pe prețul efectiv; zero derivă după 1000 de operații
aleatorii.

### F4 — Biletul
`valid_from`/`valid_until` la emitere pe toate căile (checkout online, POS,
invitații, comenzi manuale), variabilele de șablon, backfill.
**Gata când:** un bilet emis pentru un slot are fereastra corectă în UTC, inclusiv
peste schimbarea la ora de vară.

### F5 — Scanarea
`ScanValidator`, unificarea celor patru puncte de intrare, `too-early`/`expired`/
`wrong-session`/`override`, pachetul offline extins, `scanEngine.ts`, ecranul de la
poartă.
**Gata când:** un bilet de 12 iulie ora 10:00 e respins pe 3 august cu motivul
afișat; suprascrierea funcționează și se jurnalizează; testele offline din
`offline.test.ts` acoperă ceasul nesigur.

### F6 — Creare (admin) + operare (organizator)
Ecranul dedicat de creare în Filament (§12.2), pagina `Sessions` (§11), sălile pe
`VenueResource`, ecranul de program din panoul organizatorului peste precedentul
`updateVenueConfig`, separarea suprafețelor de aprobare (§12.3), permisiunea
`schedule.manage` (§12.8). Condiția `isLeisureVenue()` din
`LeisureSocietyDecontService` extinsă la `scheduling_mode === 'sessions'` (§12.6).
**Gata când:** un admin creează o locație cu program de vară și de iarnă dintr-un
singur ecran, cu previzualizare înainte de publicare; iar organizatorul își închide
apoi o zi din panoul lui, fără ca acțiunea să intre în `pending_changes`. Un decont
generat pe locația nouă produce aceleași cifre ca pe un leisure venue echivalent.

### F7 — Interfața publică
Zi → slot → bilete, hold cu numărătoare inversă, deep-link, cardurile și SEO pentru
locații permanente (§12.10).
**Gata când:** fluxul e complet pe un eveniment de test, cu locuri epuizate și zile
închise afișate corect.

### F8 — Cinema
`venue_halls` în admin, repararea `SeatingController`, snapshot leneș, grila de
program, cutoff.
**Gata când:** două filme rulează în paralel în două săli, fiecare cu propria hartă,
iar locul 12 din Sala 1 la 19:00 rămâne liber pentru proiecția de la 21:30.

### F9 — Migrare și curățare
`sessions:migrate-leisure`, ramura `sessions` în `MarketplaceTaxTemplate` (§12.12),
retragerea `SlotBookingService` și a `meta.slots_config`.
**Gata când:** niciun organizator nu mai citește din calea veche și codul mort e
șters.

---

## 15. Riscuri

**Capacitatea nu se consumă azi (B-1).** Cel mai mare risc din listă, pentru că e
deja în producție și e invizibil: sistemul afișează locuri rămase pe care nu le
verifică nimeni la vânzare. Merită verificat pe date reale **înainte** de F0, ca să
știm dacă există deja suprarezervări de reparat.

**Volumul de `event_seats` la cinema.** Acoperit de D-7 (snapshot leneș) + arhivare.
Dacă tot devine mare: partiționare pe lună a `event_seats`.

**Ceasul dispozitivelor offline.** Acoperit de toleranță, lărgirea ferestrei pe ceas
nesigur, și suprascrierea de operator. Fără cele trei împreună, validarea temporală
va fi cerută dezactivată de personalul de la poartă.

**Aprobarea care blochează operarea.** Dacă separarea din §12.3 nu se face corect, un
muzeu va aștepta un admin ca să-și închidă ziua de Crăciun. Se testează explicit în F6.

**Prețul care nu ajunge pe bilet.** Dacă suprascrierea de preț a sesiunii nu aterizează
în `tickets.price`, comisionul și decontul ies greșite — tăcut, pentru că
`SalesBreakdownService` va citi liniștit o valoare plauzibilă dar veche. E singura cale
prin care sesiunile pot strica banii; de aceea e cerință de acceptanță în F3, nu doar
un test.

**Regenerarea peste sesiuni vândute.** `is_manually_edited` + refuzul ștergerii când
`capacity_sold > 0`. Regenerarea *nu* șterge; marchează ca `orphaned` sesiunile pe
care regula nu le mai produce, și lasă decizia omului.

**Fusuri orare.** Totul se stochează în UTC, se afișează în fusul locației. Ferestrele
se calculează în timp local și se convertesc. Un muzeu care trece la ora de vară nu
trebuie să-și mute sloturile — testele din F4 acoperă exact ziua schimbării.

**Deadlock la dublul plafon.** Ordinea de blocare fixă din §6.1.

**Numele `performances` pentru un slot de muzeu.** Rămâne, cu documentație. Costul
redenumirii (20+ fișiere, patru chei străine, frontend) nu se justifică.

---

## 16. Testare

- **Unitar:** `SessionGenerator` (recurență, sezoane cu wrap-around, excepții,
  prioritate între reguli), `AvailabilityService` (matricea din D-3),
  `SessionPriceResolver` (cascada din §12.5), `ScanValidator` (toate cele opt
  rezultate, plus toleranța de ceas).
- **Concurență:** două holduri simultane pe ultimul loc; POS și online simultan;
  hold + expirare + hold; rambursare în timpul unui checkout.
- **Integrare:** flux complet zi → slot → bilete → plată → bilet cu fereastră →
  scanare validă → scanare duplicat → scanare în afara ferestrei.
- **Onboarding:** un admin creează o locație cu program din ecranul dedicat; apoi
  organizatorul închide o zi din panoul lui, fără ca acțiunea să intre în
  `pending_changes`.
- **Bani:** un bilet cumpărat cu preț de sesiune suprascris produce, în
  `SalesBreakdownService` și în decont, exact suma încasată — nu prețul de catalog.
- **Fus orar:** emitere și scanare peste ziua schimbării orei.
- **Offline:** `offline.test.ts` extins cu ferestre și ceas nesigur.

Suita rulează pe schema redusă din `tests/database/migrations/` (vezi D-002 în
`DECISIONS.md`); migrațiile noi respectă convenția de nume `sessions_` ca să fie
descoperite.

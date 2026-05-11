# Plan — panou admin custom pentru organizator de tip Leisure

## Cerințe utilizator

1. **Sidebar diferit** pentru organizatorii leisure: Evenimente / Participanți / Vânzări / Documente trebuie să arate altfel.
2. **Eveniment unic** afișat (nu listă) — leisure organizer are 1 single event de obicei.
3. **Participanți & Vânzări cu filtre** pe perioade: 7d, 14d, 1l, 3l, 6l, perioadă custom.
4. **Dashboard real-time** — check-in-uri live per poartă, staff alocat live.
5. **POS pentru emitere bilete** din panou + printare termică.
6. **Panoul alocare schimburi** — cine pe ce zi/săptămână, scanare/vânzare/poartă.
7. **Selector "Organizer Type = Leisure"** + selector template pe MarketplaceOrganizer (Filament).

---

## Arhitectura propusă

### A. Identificare organizator leisure

**Modelul:** Pe `MarketplaceOrganizer`:
- Câmp existent `organizer_type` (TextInput în Filament) — adăugăm valoare `'leisure'`
- Câmp nou `leisure_template_variant` (String, default `null`) — pentru variantele de pagină customizate (legat de F4.5)

**Migrație nouă** (aditivă, 1 coloană):
```php
Schema::table('marketplace_organizers', function (Blueprint $table) {
    if (!Schema::hasColumn('marketplace_organizers', 'leisure_template_variant')) {
        $table->string('leisure_template_variant', 32)->nullable()->after('organizer_type');
    }
});
```

**Filament** (`OrganizerResource.php` Marketplace):
- Schimbă `organizer_type` din TextInput în Select cu opțiuni:
  - `'individual'`, `'company'` (default)
  - `'leisure'` — NEW
- Adaugă Select `leisure_template_variant`:
  - `null` (default — standard layout)
  - `'reserve'` (rezervație naturală — Sf. Ana style)
  - `'aquapark'`
  - `'castle'`
  - `'adventure'`
  - `'camping'`
- Vizibil doar când `organizer_type === 'leisure'`

### B. Sidebar condiționat

**În `includes/organizer-sidebar.php`:**
- JS-ul existent (DOMContentLoaded) face fetch `/organizer/me` → primește organizer data
- Verifică `organizer.organizer_type === 'leisure'`:
  - **Dacă leisure:** afișează rute leisure-specific:
    - `🏞️ Eveniment` → `/organizator/leisure-event` (single event view)
    - `👥 Participanți` → `/organizator/leisure-participants` (cu filtre date)
    - `💰 Vânzări` → `/organizator/leisure-sales` (cu filtre date)
    - `📊 Dashboard live` → `/organizator/leisure-dashboard`
    - `🎫 Emitere bilete (POS)` → `/organizator/leisure-pos`
    - `👨‍💼 Echipă & schimburi` → `/organizator/leisure-team`
    - `📝 Conținut pagină` → `/organizator/leisure` (deja există, tab editor)
    - `📄 Documente` → `/organizator/leisure-documents`
  - **Dacă standard:** afișează rutele actuale (events, participanți, vânzări, documente standard).

**Decizia tehnică:** pentru a evita modificări invazive în sidebar PHP, folosesc CSS classes condiționate via JS:
- Link-urile standard primesc `data-org-type="standard"`
- Link-urile leisure primesc `data-org-type="leisure"`
- JS detectează tipul org și hide/show corespunzător

### C. Cele 7 pagini noi (organizer leisure-specific)

```
epas/resources/marketplaces/ambilet/organizer/
├── leisure.php                      (existing — content editor, devine "Conținut pagină")
├── leisure-event.php                (NEW — single event view, fără listă)
├── leisure-participants.php         (NEW — cu filtre date)
├── leisure-sales.php                (NEW — cu filtre date)
├── leisure-dashboard.php            (NEW — real-time live)
├── leisure-pos.php                  (NEW — emitere bilete + print)
├── leisure-team.php                 (NEW — alocare schimburi)
└── leisure-documents.php            (NEW — documente fiscale leisure)
```

Plus .htaccess routes pentru fiecare nou path.

### D. API endpoints noi

**Pe `LeisureController`:**

1. **`GET /organizer/events/{event}/leisure/participants?from=&to=&search=&page=`**
   - Listă participanți (tickets vândute) cu filtre dată + paginare
   - Returnează: ticket code, customer name/email, ticket type, visit_date, status, check-in time, gate

2. **`GET /organizer/events/{event}/leisure/sales?from=&to=&group_by=day|week|month`**
   - Time series vânzări + total per perioadă
   - Returnează: { rows: [{date, count, revenue, by_category, by_issuer}], totals }

3. **`GET /organizer/events/{event}/leisure/dashboard/live`**
   - Snapshot real-time:
     - Check-ins live ultima oră (per poartă)
     - Staff online (last_seen < 5min): cine, unde
     - Total azi: vândut, scanat, revenue, balance fiscal
     - Ocupanța curentă (people inside = checked in - checked out)
   - Folosit cu polling client-side la 10s

4. **`GET /organizer/events/{event}/leisure/team`**
   - Listă membri echipa + shifts asignate (din `team_members` extins cu shift_data JSON)

5. **`POST /organizer/events/{event}/leisure/team/shifts`**
   - CRUD shifts: { member_id, date, start, end, role, gate }

6. **`POST /organizer/events/{event}/leisure/pos/sale`** (deferred from F1)
   - Emite tickets on-site + opțional print receipt
   - Body: { items: [{ticket_type_id, qty}], payment: 'cash|card', email, print: bool }

### E. Dashboard real-time

**UI:** o pagină Tailwind cu grids și widget-uri actualizate live:

- **Banda de sus** (4 stat cards): Azi vândut, Azi scanat, Ocupare curentă, Revenue azi
- **Coloana centrală**: chart live (Chart.js sau echo de la API) — vânzări per ora ultimele 12 ore
- **Coloana dreapta**: 
  - Staff online (lista colorată: gate scanners verde, sales operators albastru, manageri galben)
  - Per poartă: contor check-ins ultima oră + total azi
- **Bottom**: ultimele 10 check-ins / vânzări (stream live, scroll)

**Tehnologii:** vanilla JS cu `setInterval(loadDashboard, 10000)` (polling). Dacă vrem WebSocket adevărat, mai târziu cu Laravel Reverb / Pusher.

### F. POS emitere bilete + printare termică

**UI:** ecran POS optim pentru tablet/touchscreen:

- Grid mare cu ticket types (cu image cover, nume, preț)
- Click → +1 la cantitate
- Sumar lateral cu total + format chitanță
- Buton "Cash" și "Card" pentru încasare
- Optional: input email customer
- Buton "Finalizează & Printează" → submit la API → după success → trimite la imprimantă termică

**Printare termică:**
- **Web printing** prin browser nu poate accesa imprimanta termică direct
- Soluții:
  1. **WebUSB** (Chrome/Edge desktop) — accesează printer USB direct
  2. **Receipt format `text/plain` cu ESC/POS** trimis la driver-ul imprimantei via aplicație helper (Electron wrapper sau Node service local)
  3. **Cea mai pragmatică:** generăm un **PDF de chitanță 80mm** și browser-ul îl trimite la printer cu format Receipt (utilizatorul configurează default printer la imprimanta termică)

Pentru MVP: PDF 80mm cu auto-print (`window.print()` cu CSS @media print 80mm width).

**API endpoint:** `POST /organizer/events/{event}/leisure/pos/sale` returnează ID-ul ordinului. Front-end navighează la `/organizator/leisure-pos-receipt?order={id}` care e o pagină print-friendly + auto-`window.print()`.

### G. Panou alocare schimburi

**Modelul** (NEW table sau extend pe `team_members.shift_data` JSON):
```json
{
  "shifts": [
    {
      "id": "uuid",
      "date": "2026-05-15",
      "start": "09:00",
      "end": "17:00",
      "role": "gate_scanner",
      "gate": "Poarta A",
      "notes": "Schimb dimineață"
    }
  ]
}
```

**UI:** o vedere calendar (gen Google Calendar style) cu:
- Coloane: zile (default 7 zile, navigable)
- Rânduri: membri echipa
- Celule: shift bars colorate per rol
- Click pe celulă goală → modal "Adaugă shift" (start/end/role/gate)
- Drag&drop pentru mutare/redimensionare shifts
- Vedere week / day toggle

**Tehnologii:** vanilla JS + Tailwind grid + Alpine.js pentru state. Pentru drag&drop, folosesc Sortable.js (CDN).

---

## Estimări

| Feature | Estimare |
|---|---|
| F5.1 fix `/organizator/leisure` API + selector organizer_type Leisure | 0.5 zi |
| F5.2 sidebar condiționat + rute .htaccess | 0.5 zi |
| F5.3 leisure-event.php (single event view) | 0.5 zi |
| F5.4 leisure-participants.php cu filtre | 0.5 zi |
| F5.5 leisure-sales.php cu filtre | 0.5 zi |
| F5.6 leisure-dashboard.php real-time | 1-1.5 zile |
| F5.7 leisure-pos.php + chitanță 80mm | 1.5-2 zile |
| F5.8 leisure-team.php alocare schimburi | 1.5-2 zile |
| F5.9 API endpoints (participants, sales, dashboard, team, pos) | 2 zile |
| **Total F5 complet** | **~10 zile** |

---

## Implementare iterativă propusă

**Faza 1 (NOW)** — esențial pentru a putea valida arhitectura:
- F5.1: fix bug API + selector organizer_type Leisure + leisure_template_variant
- F5.2: sidebar condiționat (fără ascundere — adăugare linkuri leisure în plus)

**Faza 2** — single event + raportare:
- F5.3: leisure-event.php (mut overview-ul actual din `leisure.php` aici)
- F5.4 + F5.5: participants + sales cu filtre

**Faza 3** — real-time:
- F5.6: dashboard live
- API endpoints aferente

**Faza 4** — operations:
- F5.7: POS + print
- F5.8: team scheduling

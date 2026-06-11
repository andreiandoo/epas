# Plan — Bilete pachet & reduceri condiționate

## 1. Bilete pachet (family, grup)

### Problemă

Un singur "bilet" trebuie să **emită multiple bilete fizice** la check-in:
- "Pachet Familie" = 2 adulți + 1 copil → la cumpărare → 3 QR-uri separate
- "Pachet Grup 10" = 10 persoane → 10 QR-uri

Fiecare bilet emis ar trebui să poată fi scanat independent la intrare.

### Două abordări (alege una)

#### **Opțiunea A — "Pachet ca container, N bilete fizice emise la cumpărare"** (recomandat)

Modelarea naturală pentru ticketing.

**Modelul TicketType:**
- Câmp nou `meta.package_outputs` (JSON array):
  ```json
  [
    { "ticket_type_id": 5001, "qty": 2, "label": "Adult" },
    { "ticket_type_id": 5002, "qty": 1, "label": "Copil" }
  ]
  ```
- TicketType-urile cu `package_outputs` non-vid devin "containers" — la cumpărare:
  1. Ordinul include 1× "Pachet Familie" (linia de comandă vizibilă în factură)
  2. Sistemul **emite automat** N tickets reale (3 în exemplu), legate la order, cu:
     - `parent_ticket_id` → biletul-pachet
     - `code` / `barcode` unic per ticket (QR scanabil independent)
     - `valid_date` = visit_date din order_meta
     - `ticket_type_id` = output type (Adult / Copil)
     - eventual `label` ("Adult #1", "Adult #2", "Copil #1") setat pe meta

**Avantaje:**
- Scanare independentă fiecare (1/3, 2/3, 3/3 la check-in)
- Cumpărătorul primește un singur PDF / email cu 3 QR-uri
- Stocarea biletelor în DB respectă schema actuală (1 row per emis ticket)
- Facturarea / fiscalitatea: 1 linie "Pachet Familie 130 RON" (sau N linii cu prețul split? — alegere fiscală)

**Dezavantaje:**
- Necesită modificări la `OrderConfirmationJob` / wherever generăm tickets după plată
- Migrație nu e neapărat necesară (folosim `parent_ticket_id` deja existent dacă da, sau adăugăm coloana)

**Implementare estimată:**
- ~1 zi Filament UI (Repeater pe meta.package_outputs cu Select ticket_type_id existent)
- ~1 zi Order ticket generation logic (cu DB::afterCommit, fan-out la fiecare order item cu pachet)
- ~0.5 zi UI public (afișaj clar "Acest pachet include: 2× Adult + 1× Copil" sub bilet)
- ~0.5 zi PDF email (3 QR-uri unul după altul)
- ~0.5 zi tixello-app scanare (UI care arată progress pe pachet "Adult 1/2 scanat")

**Total: ~3-4 zile**

#### **Opțiunea B — "Bilet single cu N intrări"** (simplu, mai puțin flexibil)

Un singur ticket cu `max_checkins = N`.

- Câmp nou pe TicketType: `meta.max_checkins = 3` (default 1)
- La scanare: contor de check-in-uri pe ticket; permite N scanări, apoi blocat
- Cumpărătorul primește 1 QR

**Avantaje:**
- Implementare ~1 zi (un singur câmp + logică scan)
- Backwards compatible

**Dezavantaje:**
- Toți utilizatorii pachetului trebuie să intre **împreună** (sau să-și pasereze biletul) — nu pot intra separat
- La scanare e ambiguu cine intră ("e adult sau copil?")
- Fără tracking individual per persoană

### Recomandare: **Opțiunea A** pentru evenimente fizice cu intrări multiple, B pentru promovări simple ("acces ne-limitat în 24h").

---

## 2. Reduceri condiționate (cross-product)

### Problemă

Reduceri când utilizatorul cumpără combinații specifice:
- "2+ bilete acces + 1+ rental → -10% pe tot"
- "Pachet Familie + Coș picnic → -20% la coș picnic"
- "Cumperi camping + activitate → -15 RON fixed"

### Abordare propusă

**Modelul:** câmp nou pe `events.venue_config.bundle_discounts` (JSON array):

```json
"bundle_discounts": [
  {
    "name": "Familie + picnic = 20% la picnic",
    "trigger": {
      "all": [
        { "service_category": "access", "min_qty": 2 },
        { "ticket_type_id": 5050, "min_qty": 1 }
      ]
    },
    "discount": {
      "type": "percent",
      "value": 20,
      "applies_to": "ticket_type_id:5050"
    }
  },
  {
    "name": "Camping + activitate = -15 RON pe total",
    "trigger": {
      "all": [
        { "ticket_type_id": 5100, "min_qty": 1 },
        { "service_category": "activity", "min_qty": 1 }
      ]
    },
    "discount": {
      "type": "fixed",
      "value": -15,
      "applies_to": "order_total"
    }
  }
]
```

**Trigger logic:**
- `all` — toate condițiile trebuie să se aplice (AND)
- `any` — cel puțin una (OR) — pentru viitor
- Fiecare condiție: `service_category` SAU `ticket_type_id`, plus `min_qty`

**Discount apply targets:**
- `order_total` — reducere pe totalul ordinului
- `ticket_type_id:X` — reducere pe biletul X (din ordin)
- `category:X` — reducere pe toate biletele cu categoria X

**Logica evaluare:**
- La fiecare modificare cart (client-side în Alpine + server-side la checkout)
- Pentru fiecare bundle_discount, verifică trigger.all/any
- Dacă match → aplică discount-ul cu prioritate (cel mai mare câștigă, sau cumulative — alegere)

**UI Filament:** Section nouă în "Configurare Locație" → "Reduceri pachet":
```php
Forms\Components\Repeater::make('venue_config.bundle_discounts')->schema([
    TextInput::make('name'),
    Repeater::make('trigger.all')->schema([
        Select::make('match_by')->options(['category', 'ticket_type_id']),
        Select::make('category')->visible(fn($get) => $get('match_by')==='category'),
        Select::make('ticket_type_id')->visible(fn($get) => $get('match_by')==='ticket_type_id'),
        TextInput::make('min_qty')->numeric()->default(1),
    ]),
    Select::make('discount.type')->options(['percent', 'fixed']),
    TextInput::make('discount.value')->numeric(),
    Select::make('discount.applies_to')->options(['order_total', 'category:X', 'ticket_type_id:X']),
]);
```

**UI public:** card "Reducere activă! -20% pentru Familie + picnic" în coș, vizibil când trigger e satisfăcut.

**Estimare:**
- 1 zi: schema + Filament UI editor (Repeater de Repeater)
- 0.5 zi: serializare + cast pe Event
- 1 zi: client-side evaluator în Alpine (recalcul cart total la fiecare schimbare)
- 1 zi: server-side validator la checkout (DA, e CRITIC să fie verificat și pe server)
- 0.5 zi: afișaj în coș + email confirmare

**Total: ~3-4 zile**

---

## 3. Bug `/cos` cu `NaN lei` și `[object Object]`

### Cauza

Alpine.js folosește Proxy reactive pentru data binding. Când fac `AmbiletCart.addItem(eventPayload, t, t.qty, ...)`:

- `t.qty` e un getter pe un Proxy → `toString()` returnează `[object Object]` în loc de un număr
- `parseFloat(t.effective_price)` poate eșua dacă `t` e Proxy → `NaN`
- `localStorage.setItem(key, JSON.stringify(...))` la rândul lui poate serializa Proxy ca `{}` (key-uri visible) sau `[object Object]`

### Fix aplicat în `leisure-venue.php` (LIVE după deploy)

În `checkout()`, construiesc **plain primitives** explicit:

```js
const eventPayload = {
    id: Number(EVENT.id) || 0,
    title: String(EVENT.name || ''),
    slug: String(EVENT.slug || ''),
    image: String(EVENT.image || ''),
    visit_date: String(this.selectedDate),
};
const ticketPayload = {
    id: Number(t.id) || 0,
    name: String(t.name || ''),
    price: Number(parseFloat(t.effective_price)) || 0,
    // ...
};
const qty = parseInt(t.qty, 10) || 0;
AmbiletCart.addItem(eventPayload, ticketPayload, qty, {...});
```

Toate convertite la `Number()` / `String()` / `Boolean()` pentru a "scoate" valoarea din Proxy.

După deploy + Ctrl+Shift+R + curățare localStorage (sau pur și simplu `/cos` cu cart curent va arăta corect doar pentru NEW items adăugate; cele vechi rămân stricate — clear cart manual din UI).

---

## Recomandare prioritizare

1. **NOW** — fix bug `/cos` (deja făcut, deploy + test)
2. **F5** — panou self-service organizator (deja planificat)
3. **F4.3** — bilete pachet (Opțiunea A) — feature business critic
4. **F4.4** — reduceri condiționate — feature de marketing
5. **F4.5** — refactor template-uri customizabile per client
6. **F6** — mobile app (după ce backend e stabilizat cu pachete + reduceri)
7. **F7** — InvoiceSplitter + e-Factura

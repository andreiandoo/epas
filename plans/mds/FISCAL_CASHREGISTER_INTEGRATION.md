# Integrare Case de Marcat Fiscale în POS

**Status:** 🟡 Fază 0 — descoperire
**Started:** 2026-07-31
**Owner:** Andrei
**Client-pilot:** Sf. Ana (Lacul Sfânta Ana, `event_id=4234`)

Document viu — se actualizează la fiecare pas important. Ordine cronologică (jos = mai recent).

---

## 🎯 Obiectiv

La `Finalizează` din `/organizator/leisure-pos`, pe lângă factura curentă, sistemul trebuie să:
1. Trimită automat produsele vândute către casa de marcat fiscală
2. Casa de marcat să emită bon fiscal fără ca operatorul InfoPoint să introducă manual codurile

**Global:** arhitectura trebuie să suporte **mai multe modele de casă** (Partner 200 e primul; următoarele vor fi Datecs / Tremol / etc.).

---

## 📋 Context tehnic

### Client-pilot Sf. Ana (2026-07-31)
- Casă fizică prezentă: **Partner 200** (Partner Corporation SRL București)
- **Casa este DEJA FISCALIZATĂ** — nu putem obține una nefiscalizată pentru dev
- **1 stație InfoPoint** singură
- **PC Windows** (versiune de confirmat)
- **Dev in-house** (nu integrator extern)
- **Fără presiune de deadline** — putem lucra iterativ

### Sistem POS actual (baseline)
- Pagina: `/organizator/leisure-pos` (`epas/resources/marketplaces/ambilet/organizer/leisure-pos.php`)
- Flow finalizare: `checkout()` @ `leisure-pos.php:1272` → `POST /leisure/pos-sale` (`LeisureController@posSale` @ line 960) → response cu order + tickets
- Print curent: **factura ESC/POS** via WebUSB thermal printer (`pos-printer.js`, PosPrinter object @ line 737, filtre WebUSB @ 27-36)
- **Fără cod fiscal per produs** azi în model (`TicketType` model n-are `fiscal_code`)
- **Fără config casă** azi în model (`MarketplaceOrganizer` n-are `fiscal_device_config`)

---

## 📖 Analiza inițială (2026-07-31)

### Partner 200 — capabilități

**Producător:** Partner Corporation SRL București (importator; casa e OEM chinezesc — semne Sam4s/Datecs-clone).

**Interfețe fizice:**
- `1× USB-Mini` — CDC-ACM (serial peste USB); *"destinat exclusiv conectării la PC. Nu se pot conecta scannere, unități de stocare, printer"* (§1.6)
- `1× RS232 (DB9)` — 115200 baud fix; folosit pentru periferie SAU pentru conectare alternativă la PC (§1.6)
- `1× RJ11` — sertar 12-24V, 1A
- **NU are Ethernet, LAN, WiFi, Bluetooth, TCP/IP native**
- **GPRS** — menționat în §5.1.2 pentru raportare Z automată, dar nespecificat clar pentru Partner 200 — trebuie confirmat

**Suport PC — declarat:**
- §1.7: *"Casa poate fi conectată la PC pentru programare, obținerea de rapoarte sau utilizarea ca Imprimantă Fiscală. PARTNER vă oferă un pachet de programe."*
- §1.1 accesorii: `PC Manager` + `Driver imprimantă fiscală`
- §5.1.3 `PERIODIC LA PC` — extragere rapoarte fiscale

**NU documentează:**
- Protocolul de comenzi
- Formatul de import bulk PLU
- OS-uri suportate de driver
- SDK / exemple de cod integrare
- Dacă protocolul e restricționat de driver oficial sau se poate vorbi direct pe serial

**Limitări hardware:**
- Max **3000 PLU** (§ menționată la 156)
- Max **10 departamente**
- Max **4 cote TVA** programabile (§534-540)
- Max **120 articole/bon** — eroare 11 la depășire (§1156)

### Cadru legal RO
- Bonul fiscal e emis DIRECT de AMEF certificată — **nu-l putem genera extern**
- Rolul integrării = trimitem "vinde X buc PLU Y" → casa printează cu numărul propriu
- Sigiliul fiscal + certificarea: integrare permisă doar cu driver-ul autorizat
- **Bypass-ul driverului = risc invalidare certificare** (interzis)
- Fiscalizarea = proces one-way; casa nefiscalizată → bonuri "BON NEFISCAL" nelimitat

---

## 🏗️ Variante arhitectură — evaluate

### V1 — Browser WebUSB direct la casă → ❌ RESPINS
**Motiv:** protocolul nu e documentat + risc invalidare certificare AMEF + probabil driver Windows-only pe care WebUSB nu-l poate folosi.

### V2 — Agent local pe PC → DLL oficial → Casă → ✅ RECOMANDAT
**Flow:**
```
[Browser POS]
     │  fetch http://127.0.0.1:9101/print-receipt (JSON items+PLU)
     ▼
[Agent local Windows (Node/Electron)]
     │  invocă DLL oficial Partner via node-ffi / edge-js / subprocess CLI
     ▼
[Driver oficial Partner] → USB CDC-ACM → [Casa Partner 200]
     │
     ▼
BON FISCAL PRINTAT
```

**Avantaje:**
- Driver oficial certificat → **fără impact pe certificarea AMEF**
- Izolare curată: bilete rămân pe WebUSB thermal actual; casa e canal separat
- Failover: agent down / casă offline → toast + POS continuă (bilete + factură merg)
- Extensibil multi-vendor (agent poate încărca driver Datecs / Tremol în viitor)

**Dezavantaje:**
- Build + deploy + auto-start agent pe stația InfoPoint (Windows service via `nssm` sau instalator MSI)
- Update-uri distribuite (dacă nu adăugăm auto-update)
- **HTTPS→HTTP mixed content** — Chrome blochează `fetch` din pagină HTTPS către `http://localhost` (soluții mai jos)

### V3 — Gateway Ethernet-Serial → ❌ Overkill
Adaptor hardware ~200€, pierdem mobilitatea casei portabile, tot avem nevoie de protocol.

### V4 — Watch folder export → ❌ Nu e documentat
Partner 200 nu menționează spool/import file mode.

---

## 🌐 Arhitectură globală multi-vendor (KEY DECISION)

**Requirement**: sistemul TREBUIE să suporte mai multe modele de casă în timp (Partner 200 e primul; urmează Datecs, Tremol, etc.).

### Design abstracție

**Backend (Laravel):**
```
app/Services/Fiscal/
  ├── FiscalDeviceInterface.php     ← contract abstract (methods: programPlu, printReceipt, closeDay, ping)
  ├── FiscalDeviceRegistry.php      ← rezolvă tipul din config
  ├── Devices/
  │    ├── Partner200Adapter.php    ← implementare pentru Partner 200 (protocol payload)
  │    ├── DatecsAdapter.php        ← viitor
  │    └── TremolAdapter.php        ← viitor
  └── FiscalReceiptOrchestrator.php ← invocat de posSale, alege adapter din organizer.fiscal_device_config
```

**Agent local (Node/Electron):**
```
partner-fiscal-agent/
  ├── src/
  │    ├── server.js                ← HTTP loopback (Express)
  │    ├── devices/
  │    │    ├── partner200.js       ← driver bridge (Partner DLL via node-ffi-napi)
  │    │    ├── datecs.js           ← viitor
  │    │    └── tremol.js           ← viitor
  │    └── router.js                ← primește payload, alege device driver
  ├── config.json                   ← { device_type, com_port, dll_path }
  └── package.json
```

**Payload standardizat browser → agent** (device-agnostic):
```json
{
  "device_type": "partner_200",
  "operation": "print_receipt",
  "items": [
    { "plu_code": 101, "name": "Adult", "qty": 2, "unit_price": 45.00, "vat_group": 1 },
    { "plu_code": 205, "name": "Copil", "qty": 1, "unit_price": 20.00, "vat_group": 1 }
  ],
  "payment": { "method": "cash", "amount": 110.00 },
  "order_ref": "MKT-XYZ123",
  "operator_ref": "InfoPoint"
}
```

**DB schema (tenant-agnostic):**
```sql
ALTER TABLE ticket_types
  ADD COLUMN fiscal_plu_code SMALLINT NULL,       -- 1..N (N = limita device-ului)
  ADD COLUMN fiscal_vat_group SMALLINT NULL DEFAULT 1;

ALTER TABLE marketplace_organizers
  ADD COLUMN fiscal_device_config JSONB NULL;
-- ex Partner 200:
-- { "type":"partner_200", "agent_url":"http://127.0.0.1:9101",
--   "device_serial":"AB1234567890", "vat_map":{"19":1,"9":2,"5":3,"0":4},
--   "max_plu":3000, "max_items_per_receipt":120 }
-- ex Datecs (viitor):
-- { "type":"datecs_dp25", "agent_url":"http://127.0.0.1:9101", ... }
```

**Rezultat:** aceleași cod backend + frontend, doar `type` diferă în config; adapter-ul potrivit se apelează polimorfic.

---

## 🔬 Testare pe casă fiscalizată (2026-07-31)

**Constrângere reală:** Sf. Ana are DOAR casa fiscalizată; Partner Corporation improbabil să dea una nefiscalizată gratuit.

**Ce se poate testa pe casa fiscalizată fără să emitem bonuri reale:**

| Test | Se poate? | Cum |
|---|---|---|
| Conectare USB + identificare device | ✅ | Windows Device Manager arată VID/PID + numele driver-ului |
| Handshake protocol / ping / read status | ✅ | Comenzi de query nu emit bon |
| Programare PLU (upload lista articole) | ✅ | Modifică memorie de lucru, nu MF; se poate rescrie |
| Citire rapoarte X (fără reset) | ✅ | Read-only, nu afectează MF |
| **Emitere bon fiscal REAL** | ⚠️ | **Emite bon fiscal real 0.01 RON** — merge în raportul Z al zilei; se poate anula prin storno în aceeași sesiune |
| Reset raport Z | ❌ | Golește memoria de lucru + scrie în MF; irrevocabil |

**Strategii de testare recomandate:**

1. **Testare de dezvoltare la Sf. Ana** — dacă îi conving pe operatori: emitem bonuri de 0.01 RON pe categorie "test" cu storno imediat în sesiunea următoare. Nu contaminăm raportările reale.
2. **Casa dev separată nefiscalizată** — cerem lui Partner Corporation dacă are showroom/demo unit disponibilă pentru împrumut. Uneori dealerii au unități pentru training.
3. **Nu emitem bonuri până la testul de acceptanță final** — Fazele 0-2 (descoperire + agent local + backend) se pot face fără nicio emisie: doar handshake + programare PLU + citire status. Doar Faza 3 (frontend hook) declanșează bonul real; îl facem cu suma minimă la sfârșit de zi + storno.

**Concluzie:** DA, se poate lucra pe casa fiscalizată, dar cu **regim strict de disciplină**:
- Zero emisii până la final
- Când testăm bon: suma minimă (0.01) + storno imediat
- Ideal: convingem Partner Corporation să dea o demo unit pentru dev

---

## 📞 Ce cer de la Partner Corporation

### Contact
Partner Corporation SRL București
**Tel:** 021-224.09.95
**Web:** https://www.partner.ro
**Email de găsit** — de contactat prin telefon dacă nu e vizibil pe site

### Cerere formală (template)

> **Subiect:** Solicitare documentație tehnică integrare Partner 200 cu POS propriu (Ambilet — event ticketing)
>
> Bună ziua,
>
> Ne numim [Nume firmă] și operăm platforma de ticketing Ambilet (ambilet.ro), cu contract în derulare pentru locația **Lacul Sf. Ana**. Operatorul locației folosește o casă de marcat **Partner 200** deja fiscalizată, achiziționată de la dvs. (număr serie: **[X]**, achiziție **[data]**, contract service **[Y]**).
>
> Dorim să integrăm casa de marcat Partner 200 cu sistemul nostru POS web (rulat pe PC Windows la locație) astfel încât la finalizarea unei vânzări în POS, casa să emită **bon fiscal automat** — fără ca operatorul să reintroducă manual codurile de produse.
>
> Pentru aceasta avem nevoie de următoarele:
>
> **1. Software:**
>    - Aplicația "**PC Manager**" pentru Partner 200 (versiune curentă + instalator)
>    - Driver-ul "**Imprimantă Fiscală**" pentru Windows + fișierul `.inf` de instalare
>    - Instrucțiuni de instalare + confirmare compatibilitate Windows 10/11 (64-bit)
>
> **2. Documentație tehnică integrare:**
>    - **Manual protocol comunicare** (formatele comenzilor pentru: deschidere bon, vânzare articol, aplicare discount, închidere bon cu cash/card/tichet, storno)
>    - **SDK / API** — biblioteci `.dll` (COM/ActiveX) + documentație funcții publice, cu exemple de cod (VB/C# / C++ / orice limbaj)
>    - Format import bulk PLU (upload lista articole — până la 3000)
>    - Comenzi de query: status casă, ultima eroare, număr bon curent, versiune firmware
>    - **VID/PID USB** al Partner 200 pentru identificare device
>
> **3. Confirmări legale (CRITIC):**
>    - Confirmare scrisă că **integrarea prin driver-ul oficial NU invalidează certificarea AMEF** a casei
>    - Modelul Partner 200 are modul **GPRS pentru raportare Z automată către ANAF**? Dacă nu, cum se transmite Z-report ANAF?
>
> **4. Suport dev:**
>    - Există o unitate demo/showroom **nefiscalizată** disponibilă pentru împrumut/închiriere pe durata dezvoltării (2-3 luni)? Alternativ: recomandare pentru procurare unitate de test.
>    - Există alte firme integratoare care au făcut deja acest tip de integrare cu Partner 200 și pe care ni le puteți recomanda?
>
> **5. Comercial:**
>    - Costul software-ului (dacă e licențiat) — PC Manager, Driver, SDK
>    - Suport tehnic pe durata integrării (telefon / email / on-site) — cost per intervenție
>    - Există contract dedicat pentru integratori software?
>
> Vă mulțumim, rămân în așteptarea răspunsului cât mai curând posibil.
>
> **Contact tehnic:** [nume, email, telefon]

**Prioritate cerere:** cel mai important e **§2 (SDK + protocol) + §3 (certificare)**. Restul e nice-to-have.

**Timeline așteptat:**
- Răspuns telefonic imediat: 1-2 zile lucrătoare
- Răspuns cu SDK + docs: 3-10 zile lucrătoare
- Dacă nu răspund în 5 zile lucrătoare → escaladare la integrator autorizat (Nowytech, Total Fiscal)

---

## 🗓️ Plan de execuție (fazat)

### Faza 0 — Descoperire (blocker) — ETA 1-2 săpt.
- [x] Analiză manual + arhitectură
- [ ] Contact Partner Corporation cu cererea de mai sus
- [ ] Identificare OS Windows exact pe stația Sf. Ana (10 sau 11, 32 sau 64 bit)
- [ ] Identificare serie casă de marcat + contract service (pentru cerere Partner)
- [ ] Confirmare canal de test (unitate dev demo sau accept storno pe casa fiscalizată)
- [ ] Recepție SDK/DLL + protocol docs
- **Gate:** avem SDK + protocol + confirmare certificare

### Faza 1 — Agent local prototype — ETA 1 săpt. (după Gate 0)
- [ ] Node.js + Express + node-ffi-napi (SAU Electron dacă DLL are UI)
- [ ] Endpoint-uri: `GET /health`, `POST /program-plu`, `POST /print-receipt`, `POST /close-day`
- [ ] Adapter pattern (`devices/partner200.js`) — abstracție reutilizabilă
- [ ] Test manual (Postman/curl): handshake, upload 5 PLU dummy, emitere bon test 0.01 RON, storno
- [ ] Packaging Windows service via `nssm` + auto-start la boot
- **Gate:** bon fiscal test emis end-to-end în laborator

### Faza 2 — Backend Laravel — ETA 3-4 zile
- [ ] Migrare DB: `ticket_types.fiscal_plu_code`, `ticket_types.fiscal_vat_group`, `marketplace_organizers.fiscal_device_config`
- [ ] Filament TicketType: câmpuri noi (validare unicitate PLU per event)
- [ ] Filament Organizer: pagină "Casă de marcat fiscală" (config)
- [ ] Servicii backend: `FiscalDeviceInterface` + `Partner200Adapter` + `FiscalReceiptOrchestrator`
- [ ] `posSale` response include `fiscal_plu_code` + `fiscal_vat_group` per item
- [ ] Endpoint nou `POST /organizer/fiscal/sync-plu` (bulk upload către agent → casă)

### Faza 3 — Frontend hook — ETA 2-3 zile
- [ ] În `leisure-pos.php` `checkout()` — după bilete auto-print (linia 1338), pas nou `posFiscalReceipt(data)` non-blocking (timeout 3s)
- [ ] Buton "Test bon fiscal" în panoul WebUSB (analog `lv-checkout-test`)
- [ ] Indicator status casă în header (verde=online, roșu=offline, gri=neconfigurat)
- [ ] Retry manual din admin dacă bonul a eșuat (config prin `orders.meta.fiscal_receipt_status`)

### Faza 4 — Rollout controlat — ETA 1 săpt.
- [ ] Deploy la Sf. Ana pe 1 stație
- [ ] Rulare paralelă 3-5 zile
- [ ] Cron reconciliere Z-report vs `LeisureCashierSession`
- [ ] Alertă email la discrepanțe

**Total: 4-5 săpt. dacă Faza 0 nu se blochează.**

---

## ⚠️ Riscuri active

| Risc | P | I | Mitigare | Status |
|---|---|---|---|---|
| Partner Corporation refuză SDK sau taxează scump | M | Î | Plan B integrator autorizat (~500-1500€) | 🟡 open |
| Driver Windows-only și PC-ul e alt OS | S | M | Confirmat Windows ✓ | ✅ mitigat |
| Chrome blochează HTTP loopback din HTTPS | Î | M | Agent HTTPS cert self-signed pre-instalat SAU Chrome flag `--unsafely-treat-insecure-origin-as-secure` pe origin | 🟡 open |
| Casa offline la finalizare (baterie, hârtie) | M | S | Non-blocking; `orders.meta.fiscal_receipt_status=failed` + retry manual | 📋 planned |
| GPRS Z-report ANAF nu există pe Partner 200 | ? | Î | De confirmat cu Partner | 🟡 open |
| Certificarea AMEF invalidată de bypass driver | S | Critic | STRICT V2 (driver oficial), zero WebUSB direct | ✅ decis |
| Testare emite bonuri reale (fără dev unit) | Î | M | Suma minimă 0.01 + storno + convingere Partner pt demo unit | 🟡 open |

*P = Probabilitate, I = Impact*

---

## 📎 Fișiere-cheie referite

**POS actual:**
- `epas/resources/marketplaces/ambilet/organizer/leisure-pos.php` — `checkout()` @ line 1272, `posFiscalReceipt` hook to be added
- `epas/app/Http/Controllers/Api/MarketplaceClient/Organizer/Leisure/LeisureController.php` — `posSale()` @ line 960
- `epas/resources/marketplaces/ambilet/assets/js/pos-printer.js` — `PosPrinter` @ line 737 (referință pattern WebUSB, dar NU refolosit pentru Partner 200)
- `epas/app/Models/MarketplaceOrganizer.php` — extindere cu `fiscal_device_config`
- `epas/app/Models/TicketType.php` — extindere cu `fiscal_plu_code`, `fiscal_vat_group`

**Manual Partner 200:**
- PDF: `i:/WORK/eventpilot/PARTNER_200_Manual_Utilizare.pdf`
- Text extras: `C:/Users/PC/AppData/Local/Temp/claude/i--WORK-eventpilot/f6908f99-00b4-4753-af8c-df30994cd3e9/scratchpad/partner200.txt`
- Secțiuni cheie: 1.1 (accesorii), 1.6 (interfețe), 1.7 (conectare PC), 5.1 (rapoarte)

---

## 📝 Log decizii

### 2026-07-31 — Kickoff
- Client-pilot: Sf. Ana
- Confirmat: Windows, 1 stație, casă fiscalizată prezentă, dev in-house, fără deadline
- **Decizie arhitecturală:** V2 (agent local + driver oficial) — respinse V1/V3/V4
- **Decizie strategică:** design multi-vendor de la început (adapter pattern) — nu hardcodăm Partner 200
- **Decizie test:** vom lucra pe casa fiscalizată cu regim strict (bonuri 0.01 RON + storno), în paralel cerem Partner o unitate demo dev
- **Next step:** email Partner Corporation

<!-- Adaugă note noi mai jos la fiecare pas -->

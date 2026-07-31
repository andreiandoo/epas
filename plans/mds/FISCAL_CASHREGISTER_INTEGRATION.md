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

## 🔬 Cercetare piață RO (2026-07-31)

3 agenți paraleli au făcut cercetare web pe branduri populare RO + landscape general. Rezultate în ordinea importanței:

### 🚀 DESCOPERIRI CHEIE (impactează arhitectura)

1. **`ErpNet.FP`** — server HTTP JSON open-source **BSD-0** (zero clauze) care acoperă deja **Datecs (DP-25, WP-50, FP-700X) + Tremol (M20, S25)** — [github.com/erpnet/ErpNet.FP](https://github.com/erpnet/ErpNet.FP). Cross-platform (Win/macOS/Linux/ARM). Endpoint pattern: `POST /printers/{id}/receipt`. **Practic zero cod protocol de scris** — apelezi din Laravel prin HTTP.

2. **`FiscalNet`** (Criasoft) — driver universal comercial care acoperă **8 mărci RO** printr-un singur API HTTP GET/POST: Datecs, Daisy, Custom, Olivetti, Orgtech, **Partner**, Sam4S, Tremol, Incotex — [driverfiscal.ro](https://driverfiscal.ro). Licență plătită (~zeci €/lună per stație).

3. **`ZFPLabServer` (Tremol nativ)** — server local HTTP JSON pe port 4444, gratuit, fără NDA, expus de driver-ul oficial Tremol — [tremol.bg/en/support/zfplab](https://tremol.bg/en/support/zfplab). SDK oficial suportă: C++, C#, .NET Standard 2.0, Delphi, Java, JavaScript, **PHP**, Python, VB + platforme Windows/Linux/Android/Raspberry Pi.

4. **`AMEF-V`** (Aparat de Marcat Electronic Fiscal **Virtual**) — cadru legal nou 2025-2026: aplicație SW certificată ANAF poate înlocui hardware-ul fizic — [profit.ro](https://www.profit.ro/perspective/schimbari-legislative-pentru-firme/casele-de-marcat-fizice-conectate-la-anaf-vor-putea-fi-inlocuite-cu-o-aplicatie-software-nou-cadru-legislativ-22267036). Long-term opportunity: Ambilet ar putea deveni operator SW dacă obține certificarea.

5. **Bon fiscal digital + QR obligatoriu** — până **1 noiembrie 2026** — [selectsoft.ro](https://selectsoft.ro/modificari-legislative-bon-fiscal-digital/). Casa noastră trebuie actualizată de service Partner.

6. **Partner 200 e arhitectură FILE-DROP** (nu DLL COM cum credeam inițial!) — Partner driver funcționează prin scriere `cashfile.inp` → citește `bon.log` răspuns. Practic: `file_put_contents()` din PHP direct, fără node-ffi/edge-js. **Simplifică radical Faza 1.**

7. **Cost driver Partner: 60 RON/casă fiscalizată** (licență) — [microerp.ro/driver-partner](https://www.microerp.ro/driver-partner/) — Partner Corp în parteneriat cu EconMedia Software. Foarte accesibil.

---

### 🟢 Datecs (Bulgaria, distribuit RO)

**Poziționare piață RO:** liderul pieței RO (~35-45% estimat). Prezent din 1996, în majoritatea afacerilor din RO ([datecs.ro](https://www.datecs.ro/)).

**Modele frecvente (post-2018, ANAF-compliant cu modem):**
- **DP-25 MX** — tejghea compactă, retail mic-mediu, 33 taste, 100.000 articole, modem 2G integrat. Cel mai comun model.
- **DP-05 MX** — portabilă mică, piețe / retail mobil
- **WP-50 MX** — portabilă flagship, standardul pentru livratori / vânzare ambulantă
- **WP-500 / WP-500X** — portabilă robustă, tastatură completă
- **FP-700 / FP-700X / FP-700XE** — imprimantă fiscală tejghea (USB 2.0, RS-232, LAN, GPRS, microSD)
- **FMP-350 / FMP-350X** — imprimantă fiscală portabilă Bluetooth, „braț fiscal" pentru POS Android
- **FMP-55X** — imprimantă fiscală compactă

**Protocol comunicare:** „Datecs Fiscal Printer Protocol" (familia BG-ISL), documentat public pe datecs.bg. 3 sub-dialecte: `bg.dt.c.isl` (clasic), `bg.dt.p.isl` (portabile DP/WP), `bg.dt.x.isl` (seria X 2018+). Pachete binare cu length + checksum, câmpuri comma-delimited. PDF-uri publice: [UI_Communication_protocol_FISCAL_PRINTERS_BP_EN.pdf](https://www.datecs.bg/en/downloads/pdf?id=UI_Communication_protocol_FISCAL_PRINTERS_BP_EN.pdf), [PM_FMP350X_FMP55X_FP700X_FPprotocol_v2.00_eng.pdf](https://www.datecs.bg/en/downloads/pdf?id=PM_FMP350X_FMP55X_FP700X-BUL_+FPprotocol_v2.00_eng.pdf).

**Fizic:** USB via cablu Datecs propriu (chip CH340/FTDI intern, expus ca **virtual COM port**, NU CDC-ACM nativ), RS-232, Ethernet LAN (FP-700X), Bluetooth 2.0/3.0 opțional (FMP-350), GPRS integrat pe toate MX/X.

**Integrare SW:**
- **Driver oficial**: FiscalNet Datecs (Danubius Exim) — [datecs.ro/driver-fiscalnet-datecs.html](https://www.datecs.ro/driver-fiscalnet-datecs.html). Licență **anuală ~121 RON/casă**.
- **Alternative comerciale**: Fisco (EconMedia) — HTTP JSON + folder scan, **licență unică 119 RON** — [fisco.ro](https://fisco.ro/).
- **Open-source (RECOMANDAT)**: `ErpNet.FP` (BSD-0) — HTTP JSON, transport COM/BT/TCP.
- Alternativă open-source: `FPGate` (Java) — [github.com/edabg/fpgate](https://github.com/edabg/fpgate).
- **DUDE** — oficial Datecs, gratis, DAR doar interfață de vânzare (NU SDK) — [datecs.ro/dude.html](https://www.datecs.ro/dude.html).

**Certificare RO:** Toate MX/X **certificate ANAF (AMEF)** cu **modem 2G integrat pentru A4200 automat**. SAF-T e responsabilitatea aplicației POS.

**Distribuitori RO:** [datecs.ro (Danubius Exim)](https://www.datecs.ro), FiscalOnline, IdealFiscal, MUULOX, Sedona, ITGStore, BluConect, IRUCONT, Sintezis, Alfa AMEF.

**Verdict integrare in-house:** **MEDIU-UȘOR** cu ErpNet.FP (zero cod protocol, doar HTTP). MEDIU-GREU direct pe protocol binar. Preț hardware: DP-25 MX ~600-900 RON, WP-50 MX ~700-1000 RON, FP-700X ~1500-2000 RON.

---

### 🔷 Tremol (Bulgaria, distribuit RO)

**Poziționare piață RO:** #2 pe piață. **CEL MAI DESCHIS STACK DE INTEGRARE** dintre toate mărcile — SDK multi-limbaj + server local JSON nativ. Preț M20 LAN ~950 lei.

**Modele frecvente:**
- **M20 VF** — tejghea, LAN + WiFi opțional; testat cu 45+ ERP-uri
- **M23** — versiune nouă, protocol `bg.zk.zfp`
- **S25 / Adpos M** — tejghea rebrand distribuitor
- **M-Pos / EP-05KL / EP-25** — portabile
- **Vending** — imprimantă fiscală pentru automate

**Protocol comunicare:** `ZFP` (Zfiscal Protocol) — proprietar Tremol, encapsulat de biblioteca ZFPLab. Toate modelele vorbesc același ZFP; diferă doar capabilitățile.

**Fizic:** USB CDC-ACM prin driver ZFPLab USB dedicat, RS-232, Ethernet, WiFi opțional pe M20.

**Integrare SW:**
- **Driver Windows:** ZFPLabServer + USB driver semnat — [tremol.bg/en/support/zfplab](https://tremol.bg/en/support/zfplab). Free, versiune 1.9.5 (mar 2024).
- **SDK oficial:** C++, C#, .NET Standard 2.0, Delphi, Java, JavaScript, **PHP**, Python, VB + COM Interop + DirectAPI. Windows, Linux x64/x86, Android, Raspberry Pi. **Gratuit, fără NDA.**
- **WebAPI NATIV (ZFPLabServer):** port **4444**, JSON — apelabil direct din PHP/Node/browser cu `fetch`. **Cel mai relevant pentru arhitectura EventPilot.**
- **WebAPI alternativ open-source:** ErpNet.FP (port 8001), documentat integral [PROTOCOL.md](https://github.com/erpnet/ErpNet.FP/blob/master/PROTOCOL.md). Suportă async + idempotență prin `taskId`.

**Certificare RO:** Avizat art.17 lit f) HG 804/2017 + OUG 28/1999. Toate modelele au GPRS pentru Z automat ANAF.

**Distribuitori RO:** Maxtech Sistem (tremol.ro, contact@tremol.ro, 0372.765.938), S2S Technologies, Alfa AMEF Expert, Adpos, Idealfiscal, FisCool, MUULOX. 200+ unități service.

**Verdict integrare in-house:** **UȘOR.** Rulezi ZFPLabServer/ErpNet.FP ca serviciu Windows, POS face `POST http://127.0.0.1:8001/...` cu JSON. **Fără DLL/ActiveX/COM din PHP. Zero cost licență.** Recomandare puternică pentru clienți viitori.

---

### 🔶 Custom (Italia, distribuit RO)

**Poziționare piață RO:** premium, prezent la retail mare / benzinării / HoReCa mare. Preț Q3X-F ~2.100 lei (dublu față de Tremol/Partner). Livrat majoritar ca **imprimantă fiscală** (nu casă), folosit cu POS PC/tabletă.

**Modele frecvente:**
- **Q3X F / Q3X-F RT** — imprimantă fiscală tejghea, 140 mm/s, LAN + WiFi/BT opțional
- **K3 F** — top de gamă, 280 mm/s, LAN + USB + RS-232, GPRS integrat
- **Rainbow, Big3, KSmart, BigPlus** — variante

**Protocol comunicare:** proprietar Custom binar + XON/XOFF, ESC/ASCII. **Documentație sub NDA** (vine cu SDK licențiat prin distribuitor).

**Fizic:** USB (2 porturi), 1 serial (display client), 1 Ethernet, 1 sertar, WiFi + BT opțional. VID/PID USB **nedocumentat public**.

**Integrare SW:**
- **Driver Windows:** oficial 32/64-bit, NU WHQL; instalare manuală. Există și driver Virtual COM (recomandat USB).
- **SDK oficial:** multi-platformă (Win, OPOS, JavaPOS, POS.NET, Linux, Android, iOS, macOS). Distribuit prin dealer, NU download liber. Utilitar: RX Configurator — [custom.biz](https://www.custom.biz/en_US/product/software/fiscal-rx-configurator).
- **WebAPI:** **NU există nativ.** Third-party: DxPrint ([dxprint.ro](https://dxprint.ro)), FiscalNet ([driverfiscal.ro](https://driverfiscal.ro)) — plătite.
- **DLL COM/ActiveX:** DA — wrapper OPOS.
- **Exemple cod publice:** cvasi-inexistente. Ecosistem închis B2B.

**Certificare RO:** Q3X-F și K3-F **avizate ANAF (AMEF)**, modem GPRS, suport SAF-T XML.

**Distribuitori RO:** custom-printers.ro, MUULOX, fiscalonline.ro, cademartech.ro, e-barcode.ro. Contact: info@custom.biz.

**Verdict integrare in-house:** **MEDIU-GREU.** Fără HTTP endpoint nativ → plătești FiscalNet/DxPrint sau scrii wrapper OPOS→HTTP în .NET. Justificat doar dacă clientul cere expres Custom.

---

### ⚫ Partner Corporation (Romania importator, OEM chinezesc)

**Poziționare piață RO:** Partner Corporation SRL București (aparatura-fiscala.ro, 021-7955505) — importator + brand-owner. Hardware chinezesc rebrandat (indicii Sam4s / Datecs-clone / Innovative Technologies / Zonerich). Fără dealeri alternativi cu drept import — Partner Corp e canal unic. **Casă bugetară pentru IMM + Horeca mic**, mai ieftină decât Tremol/Datecs.

**Modele frecvente:**
- **Partner 200** — tejghea, RS-232 + micro USB + Ethernet, cea mai vândută
- **Partner 300** — variantă intermediară
- **Partner 500** — legacy, serial + protocol VSU (arhitectură diferită de 200/600!)
- **Partner 600** — top-line, RS-232 + mini USB + 2×USB + Ethernet + sertar
- **Partner Touch EVO** — POS integrat cu banking POS (Android)
- **PF 80K** — imprimantă fiscală

**Protocol comunicare:** proprietar Partner, **nedocumentat public**. Arhitectură **FILE-DROP**:
- 200/600: aplicația scrie `cashfile.inp` cu comenzi `S`/`D`/`P` → driver-ul consumă → scrie `bon.log` cu răspuns
- 500: protocol VSU cu polling la 5 sec pe `C:\FiscalPrinter\VANZARE.BON`

**Fizic:** Partner 200 = RS-232 + micro-USB + Ethernet. Partner 600 = RS-232 + mini-USB + 2×USB + Ethernet + sertar. USB expus ca **Virtual COM** via driver Partner.

**Integrare SW:**
- **Driver Windows:** EconMedia Software în parteneriat cu Partner Corp — [microerp.ro/driver-partner](https://www.microerp.ro/driver-partner/). Necesită .NET Framework 4.0. **Gratuit pentru case NEfiscalizate (dev/test), 60 RON licență per casă fiscalizată în producție.**
- **SDK oficial:** DLL fiscal printer + arhitectură file-drop (`cashfile.inp` → `bon.log`). Fără wrappers PHP/C# explicit — se scrie direct fișier text.
- **PC utilities:** `PcConfig` (Partner 200) + `PcUtility` (Partner 600) — GUI Windows pentru programare/config, utilitare separate pentru citire MF + jurnal + export XML SAF-T.
- **App demo:** `FP Demo` livrată de Partner Corp pentru testare mod "fiscal printer".
- **WebAPI:** **NU** nativ. Doar prin FiscalNet plătit sau wrapper propriu.
- **DLL COM/ActiveX:** NU (arhitectură file-based).
- **Exemple cod publice:** zero pe GitHub. Comunitate exclusiv prin distribuitori.

**Certificare RO:** Toate modelele avizate ANAF (AMEF) conform HG 479/2003 + OUG 28/1999. GPRS integrat, utilitar XML SAF-T dedicat.

**Distribuitori RO:** Partner Corporation SRL (unic importator — comenzi@aparatura-fiscala.ro). Subdealeri: Selco Computers Reșița, MUULOX, MicroErp, FiscalMag, Bocp.

**Comparativ vs Tremol/Datecs:** Partner = clone chinezesc rebrandat, hardware ieftin, firmware simplu, ecosistem SW **cel mai închis** din triplet. **DAR** file-drop e trivial de wrapped din orice limbaj (inclusiv PHP direct: `file_put_contents`, apoi polling `bon.log`). **Cost licență driver minim (60 RON).**

**Verdict integrare in-house:** **MEDIU-UȘOR** datorită file-drop simplu. Dezavantaje: fără async, fără status push, fără batch, docs doar prin dealer.

---

### 🇷🇴 Landscape piață RO (branduri secundare + reglementare)

**Liste oficiale:**
- Autoritatea: **Ministerul Finanțelor** (comisia autorizare distribuitori AMEF), NU direct ANAF
- Avizul tehnic emis de **ICI București**
- Lista sintetică distribuitori: [mfinante.gov.ro — pagina AMEF](https://mfinante.gov.ro/en/aparate-de-marcat-electronice-fiscale)
- PDF integral: [ListaDistribAutoriz_18012024.pdf](https://mfinante.gov.ro/documents/35673/217758/ListaDistribAutoriz_18012024.pdf)

**Cotă piață RO (estimat, fără raport oficial):**
| Rank | Brand | Cotă |
|---|---|---|
| 1 | Datecs | ~35-45% |
| 2 | Tremol | #2 |
| 3 | Custom | HoReCa/retail mediu |
| 4 | Partner | retail/HoReCa mic-bugetar |
| 5 | Daisy | în creștere prin Daisy Tech RO |
| 6 | Sam4S | nișă mică-medie |
| 7 | Elcom | nișă (Milo Trading) |
| 8+ | Incotex, Orgtech, Olivetti, Elka, Zeka | <2% fiecare |

**Ecosistem integratori RO:**
- **Danubius Exim** — reprezentant Datecs + hardware propriu (BlueCash-50 all-in-one, FP-950MX cu A4200 automat aprilie 2025)
- **Zeus Service** — Daisy + parteneriat TOKEN pentru plăți
- **IIRUC Service** — multibrand (Zeka SM etc.)
- **Sedona / aparaturafiscala.ro** — retailer + hub drivere multibrand
- **FiscalNet (Criasoft)** — driver universal 8 mărci (Datecs, Daisy, Custom, Olivetti, Orgtech, Partner, Sam4S, Tremol, Incotex) — [driverfiscal.ro](https://driverfiscal.ro)

**Reglementări:**
- **OUG 28/1999 + Legea 15/2018** — cadru AMEF
- **A4200 / A4203** — raportare periodică ANAF via GPRS/internet direct din AMEF
- **SAF-T (D406)** — obligatoriu pentru contribuabili mici din **1 ian 2025**, perioadă de grație până 31 iul 2025. AMEF NU depune D406 direct — ERP-ul agregat trebuie să conțină vânzările
- **Bon fiscal digital + QR** — obligatoriu până **1 noiembrie 2026**
- **AMEF-V** — cadru legal nou 2025-2026, SW certificată ANAF în loc de hardware fizic — **long-term opportunity pentru Ambilet**

**Ce e permis / interzis pentru integrator SW terț:**
- ✅ **PERMIS:** integrare prin driver/SDK/protocol publicat de producător; SW-ul terț aparține integratorului
- ⚠️ **GRI:** rutare prin proxy universal (FiscalNet, FPGate) — larg tolerat comercial, responsabilitatea legală rămâne la integrator
- ❌ **INTERZIS:** bypass jurnal electronic, replay/rescriere semnătură fiscală, comunicare directă cu modulul fiscal fără protocolul oficial (evaziune, penal)

**Branduri secundare — analiză scurtă:**

| Brand | Piață RO | SDK/Protocol | Distribuitor | Verdict integrare |
|---|---|---|---|---|
| **Sam4S** (Korea) | Nișă mică-medie | Driver Windows + FiscalNet | Pos&Hard, Faktum, MUULOX | MEDIU |
| **Daisy** (Bulgaria) | Medie, în creștere | **REST + WebSocket API + FisCool** — cel mai deschis | Zeus Service, Daisy Tech RO | **UȘOR** (cel mai prietenos!) |
| **Novitus** (Polonia) | Inexistentă | SDK PL, necertificat RO | — | BLOCAT |
| **Elzab** (Polonia) | Inexistentă | — | — | BLOCAT |
| **Elcom** (Slovacia) | Nișă mică | SDK matur intl., cerere directă | Milo Trading | MEDIU-GREU |
| **Elka** (Slovacia) | Nișă foarte mică | Docs publice minime | Sagrada | MEDIU-GREU |
| **Zeka** (Turcia) | Nișă foarte mică (mobilă) | Docs minime | IIRUC | GREU / nu merită |
| **Speedy, Kalos, Alexstar, Impuls** | Necunoscut | Probabil rebrand/regional | — | IGNORĂ |

---

### 🎯 Familii de protocoale (grupare naturală pt arhitectură)

| Familie | Exemple | Interfață | Adapter Ambilet |
|---|---|---|---|
| **HTTP/JSON WebAPI local** | Tremol (ZFPLabServer :4444), Daisy (REST+WebSocket), FPGate, DatecsPay | HTTP JSON local | `AdapterHttp` — cel mai curat |
| **DLL/COM/ActiveX Windows** | Datecs FPrint, Custom, Sam4S vechi | COM object | `AdapterCom` — necesită helper Windows |
| **Serial + protocol proprietar** | Novitus, Elzab, Elka, Incotex, AMEF vechi | RS232/USB-serial | `AdapterSerial` — bibliotecă comună Bulgarian-family (STX/XOR) |
| **File-drop pattern** | **Partner 200/600**, unele legacy | Read/write fișiere pe disk | `AdapterFileDrop` — trivial din PHP |
| **Bridge/proxy universal** | FiscalNet, ErpNet.FP, FPGate | HTTP GET/POST spre proxy | `AdapterBridge` — acoperire instant multi-brand |

**Casele bulgare (Datecs, Tremol, Partner, Daisy)** partajează convenții — STX/ETX, checksum XOR, ACK/NAK. Se pot uni tehnic într-un singur `AdapterSerial` cu tabelă comenzi per firmware.

### 🏆 Recomandare strategică actualizată (bazată pe cercetare)

**NU construi N adaptere per marcă**. Construiește **5 straturi** peste un `FiscalDeviceInterface`:

1. **`AdapterHttp`** — Tremol (ZFPLabServer nativ), Daisy (REST/WebSocket), ErpNet.FP (Datecs+Tremol)
2. **`AdapterFileDrop`** — Partner 200/600, alte legacy file-based
3. **`AdapterCom`** — Datecs FPrint (fallback), Custom OPOS
4. **`AdapterSerial`** — bibliotecă Bulgarian-family cu tabelă comenzi mapabilă
5. **`AdapterBridge` (FiscalNet)** — cel mai ieftin de scris, acoperă instant 8 mărci prin HTTP → **strategia MVP**

**Efort estimat:**
- Infrastructură comună (interface, registry, DB `pos_fiscal_devices`, audit `pos_fiscal_receipts`, coadă Laravel + retry): **5-8 zile**
- `AdapterBridge` (FiscalNet universal): **1-2 zile** — susține imediat 8 mărci
- `AdapterFileDrop` (Partner 200 nativ, fără licență): **2-3 zile**
- `AdapterHttp` (Tremol ZFPLabServer): **2-4 zile**
- `AdapterHttp` (Daisy REST): **2-3 zile**
- `AdapterHttp` (ErpNet.FP pentru Datecs): **1-2 zile** — reutilizează ErpNet.FP open-source
- `AdapterCom` (Datecs FPrint direct): **5-8 zile** (helper Windows separat + IPC)
- Certificare **AMEF-V ANAF** (long-term): luni de zile, efort legal/audit

**Strategie MVP recomandată actualizată:**
- **Fază 1 (Sf. Ana Partner 200):** `AdapterFileDrop` — 2-3 zile, zero cost licență, control complet
- **Fază 2 (viitor Datecs/Tremol):** `AdapterHttp` cu ErpNet.FP / ZFPLabServer — zero cost, foarte curat
- **Fază 3 (marci exotice):** fallback pe `AdapterBridge` (FiscalNet) — cost licență acceptabil pentru mărci rare

Astfel evităm dependența de FiscalNet de la start și avem ownership complet pe casele frecvent întâlnite.

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
>    - Aplicația "**PcConfig**" pentru Partner 200 (versiune curentă + instalator)
>    - **Driver-ul Partner** (distribuit prin EconMedia Software / microerp.ro) — versiune curentă + fișier `.inf` de instalare
>    - **Aplicația demo "FP Demo"** — pentru testare mod "fiscal printer"
>    - Instrucțiuni de instalare + confirmare compatibilitate Windows 10/11 (64-bit)
>    - Confirmare cerință .NET Framework (4.0 sau mai nou)
>
> **2. Documentație tehnică integrare (CRITIC):**
>    - **Sintaxa completă `cashfile.inp`** — toate comenzile (`S` sale, `D` discount, `P` payment, `V` void, etc.) cu format exact + exemple
>    - **Sintaxa completă `bon.log`** — formatul răspunsului driver-ului (coduri eroare, confirmare, câmpuri returnate)
>    - **Locația exactă a directoarelor** (default vs configurabile) pentru `cashfile.inp` + `bon.log`
>    - **Timeout-uri și polling patterns** recomandate — cât să aștept răspunsul, cum detectez erori
>    - **Comenzi utilitare separate** — export XML SAF-T, citire memorie fiscală, jurnal electronic (executabile CLI + parametri)
>    - Format import bulk PLU (upload până la 3000 articole — batch mode?)
>    - Comenzi de query: status casă, ultima eroare, număr bon curent, versiune firmware
>    - **VID/PID USB** al Partner 200 pentru identificare device în Device Manager
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
>    - Confirmare cost driver **60 RON/casă fiscalizată** (info găsită pe microerp.ro) — modalitate plată + licențiere per stație
>    - PC Manager / SDK — cost separat sau incluse?
>    - Suport tehnic pe durata integrării (telefon / email / on-site) — cost per intervenție
>    - Există contract dedicat pentru integratori software?
>    - Există program partnership/reseller (pentru viitor, dacă recomandăm Partner altor clienți Ambilet)?
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
| ~~Partner Corporation refuză SDK sau taxează scump~~ | ~~M~~ | ~~Î~~ | **Rezolvat prin cercetare**: driver Partner e 60 RON/casă via EconMedia. File-drop pattern e simplu. | ✅ downgraded |
| Driver Windows-only și PC-ul e alt OS | S | M | Confirmat Windows ✓ | ✅ mitigat |
| Chrome blochează HTTP loopback din HTTPS (mixed content) | Î | M | Opțiuni: (a) agent HTTPS cert self-signed pre-instalat, (b) Chrome flag `--unsafely-treat-insecure-origin-as-secure=http://127.0.0.1:9101` pe stație, (c) **`AdapterFileDrop` nu are această problemă** — scrie fișier local direct fără HTTP | 🟢 mitigat pentru Partner |
| Casa offline la finalizare (baterie, hârtie) | M | S | Non-blocking; `orders.meta.fiscal_receipt_status=failed` + retry manual | 📋 planned |
| GPRS Z-report ANAF nu există pe Partner 200 | ? | Î | De confirmat cu Partner (cerere pending) | 🟡 open |
| Certificarea AMEF invalidată de bypass driver | S | Critic | STRICT driver oficial, zero WebUSB direct | ✅ decis |
| Testare emite bonuri reale (fără dev unit) | Î | M | Suma minimă 0.01 + storno + cerere Partner pt unitate demo (poate exista training unit gratis) | 🟡 open |
| **NOU**: Bon fiscal digital + QR obligatoriu 1 nov 2026 — casa Sf. Ana update? | M | M | Service Partner face upgrade firmware; verifică compatibilitatea cu integrarea noastră post-upgrade | 🟡 open |
| **NOU**: SAF-T D406 — cine agregă datele AMEF în XML? | S | M | Casa NU depune D406 direct; ERP-ul Ambilet trebuie să contribuie cu vânzările POS. Verifică dacă avem export SAF-T | 🟡 open |
| **NOU**: Licențierea driver Partner 60 RON — se aplică per casă sau per client? | S | S | Confirmă cu Partner/EconMedia; buget mic oricum | 🟡 open |

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

### 2026-07-31 (later) — Cercetare piață RO (3 agenți paraleli)
- **Descoperire 1: `ErpNet.FP` open-source BSD-0** — HTTP JSON server cross-platform, acoperă deja Datecs (DP-25/WP-50/FP-700X) + Tremol (M20/S25). Zero cod protocol pentru mărcile astea.
- **Descoperire 2: `FiscalNet` driver universal comercial RO** — acoperă 8 mărci prin HTTP GET/POST (Datecs, Daisy, Custom, Olivetti, Orgtech, Partner, Sam4S, Tremol, Incotex). Licență plătită dar shortcut major pentru MVP multi-brand.
- **Descoperire 3: `ZFPLabServer` (Tremol nativ)** — HTTP JSON pe port 4444, gratuit, SDK PHP inclus.
- **Descoperire 4 — CRITICĂ:** Partner 200 NU e DLL COM cum credeam — e arhitectură **FILE-DROP** (`cashfile.inp` → `bon.log`). Se poate wrap trivial din PHP direct (`file_put_contents` + polling). **Simplifică radical Faza 1 — nu mai avem nevoie de node-ffi/edge-js/Electron pentru Partner.**
- **Descoperire 5:** cost driver Partner = **60 RON/casă fiscalizată** (nu mii de euro cum ne temeam). Distribuit prin EconMedia Software.
- **Descoperire 6: `AMEF-V`** (Aparat de Marcat Electronic Fiscal Virtual) — cadru legal nou 2025-2026, SW certificată în loc de hardware. **Long-term opportunity Ambilet.**
- **Descoperire 7:** Bon fiscal digital + QR obligatoriu până **1 nov 2026** — de urmărit ca requirement.
- **Decizie strategică actualizată:** MVP-ul NU folosește FiscalNet (evită dependența de licență + lock-in). MVP = `AdapterFileDrop` pentru Partner 200 (2-3 zile), apoi `AdapterHttp` pentru Tremol/Daisy când apare cerere. FiscalNet rămâne fallback pentru mărci exotice.
- **Decizie arhitecturală:** 5 adaptere în loc de N per marcă:
  1. `AdapterHttp` (Tremol, Daisy, ErpNet.FP proxy)
  2. `AdapterFileDrop` (Partner 200/600)
  3. `AdapterCom` (Datecs FPrint fallback, Custom OPOS)
  4. `AdapterSerial` (Bulgarian-family cu tabelă comenzi)
  5. `AdapterBridge` (FiscalNet fallback multi-brand)
- **Cerere Partner actualizată** cu focus pe file-drop syntax (`cashfile.inp` + `bon.log`) în loc de SDK generic.

<!-- Adaugă note noi mai jos la fiecare pas -->

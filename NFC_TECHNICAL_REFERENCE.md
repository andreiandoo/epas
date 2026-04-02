# NFC Technical Reference - Cashless Festival System

> Acest document conține specificațiile tehnice NFC necesare pentru implementarea
> microserviciului Cashless. Citește-l împreună cu `PLAN_CASHLESS_FESTIVAL.md`.

---

## Arhitectura: Chip-Agnostic

Sistemul suportă **două tipuri de chip NFC**, configurabile per ediție de festival:

| | MIFARE DESFire EV3 2K | NTAG213 |
|---|---|---|
| **Sold** | Pe chip (Value File) | Server-side (CashlessAccount) |
| **Offline** | 100% funcțional | Parțial (cache local pe POS) |
| **Securitate** | AES-128, EAL5+ | Password 4 bytes (slab) |
| **Anti-tear** | Da (atomic writes) | Nu |
| **Android write** | Da (TapLinx SDK 3.0) | Da (orice app NFC) |
| **iPhone read** | Da (Core NFC + custom auth) | Da (nativ) |
| **Cost/buc (50K)** | $1.00-2.50 | $0.20-0.50 |
| **Când se alege** | Festival mare, fără internet | Festival mic cu WiFi local |

Admin-ul festivalului alege tipul în `CashlessSettings.nfc_chip_type`.

---

## Opțiunea 1: MIFARE DESFire EV3 2K (Recomandat)

### Specificații chip
- **Frecvență:** 13.56 MHz
- **Protocol:** ISO 14443A
- **Memorie:** 2048 bytes (2K) - suficient pentru purse app
- **Criptare:** AES-128 mutual authentication (3-pass)
- **Certificare:** EAL5+ (Common Criteria)
- **Anti-tear:** Da (CommitTransaction atomic)
- **UID:** 7 bytes, hardware-locked (nu poate fi clonat)
- **Cicluri R/W:** 1,000,000
- **Distanță citire (telefon):** 2-4 cm (fabric wristband cu antenă optimizată)

### Structura pe chip (Application Layout)

```
PICC Master Key (AES-128) - administrare card
│
└── Application: Festival Purse
    AID: configurat per ediție (ex: 0x010203)
    │
    ├── Key 0: Master Key (AES-128)
    │   Cine o are: doar admin festival
    │   Permisiuni: tot (creare/ștergere fișiere, rotire chei)
    │
    ├── Key 1: Top-Up Key (AES-128)
    │   Cine o are: operatori top-up (standuri alimentare)
    │   Permisiuni: Credit pe Value File, Read metadata
    │   NU poate: Debit, ștergere, modificare metadata
    │
    ├── Key 2: POS Key (AES-128)
    │   Cine o are: POS-urile vendorilor (telefoane angajați)
    │   Permisiuni: Debit pe Value File, Read balance, Write records
    │   NU poate: Credit (nu poate adăuga bani falși)
    │
    ├── Key 3: Read-Only Key (AES-128)
    │   Cine o are: app client (iPhone + Android)
    │   Permisiuni: GetValue (citire sold), Read records
    │   NU poate: Credit, Debit, Write
    │
    ├── File 0x00: Backup Data File (32 bytes, CommMode=Encrypted)
    │   Access: Read=Key3, Write=Key0, R/W=Key1
    │   Conținut:
    │   ┌──────────────────────────────────────┐
    │   │ edition_id       (4 bytes, uint32)   │
    │   │ customer_id      (4 bytes, uint32)   │
    │   │ wristband_type   (1 byte, enum)      │
    │   │ activation_ts    (4 bytes, unix ts)  │
    │   │ flags            (1 byte, bitfield)  │
    │   │ reserved         (18 bytes)          │
    │   └──────────────────────────────────────┘
    │
    ├── File 0x01: Value File (SOLDUL)
    │   Access: Read=Key3, Debit=Key2, Credit=Key1
    │   CommMode: Encrypted
    │   lower_limit: 0 (nu poate fi negativ)
    │   upper_limit: 5,000,000 (50,000.00 RON în cenți)
    │   initial_value: 0
    │   limited_credit: enabled (Key2 poate face credit mic pt refund)
    │
    └── File 0x02: Cyclic Record File (ultimele 20 tranzacții)
        Access: Read=Key3, Write=Key2
        CommMode: Encrypted
        Record size: 16 bytes
        ┌──────────────────────────────────────┐
        │ timestamp     (4 bytes, unix ts)     │
        │ amount_cents  (4 bytes, int32)       │
        │ vendor_id     (2 bytes, uint16)      │
        │ stand_id      (2 bytes, uint16)      │
        │ txn_type      (1 byte, enum)         │
        │ txn_counter   (2 bytes, uint16)      │
        │ checksum      (1 byte, XOR)          │
        └──────────────────────────────────────┘
```

### Flow-uri DESFire EV3

**Top-up (Key 1 - operator stand):**
```
1. Scan wristband NFC
2. AuthenticateEV2First(keyNo=1, aesKey=topUpKey)
3. GetValue(fileNo=0x01) → sold curent
4. Credit(fileNo=0x01, amount=topupAmountCents)
5. WriteRecord(fileNo=0x02, record={timestamp, amount, ...})
6. CommitTransaction() → atomic, anti-tear
7. Salvare tranzacție în SQLite local → sync later
```

**Charge / Plată (Key 2 - POS vendor):**
```
1. Scan wristband NFC
2. AuthenticateEV2First(keyNo=2, aesKey=posKey)
3. GetValue(fileNo=0x01) → sold curent
4. Sold suficient? → Debit(fileNo=0x01, amount=chargeAmountCents)
5. WriteRecord(fileNo=0x02, record={timestamp, -amount, vendor, stand, ...})
6. CommitTransaction()
7. Salvare tranzacție local → sync later
→ ZERO INTERNET NECESAR
```

**Read balance (Key 3 - app client sau iPhone vendor):**
```
1. Scan wristband NFC
2. AuthenticateEV2First(keyNo=3, aesKey=readOnlyKey)
3. GetValue(fileNo=0x01) → sold
4. ReadRecords(fileNo=0x02) → ultimele N tranzacții
→ Afișare sold + istoric
```

### SDK-uri și librării

**Android (POS app - write + read):**
- NXP TapLinx SDK 3.0 - oficial, gratuit, suportă DESFire EV3
  - Descărcare: https://www.mifare.net/en/products/tools/taplinx/
  - Necesită: Android 5.0+, NFC hardware
  - Licență offline (2048 bytes key)
- Open-source: https://github.com/skjolber/desfire-tools-for-android
- Tutorial: https://github.com/MichaelsPlayground/TalkToYourDESFireCard

**iOS (app client - read only):**
- Core NFC framework (iOS 13+, iPhone 7+)
- Tag type: NFCMiFareFamily.desfire
- Comenzi prin: sendMiFareISO7816Command() sau sendMiFareCommand()
- DESFire commands wrapped în ISO 7816-4 APDU: CLA=0x90, INS=command_byte
- Librărie open-source: https://github.com/TheJKM/JKDesFireReader
- ATENȚIE: TapLinx iOS SDK e buggy (crashes la auth EV2/EV3) - evită

### Key Management

- Cheile AES se generează per ediție de festival
- Se stochează criptat în DB (`cashless_nfc_keys` table)
- Se distribuie la POS-uri doar la autentificare (login vendor)
- Pe device: stocate în Android Keystore (hardware-backed)
- La end-of-festival: cheile se invalidează
- Rotire chei: posibilă mid-festival dacă e compromisă una

---

## Opțiunea 2: NTAG213 (Economic)

### Specificații chip
- **Frecvență:** 13.56 MHz
- **Protocol:** ISO 14443A, NFC Forum Type 2 Tag
- **Memorie:** 144 bytes utilizabile
- **Criptare:** Password 4 bytes (32-bit) - SLAB
- **UID:** 7 bytes
- **Cicluri R/W:** 100,000
- **Distanță citire (telefon):** 1-5 cm

### Ce se scrie pe chip
- UID-ul e factory-set (nu se scrie, se citește doar)
- Opțional: NDEF record cu URL (ex: https://festival.app/wristband/{uid})
- Opțional: password lock (4 bytes) pentru a preveni rescrierea
- **NU se scrie sold pe chip** (nesecurizat)

### Cum funcționează fără internet

```
1. La sync (când POS are internet):
   - POS descarcă lista completă: {uid → balance} pentru toți clienții
   - Stocare în SQLite local pe POS
   
2. La charge (offline):
   - Scan UID → lookup în SQLite local → sold din cache
   - Dacă sold suficient → decrementare locală → log tranzacție
   - Risc: alt POS (tot offline) poate avea cache outdated
   - Risc: sold negativ dacă 2 POS-uri debitează simultan
   
3. La reconnect:
   - Sync tranzacții locale → server → reconciliere
   - Re-download balance-uri actualizate
```

### Limitări vs DESFire
- Solduri negative posibile în mod offline
- Password-ul de 4 bytes se sparge în secunde cu brute force
- Oricine cu un telefon poate citi/scrie date pe chip
- Fără anti-tear (scriere întreruptă = date corupte)
- Necesită internet periodic pentru sync balances

---

## Comparație flow POS

```
                    DESFire EV3                    NTAG213
                    ──────────                     ───────
Scan wristband      → citește UID                  → citește UID
                      + auth AES
                      + citește sold de pe CHIP      → lookup sold din CACHE LOCAL
                    
Verificare sold     → sold de pe chip (100%         → sold din cache (poate fi stale)
                      accurate)

Charge              → Debit pe chip (atomic)        → decrementare cache local
                    → CommitTransaction              → log tranzacție local

Post-charge         → sold nou PE chip               → sold nou doar LOCAL
                    → tranzacție log local            → tranzacție log local
                    → sync to server LATER            → sync to server LATER
                                                      → update CashlessAccount pe server

Internet necesar?   → NU                             → RECOMANDAT (pentru sync cache)
                                                       (funcționează offline cu riscuri)
```

---

## Achiziție brățări

### DESFire EV3 2K (fabric wristband, custom print)

**Termeni de căutare:**
```
Alibaba: "MIFARE DESFire EV3 2K fabric wristband festival large antenna"
         "DESFire EV3 woven NFC bracelet bulk custom print"
```

**Furnizori:**
- TJ RFID - tjnfctag.com (specializat DESFire)
- Shop NFC - shopnfc.com (EU, sample-uri rapide)
- ID&C Band - idcband.com (furnizor Tomorrowland)
- Tagstand - tagstand.com (furnizor WristCoin)

**Prețuri:**
| Volum | Preț/buc |
|-------|----------|
| 1,000 | $2.50-4.00 |
| 10,000 | $1.50-3.00 |
| 50,000 | $1.00-2.00 |

### NTAG213 (fabric wristband, custom print)

**Termeni de căutare:**
```
Alibaba: "NTAG213 woven fabric wristband festival"
         "NTAG213 silicone NFC bracelet bulk"
```

**Furnizori:**
- Shop NFC - shopnfc.com (de la €0.49/buc la 5K)
- Euroko - euroko.eu
- Seritag - seritag.com

**Prețuri:**
| Volum | Preț/buc |
|-------|----------|
| 1,000 | $0.50-0.80 |
| 10,000 | $0.30-0.50 |
| 50,000 | $0.20-0.40 |

---

## Notă importantă pentru implementare

Arhitectura `NfcChipServiceInterface` face ca restul codului (SaleService,
TopUpService, CashoutService, etc.) să fie **identic** indiferent de tipul de
chip. Diferența e doar în cum se citește/scrie balance-ul:

```php
// În SaleService - IDENTIC pentru ambele chip-uri:
$chipService = NfcChipServiceFactory::make($edition);

if ($chipService->balanceIsOnChip()) {
    // DESFire: citește sold de pe chip, debitează pe chip
    $result = $chipService->charge($uid, $totalCents);
} else {
    // NTAG213: citește sold din cache/server, debitează pe server/cache
    $result = $chipService->charge($uid, $totalCents);
}

// Restul flow-ului e IDENTIC:
$sale = CashlessSale::create([...]);
$transaction = WristbandTransaction::create([...]);
// etc.
```

Toată logica de business (vânzări, rapoarte, stocuri, finance, profiling)
funcționează la fel. Doar layer-ul NFC diferă.

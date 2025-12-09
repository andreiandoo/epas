# eFactura (RO)

## Prezentare Scurtă

Navighează conformitatea fiscală românească cu ușurință folosind integrarea noastră eFactura. Pe măsură ce facturarea electronică devine obligatorie pentru afacerile care operează în România, acest serviciu asigură că rămâi conform fără bătăile de cap tehnice.

Serviciul nostru eFactura transformă automat facturile platformei tale în formatul XML UBL/CII cerut, le semnează digital și le trimite direct la ANAF SPV (sistemul electronic al Agenției Naționale de Administrare Fiscală). Nu este necesară nicio intervenție manuală.

Sistemul inteligent de gestionare a cozii gestionează întregul ciclu de viață al trimiterii. Facturile sunt puse în coadă, trimise și monitorizate pentru acceptare sau respingere. Dacă sistemul ANAF este temporar indisponibil, coada noastră de fallback asigură că nicio factură nu este pierdută. Logica de reîncercare cu backoff exponențial gestionează eșecurile tranzitorii cu grație.

Securitatea este primordială. Certificatele tale digitale sunt stocate cu criptare, iar izolarea credențialelor multi-tenant asigură că datele tale rămân private. Jurnalele complete de audit oferă trasabilitate completă pentru conformitatea cu reglementările.

Urmărește totul de pe un dashboard comprehensiv: status trimiteri, rate de acceptare, diagnostice erori și statistici în timp real. Exportă rapoarte de erori în CSV pentru rezolvare ușoară. Descarcă confirmările și chitanțele ANAF direct din platformă.

Fie că emiți sute sau mii de facturi, eFactura se scalează cu afacerea ta menținându-te conform cu reglementările fiscale românești.

---

## Descriere Detaliată

Microserviciul eFactura oferă trimitere automată de facturi electronice către Agenția Națională de Administrare Fiscală (ANAF) din România prin sistemul lor SPV (Spațiu Privat Virtual). Acest serviciu este esențial pentru afacerile care operează în România unde facturarea electronică devine obligatorie în toate sectoarele.

### Conformitate Automatizată

Când o factură este generată pe platforma ta, serviciul eFactura preia controlul. Transformă datele facturii în formatul XML cerut (UBL 2.1 sau CII), aplică semnături digitale, împachetează documentul conform specificațiilor ANAF și îl trimite la sistemul SPV.

### Gestionare Inteligentă a Cozii

Serviciul implementează un sistem sofisticat de gestionare a cozii care asigură livrare fiabilă:

1. **În coadă**: Factura este transformată și gata pentru trimitere
2. **Trimisă**: Trimisă cu succes la ANAF, în așteptarea răspunsului
3. **Acceptată**: ANAF a acceptat factura
4. **Respinsă**: ANAF a respins factura (cu informații detaliate despre eroare)
5. **Eroare**: A apărut o eroare tranzitorie, programată pentru reîncercare

### Gestionarea Erorilor

Când trimiterile eșuează, sistemul reîncearcă automat cu backoff exponențial (5min, 15min, 30min, 1h, 2h). Mesajele detaliate de eroare de la ANAF sunt capturate și afișate, permițând identificarea și rezolvarea rapidă a problemelor.

### Securitate și Izolare

Credențialele ANAF și certificatele digitale ale fiecărui tenant sunt stocate cu criptare și izolate de alți tenanți. Sistemul suportă atât medii de test cât și de producție, permițându-ți să validezi configurația înainte de a trece live.

### Trimiteri Idempotente

Serviciul folosește hash-uri XML pentru a preveni trimiterile duplicate. Dacă aceeași factură este trimisă de două ori, sistemul o recunoaște și returnează statusul trimiterii existente în loc să creeze o intrare duplicată în sistemul ANAF.

---

## Funcționalități

### Procesare Facturi
- Transformare automată a facturilor în XML eFactura (format UBL 2.1/CII)
- Semnătură digitală și împachetare conform specificațiilor ANAF
- Trimitere automată la ANAF SPV
- Trimitere idempotentă prevenind duplicatele
- Suport pentru corecții și anulări de facturi

### Gestionare Coadă
- Gestionare coadă cu logică inteligentă de reîncercare
- Flux de status: în coadă → trimisă → acceptată/respinsă
- Program de reîncercare cu backoff exponențial (5min, 15min, 30min, 1h, 2h)
- Maximum 5 încercări de reîncercare cu limite configurabile
- Coadă de fallback pentru indisponibilitate SPV

### Urmărire Status
- Interogare status cu backoff exponențial
- Actualizări de status în timp real prin webhook-uri
- Descărcare chitanțe și confirmări ANAF
- Urmărire erori și diagnostice
- Dashboard statistici în timp real

### Securitate
- Pattern adaptor agnostic de furnizor
- Stocare certificat criptată
- Izolare credențiale multi-tenant
- Jurnale complete de audit
- Comutare mediu test/producție

### Raportare
- Export CSV al erorilor
- Istoric trimiteri și analize
- Urmărire rate de succes/eșec
- Metrici timp de procesare

---

## Cazuri de Utilizare

### Platforme de Ticketing pentru Evenimente
Trimite automat toate facturile de vânzare bilete la ANAF. Gestionează vânzări de volum mare în timpul evenimentelor populare menținând conformitate 100%.

### Organizatori Multi-Eveniment
Gestionează trimiterile eFactura pentru mai multe evenimente și serii de facturi. Fiecare eveniment poate avea propria numerotare de facturi menținând conformitate centralizată.

### Vânzări Corporate de Bilete (B2B)
Emite facturi conforme pentru clienți corporate care achiziționează bilete în vrac. Include automat toate câmpurile B2B cerute.

### Servicii pe Bază de Abonament
Gestionează facturi recurente pentru abonamente de sezon, membership-uri sau pachete de abonament cu trimiteri lunare automate.

### Procesare Returnări
Când sunt emise returnări, generează și trimite automat notele de creditare cerute (facturi de stornare) la ANAF.

### Operațiuni Multi-Entitate
Gestionează conformitatea eFactura pentru mai multe entități legale de pe o singură platformă, fiecare cu propriile certificate și credențiale.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul eFactura automatizează trimiterea facturilor electronice la ANAF SPV (autoritatea fiscală română). Gestionează generarea XML, semnarea digitală, trimiterea, interogarea statusului și gestionarea erorilor.

### Arhitectură

Serviciul folosește un pattern adaptor pentru a abstractiza comunicarea cu API-ul ANAF. Aceasta permite testare ușoară cu adaptoare mock și potențial suport pentru furnizori terți de eFactura.

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `anaf_queue` | Coadă de trimitere facturi cu urmărire status |

#### Câmpuri Tabel Coadă

| Câmp | Tip | Descriere |
|------|-----|-----------|
| `tenant_id` | string | Identificator tenant |
| `invoice_id` | integer | Referință la factura originală |
| `payload_ref` | string | Cale stocare pentru fișier XML |
| `status` | enum | queued, submitted, accepted, rejected, error |
| `anaf_ids` | json | Identificatori răspuns ANAF |
| `error_message` | text | Descriere eroare dacă a eșuat |
| `response_data` | json | Răspuns ANAF complet |
| `attempts` | integer | Număr de încercări de trimitere |
| `max_attempts` | integer | Maximum încercări de reîncercare |
| `xml_hash` | string | Hash SHA-256 pentru idempotență |
| `submitted_at` | timestamp | Când a fost trimisă la ANAF |
| `accepted_at` | timestamp | Când a fost acceptată de ANAF |
| `rejected_at` | timestamp | Când a fost respinsă de ANAF |
| `next_retry_at` | timestamp | Timp programat reîncercare |

### Endpoint-uri API

#### Trimitere Factură
```
POST /api/efactura/submit
```
Pune o factură în coadă pentru trimitere eFactura.

**Corp Cerere:**
```json
{
  "invoice_id": 1001,
  "force": false
}
```

#### Reîncercare Trimitere Eșuată
```
POST /api/efactura/retry
```
Reîncearcă manual o trimitere eșuată.

#### Interogare Status
```
POST /api/efactura/poll
```
Declanșează interogarea statusului pentru facturile trimise.

#### Obține Status
```
GET /api/efactura/status/{queueId}
```
Obține statusul curent al unei trimiteri specifice.

#### Descarcă Chitanță
```
GET /api/efactura/download/{queueId}
```
Descarcă chitanța/confirmarea PDF ANAF.

#### Obține Statistici
```
GET /api/efactura/stats/{tenantId}
```
Obține statistici și analize de trimitere.

#### Listează Coada
```
GET /api/efactura/queue/{tenantId}
```
Listează toate intrările din coadă cu opțiuni de filtrare.

### Flux de Status

```
queued → submitted → accepted
                  ↘ rejected
                  ↘ error → (retry) → submitted
```

### Configurare

```php
'efactura' => [
    'environment' => env('ANAF_ENV', 'test'), // test sau production
    'max_retries' => 5,
    'backoff_schedule' => [5, 15, 30, 60, 120], // minute
    'poll_interval' => 300, // secunde
    'storage_path' => 'efactura/{tenant_id}/{invoice_id}.xml',
    'supported_formats' => ['UBL 2.1', 'CII'],
    'signing_methods' => ['XMLDSig', 'PKCS#7'],
]
```

### Exemplu de Integrare

```php
use App\Services\EFactura\EFacturaService;

// Trimite factură la eFactura
$efactura = app(EFacturaService::class);
$queueEntry = $efactura->submit($invoice);

// Verifică status
$status = $efactura->getStatus($queueEntry->id);

// Descarcă chitanță după acceptare
if ($status === 'accepted') {
    $receipt = $efactura->downloadReceipt($queueEntry->id);
}
```

### Generare XML

Serviciul generează XML conform specificației eFactura ANAF:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
  <cbc:ID>INV-2025-001</cbc:ID>
  <cbc:IssueDate>2025-01-15</cbc:IssueDate>
  <!-- ... -->
</Invoice>
```

### Coduri de Eroare

| Cod | Descriere | Acțiune |
|-----|-----------|---------|
| `INVALID_VAT` | Format CUI invalid | Verifică CUI client |
| `MISSING_FIELD` | Câmp obligatoriu lipsă | Verifică datele facturii |
| `SIGNATURE_ERROR` | Semnătură digitală invalidă | Verifică certificatul |
| `SPV_UNAVAILABLE` | Sistem ANAF indisponibil | Reîncercare automată |
| `DUPLICATE` | Factură deja trimisă | Verifică intrarea existentă |

### Monitorizare

Serviciul expune metrici pentru monitorizare:
- Trimiteri pe oră/zi
- Rată de acceptare
- Timp mediu de procesare
- Rată de eroare pe tip
- Adâncime coadă

# Asigurare Bilete

## Prezentare Scurtă

Oferă clienților tăi liniște sufletească cu Asigurarea Biletelor. Viața este imprevizibilă și uneori planurile se schimbă. Acest add-on opțional permite cumpărătorilor de bilete să-și protejeze achiziția împotriva circumstanțelor neprevăzute, creând un flux suplimentar de venituri în timp ce îmbunătățește satisfacția clienților.

În timpul checkout-ului, clienții pot opta să adauge acoperire de asigurare pentru o primă mică. Dacă nu pot participa la eveniment din motive acoperite, pot solicita înapoi banii. E atât de simplu.

Sistemul de configurare ierarhic îți oferă control complet. Setează opțiunile de asigurare la nivel de tenant ca implicite, personalizează-le per eveniment sau reglează fin setările pentru tipuri specifice de bilete. Prețul poate fi o sumă fixă sau un procent din prețul biletului, cu limite minime și maxime pentru a asigura acoperire adecvată.

Arhitectura noastră agnostică de furnizor suportă parteneri multipli de asigurare, cu calculări de cote în timp real și emitere automată de polițe. Întregul proces este fără cusur - polițele sunt emise automat când comenzile sunt confirmate, iar toată documentația este stocată securizat pentru acces ușor.

Procesarea returnărilor este la fel de simplă, cu politici configurabile pentru fără-returnare, returnare proporțională sau returnare completă dacă neutilizată. Urmărirea completă de la cotă la revendicare oferă transparență atât pentru tine cât și pentru clienții tăi.

Cel mai bine, acest serviciu este gratuit de activat. Veniturile vin din comisioane de asigurare, însemnând că nu există cost inițial pentru a oferi acest beneficiu valoros clienților.

---

## Descriere Detaliată

Asigurarea Biletelor este un serviciu comprehensiv de add-on de asigurare conceput special pentru platformele de ticketing pentru evenimente. Oferă o modalitate fără cusur de a oferi protecție biletelor clienților în timp ce generează venituri suplimentare prin comisioane de asigurare.

### Cum Funcționează Asigurarea

Când un client procedează la checkout, i se prezintă opțiunea de a adăuga asigurare la achiziția de bilete. Prima este calculată în timp real bazat pe modelul de preț configurat și valoarea biletului. Dacă este selectată, costul asigurării este adăugat la totalul comenzii.

După plata cu succes, polițele de asigurare sunt emise automat și legate de fiecare bilet. Documentele poliței sunt stocate securizat și accesibile atât clientului cât și organizatorului de evenimente.

### Configurare Ierarhică

Serviciul suportă o ierarhie de configurare pe trei niveluri:

1. **Nivel Tenant**: Setări implicite care se aplică la toate evenimentele
2. **Nivel Eveniment**: Suprascrieri pentru evenimente specifice
3. **Nivel Tip Bilet**: Control fin per categorie de bilet

Aceasta permite flexibilitate maximă minimizând totodată overhead-ul de configurare pentru cazurile standard.

### Modele de Preț

- **Sumă Fixă**: O primă setată indiferent de prețul biletului (ex: 2,00€)
- **Procentual**: Prima calculată ca procent din valoarea biletului (ex: 5%)
- **Pe Niveluri**: Rate diferite pentru intervale diferite de preț
- **Plafoane**: Limite minime și maxime pentru prime

### Integrare Furnizori

Arhitectura bazată pe adaptoare suportă integrare cu furnizori multipli de asigurare. Fiecare furnizor implementează o interfață standard pentru:
- Obținere cote
- Emitere polițe
- Procesare anulări și returnări
- Sincronizare status poliță

---

## Funcționalități

### Configurare
- Config ierarhic: tenant → eveniment → tip_bilet
- Moduri de preț: sumă fixă sau procent din prețul biletului
- Plafoane prime min/max
- Configurare politică de taxe (inclusiv/exclusiv)
- Opțiuni domeniu: per-bilet sau per-comandă
- Reguli eligibilitate (țară, tip bilet, eveniment, interval preț)

### Sistem de Cotații
- Calcul primă în timp real
- Integrare adaptor furnizor
- Strategii multiple de preț
- Suport calcul taxe

### Gestionare Polițe
- Emitere polițe idempotentă
- Pattern adaptor agnostic de furnizor
- Stocare documente poliță și URL-uri
- Urmărire status: pending, issued, voided, refunded, error
- Logare comprehensivă evenimente

### Returnare & Anulare
- Politici de anulare configurabile
- Opțiuni politică: no_refund, proportional, full_if_unused
- Sincronizare furnizor pentru returnări/anulări
- Suport returnare parțială

### Integrare Checkout
- Checkbox add-on opțional în checkout
- Afișare primă în timp real
- UI termeni & consimțământ
- Selecție per-linie sau per-comandă

### Securitate & Conformitate
- Separare clară de prețul biletului
- Consimțământ explicit necesar
- Minimizare PII
- Stocare securizată documente
- Conform GDPR

### Raportare & Analize
- Urmărire rată de atașare
- Calcul GMV prime
- Monitorizare rate anulare/returnare
- Urmărire erori furnizor
- Export CSV

---

## Cazuri de Utilizare

### Protecție Concerte & Festivaluri
Oferă fanilor încrederea să cumpere bilete cu luni înainte. Cu asigurare, știu că sunt protejați dacă boala, problemele de călătorie sau urgențele familiale împiedică participarea.

### Bilete Evenimente Corporate
Clienții B2B care achiziționează bilete pentru angajați apreciază flexibilitatea pe care o oferă asigurarea, mai ales pentru evenimente nerambursabile.

### Pachete VIP de Valoare Mare
Biletele premium cu cost semnificativ beneficiază cel mai mult de opțiunile de asigurare. Clienții sunt mai dispuși să cheltuie pe experiențe VIP când își pot proteja investiția.

### Evenimente Internaționale
Călătorii care rezervă evenimente în străinătate au riscuri suplimentare. Asigurarea oferă liniște împotriva anulărilor de zbor, problemelor de viză sau restricțiilor neașteptate de călătorie.

### Rezervări de Grup
Când organizezi participare de grup, asigurarea asigură că întregul grup este protejat, nu doar călătorii individuali.

### Abonamente de Sezon
Angajamentele pe termen lung precum abonamentele de sezon beneficiază de acoperire de asigurare care se întinde pe mai multe evenimente pe parcursul sezonului.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Asigurare Bilete oferă add-on-uri opționale de asigurare pentru achizițiile de bilete. Gestionează calculul cotațiilor, emiterea polițelor, urmărirea statusului și procesarea returnărilor printr-un sistem de adaptor agnostic de furnizor.

### Arhitectură

```
Flux Checkout → Serviciu Asigurare → Adaptor Furnizor → API Furnizor Asigurare
                      ↓
               Motor Configurare (tenant → eveniment → tip_bilet)
                      ↓
               Manager Polițe → Stocare Documente
```

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `insurance_configs` | Setări configurare ierarhice |
| `insurance_policies` | Înregistrări polițe emise |
| `insurance_events` | Evenimente ciclu de viață poliță |

### Model Configurare

```php
// Config Asigurare
[
    'tenant_id' => 'tenant_001',
    'event_id' => null, // null = implicit tenant
    'ticket_type_id' => null,
    'enabled' => true,
    'pricing_mode' => 'percentage', // sau 'fixed'
    'rate' => 5.00, // 5% sau 5,00€
    'min_premium' => 1.00,
    'max_premium' => 50.00,
    'tax_mode' => 'inclusive', // sau 'exclusive'
    'scope' => 'per_ticket', // sau 'per_order'
    'eligibility' => [
        'countries' => ['RO', 'DE', 'FR'],
        'min_ticket_price' => 10.00,
        'max_ticket_price' => 1000.00,
    ],
    'cancellation_policy' => 'proportional',
]
```

### Endpoint-uri API

#### Obține Cotație
```
POST /api/insurance/quote
```
Calculează prima de asigurare pentru articolele din coș.

**Cerere:**
```json
{
  "items": [
    {"ticket_type_id": 1, "quantity": 2, "unit_price": 50.00}
  ],
  "event_id": 123
}
```

**Răspuns:**
```json
{
  "quote_id": "q_abc123",
  "premium": 5.00,
  "currency": "EUR",
  "valid_until": "2025-01-15T12:00:00Z",
  "coverage_details": {...}
}
```

#### Emite Poliță
```
POST /api/insurance/policies
```
Emite poliță de asigurare pentru o comandă.

#### Obține Poliță
```
GET /api/insurance/policies/{policyId}
```
Obține detaliile poliței și URL-ul documentului.

#### Anulează Poliță
```
POST /api/insurance/policies/{policyId}/void
```
Anulează polița (ex: când comanda este anulată).

#### Returnează Poliță
```
POST /api/insurance/policies/{policyId}/refund
```
Procesează returnarea poliței.

### Interfață Adaptor Furnizor

```php
interface InsuranceAdapterInterface
{
    public function getQuote(QuoteRequest $request): QuoteResponse;
    public function issuePolicy(PolicyRequest $request): PolicyResponse;
    public function voidPolicy(string $policyId): bool;
    public function refundPolicy(string $policyId, float $amount): RefundResponse;
    public function syncStatus(string $policyId): PolicyStatus;
}
```

### Flux Status Poliță

```
pending → issued → [active]
                ↘ voided
                ↘ refunded
                ↘ error
```

### Exemplu de Integrare

```php
use App\Services\Insurance\InsuranceService;

// Obține cotație în timpul checkout-ului
$insurance = app(InsuranceService::class);
$quote = $insurance->getQuote($cartItems, $event);

// Emite poliță după plată
$policy = $insurance->issuePolicy($order, $quote);

// Procesează anulare la cancelare
$insurance->voidPolicy($policy->id);
```

### Rezolvare Configurare

Serviciul rezolvă configurarea verificând (în ordine):
1. Config specific tip bilet
2. Config specific eveniment
3. Config implicit tenant

```php
$config = $insurance->resolveConfig($ticketTypeId, $eventId, $tenantId);
```

### Evenimente Webhook

Serviciul emite următoarele evenimente:
- `insurance.quote.created`
- `insurance.policy.issued`
- `insurance.policy.voided`
- `insurance.policy.refunded`
- `insurance.policy.error`

### Metrici

Urmărește performanța asigurărilor:
- Rată de atașare (% comenzi cu asigurare)
- Total prime colectate
- Rate anulare/returnare
- Primă medie per comandă
- Timpuri de răspuns furnizor

# CRM (Gestionarea Relațiilor cu Clienții)

## Prezentare Scurtă

Cunoaște-ți clienții ca niciodată cu CRM-ul nostru construit special pentru organizatorii de evenimente. Fiecare achiziție de bilet, fiecare eveniment la care au participat, fiecare interacțiune - toate într-o singură vedere unificată. Transformă datele în relații și relațiile în fani loiali.

Construiește profiluri complete de clienți automat pe măsură ce oamenii interacționează cu evenimentele tale. Istoric de achiziții, înregistrări de participare, preferințe de comunicare - tot ce ai nevoie pentru a înțelege cine sunt clienții tăi și ce iubesc.

Segmentează-ți audiența cu precizie. Creează grupuri bazate pe tipare de cheltuieli, preferințe de evenimente, frecvență de participare sau orice criteriu personalizat. Targetează clienții VIP cu oferte exclusive, re-implică participanții pierduți sau răsplătește-ți cei mai loiali fani.

Lansează campanii automatizate de email care se simt personale. Întâmpină clienții noi, amintește-le participanților anteriori despre evenimente similare viitoare sau trimite oferte de ziua de naștere. CRM-ul face munca grea în timp ce tu te concentrezi pe crearea de evenimente grozave.

Urmărește valoarea pe viață a clientului pentru a înțelege care segmente îți conduc afacerea. Identifică-ți clienții cu cea mai mare valoare și asigură-te că primesc tratament VIP. Detectează riscul de churn devreme și ia măsuri înainte ca clienții să se îndepărteze.

Conformitatea GDPR este integrată, nu adăugată ulterior. Onorează preferințele de consimțământ, gestionează cererile de ștergere date și menține jurnale complete de audit. Importă și exportă datele clienților ușor cu suport CSV și Excel.

Evenimentele tale creează conexiuni. CRM-ul te ajută să le păstrezi.

---

## Descriere Detaliată

Microserviciul CRM este un sistem comprehensiv de gestionare a relațiilor cu clienții conceput special pentru organizatorii de evenimente și platformele de ticketing. Agregă datele clienților din toate punctele de contact în profiluri unificate și oferă instrumente pentru segmentare, automatizare marketing și analize clienți.

### Profiluri Unificate de Clienți

Fiecare interacțiune pe care un client o are cu platforma ta contribuie la profilul lor:
- Achiziții de bilete și istoric comenzi
- Înregistrări de participare la evenimente
- Metrici de engagement email
- Interacțiuni suport
- Note și taguri personalizate

### Segmentare Avansată

Creează segmente dinamice bazate pe criterii multiple:
- **Comportamentale**: Frecvență achiziții, rată participare, data ultimei achiziții
- **Demografice**: Locație, vârstă, preferințe
- **Bazate pe valoare**: Cheltuială totală, valoare medie comandă, valoare pe viață
- **Personalizate**: Taguri, note, câmpuri personalizate

Segmentele se actualizează automat pe măsură ce datele clienților se schimbă, asigurând că targetarea ta este mereu actuală.

### Automatizare Marketing

Configurează campanii automatizate declanșate de acțiuni ale clienților sau reguli bazate pe timp:
- Serii de bun venit pentru clienți noi
- Campanii de re-engagement pentru clienți inactivi
- Recomandări de evenimente bazate pe participarea anterioară
- Oferte de ziua de naștere și aniversare
- Solicitări feedback post-eveniment

### Valoarea pe Viață a Clientului

Sistemul calculează și urmărește CLV pentru fiecare client, permițând:
- Identificarea clienților cu valoare mare
- Analiză ROI pe canal de achiziție
- Predicție și prevenție churn
- Segmentare bazată pe valoare

### Confidențialitate Date

Conformitate GDPR completă cu:
- Gestionare consimțământ
- Drept de acces și ștergere
- Capabilități export date
- Jurnal de audit

---

## Funcționalități

### Profiluri Clienți
- Profiluri unificate de clienți cu istoric achiziții
- Urmărire istoric participare la evenimente
- Taguri și etichete personalizate
- Note client și timeline activitate
- Detectare și fuzionare duplicate
- Identificare clienți VIP

### Segmentare
- Segmentare avansată audiență
- Actualizări automate segmente
- Reguli segmente multi-criteriu
- Analize performanță segmente
- Export liste clienți

### Marketing
- Campanii automatizate de email
- Integrare marketing SMS
- Integrare cu șabloane email
- Recuperare coșuri abandonate
- Analize campanii

### Analize
- Urmărire valoare pe viață client
- Indicatori predicție churn
- Scor de engagement
- Analiză de cohortă
- Analiză tipare de achiziție

### Gestionare Date
- Import/export date clienți (CSV, Excel)
- Detectare și fuzionare duplicate
- Capabilități îmbogățire date
- Suport operațiuni în masă

### Conformitate
- Instrumente conformitate GDPR
- Gestionare consimțământ
- Fluxuri ștergere date
- Jurnal de audit

---

## Cazuri de Utilizare

### Program Clienți VIP
Identifică automat clienții cu cea mai mare valoare. Etichetează-i ca VIP și asigură-te că primesc acces prioritar, oferte exclusive și comunicare personalizată.

### Campanii de Re-engagement
Segmentează clienții care nu au cumpărat în 6 luni. Trimite-le oferte personalizate bazate pe preferințele lor anterioare de evenimente pentru a-i aduce înapoi.

### Nurturare Clienți Noi
Întâmpină clienții noi cu o serie de emailuri prezentând locația ta, evenimentele viitoare și beneficiile de loialitate. Construiește relația de la prima achiziție.

### Marketing Bazat pe Evenimente
După un concert, trimite automat email participanților despre artiști similari viitori. Folosește preferințele lor de gen pentru a sugera evenimente relevante.

### Recuperare Coș Abandonat
Când clienții lasă articole în coș, declanșează emailuri automate amintindu-le să finalizeze achiziția cu un stimulent limitat în timp.

### Marketing de Ziua de Naștere
Trimite oferte personalizate de ziua de naștere clienților, generând vânzări suplimentare în timp ce îi faci să se simtă apreciați.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul CRM oferă capabilități de gestionare a relațiilor cu clienții pentru platformele de ticketing pentru evenimente. Agregă datele clienților, permite segmentarea, suportă automatizarea marketing și oferă analize.

### Arhitectură

```
Evenimente Platformă → Serviciu CRM → Profiluri Clienți
                            ↓
                    Motor Segmentare
                            ↓
                    Automatizare Marketing → Servicii Email/SMS
                            ↓
                    Dashboard Analize
```

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `crm_customers` | Profiluri unificate clienți |
| `crm_segments` | Definiții segmente |
| `crm_segment_customers` | Apartenență segmente |
| `crm_campaigns` | Campanii marketing |
| `crm_activities` | Jurnal activitate clienți |

### Endpoint-uri API

#### Listează Clienți
```
GET /api/crm/customers
```
Listează clienții cu filtrare și paginare.

**Parametri Query:**
- `search` - Caută după nume, email
- `segment_id` - Filtrează după segment
- `tags` - Filtrează după taguri
- `min_ltv` - Valoare minimă pe viață
- `last_purchase_after` - Filtru dată

#### Obține Client
```
GET /api/crm/customers/{id}
```
Obține profilul complet al clientului cu istoric.

#### Creează Segment
```
POST /api/crm/segments
```
Creează un nou segment de clienți.

**Cerere:**
```json
{
  "name": "Clienți VIP",
  "rules": {
    "operator": "and",
    "conditions": [
      {"field": "total_spend", "operator": ">=", "value": 500},
      {"field": "events_attended", "operator": ">=", "value": 5}
    ]
  },
  "auto_update": true
}
```

#### Obține Clienți Segment
```
GET /api/crm/segments/{id}/customers
```
Listează clienții dintr-un segment.

#### Creează Campanie
```
POST /api/crm/campaigns
```
Creează campanie marketing automatizată.

#### Obține Analize LTV
```
GET /api/crm/analytics/ltv
```
Analize valoare pe viață client.

### Structură Profil Client

```json
{
  "id": "cust_abc123",
  "email": "client@exemplu.com",
  "name": "Ion Popescu",
  "phone": "+40722123456",
  "created_at": "2024-01-15T10:00:00Z",
  "metrics": {
    "total_orders": 15,
    "total_spend": 750.00,
    "events_attended": 12,
    "average_order_value": 50.00,
    "lifetime_value": 1200.00,
    "last_purchase": "2025-01-10T18:30:00Z"
  },
  "segments": ["vip", "fani-rock"],
  "tags": ["early-adopter", "newsletter"],
  "preferences": {
    "genres": ["rock", "jazz"],
    "communication": ["email"]
  },
  "consent": {
    "marketing_email": true,
    "marketing_sms": false,
    "updated_at": "2024-06-01T12:00:00Z"
  }
}
```

### Reguli Segmentare

```json
{
  "operator": "and",
  "conditions": [
    {"field": "total_spend", "operator": ">=", "value": 100},
    {
      "operator": "or",
      "conditions": [
        {"field": "last_purchase", "operator": "within", "value": "90d"},
        {"field": "events_attended", "operator": ">=", "value": 3}
      ]
    }
  ]
}
```

### Configurare

```php
'crm' => [
    'ltv_calculation_period' => 365, // zile
    'churn_threshold_days' => 180,
    'segment_refresh_interval' => 60, // minute
    'duplicate_detection' => [
        'fields' => ['email', 'phone'],
        'fuzzy_match' => true,
    ],
    'integrations' => [
        'mailchimp' => env('MAILCHIMP_API_KEY'),
        'sendgrid' => env('SENDGRID_API_KEY'),
        'twilio' => env('TWILIO_API_KEY'),
    ],
]
```

### Exemplu de Integrare

```php
use App\Services\CRM\CRMService;

$crm = app(CRMService::class);

// Găsește sau creează client
$customer = $crm->findOrCreate([
    'email' => 'client@exemplu.com',
    'name' => 'Ion Popescu',
]);

// Adaugă la segment
$crm->addToSegment($customer->id, 'vip');

// Urmărește activitate
$crm->trackActivity($customer->id, 'purchase', [
    'order_id' => 'ord_123',
    'amount' => 150.00,
]);

// Obține LTV
$ltv = $crm->getLifetimeValue($customer->id);
```

### Metrici

Urmărește performanța CRM:
- Clienți activi (30/60/90 zile)
- Rată achiziție clienți
- Rată churn
- Valoare medie pe viață
- Rate creștere segmente
- Rate conversie campanii

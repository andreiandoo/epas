# Integrare Salesforce

## Prezentare Scurtă

Adu vânzările tale de bilete pentru evenimente în CRM-ul lider mondial. Integrarea Salesforce sincronizează clienții, comenzile și datele evenimentelor direct cu Salesforce, oferind echipei tale de vânzări vizibilitate completă asupra fiecărei interacțiuni.

Fiecare achiziție de bilet spune o poveste. Vezi-o în Salesforce. Când clienții cumpără bilete, înregistrările lor de contact se actualizează automat. Istoricul achizițiilor, participarea la evenimente și tiparele de cheltuieli ajung în CRM-ul tău fără introducere manuală de date.

Contactele se sincronizează bidirecțional. Creează un client în platforma de ticketing și apare în Salesforce. Actualizează un contact în Salesforce și platforma ta reflectă schimbarea. O singură sursă de adevăr, mereu sincronizată.

Lead-urile se convertesc în oportunități. Urmărește potențialii cumpărători VIP, clienții corporativi și vânzările de grup prin pipeline-ul tău Salesforce. Leagă oportunitățile de evenimente specifice și măsoară conversia de la lead la vânzare bilet.

Maparea personalizată a câmpurilor îți dă controlul. Mapează datele biletelor la orice câmp Salesforce standard sau personalizat. Numele evenimentelor, tipurile de bilete, datele achizițiilor și câmpurile de înregistrare personalizate ajung unde ai nevoie.

Suportul SOQL permite filtrare puternică. Sincronizează doar înregistrările care contează. Filtrează după dată, eveniment, valoare achiziție sau orice alt criteriu.

Autentificarea OAuth 2.0 menține conexiunile securizate. Securitate standard din industrie cu reîmprospătare automată a token-urilor înseamnă conexiuni fiabile fără intervenție manuală.

Vezi imaginea completă a clientului în Salesforce. Transformă cumpărătorii de bilete în relații pe termen lung.

---

## Descriere Detaliată

Microserviciul de Integrare Salesforce conectează platforma ta de ticketing pentru evenimente cu Salesforce CRM prin Salesforce REST API. Permite sincronizarea bidirecțională a contactelor, lead-urilor, oportunităților și conturilor.

### Obiecte Salesforce Suportate

Integrarea funcționează cu obiecte Salesforce standard:

- **Contact**: Înregistrări individuale ale clienților cu detalii personale și de contact
- **Lead**: Clienți potențiali înainte de conversie
- **Opportunity**: Deal-uri de vânzări legate de evenimente sau pachete de bilete
- **Account**: Înregistrări companii pentru vânzări B2B de bilete

Obiectele personalizate sunt suportate prin configurarea mapării câmpurilor.

### Direcții Sincronizare

| Direcție | Descriere |
|----------|-----------|
| Platformă → Salesforce | Împinge datele clienților când sunt cumpărate biletele |
| Salesforce → Platformă | Trage actualizările făcute în Salesforce înapoi |
| Bidirecțional | Menține ambele sisteme sincronizate automat |

### Maparea Câmpurilor

Configurează cum se mapează datele platformei la câmpurile Salesforce:

| Câmp Platformă | Câmp Salesforce | Note |
|----------------|-----------------|------|
| email | Email | Identificator primar |
| first_name | FirstName | Câmp standard |
| last_name | LastName | Câmp standard |
| phone | Phone | Câmp standard |
| total_purchases | Custom_Total__c | Exemplu câmp personalizat |
| last_event | Custom_Last_Event__c | Exemplu câmp personalizat |

Creează mapări personalizate nelimitate pentru a se potrivi configurației tale Salesforce.

### Triggere Sincronizare

Datele se sincronizează automat când:

- Client nou creat
- Comandă finalizată
- Profil client actualizat
- Sincronizare manuală declanșată
- Sincronizare programată rulează

### Integrare SOQL

Interoghează datele Salesforce direct:

```sql
SELECT Id, FirstName, LastName, Email
FROM Contact
WHERE Custom_Total__c > 1000
ORDER BY CreatedDate DESC
LIMIT 100
```

Folosește interogări pentru a segmenta clienții sau a extrage date pentru raportare.

### Audit Trail

Fiecare operațiune de sincronizare este logată:

- Ce date au fost sincronizate
- Când a avut loc sincronizarea
- Dacă a reușit sau eșuat
- Detalii erori pentru depanare

---

## Funcționalități

### Sincronizare Obiecte
- Creare și sincronizare contacte
- Gestionare lead-uri
- Urmărire oportunități
- Gestionare conturi
- Suport obiecte personalizate

### Gestionare Câmpuri
- Mapare câmpuri personalizată
- Suport câmpuri standard
- Gestionare câmpuri lookup
- Mapare valori picklist
- Citire câmpuri formulă

### Opțiuni Sincronizare
- Sincronizare bidirecțională
- Push/pull unidirecțional
- Sincronizare în timp real la evenimente
- Sincronizare batch programată
- Declanșare sincronizare manuală

### Interogare și Filtrare
- Suport interogări SOQL
- Filtrare înregistrări
- Builder interogări personalizat
- Gestionare paginare
- Operațiuni date în bulk

### Securitate
- Autentificare OAuth 2.0
- Reîmprospătare automată token
- Stocare securizată credențiale
- Configurare Connected App
- Suport set permisiuni

### Monitorizare
- Logare sincronizare și audit
- Urmărire erori
- Metrici succes/eșec
- Istoric sincronizare
- Mod debug

---

## Cazuri de Utilizare

### Vânzări Evenimente B2B
Creează Accounts pentru companii care cumpără bilete corporative. Leagă Contacts ca angajați. Urmărește Opportunities pentru vânzări de grup în așteptare. Echipa ta de vânzări gestionează totul în Salesforce.

### Urmărirea Clienților VIP
Marchează cumpărătorii de bilete de mare valoare în Salesforce. Vezi istoricul lor complet de achiziții. Permite echipei de vânzări să ofere servicii personalizate și să facă upsell la pachete VIP.

### Campanii Bazate pe Evenimente
Segmentează contactele după participarea la evenimente. Creează campanii Salesforce targetând participanții anteriori. Măsoară ROI-ul marketingului de la lead la achiziție bilet.

### Gestionarea Clienților Corporativi
Urmărește companiile care cumpără bilete de grup. Gestionează reînnoirile de contracte și abonamentele de sezon. Leagă multiple contacte de conturile părinte pentru vizibilitate completă.

### Integrare Pipeline Vânzări
Creează Opportunities pentru pachete mari de bilete sau sponsorizări. Urmărește deal-urile prin pipeline-ul existent Salesforce. Prognozează veniturile din evenimente alături de alte vânzări.

### Follow-Up Post-Eveniment
Sincronizează datele de participare înapoi în Salesforce. Permite follow-up de vânzări cu participanții. Urmărește ce lead-uri s-au convertit din participarea la eveniment.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Salesforce se conectează la Salesforce prin REST API folosind OAuth 2.0. Gestionează operațiunile CRUD pe obiecte, maparea câmpurilor și sincronizarea bidirecțională.

### Cerințe Preliminare

- Org Salesforce (orice ediție cu acces API)
- Connected App configurat
- Utilizator API cu permisiuni adecvate
- Field-level security pentru câmpurile de sincronizare

### Configurare

```php
'salesforce' => [
    'client_id' => env('SALESFORCE_CLIENT_ID'),
    'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
    'redirect_uri' => env('SALESFORCE_REDIRECT_URI'),
    'api_version' => 'v58.0',
    'sandbox' => env('SALESFORCE_SANDBOX', false),
    'sync' => [
        'contacts' => true,
        'leads' => true,
        'opportunities' => true,
        'accounts' => true,
    ],
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/salesforce/auth
```

Returnează URL-ul de autorizare OAuth Salesforce.

**Răspuns:**
```json
{
  "auth_url": "https://login.salesforce.com/services/oauth2/authorize?..."
}
```

#### Callback OAuth

```
POST /api/integrations/salesforce/callback
```

Gestionează callback-ul OAuth, stochează token-urile.

#### Status Conexiune

```
GET /api/integrations/salesforce/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "instance_url": "https://yourorg.salesforce.com",
  "user": "admin@yourorg.com",
  "last_sync": "2025-01-15T10:30:00Z"
}
```

#### Sincronizare Contacte

```
POST /api/integrations/salesforce/sync/contacts
```

**Cerere:**
```json
{
  "direction": "push",
  "filter": {
    "created_after": "2025-01-01"
  }
}
```

#### Obținere Contact

```
GET /api/integrations/salesforce/contacts/{id}
```

#### Creare/Actualizare Contact

```
POST /api/integrations/salesforce/contacts
```

**Cerere:**
```json
{
  "email": "client@exemplu.com",
  "first_name": "Ion",
  "last_name": "Popescu",
  "phone": "+40721234567",
  "custom_fields": {
    "Total_Tickets__c": 5,
    "Last_Event__c": "Summer Festival 2025"
  }
}
```

#### Executare Interogare SOQL

```
POST /api/integrations/salesforce/query
```

**Cerere:**
```json
{
  "query": "SELECT Id, Email, FirstName FROM Contact WHERE Email != null LIMIT 10"
}
```

#### Obținere Mapări Câmpuri

```
GET /api/integrations/salesforce/mappings
```

#### Actualizare Mapări Câmpuri

```
PUT /api/integrations/salesforce/mappings
```

**Cerere:**
```json
{
  "contact": {
    "email": "Email",
    "first_name": "FirstName",
    "last_name": "LastName",
    "total_spent": "Total_Spent__c"
  }
}
```

### Serviciul de Sincronizare

```php
class SalesforceSyncService
{
    public function syncContact(Customer $customer): SalesforceContact
    {
        $data = $this->mapCustomerToSalesforce($customer);

        // Verifică dacă contactul există
        $existing = $this->findByEmail($customer->email);

        if ($existing) {
            return $this->client->update('Contact', $existing->Id, $data);
        }

        return $this->client->create('Contact', $data);
    }

    public function pullContacts(array $filter = []): Collection
    {
        $query = $this->buildQuery('Contact', $filter);
        $results = $this->client->query($query);

        foreach ($results as $record) {
            $this->updateLocalCustomer($record);
        }

        return $results;
    }
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `salesforce_connections` | Token-uri OAuth și info org |
| `salesforce_sync_logs` | Istoric operațiuni sincronizare |
| `salesforce_field_mappings` | Config mapare câmpuri |
| `salesforce_object_cache` | ID-uri Salesforce în cache |

### Gestionarea Erorilor

| Eroare | Descriere | Rezolvare |
|--------|-----------|-----------|
| INVALID_SESSION_ID | Token expirat | Reîmprospătează token automat |
| DUPLICATE_VALUE | Înregistrare există | Actualizează în loc de creare |
| REQUIRED_FIELD_MISSING | Câmp obligatoriu lipsă | Verifică mapările câmpurilor |
| FIELD_INTEGRITY_EXCEPTION | Valoare câmp invalidă | Validează formatul datelor |

### Reîmprospătare Token

```php
// Reîmprospătare automată token
if ($this->isTokenExpired()) {
    $newTokens = $this->client->refreshToken($this->refreshToken);
    $this->storeTokens($newTokens);
}
```

### Operațiuni în Bulk

Pentru volume mari de date:

```php
// Sincronizare în bulk (până la 200 înregistrări per apel)
$batches = $customers->chunk(200);

foreach ($batches as $batch) {
    $this->client->composite('Contact', $batch->toArray());
}
```

### Testare

1. Folosește Salesforce Sandbox pentru testare
2. Setează `SALESFORCE_SANDBOX=true`
3. Creează Connected App de test în sandbox
4. Verifică mapările câmpurilor cu date exemplu
5. Testează sincronizarea în ambele direcții

### Bune Practici de Securitate

1. Folosește utilizator API dedicat cu permisiuni minime
2. Activează restricții IP pe Connected App
3. Stochează token-urile criptat
4. Implementează logare audit
5. Rotație regulată a token-urilor

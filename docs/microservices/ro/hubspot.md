# Integrare HubSpot

## Prezentare Scurtă

Unește afacerea ta de evenimente cu platforma puternică de CRM și marketing HubSpot. Integrarea HubSpot sincronizează automat cumpărătorii de bilete, deal-urile și datele companiilor, permițând automatizarea sofisticată a marketingului bazată pe comportamentul real de achiziție.

Fiecare vânzare de bilet îmbogățește datele tale HubSpot. Contactele clienților se actualizează cu istoricul achizițiilor, participarea la evenimente și tiparele de cheltuieli. Echipa ta de marketing vede imaginea completă fără introducere manuală de date.

Contactele circulă în ambele direcții. Creează un client la checkout și apare în HubSpot. Actualizează preferințele unui contact în HubSpot și platforma ta reflectă schimbarea. Mereu sincronizate, mereu actuale.

Deal-urile urmăresc pipeline-ul de vânzări. Leagă oportunitățile de evenimente specifice, urmărește pachetele de bilete corporative și prognozează veniturile. Procesul tău de vânzări trăiește în HubSpot în timp ce ticketingul rulează fluid.

Maparea proprietăților îți oferă flexibilitate. Mapează datele biletelor la orice proprietate HubSpot - standard sau personalizată. Numele evenimentelor, tipurile de bilete, câmpurile de înregistrare și sumele achizițiilor ajung exact unde ai nevoie.

Automatizarea marketingului se declanșează pe comportament real. Segmentează contactele după evenimentele la care au participat, cheltuielile totale sau tipurile de bilete cumpărate. Construiește workflow-uri care răspund acțiunilor reale ale clienților.

OAuth 2.0 menține totul securizat. Conectează-te o dată, rămâi conectat. Reîmprospătarea automată a token-urilor înseamnă că nu e necesară reconectarea manuală.

Fă din HubSpot centrul de comandă pentru marketingul evenimentelor tale. Cunoaște-ți clienții mai bine ca niciodată.

---

## Descriere Detaliată

Microserviciul de Integrare HubSpot conectează platforma ta de ticketing pentru evenimente cu HubSpot CRM prin HubSpot API. Permite sincronizarea bidirecțională a contactelor, deal-urilor și companiilor pentru managementul complet al relațiilor cu clienții.

### Obiecte HubSpot Suportate

Integrarea funcționează cu obiecte HubSpot de bază:

- **Contacts**: Persoane individuale care cumpără bilete sau se înregistrează la evenimente
- **Deals**: Oportunități de vânzări pentru pachete de bilete, sponsorizări sau vânzări corporative
- **Companies**: Organizații care fac achiziții în bulk sau necesită facturare
- **Tickets**: Tichete de suport (obiectul de serviciu HubSpot, dacă e necesar)

### Cum Funcționează Sincronizarea

Când apar evenimente în platformă, datele curg către HubSpot:

| Eveniment Platformă | Acțiune HubSpot |
|--------------------|-----------------|
| Client creat | Creare/actualizare Contact |
| Comandă finalizată | Actualizare proprietăți Contact, creare Deal |
| Achiziție companie | Creare/actualizare Company, legare Contacts |
| Înregistrare completată | Actualizare Contact cu câmpuri custom |

### Maparea Proprietăților

HubSpot folosește "proprietăți" în loc de câmpuri. Integrarea mapează:

- Proprietăți standard (email, firstname, lastname, phone)
- Proprietăți personalizate pe care le creezi în HubSpot
- Proprietăți calculate bazate pe datele biletelor

Exemple de mapări:
- `total_purchases` → `total_event_purchases`
- `last_event_date` → `last_event_attended`
- `favorite_event_type` → `preferred_event_category`

### Căutare și Filtrare

Interoghează înregistrările HubSpot folosind filtre:

```json
{
  "filterGroups": [{
    "filters": [{
      "propertyName": "total_event_purchases",
      "operator": "GTE",
      "value": "500"
    }]
  }]
}
```

Folosește filtre pentru a segmenta clienții de mare valoare, cumpărătorii recenți sau participanții la evenimente specifice.

### Suport Webhook

HubSpot poate notifica platforma ta despre schimbări:

- Actualizări proprietăți contact
- Schimbări etape deal
- Modificări companii
- Corecții manuale de date

Aceasta permite sincronizare bidirecțională adevărată unde schimbările din HubSpot se reflectă în platformă.

---

## Funcționalități

### Gestionare Contacte
- Creare și sincronizare contacte
- Mapare proprietăți (standard și custom)
- Căutare și filtrare contacte
- Urmărire etape lifecycle
- Integrare liste contacte

### Urmărire Deal-uri
- Creare deal-uri
- Gestionare etape deal
- Urmărire venituri
- Asociere Deal-Contact
- Gestionare pipeline

### Înregistrări Companii
- Creare și sincronizare companii
- Asocieri Contact-Company
- Proprietăți companie
- Relații companie părinte-copil

### Opțiuni Sincronizare
- Sincronizare bidirecțională
- Sincronizare în timp real la evenimente
- Sincronizare batch programată
- Declanșare sincronizare manuală
- Actualizări incrementale

### Securitate
- Autentificare OAuth 2.0
- Reîmprospătare automată token
- Stocare securizată credențiale
- Permisiuni bazate pe scope

### Monitorizare
- Logare sincronizare
- Urmărire erori
- Istoric webhook
- Mod debug

---

## Cazuri de Utilizare

### Segmentare Marketing
Segmentează contactele după participarea la evenimente, valoarea achizițiilor sau tipurile de bilete. Creează liste HubSpot pentru campanii email targetate. Trimite conținut personalizat bazat pe comportamentul real.

### Pipeline Vânzări
Urmărește vânzările de bilete corporative ca Deal-uri. Mută oportunitățile prin pipeline. Prognozează veniturile din evenimente alături de restul afacerii.

### Ciclul de Viață al Clientului
Urmărește clienții de la prima achiziție la cumpărător fidel recurent. Automatizează actualizările etapelor lifecycle bazate pe frecvența și valoarea achizițiilor.

### Automatizare Marketing Evenimente
Declanșează workflow-uri când clienții cumpără bilete. Trimite automat secvențe de confirmare, informații pre-eveniment și follow-up-uri post-eveniment.

### Gestionare Companii
Leagă multiple contacte de înregistrările companiilor. Urmărește vânzările de bilete B2B per organizație. Gestionează conturile corporative și achizițiile în bulk.

### Integrare Suport
Creează tichete HubSpot când clienții au probleme. Urmărește rezolvarea alături de istoricul achizițiilor. Oferă suport cu context.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare HubSpot se conectează la HubSpot prin API-ul v3 folosind OAuth 2.0. Gestionează operațiunile pe contacte, deal-uri și companii cu capabilități de sincronizare bidirecțională.

### Cerințe Preliminare

- Cont HubSpot (CRM gratuit sau nivel plătit)
- Cont developer pentru aplicația OAuth
- Scope-uri API: contacts, deals, companies

### Configurare

```php
'hubspot' => [
    'client_id' => env('HUBSPOT_CLIENT_ID'),
    'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
    'redirect_uri' => env('HUBSPOT_REDIRECT_URI'),
    'scopes' => ['crm.objects.contacts.read', 'crm.objects.contacts.write',
                 'crm.objects.deals.read', 'crm.objects.deals.write',
                 'crm.objects.companies.read', 'crm.objects.companies.write'],
    'sync' => [
        'contacts' => true,
        'deals' => true,
        'companies' => true,
    ],
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/hubspot/auth
```

Returnează URL-ul de autorizare OAuth HubSpot.

#### Callback OAuth

```
POST /api/integrations/hubspot/callback
```

Gestionează callback-ul OAuth și stochează token-urile.

#### Status Conexiune

```
GET /api/integrations/hubspot/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "portal_id": "12345678",
  "hub_domain": "companiata.hubspot.com",
  "last_sync": "2025-01-15T10:30:00Z"
}
```

#### Sincronizare Contacte

```
POST /api/integrations/hubspot/sync/contacts
```

#### Creare/Actualizare Contact

```
POST /api/integrations/hubspot/contacts
```

**Cerere:**
```json
{
  "email": "client@exemplu.com",
  "properties": {
    "firstname": "Ion",
    "lastname": "Popescu",
    "phone": "+40721234567",
    "total_event_purchases": 500,
    "last_event_attended": "Summer Festival 2025"
  }
}
```

#### Căutare Contacte

```
POST /api/integrations/hubspot/contacts/search
```

**Cerere:**
```json
{
  "filterGroups": [{
    "filters": [{
      "propertyName": "email",
      "operator": "EQ",
      "value": "client@exemplu.com"
    }]
  }],
  "properties": ["firstname", "lastname", "email", "total_event_purchases"]
}
```

#### Creare Deal

```
POST /api/integrations/hubspot/deals
```

**Cerere:**
```json
{
  "properties": {
    "dealname": "Bilete Corporative - Summer Festival",
    "amount": 5000,
    "dealstage": "qualifiedtobuy",
    "pipeline": "default"
  },
  "associations": {
    "contacts": ["contact_123"],
    "companies": ["company_456"]
  }
}
```

#### Obținere Mapări Proprietăți

```
GET /api/integrations/hubspot/mappings
```

#### Actualizare Mapări Proprietăți

```
PUT /api/integrations/hubspot/mappings
```

### Serviciul de Sincronizare

```php
class HubSpotSyncService
{
    public function syncContact(Customer $customer): array
    {
        $properties = $this->mapToHubSpot($customer);

        // Caută contact existent
        $existing = $this->searchByEmail($customer->email);

        if ($existing) {
            return $this->client->contacts()->update($existing['id'], $properties);
        }

        return $this->client->contacts()->create($properties);
    }

    public function createDealFromOrder(Order $order): array
    {
        return $this->client->deals()->create([
            'properties' => [
                'dealname' => "Comandă #{$order->number}",
                'amount' => $order->total,
                'dealstage' => 'closedwon',
            ],
            'associations' => [
                'contacts' => [$order->customer->hubspot_id],
            ],
        ]);
    }
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `hubspot_connections` | Token-uri OAuth și info portal |
| `hubspot_sync_logs` | Istoric operațiuni sincronizare |
| `hubspot_property_mappings` | Config mapare proprietăți |

### Gestionarea Erorilor

| Eroare | Descriere | Rezolvare |
|--------|-----------|-----------|
| 401 | Neautorizat | Reîmprospătează token |
| 409 | Conflict | Înregistrarea există deja, actualizează în loc |
| 429 | Limită rate | Implementează backoff |
| 400 | Cerere invalidă | Verifică numele și valorile proprietăților |

### Limite Rate

Limite API HubSpot:
- 100 cereri per 10 secunde
- 500.000 cereri per zi (variază după nivel)

Implementează coadă de cereri pentru operațiuni în bulk.

### Webhook-uri

Configurează webhook-uri HubSpot pentru sincronizare bidirecțională:

```php
// Endpoint webhook
POST /api/webhooks/hubspot

// Gestionează schimbările primite
public function handleWebhook(Request $request): void
{
    foreach ($request->input('events') as $event) {
        if ($event['subscriptionType'] === 'contact.propertyChange') {
            $this->syncFromHubSpot($event['objectId']);
        }
    }
}
```

### Testare

1. Creează cont de test HubSpot developer
2. Configurează aplicația OAuth cu portal de test
3. Testează crearea și actualizarea contactelor
4. Verifică mapările proprietăților
5. Testează asocierile deal-urilor

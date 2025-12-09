# Integrare Google Ads

## Prezentare Scurtă

Nu mai ghici care reclame vând bilete. Integrarea Google Ads conectează vânzările tale de bilete direct la campaniile publicitare, arătându-ți exact care cuvinte cheie, reclame și audiențe generează venituri.

Fiecare achiziție de bilet devine o conversie pe care o poți urmări. Când cineva dă click pe reclama ta și cumpără bilete, Google Ads știe. Aceste date se întorc la campaniile tale, permițând algoritmilor de licitare inteligentă să găsească mai mulți cumpărători ca cei mai buni clienți ai tăi.

Conversiile îmbunătățite duc atribuirea mai departe. Prin partajarea securizată a datelor hash-uite ale clienților, ajuți Google să potrivească mai multe achiziții cu click-uri pe reclame - chiar și când cookie-urile eșuează. Atribuire mai bună înseamnă optimizare mai inteligentă.

Construiește audiențe din baza ta de clienți. Încarcă listele de cumpărători în Google Ads și găsește persoane similare în Search, YouTube și Display. Targetează participanții anteriori pentru evenimente viitoare sau exclude deținătorii de bilete existenți pentru a te concentra pe audiențe noi.

Urmărirea server-side înseamnă date fiabile. Spre deosebire de pixelii bazați pe browser pe care ad blocker-ele îi pot opri, integrarea noastră trimite datele de conversie direct de pe serverele tale. Fiecare vânzare este contorizată cu acuratețe.

Statusul conversiilor în timp real îți permite să monitorizezi ce se întâmplă. Vezi care conversii au fost trimise, potrivite și atribuite. Rezolvă problemele înainte să afecteze performanța campaniilor.

Modul test validează configurația înainte de a merge live. Trimite conversii de test și verifică că apar corect în Google Ads înainte de a activa urmărirea în producție.

Transformă investiția ta în Google Ads în vânzări de bilete măsurabile. Cunoaște-ți adevăratul return on ad spend și scalează ce funcționează.

---

## Descriere Detaliată

Microserviciul de Integrare Google Ads oferă urmărirea conversiilor server-side și gestionarea audiențelor pentru campaniile tale Google Ads. Trimite automat evenimente de achiziție și lead-uri către Google, permițând atribuirea precisă și optimizarea campaniilor.

### Cum Funcționează

Când un client finalizează o achiziție de bilet:

1. Sistemul captează detaliile tranzacției și orice identificatori de click Google (GCLID, GBRAID, WBRAID)
2. Datele clientului sunt hash-uite folosind SHA-256 pentru confidențialitate
3. Un eveniment de conversie este trimis către Google Ads prin API
4. Google potrivește conversia cu click-ul original pe reclamă
5. Datele de atribuire îmbunătățesc optimizarea campaniei tale

### Urmărirea Identificatorilor de Click

Integrarea urmărește multipli identificatori de click Google:

- **GCLID**: ID standard Google Click din reclamele Search și Shopping
- **GBRAID**: Măsurare app-to-web pentru utilizatorii iOS
- **WBRAID**: Identificator de măsurare web-to-app

Acești identificatori sunt capturați când utilizatorii ajung de la Google Ads și stocați pe tot parcursul călătoriei lor de achiziție.

### Conversii Îmbunătățite

Conversiile îmbunătățite îmbunătățesc atribuirea prin trimiterea datelor hash-uite ale clienților:
- Adresă de email (hash-uită SHA-256)
- Număr de telefon (hash-uit SHA-256)
- Nume și adresă (hash-uite SHA-256)

Google folosește aceste date pentru a potrivi conversiile când identificatorii de click nu sunt disponibili, îmbunătățind semnificativ acuratețea atribuirii.

### Acțiuni de Conversie

Integrarea suportă multiple tipuri de conversie:

- **Purchase**: Când un client finalizează o comandă de bilete
- **Lead**: Când cineva se înregistrează sau trimite un formular
- **Add to Cart**: Când biletele sunt adăugate în coș
- **Begin Checkout**: Când checkout-ul este inițiat

Fiecare conversie include date despre valoare pentru urmărirea veniturilor și licitarea bazată pe valoare.

### Sincronizare Audiențe

Integrarea Customer Match îți permite să:
- Încarci liste de clienți în Google Ads
- Creezi audiențe din cumpărătorii anteriori
- Construiești audiențe lookalike pentru a găsi clienți noi
- Excluzi clienții existenți din campanii

Listele se sincronizează automat pe baza configurației tale, menținând audiențele proaspete.

### Ferestre de Atribuire

Conversiile sunt atribuite pe baza setărilor de atribuire Google Ads:
- Conversii click-through: Până la 90 de zile
- Conversii view-through: Până la 30 de zile
- Model de atribuire: Data-driven sau bazat pe reguli

---

## Funcționalități

### Urmărirea Conversiilor
- Urmărire automată conversii achiziție
- Urmărire lead-uri și înregistrări
- Evenimente add to cart
- Evenimente begin checkout
- Acțiuni de conversie personalizate
- Urmărirea valorii conversiilor

### Conversii Îmbunătățite
- Potrivire email hash-uit
- Potrivire telefon hash-uit
- Îmbunătățire date first-party
- Atribuire cross-device
- Partajare date conformă confidențialității

### Suport Click ID
- Urmărire și stocare GCLID
- GBRAID pentru atribuire iOS
- Urmărire WBRAID web-to-app
- Backup cookie first-party
- Validare Click ID

### Gestionarea Audiențelor
- Încărcări Customer Match
- Sincronizare automată liste
- Segmentarea audiențelor
- Targetare lookalike
- Liste de excludere

### Monitorizare și Testare
- Status conversii în timp real
- Mod test pentru validare
- Deduplicarea conversiilor
- Logare și alerte erori
- Urmărirea răspunsurilor API

### Integrare Campanii
- Suport licitare bazată pe valoare
- Mapare acțiuni de conversie
- Suport conturi multiple
- Încărcări conversii offline
- Procesare batch

---

## Cazuri de Utilizare

### Campanii Performance Max
Alimentează cu date de conversie precise campaniile Performance Max. AI-ul Google are nevoie de semnale de calitate pentru a optimiza în Search, YouTube, Display și Discover. Date mai bune înseamnă performanță mai bună.

### Optimizare Campanii Search
Urmărește care cuvinte cheie generează vânzări de bilete, nu doar click-uri. Licitează mai mult pe cuvintele cheie care convertesc și reduce cheltuielile pe cele care generează trafic dar fără venituri.

### Publicitate YouTube
Măsoară adevăratul return pe reclamele YouTube. Vezi care campanii video generează achiziții de bilete și optimizează creative-ul pe baza vânzărilor reale, nu doar a vizualizărilor.

### Campanii de Remarketing
Construiește audiențe din vizitatorii site-ului și cumpărătorii anteriori. Arată reclame targetate persoanelor care au navigat evenimente dar nu au cumpărat, sau promovează evenimente noi participanților anteriori.

### Smart Bidding
Activează licitarea Target ROAS sau Maximize Conversion Value. Cu date de conversie precise inclusiv valorile achizițiilor, algoritmii Google optimizează pentru venituri, nu doar conversii.

### Atribuire Cross-Channel
Înțelege cum funcționează împreună diferitele canale Google Ads. Vezi calea completă de la primul click până la achiziția biletului prin multiple puncte de contact.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Google Ads se conectează la Google Ads API pentru a trimite conversii offline și gestiona audiențele Customer Match. Gestionează autentificarea OAuth, încărcările de conversii și sincronizarea audiențelor.

### Cerințe Preliminare

- Cont Google Ads cu acces API
- Credențiale OAuth 2.0 configurate
- Token de dezvoltator Google Ads API
- Acțiuni de conversie create în Google Ads

### Configurare

```php
'google_ads' => [
    'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'oauth' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
    ],
    'conversion_actions' => [
        'purchase' => 'conversions/123456789',
        'lead' => 'conversions/987654321',
    ],
    'enhanced_conversions' => true,
    'test_mode' => env('GOOGLE_ADS_TEST_MODE', false),
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/google-ads/auth
```

Returnează URL-ul OAuth pentru conectarea contului.

**Răspuns:**
```json
{
  "auth_url": "https://accounts.google.com/o/oauth2/v2/auth?...",
  "state": "abc123"
}
```

#### Callback OAuth

```
POST /api/integrations/google-ads/callback
```

Gestionează callback-ul OAuth și stochează token-urile.

#### Status Conexiune

```
GET /api/integrations/google-ads/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "customer_id": "123-456-7890",
  "account_name": "Contul Meu Google Ads",
  "last_sync": "2025-01-15T10:30:00Z"
}
```

#### Trimitere Conversie

```
POST /api/integrations/google-ads/conversions
```

**Cerere:**
```json
{
  "conversion_action": "purchase",
  "order_id": "order_123",
  "conversion_time": "2025-01-15T14:30:00Z",
  "conversion_value": 150.00,
  "currency_code": "EUR",
  "gclid": "CjwKCAiA...",
  "user_data": {
    "email": "client@exemplu.com",
    "phone": "+40721234567",
    "first_name": "Ion",
    "last_name": "Popescu"
  }
}
```

**Răspuns:**
```json
{
  "success": true,
  "conversion_id": "conv_abc123",
  "status": "ACCEPTED"
}
```

#### Listare Acțiuni de Conversie

```
GET /api/integrations/google-ads/conversion-actions
```

Returnează acțiunile de conversie disponibile din contul Google Ads.

#### Creare Audiență

```
POST /api/integrations/google-ads/audiences
```

**Cerere:**
```json
{
  "name": "Cumpărători Evenimente Anterioare",
  "description": "Clienți care au cumpărat bilete în ultimele 90 de zile",
  "membership_life_span": 90
}
```

#### Sincronizare Membri Audiență

```
POST /api/integrations/google-ads/audiences/{id}/sync
```

**Cerere:**
```json
{
  "operation": "add",
  "members": [
    {
      "email": "user1@exemplu.com",
      "phone": "+40721234567"
    },
    {
      "email": "user2@exemplu.com"
    }
  ]
}
```

### Structura Datelor de Conversie

```json
{
  "conversion_action": "customers/123456/conversionActions/789",
  "conversion_date_time": "2025-01-15 14:30:00+00:00",
  "conversion_value": 150.00,
  "currency_code": "EUR",
  "order_id": "order_123",
  "gclid": "CjwKCAiA...",
  "gbraid": null,
  "wbraid": null,
  "user_identifiers": [
    {
      "hashed_email": "a1b2c3d4..."
    },
    {
      "hashed_phone_number": "e5f6g7h8..."
    }
  ]
}
```

### Hash-uire Conversii Îmbunătățite

```php
// Hash email (lowercase, eliminare spații)
$email = strtolower(trim($email));
$hashedEmail = hash('sha256', $email);

// Hash telefon (format E.164)
$phone = preg_replace('/[^0-9+]/', '', $phone);
$hashedPhone = hash('sha256', $phone);

// Hash nume (lowercase, trim, UTF-8)
$firstName = strtolower(trim($firstName));
$hashedFirstName = hash('sha256', $firstName);
```

### Declanșatoare Evenimente

Integrarea trimite automat conversii pentru:

| Eveniment | Acțiune Conversie | Valoare |
|-----------|-------------------|---------|
| Comandă Finalizată | purchase | Total comandă |
| Utilizator Înregistrat | lead | Configurabil |
| Formular Trimis | lead | Configurabil |

### Încărcare Batch

Pentru scenarii cu volum mare, conversiile sunt grupate:

```php
// Conversiile sunt puse în coadă și trimise în loturi
$batchSize = 2000; // Limita Google
$conversions->chunk($batchSize)->each(function ($batch) {
    $this->uploadConversionBatch($batch);
});
```

### Gestionarea Erorilor

| Eroare | Descriere | Acțiune |
|--------|-----------|---------|
| INVALID_GCLID | Click ID negăsit | Conversie trimisă doar cu date utilizator |
| DUPLICATE_CONVERSION | Deja încărcată | Skip, deduplicarea funcționează |
| INVALID_CONVERSION_ACTION | Acțiune negăsită | Verifică configurația |
| AUTHENTICATION_ERROR | Token expirat | Reîmprospătează token-urile OAuth |

### Testare

Activează modul test pentru validare fără a afecta campaniile:

```php
'test_mode' => true
```

Conversiile de test apar în Google Ads sub "Test events" și nu afectează licitarea.

### Securitate

1. Toate datele utilizatorilor sunt hash-uite înainte de transmisie
2. Token-urile OAuth stocate criptat
3. Apelurile API folosesc doar HTTPS
4. Retenție minimă a datelor
5. Verificare consimțământ GDPR înainte de trimitere

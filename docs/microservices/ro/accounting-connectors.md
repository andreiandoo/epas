# Conectori Contabilitate

## Prezentare Scurtă

Fă legătura între platforma ta de ticketing și software-ul tău de contabilitate cu Conectorii de Contabilitate. Acest serviciu puternic de integrare sincronizează automat facturile tale cu platformele de contabilitate de top, eliminând introducerea manuală a datelor și reducând erorile.

Fie că folosești SmartBill, FGO, Exact, Xero sau QuickBooks, sistemul nostru de adaptoare agnostice de furnizor se conectează fără probleme cu fluxul tău existent de contabilitate. Fiecare vânzare de bilet, returnare și tranzacție curge direct în software-ul tău de contabilitate fără să ridici un deget.

Expertul inteligent de mapare te ghidează prin conectarea produselor, taxelor, conturilor și seriilor de facturi între sisteme. Odată configurat, serviciul asigură automat că clienții există în software-ul tău de contabilitate înainte de emiterea facturilor, sincronizează produsele cu configurațiile corecte de taxe și creează facturi cu toate detaliile necesare.

Ai nevoie de note de creditare pentru returnări? Sunt generate automat. Vrei facturi PDF livrate clienților? Le recuperăm de la furnizorul tău de contabilitate și le livrăm fără probleme. Coada de joburi cu logică de reîncercare asigură că nimic nu se pierde, chiar și în timpul problemelor temporare de conectivitate.

Design-ul cu securitatea pe primul loc înseamnă opțiuni de autentificare OAuth2 și cheie API, stocare criptată a credențialelor și testare conexiune înainte de a trece live. Urmărire completă a erorilor și gestionare dead-letter queue înseamnă că ești mereu conștient de orice probleme.

Nu mai copia date între sisteme. Lasă Conectorii de Contabilitate să facă munca grea în timp ce te concentrezi pe creșterea afacerii tale de evenimente.

---

## Descriere Detaliată

Conectorii de Contabilitate este un serviciu comprehensiv de integrare care automatizează fluxul de date financiare între platforma ta de ticketing și sistemele externe de contabilitate. Construit cu un pattern de adaptor agnostic de furnizor, suportă multipli furnizori de software de contabilitate menținând o experiență consistentă de integrare.

### Furnizori Suportați

- **SmartBill** - Software popular de contabilitate românesc
- **FGO** - Planificare resurse enterprise
- **Exact** - Software cloud pentru business
- **Xero** - Contabilitate bazată pe cloud
- **QuickBooks** - Contabilitate pentru afaceri mici

### Cum Funcționează

1. **Configurare**: Folosește expertul de mapare pentru a conecta structurile de date ale platformei tale cu entitățile software-ului de contabilitate (clienți, produse, taxe, conturi, serii de facturi).

2. **Sincronizare Clienți**: Înainte de emiterea unei facturi, serviciul asigură că clientul există în software-ul tău de contabilitate folosind funcția `ensureCustomer`. Dacă nu, îl creează automat.

3. **Sincronizare Produse**: Funcția `ensureProducts` verifică că toate articolele există în sistemul tău de contabilitate cu configurațiile corecte de taxe.

4. **Emitere Facturi**: Facturile sunt create prin API-ul furnizorului cu toate câmpurile mapate, calculele corecte de taxe și seria corectă de facturi.

5. **Livrare Documente**: Odată emise, facturile PDF sunt recuperate de la furnizorul de contabilitate și pot fi livrate automat clienților.

### Mod Emitere Externă

Serviciul suportă modul `issue_extern` unde facturile sunt create direct în sistemul extern de contabilitate în loc de local. Acesta este ideal pentru afacerile care vor ca software-ul lor de contabilitate să fie sursa de adevăr pentru facturare.

### Integrare eFactura

Pentru afacerile românești, serviciul se integrează cu trimiterea eFactura. Poți alege dacă eFactura este gestionată de furnizorul de contabilitate (gestionată de furnizor) sau de serviciul eFactura al platformei noastre.

---

## Funcționalități

### Integrare Furnizori
- Pattern adaptor agnostic de furnizor
- Furnizori suportați: SmartBill, FGO, Exact, Xero, QuickBooks
- Emitere factură externă (mod issue_extern)
- Autentificare OAuth2 și cheie API
- Testare conexiune înainte de activare

### Mapare Date
- Expert mapare pentru produse, taxe, conturi și serii
- Sincronizare clienți cu creare automată (ensureCustomer)
- Sincronizare produse cu configurare taxe (ensureProducts)
- Suport mapare câmpuri personalizate
- Suport multi-valută

### Procesare Facturi
- Creare facturi prin API furnizor
- Recuperare și livrare PDF
- Generare note de creditare pentru returnări
- Prevenire emitere duplicată
- Procesare facturi în lot

### Fiabilitate
- Coadă de joburi cu logică de reîncercare
- Urmărire erori și coadă dead-letter (DLQ)
- Gestionare automată failover
- Jurnal tranzacții
- Stocare criptată credențiale

### Conformitate
- Opțiuni integrare eFactura
- eFactura gestionată de furnizor sau platformă
- Gestionare TVA conform specificațiilor furnizorului
- Suport conformitate reglementară

---

## Cazuri de Utilizare

### Contabilitate Automatizată
Fiecare vânzare de bilet creează automat o factură corespunzătoare în software-ul tău de contabilitate. Reconcilierea de sfârșit de zi devine o simplă verificare în loc de ore de introducere manuală a datelor.

### Configurare Multi-Furnizor
Organizațiile mari care folosesc diferite software-uri de contabilitate pentru diferite entități pot configura furnizori separați pentru fiecare tenant gestionând totul de pe o singură platformă.

### Vizibilitate Financiară în Timp Real
Echipele de finanțe văd veniturile în software-ul lor de contabilitate pe măsură ce se întâmplă, permițând raportare în timp real și gestionarea fluxului de numerar.

### Procesare Returnări
Când sunt emise returnări, notele de creditare sunt generate automat și sincronizate cu software-ul tău de contabilitate, menținând înregistrări financiare precise.

### Conformitate Fiscală
Maparea corectă a taxelor asigură că toate facturile sunt emise cu cotele corecte de TVA și respectă reglementările fiscale locale.

### Urmă de Audit
Jurnalele complete de sincronizare oferă auditorilor trasabilitate clară între tranzacțiile platformei și înregistrările contabile.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Conectori de Contabilitate oferă sincronizare automată de facturi între platforma de ticketing și furnizorii externi de software de contabilitate. Folosește un pattern adaptor pentru integrare agnostică de furnizor.

### Arhitectură

```
Factură Platformă → Serviciu Contabilitate → Adaptor Furnizor → API Extern
                                          ↓
                                   Motor Mapare
                                          ↓
                                   Manager Coadă
```

### Interfață Adaptor

Fiecare furnizor implementează `AccountingAdapterInterface`:

```php
interface AccountingAdapterInterface
{
    public function authenticate(): bool;
    public function testConnection(): bool;
    public function ensureCustomer(Customer $customer): string;
    public function ensureProducts(array $products): array;
    public function createInvoice(Invoice $invoice): InvoiceResponse;
    public function createCreditNote(Invoice $original, array $items): CreditNoteResponse;
    public function getInvoicePdf(string $invoiceId): string;
    public function getInvoiceStatus(string $invoiceId): string;
}
```

### Configurare

```php
'accounting' => [
    'default_provider' => env('ACCOUNTING_PROVIDER', 'smartbill'),
    'providers' => [
        'smartbill' => [
            'api_key' => env('SMARTBILL_API_KEY'),
            'email' => env('SMARTBILL_EMAIL'),
            'company_cif' => env('SMARTBILL_CIF'),
        ],
        'xero' => [
            'client_id' => env('XERO_CLIENT_ID'),
            'client_secret' => env('XERO_CLIENT_SECRET'),
        ],
        // ... alți furnizori
    ],
    'issue_extern' => true,
    'auto_pdf_delivery' => true,
    'retry_attempts' => 3,
    'retry_delay' => 60, // secunde
]
```

### Endpoint-uri API

#### Test Conexiune
```
POST /api/accounting/test-connection
```
Verifică credențialele și conectivitatea cu furnizorul de contabilitate.

#### Sincronizare Client
```
POST /api/accounting/customers/sync
```
Asigură că clientul există în sistemul de contabilitate.

#### Creare Factură
```
POST /api/accounting/invoices
```
Creează factură în sistemul extern de contabilitate.

#### Creare Notă de Creditare
```
POST /api/accounting/credit-notes
```
Creează notă de creditare pentru returnare.

#### Obține PDF Factură
```
GET /api/accounting/invoices/{id}/pdf
```
Recuperează PDF de la furnizorul de contabilitate.

#### Obține Status Sincronizare
```
GET /api/accounting/sync-status/{jobId}
```
Verifică statusul jobului de sincronizare.

### Configurare Mapare

```json
{
  "products": {
    "ticket_general": "external_product_id_1",
    "ticket_vip": "external_product_id_2"
  },
  "taxes": {
    "standard": "19%",
    "reduced": "9%"
  },
  "accounts": {
    "revenue": "411",
    "receivables": "4111"
  },
  "series": {
    "default": "EPAS",
    "refund": "STORNO"
  }
}
```

### Exemplu de Integrare

```php
use App\Services\Accounting\AccountingService;

// Obține instanța serviciului
$accounting = app(AccountingService::class);

// Asigură că clientul există
$externalCustomerId = $accounting->ensureCustomer($customer);

// Creează factură
$response = $accounting->createInvoice($invoice, [
    'customer_id' => $externalCustomerId,
    'series' => 'EPAS',
    'send_to_anaf' => true,
]);

// Obține PDF
$pdf = $accounting->getInvoicePdf($response->external_id);
```

### Coadă de Joburi

Operațiunile eșuate sunt puse în coadă pentru reîncercare:

```php
// Structură coadă
[
    'job_id' => 'uuid',
    'type' => 'create_invoice',
    'payload' => [...],
    'attempts' => 0,
    'max_attempts' => 3,
    'next_retry_at' => '2025-01-15 10:00:00',
    'error_message' => null,
]
```

### Gestionare Erori

| Eroare | Descriere | Acțiune |
|--------|-----------|---------|
| `AUTH_FAILED` | Autentificare eșuată | Verifică credențialele |
| `CUSTOMER_NOT_FOUND` | Clientul nu există | Rulează ensureCustomer |
| `PRODUCT_NOT_MAPPED` | Produs nemapat | Actualizează config mapare |
| `RATE_LIMITED` | Limită rate API atinsă | Reîncercare automată |
| `PROVIDER_ERROR` | Eroare API extern | Verifică status furnizor |

### Monitorizare

Urmărește sănătatea sincronizării:
- Facturi sincronizate pe oră
- Rate de succes/eșec
- Latență medie sincronizare
- Adâncime coadă
- Rate de eroare pe tip

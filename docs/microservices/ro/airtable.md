# Integrare Airtable

## Prezentare Scurtă

Alimentează-ți workflow-urile cu platforma flexibilă de baze de date Airtable. Integrarea Airtable sincronizează comenzile, biletele și datele clienților în baze Airtable, permițând vizualizări personalizate, automatizări și colaborare în echipă pe care spreadsheet-urile nu le pot oferi.

Airtable combină simplitatea spreadsheet-urilor cu puterea bazelor de date. Datele tale de evenimente devin acționabile - sortabile, filtrabile, conectabile și automatizabile.

Împinge date automat când apar evenimente. Comenzile noi apar în bază instant. Vânzările de bilete populează tabelele în timp real. Înregistrările clienților se actualizează continuu.

Sincronizarea bidirecțională menține totul aliniat. Actualizează o înregistrare în Airtable și schimbările ajung înapoi în platformă. Sincronizare adevărată în ambele direcții.

Maparea personalizată a câmpurilor conectează datele corect. Mapează câmpurile biletelor la coloanele Airtable. Leagă înregistrări conexe. Păstrează tipurile de date și relațiile.

Baze multiple organizează operațiuni complexe. Datele de vânzări într-o bază, gestionarea clienților în alta, planificarea evenimentelor într-a treia. Conectate dar organizate.

OAuth sau Personal Access Token - alegerea ta. OAuth securizat pentru integrare completă, sau PAT simplu pentru configurare rapidă. Flexibilitate în autentificare.

Construiește workflow-uri personalizate pe baza datelor tale. Instrumentele de automatizare Airtable se declanșează de la înregistrările sincronizate. Creează, atribuie, notifică - automat.

Fă datele evenimentelor să muncească mai mult. Dincolo de stocare către acțiune.

---

## Descriere Detaliată

Microserviciul de Integrare Airtable conectează platforma ta de ticketing pentru evenimente cu platforma de baze de date Airtable. Permite împingerea înregistrărilor în tabele Airtable și opțional sincronizarea schimbărilor înapoi.

### Fluxul Datelor

| Direcție | Descriere |
|----------|-----------|
| Platformă → Airtable | Împinge comenzi, bilete, clienți în tabele |
| Airtable → Platformă | Sincronizează actualizări înapoi (mod bidirecțional) |

### Tipuri de Date Suportate

- **Comenzi**: Înregistrări complete comenzi cu articole
- **Bilete**: Înregistrări bilete individuale cu date participant
- **Clienți**: Profile clienți cu istoric achiziții
- **Evenimente**: Înregistrări evenimente cu configurare

### Maparea Câmpurilor

Airtable suportă diverse tipuri de câmpuri:
- Text o linie
- Text lung
- Număr, Valută
- Dată, DateTime
- Selectare simplă/multiplă
- Înregistrări legate
- Atașamente

Mapează câmpurile platformei la tipuri de câmpuri Airtable potrivite pentru funcționalitate optimă.

### Moduri Sincronizare

- **Doar Push**: Datele curg către Airtable, fără sincronizare înapoi
- **Bidirecțional**: Schimbările în oricare sistem se sincronizează în celălalt
- **Programat**: Sincronizare periodică la intervale configurate
- **Timp Real**: Sincronizare imediată la schimbări de înregistrări

---

## Funcționalități

### Sincronizare Date
- Export comenzi
- Export bilete
- Export clienți
- Export evenimente

### Mapare Câmpuri
- Mapare câmpuri personalizată
- Conversie tipuri câmpuri
- Suport înregistrări legate
- Gestionare atașamente

### Opțiuni Sincronizare
- Autentificare OAuth 2.0
- Suport Personal Access Token
- Timp real sau programat
- Sincronizare bidirecțională

### Gestionare Baze
- Listare baze disponibile
- Listare tabele în baze
- Creare tabele
- Gestionare câmpuri

### Suport Automatizare
- Triggere webhook
- Evenimente creare înregistrări
- Notificări actualizare

---

## Cazuri de Utilizare

### Workflow Planificare Evenimente
Urmărește planificarea evenimentelor în Airtable cu date vânzări bilete legate. Vezi ce evenimente se vând bine. Coordonează planificarea cu realitatea vânzărilor.

### Customer Success
Gestionează relațiile cu clienții în Airtable. Leagă înregistrările de achiziții de profilele clienților. Urmărește engagement-ul și follow-up-urile.

### Dashboard Operațiuni
Construiește dashboard-uri vizuale cu vizualizări Airtable. Kanban boards pentru status comenzi. Vizualizări calendar pentru evenimente.

### Colaborare Echipă
Partajează bazele cu membrii echipei. Atribuie sarcini bazate pe datele biletelor. Colaborează fără a partaja accesul la platformă.

---

## Documentație Tehnică

### Configurare

```php
'airtable' => [
    'client_id' => env('AIRTABLE_CLIENT_ID'),
    'client_secret' => env('AIRTABLE_CLIENT_SECRET'),
    'redirect_uri' => env('AIRTABLE_REDIRECT_URI'),
    // Sau folosește PAT
    'personal_access_token' => env('AIRTABLE_PAT'),
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/airtable/auth
```

#### Listare Baze

```
GET /api/integrations/airtable/bases
```

#### Listare Tabele

```
GET /api/integrations/airtable/bases/{baseId}/tables
```

#### Sincronizare Înregistrări

```
POST /api/integrations/airtable/sync
```

**Cerere:**
```json
{
  "base_id": "appXXXXXXX",
  "table_id": "tblXXXXXXX",
  "data_type": "orders",
  "mode": "push",
  "field_mapping": {
    "order_number": "Număr Comandă",
    "customer_email": "Email Client",
    "total": "Sumă Totală"
  }
}
```

#### Creare Înregistrare

```
POST /api/integrations/airtable/bases/{baseId}/tables/{tableId}/records
```

**Cerere:**
```json
{
  "fields": {
    "Număr Comandă": "ORD-2025-001",
    "Email Client": "client@exemplu.com",
    "Sumă Totală": 150.00
  }
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `airtable_connections` | Token-uri OAuth sau PAT |
| `airtable_sync_configs` | Configurare sincronizare |
| `airtable_field_mappings` | Config mapare câmpuri |
| `airtable_sync_logs` | Istoric operațiuni sincronizare |

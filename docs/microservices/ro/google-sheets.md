# Integrare Google Sheets

## Prezentare Scurtă

Transformă datele tale de bilete în spreadsheet-uri acționabile. Integrarea Google Sheets exportă comenzi, bilete, clienți și analize evenimente direct în Google Sheets. Construiește dashboard-uri în timp real, automatizează rapoarte și partajează date live cu echipa.

Spreadsheet-urile alimentează deciziile de business. Acum datele tale de evenimente ajung acolo automat. Fără exporturi manuale, fără numere depășite, fără erori de copy-paste.

Sincronizarea în timp real menține sheet-urile actuale. Comenzile noi se adaugă automat pe măsură ce intră. Dashboard-ul de vânzări se actualizează în timp ce privești. Date live la îndemână.

Exporturile programate rulează singure. Rapoarte zilnice de vânzări în fiecare dimineață. Sumare săptămânale pentru management. Analize lunare pentru planificare. Setează și uită.

Maparea personalizată a coloanelor îți dă controlul. Alege ce date merg unde. Mapează câmpurile să se potrivească template-urilor existente. Exportă exact ce ai nevoie.

Spreadsheet-uri multiple îți organizează datele. Comenzile într-un sheet, clienții în altul, evenimentele într-al treilea. Organizare clară, acces ușor.

Partajează cu echipa fără efort. Colaborarea Google Sheets înseamnă că toată lumea vede aceleași date. Finance, marketing, operațiuni - toți aliniați.

Securitatea OAuth 2.0 îți protejează contul Google. Autorizare securizată, fără parole stocate. Conectează-te cu încredere.

Transformă datele brute în insights. Ia decizii mai bune mai rapid.

---

## Descriere Detaliată

Microserviciul de Integrare Google Sheets conectează platforma ta de ticketing pentru evenimente cu Google Sheets, permițând export automat de date, sincronizare în timp real și raportare personalizată.

### Tipuri de Export

| Tip Date | Conținut |
|----------|----------|
| Comenzi | Detalii comandă, totaluri, status, referință client |
| Bilete | Bilete individuale, participanți, status check-in |
| Clienți | Info contact, istoric achiziții, preferințe |
| Evenimente | Detalii eveniment, sumar vânzări, participare |

### Moduri Sincronizare

- **Sincronizare Completă**: Exportă toate datele, înlocuind conținutul existent
- **Incrementală**: Adaugă doar înregistrări noi de la ultima sincronizare
- **Append**: Adaugă rânduri noi fără a atinge datele existente
- **Timp Real**: Sincronizare continuă pe măsură ce apar evenimente

### Opțiuni Programare

| Frecvență | Caz de Utilizare |
|-----------|------------------|
| Timp real | Dashboard-uri live, monitorizare urgentă |
| Orar | Perioade active de vânzări |
| Zilnic | Raportare regulată |
| Săptămânal | Sumare management |

### Maparea Coloanelor

Configurează ce câmpuri apar și în ce ordine:
- Selectează câmpurile de inclus
- Setează ordinea coloanelor
- Redenumește headerele
- Aplică reguli de formatare

---

## Funcționalități

### Export Date
- Export comenzi
- Export bilete cu participanți
- Export liste clienți
- Export analize evenimente

### Opțiuni Sincronizare
- Sincronizare timp real
- Sincronizare programată
- Sincronizare manuală
- Completă sau incrementală

### Gestionare Spreadsheet-uri
- Creare spreadsheet-uri noi
- Selectare spreadsheet-uri existente
- Sheet-uri multiple per tip
- Headere automate

### Personalizare
- Mapare coloane personalizată
- Selecție câmpuri
- Formatare headere
- Formatare date/numere

### Autentificare
- Autentificare securizată OAuth 2.0
- Reîmprospătare token
- Suport conturi multiple
- Scoping permisiuni

### Monitorizare
- Istoric sincronizare
- Urmărire joburi
- Logare erori
- Confirmare livrare

---

## Cazuri de Utilizare

### Dashboard Vânzări Timp Real
Urmărește vânzările de bilete pe măsură ce se întâmplă. Creează grafice care se actualizează automat. Partajează cu stakeholderii pentru vizibilitate live.

### Rapoarte Zilnice Finance
Exportă comenzile zilnice pentru reconciliere. Rapoarte automatizate în fiecare dimineață. Echipa de finance are mereu date proaspete.

### Analiză Marketing
Exportă date clienți pentru segmentare. Analizează tiparele de achiziție. Planifică campanii targetate bazate pe comportament real.

### Operațiuni în Ziua Evenimentului
Liste participanți live pentru echipele de check-in. Urmărire participare în timp real. Monitorizare capacitate în timpul evenimentelor.

### Raportare Post-Eveniment
Analize complete ale evenimentului. Rapoarte participare pentru sponsori. Date pentru planificarea evenimentelor viitoare.

---

## Documentație Tehnică

### Configurare

```php
'google_sheets' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    'scopes' => [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive.file',
    ],
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/google-sheets/auth
```

#### Creare Spreadsheet

```
POST /api/integrations/google-sheets/spreadsheets
```

**Cerere:**
```json
{
  "title": "Raport Vânzări Eveniment",
  "data_type": "orders",
  "columns": ["order_number", "customer_email", "total", "status", "created_at"]
}
```

#### Sincronizare Date

```
POST /api/integrations/google-sheets/sync
```

**Cerere:**
```json
{
  "spreadsheet_id": "1ABC...",
  "data_type": "orders",
  "mode": "incremental",
  "filters": {
    "created_after": "2025-01-01"
  }
}
```

#### Programare Sincronizare

```
POST /api/integrations/google-sheets/schedules
```

**Cerere:**
```json
{
  "spreadsheet_id": "1ABC...",
  "frequency": "daily",
  "time": "06:00",
  "timezone": "Europe/Bucharest",
  "data_type": "orders"
}
```

#### Obținere Istoric Sincronizare

```
GET /api/integrations/google-sheets/sync-history
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `google_sheets_connections` | Token-uri OAuth |
| `google_sheets_spreadsheets` | Spreadsheet-uri legate |
| `google_sheets_sync_jobs` | Joburi programate |
| `google_sheets_column_mappings` | Mapări câmpuri |

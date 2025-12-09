# Integrare Google Workspace

## Prezentare Scurtă

Conectează platforma ta de evenimente la suita de productivitate Google. Integrarea Google Workspace sincronizează datele tale cu Google Drive, Calendar și Gmail. Workflow-uri fluide acolo unde echipa ta deja lucrează.

Google Workspace alimentează munca modernă. Acum operațiunile tale de evenimente curg natural în Drive, Calendar și Gmail. Fișiere stocate, evenimente programate, email-uri trimise - totul automatizat.

Google Drive organizează fișierele tale de evenimente. Exportă rapoarte și liste de participanți direct în foldere Drive. Partajează cu stakeholderii prin interfețe familiare. Stocare cloud cu securitatea Google.

Google Calendar menține programările sincronizate. Creează evenimente de calendar pentru evenimentele tale cu bilete. Trimite invitații automat. Vezi capacitatea și timing-ul dintr-o privire.

Gmail trimite comunicări profesionale. Confirmări comenzi, remindere evenimente, mesaje de follow-up. Livrare fiabilă prin infrastructura Google.

Shared Drives permit colaborarea în echipă. Fișierele evenimentelor accesibile întregii echipe. Fără a mai căuta prin atașamente de email. Totul într-un singur loc organizat.

OAuth 2.0 asigură acces securizat și granular. Solicită doar permisiunile de care ai nevoie. Utilizatorii autorizează prin fluxuri Google familiare. Securitate și comoditate împreună.

Lucrează mai inteligent cu Google. Evenimentele tale, datele tale, workspace-ul tău.

---

## Descriere Detaliată

Microserviciul de Integrare Google Workspace conectează platforma ta de ticketing pentru evenimente cu aplicațiile de productivitate Google. Permite gestionarea fișierelor, sincronizarea calendarului și comunicarea prin email printr-o integrare unificată.

### Componente Integrare

| Serviciu | Capabilități |
|----------|--------------|
| Google Drive | Încărcări fișiere, gestionare foldere, partajare, Shared Drives |
| Google Calendar | Creare evenimente, invitații, remindere, disponibilitate |
| Gmail | Trimitere email, template-uri, urmărire, atașamente |

### Autentificare

Google OAuth 2.0 oferă autorizare securizată:
- Scoping granular al permisiunilor
- Autentificare utilizator sau service account
- Delegare la nivel de domeniu pentru enterprise
- Reîmprospătare automată token-uri

### Fluxul Datelor

| Direcție | Descriere |
|----------|-----------|
| Platformă → Drive | Încarcă rapoarte, exporturi, documente |
| Platformă → Calendar | Creează și actualizează evenimente |
| Platformă → Gmail | Trimite comunicări email |
| Google Workspace → Platformă | Primește notificări webhook |

---

## Funcționalități

### Integrare Google Drive
- Încărcare fișiere în My Drive sau Shared Drives
- Creare și organizare foldere
- Export rapoarte (PDF, Excel, CSV)
- Generare link-uri partajabile
- Setare permisiuni fișiere
- Suport colaborare în timp real

### Integrare Google Calendar
- Creare evenimente calendar
- Trimitere invitații participanților
- Setare remindere și notificări
- Gestionare evenimente recurente
- Verificare disponibilitate
- Suport pentru calendare multiple

### Integrare Gmail
- Trimitere email-uri tranzacționale
- Suport email HTML
- Atașamente fișiere
- Sistem template-uri
- Urmărire livrare
- Urmărire răspunsuri

### Colaborare Echipă
- Suport Shared Drive
- Organizare foldere echipă
- Acces colaborativ documente
- Gestionare permisiuni
- Logare activitate

### Autentificare
- Autentificare securizată OAuth 2.0
- Suport service account
- Delegare la nivel de domeniu
- Acces multi-utilizator
- Scoping permisiuni

---

## Cazuri de Utilizare

### Raportare Automatizată
Rapoartele zilnice de vânzări se încarcă în Drive automat. Echipa de finance accesează datele prin interfețe Google familiare. Fără exporturi manuale necesare.

### Programare Evenimente
Sincronizează evenimentele cu bilete în calendarele echipei. Coordonează programările personalului. Blochează timp pentru setup și demontare. Toată lumea vede același program.

### Comunicări cu Clienții
Trimite email-uri profesionale prin Gmail. Confirmări comenzi cu bilete atașate. Remindere evenimente înaintea show-ului. Sondaje post-eveniment și mesaje de mulțumire.

### Partajare Fișiere în Echipă
Stochează materialele evenimentelor în Shared Drives. Active marketing, checklist-uri operaționale, contracte furnizori. Acces de oriunde, colaborare în timp real.

### Actualizări Stakeholderi
Partajează rapoarte live cu stakeholderii prin link-uri Drive. Actualizează o dată, toată lumea vede date curente. Perfect pentru sponsori, parteneri și executivi.

---

## Documentație Tehnică

### Configurare

```php
'google_workspace' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    'scopes' => [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/gmail.send',
    ],
    // Pentru delegare la nivel de domeniu
    'service_account_credentials' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
    'impersonate_user' => env('GOOGLE_IMPERSONATE_EMAIL'),
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/google-workspace/auth
```

#### Încărcare în Drive

```
POST /api/integrations/google-workspace/drive/upload
```

**Cerere:**
```json
{
  "file_name": "Raport Vânzări - Eveniment 123.pdf",
  "mime_type": "application/pdf",
  "folder_id": "1ABC...",
  "content_base64": "...",
  "shared_drive_id": "0BCD..."
}
```

**Răspuns:**
```json
{
  "success": true,
  "file_id": "1XYZ...",
  "web_view_link": "https://drive.google.com/file/d/1XYZ.../view",
  "web_content_link": "https://drive.google.com/uc?id=1XYZ..."
}
```

#### Creare Folder

```
POST /api/integrations/google-workspace/drive/folders
```

**Cerere:**
```json
{
  "name": "Festival de Vară 2025",
  "parent_id": "1ABC...",
  "shared_drive_id": "0BCD..."
}
```

#### Export Raport în Drive

```
POST /api/integrations/google-workspace/drive/export
```

**Cerere:**
```json
{
  "report_type": "attendees",
  "event_id": "evt_123",
  "format": "xlsx",
  "folder_id": "1ABC...",
  "include_columns": ["name", "email", "ticket_type", "checked_in"]
}
```

#### Creare Eveniment Calendar

```
POST /api/integrations/google-workspace/calendar/events
```

**Cerere:**
```json
{
  "calendar_id": "primary",
  "summary": "Festival de Vară 2025",
  "description": "Festival anual de muzică de vară...",
  "location": "Arena Parc Central",
  "start": {
    "dateTime": "2025-07-15T18:00:00",
    "timeZone": "Europe/Bucharest"
  },
  "end": {
    "dateTime": "2025-07-15T23:00:00",
    "timeZone": "Europe/Bucharest"
  },
  "attendees": [
    {"email": "echipa@companie.com"},
    {"email": "operatiuni@companie.com"}
  ],
  "reminders": {
    "useDefault": false,
    "overrides": [
      {"method": "email", "minutes": 1440},
      {"method": "popup", "minutes": 60}
    ]
  }
}
```

**Răspuns:**
```json
{
  "success": true,
  "event_id": "abc123...",
  "html_link": "https://calendar.google.com/event?eid=..."
}
```

#### Trimitere Email prin Gmail

```
POST /api/integrations/google-workspace/gmail/send
```

**Cerere:**
```json
{
  "to": ["client@exemplu.com"],
  "cc": ["suport@companie.com"],
  "subject": "Biletele Tale pentru Festival de Vară 2025",
  "body_html": "<html><body>...</body></html>",
  "body_text": "Fallback text simplu...",
  "attachments": [
    {
      "filename": "bilete.pdf",
      "mime_type": "application/pdf",
      "content_base64": "..."
    }
  ],
  "reply_to": "evenimente@companie.com"
}
```

**Răspuns:**
```json
{
  "success": true,
  "message_id": "msg123...",
  "thread_id": "thread456..."
}
```

#### Sincronizare Eveniment în Calendar

```
POST /api/integrations/google-workspace/calendar/sync
```

**Cerere:**
```json
{
  "event_id": "evt_123",
  "calendar_id": "calendar-echipa@group.calendar.google.com",
  "include_details": true,
  "sync_updates": true
}
```

### Setup Service Account

Pentru integrare server-to-server:

1. Creează service account în Google Cloud Console
2. Activează delegare la nivel de domeniu
3. Configurează scope-urile OAuth necesare în consola admin
4. Descarcă JSON-ul cu credențiale

```php
$client = new Google_Client();
$client->setAuthConfig('/path/to/service-account.json');
$client->setScopes([
    Google_Service_Drive::DRIVE_FILE,
    Google_Service_Calendar::CALENDAR,
    Google_Service_Gmail::GMAIL_SEND,
]);
$client->setSubject('utilizator@domeniu.com'); // Impersonare
```

### Notificări Webhook

Configurează notificări push pentru schimbări Drive:

```
POST /api/integrations/google-workspace/drive/watch
```

**Cerere:**
```json
{
  "file_id": "1ABC...",
  "webhook_url": "https://platforma-ta.com/webhooks/drive",
  "expiration": "2025-02-01T00:00:00Z"
}
```

### Operațiuni Shared Drive

```php
// Listare Shared Drives
GET /api/integrations/google-workspace/drive/shared-drives

// Creare folder în Shared Drive
POST /api/integrations/google-workspace/drive/folders
{
  "name": "Evenimente Q1 2025",
  "shared_drive_id": "0ACD..."
}

// Încărcare în Shared Drive
POST /api/integrations/google-workspace/drive/upload
{
  "shared_drive_id": "0ACD...",
  "supports_all_drives": true,
  ...
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `google_workspace_connections` | Token-uri OAuth |
| `google_workspace_files` | Referințe fișiere încărcate |
| `google_workspace_calendar_syncs` | Mapări sincronizare calendar |
| `google_workspace_email_logs` | Istoric email-uri trimise |
| `google_workspace_folders` | Cache structură foldere |


# Integrare Microsoft 365

## Prezentare Scurtă

Conectează platforma ta de evenimente la ecosistemul Microsoft. Integrarea Microsoft 365 sincronizează biletele, comenzile și datele clienților cu OneDrive, Outlook, Teams și Calendar. Lucrează acolo unde echipa ta deja lucrează.

Microsoft 365 alimentează comunicarea enterprise. Acum datele tale de evenimente curg în acest ecosistem automat. Fișiere în OneDrive, notificări în Teams, evenimente în Outlook Calendar.

OneDrive stochează fișierele tale de evenimente în siguranță. Exportă rapoarte, liste de participanți și facturi direct în foldere OneDrive. Organizează pe eveniment, pe dată sau pe tip. Stocare cloud cu securitate enterprise.

Notificările Teams țin pe toată lumea informată. Alerte vânzări în canale dedicate. Actualizări check-in pentru operațiuni. Sosiri VIP pentru customer success. Conștientizare în timp real fără a schimba instrumentele.

Integrarea Outlook Calendar sincronizează evenimentele tale. Creează intrări de calendar pentru evenimente programate. Trimite invitații cu detalii eveniment. Coordonează programele în organizație.

Email-ul prin Outlook livrează comunicări branded. Email-uri de confirmare, mesaje reminder, follow-up-uri post-eveniment. Livrare profesională prin tenant-ul tău Microsoft.

OAuth 2.0 cu Azure AD asigură acces securizat. Autentificare enterprise-grade folosind furnizorul de identitate existent. Comoditate single sign-on cu securitate completă.

Adu evenimentele tale în suita de productivitate Microsoft. Workflow-uri unificate, date unificate.

---

## Descriere Detaliată

Microserviciul de Integrare Microsoft 365 conectează platforma ta de ticketing pentru evenimente cu suita de productivitate Microsoft. Permite stocare fișiere, mesagerie de echipă, gestionare calendar și comunicare email printr-o integrare unificată.

### Componente Integrare

| Serviciu | Capabilități |
|----------|--------------|
| OneDrive | Încărcări fișiere, gestionare foldere, partajare |
| Teams | Mesaje în canale, notificări chat, carduri adaptive |
| Outlook Calendar | Creare evenimente, invitații participanți, remindere |
| Outlook Mail | Trimitere email, suport template-uri, urmărire |

### Autentificare

Azure AD OAuth 2.0 oferă autentificare enterprise:
- Single sign-on cu conturi organizaționale
- Scoping granular al permisiunilor
- Consimțământ admin pentru acces la nivel de organizație
- Gestionare reîmprospătare token-uri

### Fluxul Datelor

| Direcție | Descriere |
|----------|-----------|
| Platformă → OneDrive | Încarcă rapoarte, exporturi, documente |
| Platformă → Teams | Trimite notificări și actualizări |
| Platformă → Calendar | Creează și actualizează evenimente |
| Platformă → Outlook | Trimite comunicări email |
| Microsoft 365 → Platformă | Primește callback-uri webhook |

---

## Funcționalități

### Integrare OneDrive
- Încărcare fișiere pe drive-uri utilizator sau partajate
- Creare și organizare foldere
- Exporturi rapoarte (PDF, Excel, CSV)
- Încărcări liste participanți
- Stocare facturi și chitanțe
- Generare link-uri de partajare

### Notificări Teams
- Postare mesaje în canale
- Notificări chat
- Formatare Adaptive Card
- Butoane de acțiune în mesaje
- Suport @mention
- Atașamente media rich

### Integrare Calendar
- Creare evenimente Outlook calendar
- Trimitere invitații meeting
- Sincronizare programări evenimente
- Remindere automate
- Gestionare participanți
- Link-uri locație și meeting online

### Integrare Email
- Trimitere email-uri tranzacționale
- Mesaje bazate pe template-uri
- Comunicări branded
- Urmărire livrare
- HTML și text simplu
- Suport atașamente

### Autentificare
- Azure AD OAuth 2.0
- Single sign-on
- Suport multi-tenant
- Flux consimțământ admin
- Scoping permisiuni
- Gestionare token-uri

---

## Cazuri de Utilizare

### Management Evenimente Enterprise
Organizațiile mari gestionează evenimentele prin instrumente Microsoft familiare. Rapoartele ajung în SharePoint, notificările curg prin Teams, calendarele rămân sincronizate între departamente.

### Coordonare Echipă
Echipele de operațiuni evenimente primesc actualizări în timp real în canale Teams. Etape vânzări, avertismente capacitate, check-in-uri VIP - toate vizibile unde echipele deja comunică.

### Raportare Automatizată
Rapoartele zilnice de vânzări se încarcă în OneDrive automat. Sumarele săptămânale ajung în foldere partajate. Echipa de finance accesează datele prin interfețe familiare.

### Planificare Bazată pe Calendar
Programările evenimentelor se sincronizează în calendarele organizaționale. Membrii echipei văd evenimentele viitoare. Sălile de meeting pot fi rezervate. Conflictele devin vizibile din timp.

### Comunicări cu Clienții
Trimite email-uri profesionale prin tenant-ul tău Microsoft. Confirmări branded, follow-up-uri personalizate și remindere automate - toate din domeniul tău.

---

## Documentație Tehnică

### Configurare

```php
'microsoft_365' => [
    'client_id' => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'tenant_id' => env('AZURE_TENANT_ID'),
    'redirect_uri' => env('AZURE_REDIRECT_URI'),
    'scopes' => [
        'Files.ReadWrite.All',
        'Mail.Send',
        'Calendars.ReadWrite',
        'ChannelMessage.Send',
    ],
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/microsoft-365/auth
```

#### Încărcare în OneDrive

```
POST /api/integrations/microsoft-365/onedrive/upload
```

**Cerere:**
```json
{
  "file_type": "report",
  "format": "pdf",
  "event_id": "evt_123",
  "folder_path": "/Evenimente/2025/Rapoarte",
  "data_type": "attendees"
}
```

**Răspuns:**
```json
{
  "success": true,
  "file_id": "01ABC...",
  "web_url": "https://onedrive.live.com/...",
  "download_url": "https://..."
}
```

#### Trimitere Mesaj Teams

```
POST /api/integrations/microsoft-365/teams/message
```

**Cerere:**
```json
{
  "team_id": "team-uuid",
  "channel_id": "channel-uuid",
  "message": {
    "type": "adaptive_card",
    "content": {
      "type": "AdaptiveCard",
      "body": [
        {
          "type": "TextBlock",
          "text": "Vânzare Nouă de Bilete!",
          "weight": "bolder"
        },
        {
          "type": "FactSet",
          "facts": [
            {"title": "Eveniment", "value": "Festival de Vară 2025"},
            {"title": "Bilete", "value": "4"},
            {"title": "Total", "value": "200.00 EUR"}
          ]
        }
      ],
      "actions": [
        {
          "type": "Action.OpenUrl",
          "title": "Vezi Comanda",
          "url": "https://platform.com/orders/123"
        }
      ]
    }
  }
}
```

#### Creare Eveniment Calendar

```
POST /api/integrations/microsoft-365/calendar/events
```

**Cerere:**
```json
{
  "subject": "Festival de Vară 2025",
  "start": "2025-07-15T18:00:00",
  "end": "2025-07-15T23:00:00",
  "timezone": "Europe/Bucharest",
  "location": "Arena Parc Central",
  "body": "Festival anual de muzică de vară cu...",
  "attendees": ["echipa@companie.com"],
  "is_online_meeting": false,
  "reminder_minutes": 1440
}
```

#### Trimitere Email

```
POST /api/integrations/microsoft-365/mail/send
```

**Cerere:**
```json
{
  "to": ["client@exemplu.com"],
  "subject": "Biletele Tale pentru Festival de Vară",
  "body": "<html>...</html>",
  "body_type": "html",
  "attachments": [
    {
      "name": "bilete.pdf",
      "content_type": "application/pdf",
      "content_base64": "..."
    }
  ],
  "save_to_sent": true
}
```

### Carduri Adaptive Teams

Exemplu card de notificare:

```json
{
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "type": "AdaptiveCard",
  "version": "1.4",
  "body": [
    {
      "type": "Container",
      "items": [
        {
          "type": "TextBlock",
          "text": "Comandă Finalizată",
          "weight": "bolder",
          "size": "medium"
        },
        {
          "type": "ColumnSet",
          "columns": [
            {
              "type": "Column",
              "items": [
                {"type": "TextBlock", "text": "Client"},
                {"type": "TextBlock", "text": "Eveniment"},
                {"type": "TextBlock", "text": "Total"}
              ]
            },
            {
              "type": "Column",
              "items": [
                {"type": "TextBlock", "text": "Ion Popescu"},
                {"type": "TextBlock", "text": "Festival de Vară"},
                {"type": "TextBlock", "text": "150.00 EUR", "color": "good"}
              ]
            }
          ]
        }
      ]
    }
  ],
  "actions": [
    {
      "type": "Action.OpenUrl",
      "title": "Vezi Comanda",
      "url": "https://platform.com/orders/123"
    }
  ]
}
```

### Evenimente Webhook

Configurează webhook-uri pentru a primi evenimente Microsoft 365:

```
POST /api/integrations/microsoft-365/webhooks
```

**Cerere:**
```json
{
  "resource": "/me/mailFolders/inbox/messages",
  "change_type": "created",
  "notification_url": "https://platforma-ta.com/webhooks/m365",
  "expiration_datetime": "2025-02-01T00:00:00Z"
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `microsoft_365_connections` | Token-uri OAuth și info tenant |
| `microsoft_365_files` | Referințe fișiere încărcate |
| `microsoft_365_teams_configs` | Configurări canale Teams |
| `microsoft_365_calendar_syncs` | Mapări sincronizare calendar |
| `microsoft_365_email_logs` | Istoric email-uri trimise |


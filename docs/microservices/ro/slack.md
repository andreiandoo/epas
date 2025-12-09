# Integrare Slack

## Prezentare Scurtă

Ține-ți echipa la curent fără a părăsi Slack. Integrarea Slack trimite notificări în timp real despre comenzi, clienți și evenimente direct în canalele workspace-ului tău. Echipa ta rămâne informată, răspunde mai rapid și nu ratează niciodată actualizări importante.

Comandă nouă intră? Canalul de vânzări știe instantaneu. Client VIP face o achiziție? Alertează oamenii potriviți. Evenimentul se vinde complet? Sărbătoriți împreună. Integrarea Slack transformă platforma ta de ticketing într-un hub de comunicare pentru echipă.

Formatarea avansată a mesajelor face notificările acționabile. Vezi detaliile comenzii, informațiile clientului și linkuri rapide fără a da click în altă parte. Formatarea Block Kit prezintă datele frumos în stilul nativ Slack.

Trimite în orice canal. Rutează notificări diferite în canale diferite - alerte de vânzări în #sales, probleme suport în #support, sărbătoriri milestone în #general. Tu controlezi unde merge fiecare mesaj.

Fișierele și atașamentele țin pe toată lumea informată. Partajează rapoarte, exportă date și trimite documente direct prin Slack. Nu mai căuta prin emailuri pentru raportul de vânzări.

Suportul pentru workspace-uri multiple se scalează cu organizația ta. Conectează echipe, departamente sau branduri diferite la propriile lor workspace-uri Slack. Fiecare conexiune e independentă și securizată.

OAuth 2.0 face conexiunea simplă și securizată. Click pentru autorizare, selectează workspace-ul și începe să primești notificări. Nu e necesară gestionarea manuală a token-urilor.

Transformă Slack în dashboard-ul operațiunilor tale de evenimente. Awareness în timp real pentru întreaga echipă.

---

## Descriere Detaliată

Microserviciul de Integrare Slack conectează platforma ta de ticketing pentru evenimente cu workspace-urile Slack, permițând notificări automatizate, partajare de fișiere și comunicare în echipă prin API-ul Slack.

### Tipuri de Notificări

Integrarea trimite diverse notificări:

| Eveniment | Conținut Mesaj |
|-----------|----------------|
| Comandă Nouă | Detalii comandă, client, articole, total |
| Achiziție VIP | Alertă comandă de mare valoare cu detalii |
| Rambursare Emisă | Sumă rambursare, motiv, client |
| Eveniment Publicat | Detalii eveniment, link bilete |
| Inventar Scăzut | Avertisment când biletele se termină |
| Sumar Zilnic | Recapitulare vânzări, numere participare |

### Formatarea Mesajelor

Mesajele folosesc Block Kit Slack pentru formatare avansată:

- **Headers**: Titluri clare de notificare
- **Sections**: Blocuri de conținut organizate
- **Fields**: Perechi cheie-valoare de date
- **Buttons**: Linkuri de acțiune rapidă
- **Dividers**: Separare vizuală

Exemplu structură mesaj:
```
🎫 Comandă Nouă #1234
─────────────────
Client: Ion Popescu
Eveniment: Summer Festival 2025
Bilete: 2x VIP Pass
Total: €150.00

[Vezi Comanda] [Contactează Clientul]
```

### Gestionarea Canalelor

Configurează ce notificări merg unde:

- Creează mapări de canale în dashboard
- Rutează după tipul notificării
- Rutează după eveniment sau organizator
- Suport canale private cu membership bot

### Încărcări Fișiere

Partajează fișiere direct în Slack:

- Rapoarte de vânzări zilnice/săptămânale
- Liste export clienți
- Date participare evenimente
- Documente generate personalizat

Fișierele se încarcă asincron și apar în canalul desemnat.

### Suport Webhook

Primește evenimente Slack în platformă:

- Reacții la mesaje pentru feedback rapid
- Comenzi slash pentru interogări
- Răspunsuri butoane interactive
- Trimiteri modale

---

## Funcționalități

### Mesagerie
- Trimite mesaje în orice canal
- Formatare mesaje avansată cu blocuri
- Suport răspunsuri în thread
- Reacții emoji
- Editare și ștergere mesaje

### Notificări
- Notificări comenzi
- Alerte clienți
- Actualizări evenimente
- Avertismente inventar
- Notificări personalizate

### Partajare Fișiere
- Încărcări fișiere în canale
- Partajare documente
- Distribuție rapoarte
- Atașamente imagini

### Gestionare Canale
- Listare canale disponibile
- Creare canale noi
- Reguli rutare canale
- Suport canale private

### Autentificare
- Conexiune securizată OAuth 2.0
- Suport workspace-uri multiple
- Reîmprospătare automată token
- Scoping permisiuni

### Monitorizare
- Logare livrare mesaje
- Urmărire evenimente webhook
- Notificări erori
- Istoric activitate

---

## Cazuri de Utilizare

### Alerte Vânzări
Notificări instantanee când intră comenzi. Achizițiile de mare valoare alertează echipa de vânzări. Sumarele zilnice țin pe toată lumea aliniată la performanță.

### Coordonare Operațiuni
Actualizări în timp real în ziua evenimentului. Contoare scanări bilete, alerte participare și avertismente capacitate ajută echipele de operațiuni să răspundă rapid.

### Serviciu Clienți
Notificările de rambursare alertează echipele de suport. Problemele clienților marcate în canale dedicate. Coordonarea răspunsurilor se întâmplă natural în Slack.

### Vizibilitate Executivi
Rapoarte sumare în canalele leadership-ului. Sărbătoriri milestone partajate la nivel de companie. Actualizări venituri fără verificarea dashboard-ului.

### Coordonare Multi-Echipă
Marketing-ul primește notificări de publicare evenimente. Finance vede sumarele zilnice de venituri. Fiecare echipă primește informații relevante în canalele lor.

### Management Echipă Remotă
Echipele distribuite rămân conectate. Actualizări în timp real indiferent de locație. Awareness asincron prin mesaje persistente.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Slack folosește Web API și Events API Slack pentru a trimite mesaje, încărca fișiere și primi evenimente webhook. OAuth 2.0 gestionează autorizarea workspace-ului.

### Cerințe Preliminare

- Workspace Slack
- Aplicație Slack creată în api.slack.com
- Bot Token Scopes configurate
- URL Redirect pentru OAuth

### Configurare

```php
'slack' => [
    'client_id' => env('SLACK_CLIENT_ID'),
    'client_secret' => env('SLACK_CLIENT_SECRET'),
    'redirect_uri' => env('SLACK_REDIRECT_URI'),
    'signing_secret' => env('SLACK_SIGNING_SECRET'),
    'scopes' => [
        'chat:write',
        'channels:read',
        'files:write',
        'reactions:write',
    ],
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/slack/auth
```

Returnează URL-ul de autorizare OAuth Slack.

#### Callback OAuth

```
POST /api/integrations/slack/callback
```

Gestionează callback-ul OAuth și stochează token-urile.

#### Status Conexiune

```
GET /api/integrations/slack/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "workspace": "Compania Ta",
  "team_id": "T1234567",
  "bot_user_id": "U7654321",
  "channels_count": 15
}
```

#### Trimitere Mesaj

```
POST /api/integrations/slack/messages
```

**Cerere:**
```json
{
  "channel": "C1234567890",
  "text": "Comandă nouă primită!",
  "blocks": [
    {
      "type": "header",
      "text": {
        "type": "plain_text",
        "text": "🎫 Comandă Nouă #1234"
      }
    },
    {
      "type": "section",
      "fields": [
        {"type": "mrkdwn", "text": "*Client:*\nIon Popescu"},
        {"type": "mrkdwn", "text": "*Total:*\n€150.00"}
      ]
    }
  ]
}
```

#### Listare Canale

```
GET /api/integrations/slack/channels
```

#### Încărcare Fișier

```
POST /api/integrations/slack/files
```

**Cerere (multipart/form-data):**
```
file: [binary]
channels: C1234567890
filename: raport-zilnic.pdf
title: Raport Vânzări Zilnic
```

#### Adăugare Reacție

```
POST /api/integrations/slack/reactions
```

**Cerere:**
```json
{
  "channel": "C1234567890",
  "timestamp": "1234567890.123456",
  "name": "white_check_mark"
}
```

### Construire Mesaje

```php
class SlackMessageBuilder
{
    public function orderNotification(Order $order): array
    {
        return [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => "🎫 Comandă Nouă #{$order->number}",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Client:*\n{$order->customer->name}"],
                        ['type' => 'mrkdwn', 'text' => "*Total:*\n€{$order->total}"],
                        ['type' => 'mrkdwn', 'text' => "*Eveniment:*\n{$order->event->name}"],
                        ['type' => 'mrkdwn', 'text' => "*Bilete:*\n{$order->items->count()}"],
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'Vezi Comanda'],
                            'url' => route('orders.show', $order),
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

### Rutare Canale

```php
// Configurare
'slack_routing' => [
    'order_created' => ['#sales', '#orders'],
    'refund_issued' => ['#support', '#finance'],
    'event_published' => ['#marketing'],
    'vip_purchase' => ['#vip-alerts'],
]
```

### Handler Webhook

```php
// Primește evenimente Slack
POST /api/webhooks/slack

public function handleWebhook(Request $request): Response
{
    // Verifică semnătura
    $this->verifySlackSignature($request);

    $payload = $request->input();

    // Gestionează challenge-ul de verificare URL
    if ($payload['type'] === 'url_verification') {
        return response($payload['challenge']);
    }

    // Gestionează evenimente
    if ($payload['type'] === 'event_callback') {
        $this->processEvent($payload['event']);
    }

    return response('OK');
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `slack_connections` | Token-uri OAuth și info workspace |
| `slack_channels` | Listă canale în cache |
| `slack_messages` | Log mesaje trimise |
| `slack_webhooks` | Evenimente webhook primite |

### Gestionarea Erorilor

| Eroare | Descriere | Rezolvare |
|--------|-----------|-----------|
| channel_not_found | ID canal invalid | Verifică că canalul există |
| not_in_channel | Bot-ul nu e în canal | Invită bot-ul în canal |
| token_revoked | Token OAuth invalid | Re-autorizează conexiunea |
| rate_limited | Prea multe cereri | Implementează backoff |

### Limite Rate

Limite API Slack:
- Tier 1: 1 cerere pe secundă
- Tier 2: 20 cereri pe minut
- Tier 3: 50 cereri pe minut

Majoritatea endpoint-urilor de mesagerie sunt Tier 3.

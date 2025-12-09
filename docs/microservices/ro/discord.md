# Integrare Discord

## Prezentare Scurtă

Implică-ți comunitatea acolo unde deja se întâlnesc. Integrarea Discord trimite anunțuri de evenimente, notificări de comenzi și actualizări direct în serverele tale Discord. Construiește entuziasmul, partajează știri și ține fanii informați în canalele pe care le iubesc.

Evenimente gaming, festivaluri de muzică, turnee esports - Discord e locul unde publicul tău trăiește. Acum platforma ta de ticketing vorbește limba lor. Anunță evenimente noi, celebrează sold out-urile și construiește hype automat.

Mesageria webhook livrează actualizări instantanee. Configurează un URL webhook și începe să trimiți. Nu e necesară configurare complexă de bot pentru notificări de bază. Mesajele apar ca și cum ar fi de la brandul tău.

Mesajele rich embed arată profesional. Culori personalizate, imagini, thumbnails și câmpuri formatate creează anunțuri care atrag atenția. Mesajele tale ies în evidență în canale aglomerate.

Integrarea bot deblochează funcții avansate. Accesul complet la bot Discord permite gestionarea canalelor, interacțiuni cu utilizatorii și răspunsuri dinamice. Construiește engagement mai profund cu comunitatea.

Suportul pentru servere multiple ajunge la toate comunitățile tale. Evenimente diferite pot notifica servere diferite. Comunitățile regionale rămân informate despre evenimentele locale.

Brandingul personalizat face mesajele ale tale. Setează username-ul și avatarul botului să se potrivească brandului tău. Fiecare mesaj întărește identitatea ta.

Logarea mesajelor urmărește tot ce s-a trimis. Știi ce a plecat, când și către ce servere. Depanează problemele de livrare cu istoric complet.

Transformă-ți comunitatea Discord în fani care cumpără bilete. Anunță, implică, vinde.

---

## Descriere Detaliată

Microserviciul de Integrare Discord conectează platforma ta de ticketing pentru evenimente cu serverele Discord, permițând notificări automatizate prin webhookuri și funcționalitate opțională de bot pentru funcții avansate.

### Metode de Integrare

Integrarea suportă două abordări:

#### Webhookuri (Simplu)
- Nu necesită bot
- Configurează URL-ul webhook în setările canalului Discord
- Trimite mesaje direct la webhookuri
- Limitat la trimiterea de mesaje

#### Integrare Bot (Avansat)
- Acces complet la API Discord
- Gestionare canale și servere
- Interacțiuni cu utilizatori
- Monitorizare reacții
- Gestionare roluri

### Tipuri de Mesaje

| Notificare | Conținut |
|------------|----------|
| Anunț Eveniment | Detalii eveniment, date, link bilete |
| Bilete în Vânzare | Notificare început vânzare cu link |
| Inventar Scăzut | Mesaj de urgență când biletele se termină |
| Sold Out | Anunț celebratoriu |
| Reminder Eveniment | Notificare eveniment apropiat |
| Confirmare Comandă | Detalii achiziție (DM privat opțional) |

### Formatare Embed

Embed-urile Discord oferă formatare avansată a mesajelor:

```json
{
  "title": "🎫 Summer Festival 2025",
  "description": "Biletele sunt acum în vânzare!",
  "color": 5814783,
  "fields": [
    {"name": "Data", "value": "15 Iulie 2025", "inline": true},
    {"name": "Locație", "value": "Central Park", "inline": true},
    {"name": "Bilete de la", "value": "€50", "inline": true}
  ],
  "thumbnail": {"url": "https://..."},
  "image": {"url": "https://..."},
  "footer": {"text": "Ia-ți biletele acum!"}
}
```

### Gestionarea Serverelor

Cu integrarea bot:
- Listează serverele la care s-a alăturat bot-ul
- Listează canalele din servere
- Creează canale de anunțuri
- Gestionează permisiunile canalelor
- Postează în canale specifice

### Urmărirea Livrării

Toate mesajele sunt logate cu:
- Timestamp
- Server/canal țintă
- Conținut mesaj
- Status livrare
- Detalii eroare (dacă există)

---

## Funcționalități

### Mesagerie
- Livrare mesaje webhook
- Mesaje rich embed
- Culori embed personalizate
- Suport imagini și thumbnails
- Embed-uri multiple per mesaj

### Funcții Bot
- Autorizare bot OAuth 2.0
- Listare servere (guild-uri)
- Listare canale
- Creare canale
- Gestionare permisiuni

### Branding
- Username bot personalizat
- Avatar bot personalizat
- Culori embed de brand
- Personalizare footer

### Notificări
- Anunțuri evenimente
- Notificări vânzări
- Alerte inventar
- Mesaje reminder
- Notificări personalizate

### Management
- Suport servere multiple
- Rutare canale
- Gestionare webhookuri
- Istoric mesaje

### Monitorizare
- Logare livrări
- Urmărire erori
- Istoric mesaje
- Mod debug

---

## Cazuri de Utilizare

### Evenimente Gaming
Turnee esports, convenții gaming și LAN party-uri prosperă pe Discord. Anunță vânzările de bilete acolo unde gamerii deja se adună. Construiește hype pre-eveniment în canalele comunității.

### Comunități Muzicale
Serverele de fani ale artiștilor primesc anunțuri exclusive. Notificările pre-vânzare recompensează fanii fideli. Construiește relații directe cu audiența ta.

### Promovare Festivaluri
Comunitățile festivalurilor de muzică răspândesc vestea organic. Anunțurile de lineup creează momente partajabile. Comunitățile de fani îți amplifică reach-ul.

### Grupuri Evenimente Locale
Servere Discord regionale pentru evenimente locale. Promovare condusă de comunitate. Descoperire evenimente din cartier.

### Cluburi VIP de Fani
Servere Discord exclusive pentru superfani. Anunțuri acces timpuriu. Oferte speciale pentru membrii comunității.

### Actualizări în Ziua Evenimentului
Actualizări în timp real în timpul evenimentelor. Schimbări de orar, alerte meteo, anunțuri speciale. Ține participanții informați.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Discord se conectează la Discord prin webhookuri pentru mesagerie simplă sau API-ul Discord pentru funcționalitate completă de bot. OAuth 2.0 gestionează autorizarea botului.

### Cerințe Preliminare

Pentru webhookuri:
- Server Discord cu permisiune de gestionare webhookuri
- URL webhook din setările canalului

Pentru bot:
- Aplicație Discord Developer Portal
- Token bot
- OAuth2 configurat cu scope-urile necesare

### Configurare

```php
'discord' => [
    'client_id' => env('DISCORD_CLIENT_ID'),
    'client_secret' => env('DISCORD_CLIENT_SECRET'),
    'bot_token' => env('DISCORD_BOT_TOKEN'),
    'redirect_uri' => env('DISCORD_REDIRECT_URI'),
    'default_color' => 5814783, // Culoare embed
]
```

### Endpoint-uri API

#### Autorizare OAuth (Bot)

```
GET /api/integrations/discord/auth
```

Returnează URL-ul OAuth Discord pentru autorizarea botului.

#### Callback OAuth

```
POST /api/integrations/discord/callback
```

Gestionează callback-ul OAuth pentru conexiunile bot.

#### Status Conexiune

```
GET /api/integrations/discord/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "bot_name": "Event Bot",
  "guilds_count": 5,
  "webhooks_count": 3
}
```

#### Listare Guild-uri (Servere)

```
GET /api/integrations/discord/guilds
```

#### Listare Canale

```
GET /api/integrations/discord/guilds/{guildId}/channels
```

#### Trimitere Mesaj Webhook

```
POST /api/integrations/discord/webhooks/{webhookId}/messages
```

**Cerere:**
```json
{
  "content": "Vezi noul nostru eveniment!",
  "embeds": [{
    "title": "🎫 Summer Festival 2025",
    "description": "Cel mai mare eveniment al anului!",
    "color": 5814783,
    "fields": [
      {"name": "Data", "value": "15 Iulie 2025", "inline": true},
      {"name": "Preț", "value": "De la €50", "inline": true}
    ],
    "image": {"url": "https://exemplu.com/banner.jpg"},
    "url": "https://bilete.exemplu.com/summer-festival"
  }],
  "username": "Anunțuri Evenimente",
  "avatar_url": "https://exemplu.com/logo.png"
}
```

#### Trimitere Mesaj Bot

```
POST /api/integrations/discord/channels/{channelId}/messages
```

**Cerere:**
```json
{
  "content": "🎉 **SOLD OUT** - Summer Festival 2025",
  "embeds": [{
    "title": "Mulțumim!",
    "description": "Toate biletele s-au vândut.",
    "color": 15844367
  }]
}
```

#### Creare Webhook

```
POST /api/integrations/discord/channels/{channelId}/webhooks
```

**Cerere:**
```json
{
  "name": "Notificări Evenimente",
  "avatar": "imagine_codată_base64"
}
```

### Serviciu Webhook

```php
class DiscordWebhookService
{
    public function sendAnnouncement(string $webhookUrl, Event $event): void
    {
        Http::post($webhookUrl, [
            'embeds' => [[
                'title' => "🎫 {$event->name}",
                'description' => $event->description,
                'color' => 5814783,
                'fields' => [
                    ['name' => 'Data', 'value' => $event->date->format('j F Y'), 'inline' => true],
                    ['name' => 'Locație', 'value' => $event->venue->name, 'inline' => true],
                    ['name' => 'Bilete de la', 'value' => "€{$event->min_price}", 'inline' => true],
                ],
                'thumbnail' => ['url' => $event->thumbnail_url],
                'image' => ['url' => $event->banner_url],
                'url' => $event->ticket_url,
                'footer' => ['text' => 'Ia-ți biletele acum!'],
            ]],
            'username' => config('discord.bot_name'),
            'avatar_url' => config('discord.bot_avatar'),
        ]);
    }
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `discord_connections` | Token-uri OAuth bot |
| `discord_webhooks` | URL-uri webhook stocate |
| `discord_messages` | Log mesaje trimise |

### Gestionarea Erorilor

| Eroare | Descriere | Rezolvare |
|--------|-----------|-----------|
| 10003 | Canal necunoscut | Canalul șters sau bot-ul eliminat |
| 10015 | Webhook necunoscut | Webhook șters |
| 50001 | Acces lipsă | Bot-ul nu are permisiuni |
| 50013 | Permisiuni lipsă | Permisiune specifică necesară |

### Limite Rate

Limite rate Discord:
- Webhookuri: 30 cereri pe minut per webhook
- API Bot: 50 cereri pe secundă global
- Creare mesaje: 5 per 5 secunde per canal

### Limite Embed

- Titlu: 256 caractere
- Descriere: 4096 caractere
- Câmpuri: maxim 25
- Nume câmp: 256 caractere
- Valoare câmp: 1024 caractere
- Dimensiune totală embed: 6000 caractere

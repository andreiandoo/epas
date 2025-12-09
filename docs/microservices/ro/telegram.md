# Integrare Bot Telegram

## Prezentare Scurtă

Ajunge la milioane de utilizatori Telegram cu propriul bot de evenimente. Integrarea Bot Telegram trimite notificări, actualizări și reminder-uri direct pe telefoanele abonaților. Construiește un canal de comunicare directă cu audiența ta care ocolește inbox-urile aglomerate.

Creează-ți bot-ul prin @BotFather și conectează-l la platformă. Abonații optează să primească mesaje pornind bot-ul tău, construind o audiență angajată care vrea să audă de la tine.

Confirmările de comandă ajung instantaneu. Cumpărătorii de bilete primesc confirmarea, codurile QR și detaliile evenimentului direct în Telegram. Fără întârzieri email, fără griji cu folderul spam.

Reminder-urile de eveniment cresc participarea. Mesajele automatizate înainte de evenimente reamintesc deținătorilor de bilete datele, orele și informațiile importante. Reduce neprezentările cu notificări la timp.

Anunțurile broadcast ajung la toți simultan. Lansări de evenimente noi, oferte speciale și actualizări importante ajung la toți abonații în același timp. Construiește anticipație și generează vânzări.

Tastaturile inline fac mesajele interactive. Adaugă butoane pentru acțiuni rapide - vezi bilete, obține direcții, contactează suportul. Utilizatorii interacționează fără a părăsi conversația.

Integrarea cu canale îți amplifică reach-ul. Postează în canalele Telegram pentru anunțuri publice. Construiește comunități în jurul evenimentelor și brandurilor tale.

Gestionarea abonaților îți urmărește audiența. Vezi cine e abonat, segmentează după preferințe și măsoară engagement-ul. Înțelege-ți comunitatea Telegram.

Conectează-te direct cu audiența. Fără algoritmi, fără reclame, doar comunicare directă.

---

## Descriere Detaliată

Microserviciul de Integrare Bot Telegram conectează platforma ta de ticketing pentru evenimente cu Telegram prin Bot API. Permite mesagerie automatizată, gestionarea abonaților și notificări interactive.

### Configurare Bot

1. Creează bot prin @BotFather pe Telegram
2. Primește token-ul botului
3. Configurează token-ul în setările platformei
4. Setează URL-ul webhook pentru mesaje primite
5. Începe să implici abonații

### Tipuri de Mesaje

- **Mesaje Text**: Text simplu cu formatare markdown
- **Fotografii**: Imagini evenimente cu descrieri
- **Documente**: Bilete PDF, facturi
- **Tastaturi Inline**: Meniuri interactive cu butoane
- **Locație**: Hărți locație și direcții

### Gestionarea Abonaților

Când utilizatorii dau `/start` bot-ului tău:
- ID-ul utilizatorului capturat și stocat
- Mesaj de bun venit trimis
- Preferințe opțional colectate
- Utilizator adăugat la lista de broadcast

### Actualizări Webhook

Primește notificări în timp real când utilizatorii:
- Pornesc bot-ul
- Trimit mesaje
- Dau click pe butoane inline
- Partajează informații de contact

---

## Funcționalități

### Mesagerie
- Trimitere mesaje text
- Partajare fotografii și media
- Atașamente documente
- Formatare Markdown
- Tastaturi inline

### Notificări
- Confirmări comenzi
- Reminder-uri evenimente
- Livrare bilete
- Anunțuri broadcast
- Notificări personalizate

### Gestionarea Abonaților
- Abonare automată la /start
- Gestionare listă abonați
- Urmărire preferințe
- Gestionare dezabonare

### Integrare Canale
- Postare în canale
- Gestionare canale
- Anunțuri publice
- Construire comunitate

### Interactivitate
- Butoane tastatură inline
- Gestionare callback queries
- Opțiuni răspuns rapid
- Deep linking

---

## Cazuri de Utilizare

### Livrare Bilete
Trimite biletele direct în Telegram. Codurile QR se afișează perfect pe mobil. Nu e necesară printarea, mereu accesibile.

### Reminder-uri Evenimente
Reminder-uri automatizate înainte de evenimente. Oră, locație și ce să aduci. Reduce neprezentările și îmbunătățește experiența.

### Vânzări Flash
Notificări instantanee pentru oferte limitate. Abonații acționează rapid pe deal-uri exclusive. Generează urgență și conversii.

### Construire Comunitate
Construiește comunități angajate în jurul evenimentelor. Actualizările regulate mențin interesul. Transformă cumpărătorii ocazionali în fani fideli.

---

## Documentație Tehnică

### Configurare

```php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_url' => env('APP_URL') . '/api/webhooks/telegram',
]
```

### Endpoint-uri API

#### Trimitere Mesaj

```
POST /api/integrations/telegram/messages
```

**Cerere:**
```json
{
  "chat_id": 123456789,
  "text": "Biletele tale sunt gata! 🎫",
  "parse_mode": "Markdown",
  "reply_markup": {
    "inline_keyboard": [[
      {"text": "Vezi Biletele", "url": "https://..."}
    ]]
  }
}
```

#### Trimitere Fotografie

```
POST /api/integrations/telegram/photos
```

#### Mesaj Broadcast

```
POST /api/integrations/telegram/broadcast
```

#### Obținere Abonați

```
GET /api/integrations/telegram/subscribers
```

### Handler Webhook

```php
POST /api/webhooks/telegram

public function handleWebhook(Request $request): void
{
    $update = $request->all();

    if (isset($update['message']['text'])) {
        if ($update['message']['text'] === '/start') {
            $this->handleStart($update['message']['from']);
        }
    }

    if (isset($update['callback_query'])) {
        $this->handleCallback($update['callback_query']);
    }
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `telegram_subscribers` | Abonați bot |
| `telegram_messages` | Log mesaje trimise |
| `telegram_callbacks` | Log callback queries |

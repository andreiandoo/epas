# Integrare Zapier

## Prezentare Scurtă

Conectează platforma ta de evenimente la peste 5.000 de aplicații fără a scrie o singură linie de cod. Integrarea Zapier transformă vânzările de bilete, înregistrările și evenimentele în triggere care automatizează întregul flux de lucru al afacerii tale.

Când cineva cumpără un bilet, lucrurile se întâmplă automat. Adaugă-l în lista ta de email. Creează un contact CRM. Trimite o notificare Slack. Actualizează spreadsheet-ul. Zapier face posibil - tu doar configurezi fluxul o dată.

Triggerele în timp real se activează în momentul în care apar evenimentele. Comandă plasată? Trigger trimis. Client înregistrat? Trigger trimis. Eveniment publicat? Aplicațiile conectate știu imediat. Fără întârzieri, fără exporturi manuale.

Șase triggere puternice acoperă evenimentele cheie ale afacerii: comenzi create, bilete vândute, clienți înregistrați, evenimente publicate, înregistrări completate și rambursări emise. Fiecare trigger trimite date complete pentru a alimenta automatizările.

Tehnologia REST Hook înseamnă livrare eficientă și instantanee. Spre deosebire de integrările bazate pe polling, webhook-urile împing datele către Zapier în momentul în care se întâmplă ceva. Fluxurile tale rulează în timp real.

Nu e nevoie de programare. Builder-ul vizual de workflow Zapier permite oricui să creeze automatizări. Conectează-te la Mailchimp, Google Sheets, Salesforce, Slack și mii de altele. Dacă o aplicație e pe Zapier, o poți conecta.

Urmărește totul cu logging-ul integrat. Vezi care triggere s-au activat, când s-au activat și ce date au fost trimise. Rezolvă problemele rapid cu înregistrări detaliate de livrare.

Automatizează munca repetitivă. Concentrează-te pe evenimentele tale.

---

## Descriere Detaliată

Microserviciul de Integrare Zapier permite platformei tale de evenimente să comunice cu mii de aplicații terțe prin platforma de automatizare Zapier. Oferă triggere bazate pe webhook care se activează când apar evenimente cheie în sistemul tău.

### Cum Funcționează Zapier

Zapier conectează aplicații prin "Zap-uri" - fluxuri automatizate cu triggere și acțiuni:

1. **Trigger**: Se întâmplă ceva în platforma ta (ex: bilet vândut)
2. **Acțiune**: Zapier face ceva într-o altă aplicație (ex: adaugă în Mailchimp)

Platforma ta oferă triggerele. Utilizatorii Zapier configurează ce acțiuni se întâmplă ca răspuns.

### Arhitectura REST Hook

Integrarea folosește REST Hooks (subscripții webhook) în loc de polling:

- **Subscribe**: Când un utilizator creează un Zap, Zapier se abonează la trigger-ul tău
- **Fire**: Când apare evenimentul, platforma ta trimite date către Zapier
- **Unsubscribe**: Când Zap-ul e dezactivat, subscripția e eliminată

Această arhitectură e mai eficientă decât polling-ul și livrează date instantaneu.

### Triggere Disponibile

| Trigger | Se Activează Când | Date Incluse |
|---------|-------------------|--------------|
| Order Created | Comandă nouă plasată | Detalii comandă, articole, client, totaluri |
| Ticket Sold | Bilet individual cumpărat | Detalii bilet, participant, info eveniment |
| Customer Created | Client nou se înregistrează | Profil client, info contact |
| Event Published | Eveniment devine live | Detalii eveniment, date, locație, bilete |
| Registration Completed | Înregistrare completă finalizată | Date înregistrare, câmpuri custom |
| Refund Issued | Rambursare procesată | Sumă rambursare, motiv, referință comandă |

### Autentificare

Integrarea folosește autentificare cu cheie API:
- Utilizatorii generează o cheie API în platforma ta
- O introduc când se conectează în Zapier
- Toate apelurile webhook includ cheia pentru verificare

### Livrarea Webhook

Când un trigger se activează:

1. Evenimentul apare în platformă
2. Sistemul identifică subscripțiile active pentru acel trigger
3. Payload-ul e construit cu datele relevante
4. Webhook-ul e trimis la fiecare endpoint abonat
5. Livrarea e logată pentru urmărire

Livrările eșuate sunt reîncercate cu backoff exponențial.

---

## Funcționalități

### Triggere
- Trigger comandă creată
- Trigger bilet vândut
- Trigger client creat
- Trigger eveniment publicat
- Trigger înregistrare completată
- Trigger rambursare emisă

### Integrare
- Subscripții REST Hook
- Livrare webhook în timp real
- Reîncercare automată la eșec
- Gestionare subscripții
- Suport multi-Zap

### Autentificare și Securitate
- Autentificare cheie API
- Endpoint-uri webhook securizate
- Suport rotație chei
- Chei API per utilizator

### Monitorizare
- Logare triggere
- Urmărire livrare
- Raportare erori
- Istoric webhook
- Informații debug

### Date
- Payload-uri complete evenimente
- Includere câmpuri custom
- Timestamp-uri formatate
- Date relații incluse
- Structură date consistentă

---

## Cazuri de Utilizare

### Automatizare Email Marketing
Când cineva cumpără un bilet, adaugă-l automat în lista Mailchimp, ConvertKit sau ActiveCampaign. Etichetează-l în funcție de tipul evenimentului. Începe secvențe de email automatizate.

### Actualizări CRM
Creează sau actualizează contacte în Salesforce, HubSpot sau Pipedrive când clienții fac achiziții. Urmărește automat istoricul achizițiilor și participarea la evenimente în CRM.

### Notificări Echipă
Trimite mesaje Slack sau Microsoft Teams când intră comenzi. Alertează echipa pentru achiziții VIP, comenzi mari sau evenimente specifice. Ține pe toată lumea informată fără verificare manuală.

### Urmărire Spreadsheet
Adaugă comenzi noi în Google Sheets sau Excel. Construiește dashboard-uri de urmărire vânzări în timp real. Creează automat înregistrări backup pentru toate tranzacțiile.

### Suport Clienți
Creează tichete help desk în Zendesk sau Freshdesk când sunt emise rambursări. Atribuie automat sarcini de follow-up când se activează anumite triggere.

### Social Media
Postează pe canalele sociale când evenimentele sunt publicate. Partajează milestone-uri când vânzările de bilete ating anumite numere. Automatizează promovarea evenimentelor.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Zapier oferă endpoint-uri REST Hook pentru ca Zapier să se aboneze la evenimentele platformei. Gestionează subscripțiile, activează webhook-uri când apar evenimente și logază toată activitatea.

### Configurare

```php
'zapier' => [
    'enabled' => env('ZAPIER_ENABLED', true),
    'api_key_header' => 'X-API-Key',
    'retry_attempts' => 3,
    'retry_delay' => [1, 5, 30], // secunde
    'timeout' => 30,
    'logging' => true,
]
```

### Endpoint-uri API

#### Abonare la Trigger

```
POST /api/zapier/hooks/subscribe
```

Apelat de Zapier când un Zap e activat.

**Headere:**
```
X-API-Key: cheia_ta_api
```

**Cerere:**
```json
{
  "hookUrl": "https://hooks.zapier.com/hooks/catch/123/abc",
  "trigger": "order_created"
}
```

**Răspuns:**
```json
{
  "id": "hook_abc123",
  "trigger": "order_created",
  "hookUrl": "https://hooks.zapier.com/hooks/catch/123/abc",
  "active": true,
  "created_at": "2025-01-15T10:30:00Z"
}
```

#### Dezabonare de la Trigger

```
DELETE /api/zapier/hooks/{hookId}
```

Apelat când un Zap e dezactivat.

#### Listare Subscripții

```
GET /api/zapier/hooks
```

Returnează subscripțiile webhook active.

#### Test Trigger

```
POST /api/zapier/hooks/test/{trigger}
```

Trimite date exemplu pentru configurarea Zap-ului.

### Payload-uri Triggere

#### Order Created

```json
{
  "id": "order_123",
  "order_number": "ORD-2025-0001",
  "status": "completed",
  "total": 150.00,
  "currency": "EUR",
  "created_at": "2025-01-15T14:30:00Z",
  "customer": {
    "id": "cust_456",
    "email": "client@exemplu.com",
    "first_name": "Ion",
    "last_name": "Popescu"
  },
  "items": [
    {
      "ticket_type": "VIP Pass",
      "event_name": "Summer Festival 2025",
      "quantity": 2,
      "unit_price": 75.00
    }
  ],
  "event": {
    "id": "evt_789",
    "name": "Summer Festival 2025",
    "date": "2025-07-15T18:00:00Z"
  }
}
```

#### Ticket Sold

```json
{
  "id": "ticket_abc",
  "ticket_number": "TKT-2025-12345",
  "ticket_type": "Acces General",
  "status": "valid",
  "price": 50.00,
  "attendee": {
    "first_name": "Maria",
    "last_name": "Ionescu",
    "email": "maria@exemplu.com"
  },
  "event": {
    "id": "evt_789",
    "name": "Summer Festival 2025",
    "date": "2025-07-15T18:00:00Z",
    "venue": "Arena Central Park"
  },
  "order_id": "order_123"
}
```

#### Customer Created

```json
{
  "id": "cust_456",
  "email": "clientnou@exemplu.com",
  "first_name": "Alexandru",
  "last_name": "Dumitru",
  "phone": "+40721234567",
  "created_at": "2025-01-15T10:00:00Z",
  "source": "checkout",
  "marketing_consent": true
}
```

### Livrarea Webhook

```php
class ZapierWebhookService
{
    public function fireWebhook(string $trigger, array $data): void
    {
        $subscriptions = $this->getActiveSubscriptions($trigger);

        foreach ($subscriptions as $subscription) {
            dispatch(new SendZapierWebhook(
                $subscription->hook_url,
                $data,
                $subscription->id
            ));
        }
    }
}

// În event listener
class OrderCreatedListener
{
    public function handle(OrderCreated $event): void
    {
        app(ZapierWebhookService::class)->fireWebhook(
            'order_created',
            $event->order->toZapierPayload()
        );
    }
}
```

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `zapier_connections` | Conexiuni chei API |
| `zapier_triggers` | Subscripții webhook |
| `zapier_trigger_logs` | Istoric livrări |
| `zapier_actions` | Cereri acțiuni primite |

### Gestionarea Erorilor

Livrările webhook eșuate sunt reîncercate:

```php
// Program reîncercare
$retryDelays = [1, 5, 30]; // secunde

// După ce toate reîncercările eșuează
if ($attempts >= 3) {
    $subscription->markAsFailed();
    Log::error('Livrarea webhook Zapier a eșuat', [
        'hook_id' => $subscription->id,
        'attempts' => $attempts,
    ]);
}
```

### Testare

Testează integrarea Zapier:

1. Creează cheie API de test în platformă
2. Configurează Zap în Zapier cu trigger-ul tău
3. Folosește "Test Trigger" pentru a trimite date exemplu
4. Verifică că datele apar corect în Zapier
5. Finalizează configurarea Zap-ului cu acțiunea dorită

### Securitate

1. Validează cheile API la fiecare cerere
2. Folosește HTTPS pentru toate URL-urile webhook
3. Implementează rate limiting
4. Logează toate schimbările de subscripție
5. Monitorizează pentru activitate neobișnuită

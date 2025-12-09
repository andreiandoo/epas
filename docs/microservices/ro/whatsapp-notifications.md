# Notificări WhatsApp

## Prezentare Scurtă

Transformă modul în care comunici cu clienții tăi prin Notificări WhatsApp. Într-o lume în care comunicarea instantanee este esențială, acest serviciu puternic îți permite să ajungi la audiența ta direct pe platforma de mesagerie pe care o folosesc cel mai mult.

Trimite confirmări automate de comandă în momentul în care o achiziție este finalizată, asigurându-te că clienții tăi se simt apreciați și informați. Programează remindere inteligente pentru evenimente la intervale D-7, D-3 și D-1 pentru a maximiza participarea și a reduce absențele. Lansează campanii promoționale targetate pentru a crește vânzările de bilete și a implica audiența cu oferte personalizate.

Construit cu conformitatea în minte, serviciul nostru include gestionare completă a consimțământului opt-in/opt-out care respectă cerințele GDPR. Sistemul de gestionare a template-urilor cu flux de aprobare BSP asigură că toate mesajele tale sunt pre-aprobate și gata de trimis. Suportul multi-furnizor (360dialog, Twilio, Meta Cloud API) îți oferă flexibilitate și fiabilitate.

Urmărește fiecare mesaj cu confirmări de livrare și citire, monitorizează costurile în timp real și accesează statistici și rapoarte comprehensive. Fie că organizezi un concert, conferință sau festival, Notificările WhatsApp te ajută să menții conexiuni semnificative cu audiența ta pe tot parcursul călătoriei lor de client.

---

## Descriere Detaliată

Notificări WhatsApp este o soluție completă de mesagerie concepută special pentru organizatorii de evenimente și platformele de ticketing. Serviciul utilizează API-ul WhatsApp Business pentru a livra mesaje tranzacționale și promoționale la scară, menținând în același timp conformitatea completă cu reglementările de mesagerie.

### Cum Funcționează

Când un client finalizează o achiziție, sistemul poate trimite automat o confirmare de comandă prin WhatsApp cu toate detaliile relevante, inclusiv informații despre bilet, detalii eveniment și link-uri de descărcare. Design-ul idempotent asigură că nu sunt trimise mesaje duplicate chiar dacă procesul de comandă este declanșat de mai multe ori.

Pentru reminderele de evenimente, sistemul inteligent de programare pune automat în coadă mesajele la intervale configurabile înainte de fiecare eveniment. Motorul conștient de fusul orar asigură că reminderele ajung la ore locale potrivite pentru clienții tăi, indiferent de locația lor.

Campaniile promoționale pot fi segmentate și targetate către grupuri specifice de clienți. Cu modul dry-run, poți testa campaniile înainte de trimitere pentru a te asigura că totul arată perfect. Variabilele de template permit personalizarea cu numele clienților, detaliile evenimentului, codurile de reducere și multe altele.

### Conformitate și Securitate

Toate mesajele necesită consimțământul clientului prin sistemul de gestionare opt-in. Clienții pot renunța oricând, iar preferințele lor sunt imediat respectate pe toate canalele de comunicare. Sistemul menține jurnale complete de audit pentru conformitatea cu reglementările.

### Flexibilitate Furnizor

Arhitectura bazată pe adaptoare suportă multipli furnizori de soluții business (BSP), permițându-ți să alegi furnizorul care se potrivește cel mai bine nevoilor tale sau să schimbi furnizorii fără a modifica integrarea.

---

## Funcționalități

### Capabilități de Mesagerie
- Mesaje de confirmare comandă cu livrare idempotentă
- Remindere automate pentru evenimente la intervale D-7, D-3 și D-1
- Campanii promoționale cu segmentare audiență
- Template-uri cu variabile ({first_name}, {event_name}, {discount_code}, etc.)
- Mod dry-run pentru testarea campaniilor înainte de trimitere

### Gestionare Consimțământ
- Gestionare consimțământ opt-in/opt-out (conform GDPR)
- Validare numere de telefon E.164
- Urmărire sursă consimțământ (checkout, pagină setări, etc.)
- Istoric complet al modificărilor de consimțământ

### Gestionare Template-uri
- Gestionare template-uri cu flux de aprobare BSP
- Suport pentru mai multe categorii de template-uri (order_confirm, reminder, promo, otp)
- Suport template-uri multi-limbă
- Urmărire în timp real a statusului template-urilor

### Integrare Furnizori
- Suport multi-BSP (360dialog, Twilio, Meta Cloud API)
- Pattern adaptor agnostic de furnizor
- Capabilități de failover automat
- Rate limiting și throttling

### Tracking și Analiză
- Confirmări de livrare și citire
- Urmărire costuri și deducere sold
- Statistici și raportare comprehensive
- Istoric mesaje și jurnale de audit
- Integrare webhook pentru actualizări de status

### Programare
- Programare remindere conștientă de fusul orar
- Intervale de reminder configurabile
- Programare în masă pentru evenimente
- Gestionare și anulare programări

---

## Cazuri de Utilizare

### Organizatori de Concerte și Festivaluri
Trimite confirmări instant de bilete și construiește entuziasmul cu remindere de numărătoare inversă. Promovează disponibilitatea de ultim moment a biletelor sau upgrade-uri VIP speciale către audiența ta implicată.

### Conferințe și Evenimente Business
Ține participanții informați cu remindere de sesiuni, schimbări de program și oportunități de networking. Partajează informații despre prezentatori și detalii despre locație direct pe telefoanele lor.

### Teatru și Arte Performative
Reamintește patronilor despre spectacolele viitoare, partajează informații pre-spectacol și promovează pachete de abonament sau reprezentații viitoare.

### Evenimente Sportive
Trimite remindere de zi de meci cu informații despre parcare, ore de deschidere a porților și oferte exclusive de merchandise către deținătorii de bilete.

### Evenimente Recurente
Construiește loialitatea clienților notificând participanții anteriori despre edițiile viitoare și oferind reduceri early-bird.

### Comunicări de Urgență
Notifică rapid toți deținătorii de bilete despre schimbări de locație, amânări de evenimente sau informații importante de siguranță.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Notificări WhatsApp oferă o soluție completă de mesagerie pentru platformele de ticketing pentru evenimente. Gestionează întregul ciclu de viață al mesajelor WhatsApp incluzând gestionarea consimțământului, administrarea template-urilor, livrarea mesajelor și analize.

### Arhitectură

Serviciul urmează o arhitectură bazată pe adaptoare care abstractizează implementările specifice BSP în spatele unei interfețe comune. Aceasta permite integrare fără probleme cu multipli furnizori (360dialog, Twilio, Meta Cloud API) menținând un API consistent.

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `wa_optin` | Înregistrări consimțământ clienți cu status opt-in/opt-out |
| `wa_templates` | Template-uri mesaje cu status aprobare |
| `wa_messages` | Înregistrări mesaje trimise cu status livrare |
| `wa_schedules` | Mesaje programate pentru livrare viitoare |

### Endpoint-uri API

#### Gestionare Template-uri
```
POST /api/wa/templates
```
Creează sau actualizează un template de mesaj pentru aprobare BSP.

#### Trimitere Mesaje
```
POST /api/wa/send/confirm
```
Trimite mesaj de confirmare comandă (idempotent).

```
POST /api/wa/send/promo
```
Trimite mesaj promoțional către clienții cu opt-in.

#### Programare Remindere
```
POST /api/wa/schedule/reminders
```
Programează mesaje reminder pentru evenimente la intervale configurate.

#### Webhook-uri
```
POST /api/wa/webhook
```
Primește actualizări de status livrare de la BSP.

#### Gestionare Opt-in
```
POST /api/wa/optin
```
Înregistrează preferința de opt-in sau opt-out a clientului.

#### Statistici
```
GET /api/wa/stats/{tenantId}
```
Obține statistici și analize de mesagerie.

```
GET /api/wa/messages/{tenantId}
```
Listează mesajele trimise cu informații de status și cost.

```
GET /api/wa/schedules/{tenantId}
```
Listează mesajele programate în așteptare pentru livrare.

### Tipuri de Mesaje

| Tip | Descriere |
|-----|-----------|
| `order_confirm` | Confirmare comandă și bilet |
| `reminder` | Reminder eveniment (D-7, D-3, D-1) |
| `promo` | Mesaj campanie promoțională |
| `otp` | Verificare parolă unică |

### Configurare

```php
'whatsapp' => [
    'default_provider' => env('WHATSAPP_PROVIDER', 'twilio'),
    'reminder_intervals' => ['D-7', 'D-3', 'D-1'],
    'rate_limit' => [
        'per_minute' => 100,
        'per_hour' => 1000,
    ],
    'cost_tracking' => true,
]
```

### Exemplu de Integrare

```php
use App\Services\WhatsApp\WhatsAppService;

// Trimite confirmare comandă
$whatsapp = app(WhatsAppService::class);
$whatsapp->sendOrderConfirmation($order, $customer);

// Programează remindere eveniment
$whatsapp->scheduleReminders($event, $attendees);

// Trimite campanie promoțională
$whatsapp->sendPromo($campaign, $segment);
```

### Gestionare Erori

Serviciul implementează backoff exponențial pentru livrările eșuate și menține jurnale detaliate de erori. Mesajele eșuate sunt reîncercate automat până la numărul maxim configurat de încercări înainte de a fi mutate într-o coadă dead-letter.

### Metrici

Serviciul urmărește următoarele metrici:
- Mesaje trimise/livrate/citite per tenant
- Cost per mesaj și cheltuială totală
- Rate de opt-in/opt-out
- Rate de aprobare template-uri
- Rate de succes livrare

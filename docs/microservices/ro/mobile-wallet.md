# Carduri Mobile Wallet

## Prezentare Scurtă

Transformă smartphone-urile clienților tăi în purtătoare de bilete cu Cardurile Mobile Wallet. În lumea de azi centrată pe mobil, nimeni nu vrea să printeze bilete sau să caute prin emailuri la intrarea în locație. Oferă clienților tăi confortul pe care îl așteaptă.

Cu o singură apăsare, participanții pot adăuga biletele de eveniment în Apple Wallet sau Google Pay. Biletele sunt mereu accesibile, chiar și offline, direct din ecranul principal al telefonului. Nu este necesară descărcarea unei aplicații, nu trebuie crearea unui cont - doar confort pur.

Dar nu e doar despre stocare. Cardurile mobile wallet sunt inteligente. Când detaliile evenimentului se schimbă - locație nouă, oră actualizată sau schimbare de program - cardurile se actualizează automat pe dispozitivele clienților. Trimite notificări push direct pe card amintindu-le despre eveniment, sfaturi de parcare sau oferte speciale.

Reminderele bazate pe locație adaugă un alt nivel de magie. Pe măsură ce clienții se apropie de locație, telefonul lor poate să le amintească despre bilet. Codul de bare sau QR de pe card se integrează perfect cu scannerele tale existente de bilete.

Pentru organizatorii de evenimente, beneficiile se multiplică. Urmărește engagement-ul cu analize de carduri, gestionează cardurile în lot pentru evenimente mari și menține prezența brandului tău cu carduri personalizate cu logo-ul, culorile și imaginile tale.

Fie că e un singur bilet de concert sau un abonament complet de festival, Mobile Wallet aduce biletele tale în era modernă. Oferă clienților tăi o experiență demnă de evenimentul lor.

---

## Descriere Detaliată

Cardurile Mobile Wallet este un serviciu comprehensiv pentru generarea și gestionarea biletelor digitale compatibile cu Apple Wallet și Google Pay. Serviciul transformă biletele tradiționale PDF sau email în carduri wallet dinamice și interactive care trăiesc pe dispozitivele mobile ale clienților.

### Suport Platforme

**Apple Wallet**: Generează fișiere .pkpass compatibile cu dispozitivele iOS. Cardurile apar în aplicația nativă Wallet și suportă actualizări în timp real, notificări push și remindere bazate pe locație.

**Google Pay**: Creează carduri bazate pe JWT pentru dispozitivele Android. Se integrează cu aplicația Google Pay și suportă capabilități similare de actualizare și notificare.

### Ciclul de Viață al Cardului

1. **Generare**: Când un bilet este achiziționat, un card wallet este generat automat cu detaliile evenimentului, informații despre participant și un cod de bare unic.

2. **Livrare**: Cardurile sunt trimise prin email cu un buton "Adaugă în Wallet", sau pot fi adăugate direct din pagina de confirmare a biletului.

3. **Actualizări**: Când detaliile evenimentului se schimbă, serviciul împinge actualizări către toate cardurile emise. Clienții văd noile informații fără nicio acțiune necesară.

4. **Check-in**: Codul de bare de pe card este scanat la intrare, exact ca un bilet tradițional. Cardul poate fi marcat ca "folosit" pentru a preveni reintrarea.

5. **Expirare**: După eveniment, cardurile pot fi arhivate automat sau expirate bazat pe configurația ta.

### Personalizare

Cardurile pot fi complet personalizate cu identitatea vizuală a organizației tale:
- Imagini logo și banner
- Scheme de culori personalizate
- Imagini specifice evenimentului
- Conținut localizat în mai multe limbi

---

## Funcționalități

### Generare Carduri
- Generare Apple Wallet (.pkpass)
- Generare card Google Pay
- Design card personalizat cu branding
- Generare cod de bare/QR
- Tipuri multiple de bilete per card
- Generare carduri în lot

### Actualizări în Timp Real
- Împingere schimbări detalii eveniment către carduri
- Actualizări de timp și locație
- Notificări push în wallet
- Remindere bazate pe locație
- Gestionare expirare carduri

### Livrare și Acces
- Livrare automată card prin email
- Link-uri directe add-to-wallet
- Suport pentru carduri serii evenimente
- Acces offline pentru clienți

### Integrare
- Integrare cu scanner bilete
- Sincronizare status check-in
- Integrare sistem comenzi
- Sincronizare gestionare evenimente

### Analize
- Analize carduri și urmărire engagement
- Rate de conversie add-to-wallet
- Urmărire livrare actualizări
- Metrici de engagement

---

## Cazuri de Utilizare

### Bilete de Concert
Înlocuiește biletele de hârtie cu carduri digitale elegante. Fanii arată telefonul la intrare, iar entuziasmul începe înainte de prima notă.

### Abonamente Festival Multi-Zi
Emite un singur card care funcționează pe toate zilele festivalului. Actualizările țin participanții informați despre schimbări de program și anunțuri speciale.

### Ecusoane de Conferință
Ecusoane digitale cu informații participant, acces la sesiuni și funcții de networking toate într-un singur card wallet.

### Evenimente Sportive
Deținătorii de abonamente de sezon primesc carduri care se actualizează pentru fiecare meci de acasă cu data, adversarul și informații despre loc.

### Teatru și Spectacole
Carduri elegante care reflectă sofisticarea spectacolului, cu remindere pentru ora cortinei și informații despre locație.

### Transport și Parcare
Combină admiterea la eveniment cu cardurile de parcare, toate într-o singură locație convenabilă în wallet.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Carduri Mobile Wallet generează și gestionează bilete digitale Apple Wallet (.pkpass) și Google Pay. Gestionează crearea cardurilor, actualizările, notificările și analizele.

### Arhitectură

```
Comandă Confirmată → Serviciu Wallet → Generator Carduri → API-uri Apple/Google
                           ↓
                    Manager Actualizări → Notificări Push
                           ↓
                    Tracker Analize
```

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `wallet_passes` | Înregistrări carduri generate |
| `wallet_pass_updates` | Istoric actualizări și status |

### Structură Card (Apple Wallet)

```json
{
  "formatVersion": 1,
  "passTypeIdentifier": "pass.com.example.event",
  "serialNumber": "ticket_abc123",
  "teamIdentifier": "TEAM_ID",
  "organizationName": "Organizator Eveniment",
  "description": "Bilet Eveniment",
  "eventTicket": {
    "primaryFields": [
      {"key": "event", "label": "EVENIMENT", "value": "Concert de Vară"}
    ],
    "secondaryFields": [
      {"key": "date", "label": "DATA", "value": "15 Iulie 2025"}
    ],
    "auxiliaryFields": [
      {"key": "seat", "label": "LOC", "value": "A-15"}
    ],
    "backFields": [
      {"key": "terms", "label": "Termeni", "value": "..."}
    ]
  },
  "barcode": {
    "format": "PKBarcodeFormatQR",
    "message": "TICKET-ABC123",
    "messageEncoding": "iso-8859-1"
  },
  "locations": [
    {"latitude": 44.4268, "longitude": 26.1025}
  ],
  "relevantDate": "2025-07-15T18:00:00+03:00"
}
```

### Endpoint-uri API

#### Generează Card
```
POST /api/wallet/generate/{ticketId}
```
Generează card wallet pentru un bilet.

**Răspuns:**
```json
{
  "pass_id": "pass_abc123",
  "apple_url": "https://example.com/wallet/apple/pass_abc123",
  "google_url": "https://example.com/wallet/google/pass_abc123",
  "created_at": "2025-01-15T10:00:00Z"
}
```

#### Obține Card
```
GET /api/wallet/pass/{passId}
```
Obține detaliile cardului și URL-urile de descărcare.

#### Actualizează Card
```
POST /api/wallet/update/{passId}
```
Împinge actualizări către un card existent.

#### Trimite Notificare
```
POST /api/wallet/notify/{passId}
```
Trimite notificare push către deținătorul cardului.

#### Obține Analize
```
GET /api/wallet/analytics/{tenantId}
```
Obține analizele de engagement pentru carduri.

### Configurare

```php
'wallet' => [
    'apple' => [
        'pass_type_identifier' => env('APPLE_PASS_TYPE_ID'),
        'team_identifier' => env('APPLE_TEAM_ID'),
        'certificate_path' => env('APPLE_CERT_PATH'),
        'certificate_password' => env('APPLE_CERT_PASSWORD'),
    ],
    'google' => [
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'service_account_file' => env('GOOGLE_SERVICE_ACCOUNT'),
    ],
    'default_images' => [
        'logo' => 'wallet/default-logo.png',
        'strip' => 'wallet/default-strip.png',
    ],
]
```

### Exemplu de Integrare

```php
use App\Services\Wallet\WalletService;

// Generează card pentru bilet
$wallet = app(WalletService::class);
$pass = $wallet->generatePass($ticket);

// Obține URL-uri descărcare
$appleUrl = $pass->getAppleWalletUrl();
$googleUrl = $pass->getGooglePayUrl();

// Împinge actualizare când evenimentul se schimbă
$wallet->updatePass($pass->id, [
    'event_time' => '19:00',
    'venue' => 'Nume Locație Actualizată',
]);

// Trimite notificare
$wallet->sendNotification($pass->id, 'Evenimentul începe într-o oră!');
```

### Flux Actualizare Card

1. Detaliile evenimentului se schimbă în sistem
2. Serviciul wallet identifică cardurile afectate
3. Payload-ul de actualizare este generat
4. Notificare push trimisă la Apple/Google
5. Dispozitivul extrage noile date ale cardului
6. Clientul vede informațiile actualizate

### Platforme Suportate

| Platformă | Format | Funcții |
|-----------|--------|---------|
| Apple Wallet | .pkpass (PKCS#7) | Actualizări, notificări, locație |
| Google Pay | JWT | Actualizări, notificări |

### Metrici

Urmărește performanța cardurilor:
- Carduri generate per eveniment
- Rată de conversie add-to-wallet
- Rată de succes livrare actualizări
- Rată de engagement notificări
- Rată de check-in din carduri wallet

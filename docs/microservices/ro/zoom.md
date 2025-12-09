# Integrare Zoom

## Prezentare Scurtă

Adu-ți evenimentele în virtual cu integrare Zoom fără probleme. Creează întâlniri și webinare automat când evenimentele sunt publicate. Sincronizează deținătorii de bilete ca participanți înregistrați. Urmărește prezența și gestionează înregistrările - totul din platforma ta de ticketing.

Evenimentele virtuale merită aceeași experiență profesională ca cele în persoană. Integrarea Zoom face legătura între ticketing și videoconferință, eliminând configurarea manuală și introducerea datelor.

Crearea automată a întâlnirilor economisește ore. Publică un eveniment virtual și detaliile întâlnirii Zoom se generează instant. Linkuri întâlnire, parole și informații de apelare gata pentru cumpărătorii de bilete.

Sincronizarea participanților asigură acces fluid. Deținătorii de bilete devin automat participanți înregistrați Zoom. Fără încărcări manuale de liste, fără probleme de acces în ziua evenimentului.

Urmărirea prezenței oferă insights. Vezi cine s-a alăturat, cât a stat și tiparele de engagement. Înțelege-ți audiența virtuală ca niciodată.

Suportul webinar gestionează audiențe mari. Scalează de la întâlniri intime la mii de participanți. Înregistrare, Q&A și sondaje toate sincronizate.

Gestionarea înregistrărilor păstrează conținutul. Accesează înregistrările cloud, partajează cu participanții sau reutilizează pentru marketing. Evenimentele tale virtuale trăiesc mai departe.

Securitatea OAuth 2.0 îți protejează contul Zoom. Autentificare standard din industrie cu reîmprospătare automată token. Conectează-te o dată, rămâi conectat.

Adu-ți evenimentele online fără bătăi de cap. Experiențe virtuale profesionale, automatizate.

---

## Descriere Detaliată

Microserviciul de Integrare Zoom conectează platforma ta de ticketing pentru evenimente cu infrastructura de întâlniri și webinare Zoom. Automatizează crearea întâlnirilor, gestionarea participanților și urmărirea prezenței.

### Tipuri de Întâlniri

| Tip | Cel Mai Bun Pentru | Capacitate |
|-----|-------------------|------------|
| Meeting | Sesiuni interactive | Până la 1.000 |
| Webinar | Prezentări | Până la 50.000 |

### Configurare Automată

Când creezi un eveniment virtual:
1. Întâlnire/webinar Zoom creat automat
2. Setări configurate (waiting room, parole, etc.)
3. Link de join generat și stocat
4. Pagina evenimentului actualizată cu detaliile întâlnirii

### Sincronizare Participanți

Când biletele sunt cumpărate:
1. Informațiile cumpărătorului capturate
2. Participant adăugat la întâlnirea Zoom
3. Email de confirmare cu link de join trimis
4. Lista participanților menținută sincronizată

### Urmărirea Prezenței

După întâlniri:
- Timpii join/leave înregistrați
- Durata calculată
- Rapoarte prezență generate
- Date sincronizate în înregistrările clienților

---

## Funcționalități

### Gestionare Întâlniri
- Creare întâlniri programate
- Creare întâlniri instant
- Actualizare setări întâlnire
- Ștergere întâlniri
- Obținere detalii întâlnire

### Suport Webinar
- Creare webinare
- Gestionare panelisti
- Gestionare înregistrări
- Q&A și sondaje
- Sesiuni de practică

### Sincronizare Participanți
- Adăugare automată participanți
- Sincronizare date deținători bilete
- Actualizare înregistrări
- Eliminare la rambursare

### Prezență
- Urmărire timpi join
- Înregistrare durată
- Generare rapoarte
- Sincronizare în CRM

### Înregistrări
- Acces înregistrări cloud
- Descărcare înregistrări
- Partajare linkuri înregistrări
- Ștergere înregistrări

### Autentificare
- Conexiune OAuth 2.0
- Reîmprospătare automată token
- Verificare semnătură webhook

---

## Cazuri de Utilizare

### Conferințe Virtuale
Conferințe multi-sesiune cu breakout rooms. Urmărirea participanților între sesiuni. Experiență profesională de eveniment virtual.

### Workshop-uri Online
Workshop-uri interactive cu engagement participanți. Partajare ecran și colaborare. Certificate de participare bazate pe prezență.

### Serii de Webinare
Conținut educațional la scară. Înregistrarea blochează conținutul. Înregistrările extind valoarea.

### Evenimente Hibride
Evenimente în persoană cu opțiune de participare virtuală. Același conținut, livrare diferită. Reach extins fără limitele locației.

---

## Documentație Tehnică

### Configurare

```php
'zoom' => [
    'client_id' => env('ZOOM_CLIENT_ID'),
    'client_secret' => env('ZOOM_CLIENT_SECRET'),
    'redirect_uri' => env('ZOOM_REDIRECT_URI'),
    'webhook_secret' => env('ZOOM_WEBHOOK_SECRET'),
]
```

### Endpoint-uri API

#### Autorizare OAuth

```
GET /api/integrations/zoom/auth
```

#### Creare Întâlnire

```
POST /api/integrations/zoom/meetings
```

**Cerere:**
```json
{
  "topic": "Workshop de Vară 2025",
  "type": 2,
  "start_time": "2025-07-15T14:00:00Z",
  "duration": 120,
  "timezone": "Europe/Bucharest",
  "settings": {
    "waiting_room": true,
    "registration_type": 2,
    "approval_type": 0
  }
}
```

#### Adăugare Participant

```
POST /api/integrations/zoom/meetings/{meetingId}/registrants
```

**Cerere:**
```json
{
  "email": "participant@exemplu.com",
  "first_name": "Ion",
  "last_name": "Popescu"
}
```

#### Obținere Raport Prezență

```
GET /api/integrations/zoom/meetings/{meetingId}/participants
```

### Evenimente Webhook

| Eveniment | Descriere |
|-----------|-----------|
| meeting.started | Întâlnirea a început |
| meeting.ended | Întâlnirea s-a încheiat |
| meeting.participant_joined | Cineva s-a alăturat |
| meeting.participant_left | Cineva a plecat |
| recording.completed | Înregistrare gata |

### Schemă Bază de Date

| Tabel | Descriere |
|-------|-----------|
| `zoom_connections` | Token-uri OAuth |
| `zoom_meetings` | Înregistrări întâlniri |
| `zoom_registrants` | Participanți sincronizați |
| `zoom_attendance` | Date prezență |

# Gestionare Listă de Așteptare

## Prezentare Scurtă

Transformă dezamăgirea sold-out în vânzări viitoare cu Gestionarea Listei de Așteptare. Când biletele se vând, nu pierde acei clienți entuziaști - captează-le interesul și convertește-i când biletele devin disponibile.

Lista de așteptare inteligentă se activează automat când un eveniment face sold-out. Clienții se înscriu cu tipul și cantitatea preferată de bilete. Urmărirea poziției arată unde se află în rând, menținând așteptările clare.

Când biletele devin disponibile - fie din anulări, returnări sau lansări noi - sistemul notifică clienții instant prin email și SMS. Ferestrele de achiziție limitate în timp creează urgență: revendică-ți biletele în fereastra de timp sau pierzi locul în favoarea următoarei persoane din rând.

Algoritmii de distribuție echitabilă asigură acces echitabil. Membrii VIP și de loialitate pot primi prioritate, răsplătindu-ți cei mai buni clienți. Sau păstrează-l strict primul-venit-primul-servit - regulile tale, alegerea ta.

Gestionarea eliberării în masă gestionează efficient lansările mari de bilete. Eliberează 100 de bilete către lista de așteptare cu loturi de notificări configurabile. Urmărește ratele de conversie pentru a înțelege câți membri ai listei de așteptare chiar cumpără.

Integrarea cu procesul tău de returnări înseamnă că biletele eliberate curg automat către lista de așteptare. Nu e necesară intervenție manuală - sistemul gestionează actualizările de disponibilitate fără cusur.

Afișarea poziției ține clienții informați și implicați. Știu exact unde se află și sunt actualizați pe măsură ce rândul avansează. Transparența construiește încredere.

Nu mai pierde niciodată o vânzare din cauza "sold out". Gestionarea Listei de Așteptare ține ușa deschisă.

---

## Funcționalități

### Operațiuni Listă Așteptare
- Activare automată listă așteptare la sold-out
- Gestionare coadă prioritate
- Preferințe tip bilet
- Preferințe cantitate per client
- Afișare poziție listă pentru clienți

### Notificări
- Notificări instant disponibilitate
- Notificări SMS și email
- Ferestre de achiziție limitate în timp
- Gestionare eliberare în masă

### Echitate & Prioritate
- Algoritmi distribuție echitabilă
- Opțiuni prioritate VIP/loialitate
- Opțiune primul-venit-primul-servit

### Integrare
- Integrare cu eliberări returnări
- Formulare personalizabile listă așteptare
- Analize și urmărire conversie

---

## Documentație Tehnică

### Endpoint-uri API

```
POST /api/waitlist/join
```
Înscrie-te în lista de așteptare.

```
GET /api/waitlist/position/{customerId}
```
Obține poziția clientului.

```
POST /api/waitlist/release/{eventId}
```
Eliberează bilete către lista de așteptare.

```
GET /api/waitlist/stats/{eventId}
```
Obține statisticile listei de așteptare.

```
DELETE /api/waitlist/leave/{customerId}
```
Părăsește lista de așteptare.

### Configurare

```php
'waitlist' => [
    'notification_window' => '24 hours',
    'purchase_window' => '2 hours',
    'max_waitlist_size' => 10000,
]
```

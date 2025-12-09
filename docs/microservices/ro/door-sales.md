# Vânzări la Ușă

## Prezentare Scurtă

Vinde bilete chiar la ușa locației cu Vânzări la Ușă. Când clienții sosesc fără bilete, nu-i refuza - transformă-i în participanți. Acest sistem complet de punct de vânzare pune vânzările de bilete în mâinile tale, la propriu.

Acceptă plăți numerar, card sau mobile cu ușurință. Sistemul suportă procesatori multipli de plăți inclusiv Stripe, Square și SumUp, oferindu-ți flexibilitate în cum gestionezi tranzacțiile. Printarea termică a chitanțelor asigură că clienții pleacă cu dovada achiziției.

Sincronizarea în timp real a inventarului înseamnă că nu vei supravinde niciodată. Pe măsură ce biletele sunt vândute la ușă, disponibilitatea se actualizează instant pe toate canalele de vânzare. Inventarul online și vânzările la ușă rămân perfect sincronizate.

Când internetul cade, afacerea nu se oprește. Modul offline stochează tranzacțiile local și sincronizează automat când conectivitatea revine. Gestionează până la 1000 de tranzacții offline fără probleme.

Reconcilierea de sfârșit de zi este simplă. Rapoartele zilnice defalcă vânzările pe metodă de plată, tip de bilet și membru al personalului. Gestionarea sertarului de numerar urmărește fiecare sold de deschidere și închidere.

Dispozitive multiple pot funcționa simultan la diferite intrări ale locației. Controalele de acces ale personalului asigură că membrii echipei văd doar ce au nevoie, în timp ce jurnalele de audit urmăresc fiecare tranzacție pentru responsabilitate completă.

Când sunt necesare rambursări sau schimburi, procesează-le pe loc. Caută clienții după telefon sau email pentru a găsi achizițiile originale instant.

Nu pierde nicio vânzare. Vânzări la Ușă pune casa de bilete oriunde.

---

## Descriere Detaliată

Vânzări la Ușă este o soluție comprehensivă de punct de vânzare (POS) concepută special pentru vânzarea biletelor la locațiile de evenimente. Oferă toate instrumentele necesare pentru vânzări eficiente de bilete la fața locului menținând sincronizarea în timp real cu sistemul central de ticketing.

### Suport Multi-Dispozitiv

Desfășoară tablete multiple sau terminale POS la diferite intrări ale locației. Fiecare dispozitiv operează independent menținând sincronizarea cu sistemul central.

### Procesare Plăți

Suport integrat pentru procesatori majori de plăți:
- **Stripe**: Plăți cu cardul prin Stripe Terminal
- **Square**: Integrare cititor Square
- **SumUp**: Suport cititor portabil de carduri
- **Numerar**: Gestionare completă numerar cu urmărire sertar

### Reziliență Offline

Sistemul este construit pentru fiabilitate:
- Stocare locală tranzacții când offline
- Sincronizare automată când conectivitatea revine
- Capacitate pentru 1000+ tranzacții offline
- Fără vânzări duplicate sau conflicte de inventar

### Gestionare Inventar

Sincronizarea în timp real asigură acuratețe:
- Actualizări instant inventar pe canale
- Prevenție supravânzare
- Rezervări în timpul checkout-ului
- Eliberare automată coșuri abandonate

---

## Funcționalități

### POS de Bază
- Sincronizare în timp real a inventarului de bilete
- Metode multiple de plată (numerar, card, mobil)
- Printare termică chitanțe
- Generare bilete cod de bare/QR
- Căutare clienți după telefon/email

### Fiabilitate
- Mod offline cu sincronizare automată
- Suport dispozitive multiple per locație
- Coadă tranzacții
- Rezolvare conflicte

### Gestionare
- Rapoarte zilnice vânzări și reconciliere
- Gestionare sertar numerar
- Controale acces personal și jurnale audit
- Procesare rambursări și schimburi

### Integrare
- Integrare cu sistemul principal de ticketing
- Flexibilitate procesatori plăți
- Personalizare chitanțe
- Raportare în timp real

---

## Cazuri de Utilizare

### Casă de Bilete Sală de Concerte
Personalul la ferestre multiple vinde bilete simultan, fiecare cu propriul sertar de numerar. La sfârșitul nopții, reconciliază toate sertarele și exportă datele de vânzări.

### Puncte de Intrare Festival
Porți multiple au fiecare o tabletă pentru vânzarea abonamentelor de zi clienților walk-up. Modul offline gestionează conectivitatea intermitentă a festivalului.

### Will-Call Plus Vânzări Teatru
Combină ridicarea will-call cu vânzările la fața locului la aceeași stație. Caută rezervările și procesează achiziții noi.

### Arenă Sportivă
Porți multiple de intrare gestionează vânzările de zi de meci pentru fanii care întârzie. Tranzacțiile rapide minimizează cozile.

---

## Documentație Tehnică

### Endpoint-uri API

```
POST /api/door-sales/transaction
```
Procesează o tranzacție de vânzare.

```
GET /api/door-sales/inventory/{eventId}
```
Obține inventarul disponibil pentru eveniment.

```
POST /api/door-sales/sync
```
Sincronizează tranzacțiile offline.

```
GET /api/door-sales/reports/{tenantId}
```
Obține rapoarte de vânzări.

```
POST /api/door-sales/refund
```
Procesează o rambursare.

### Configurare

```php
'door_sales' => [
    'supported_devices' => ['tablet', 'pos-terminal', 'mobile'],
    'payment_processors' => ['stripe', 'square', 'sumup'],
    'offline_capacity' => 1000,
    'receipt_printer' => 'thermal',
]
```

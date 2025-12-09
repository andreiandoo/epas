# Rezervări de Grup

## Prezentare Scurtă

Simplifică achizițiile de bilete în vrac cu Rezervări de Grup. Evenimente corporate, excursii școlare, grupuri turistice - când organizațiile au nevoie de bilete multiple, checkout-ul standard nu e suficient. Rezervările de Grup oferă o experiență simplificată pentru comenzi mari.

Activează reduceri de grup pe niveluri care răsplătesc achizițiile mai mari. Cumpără 10 bilete, primește 10% reducere. Cumpără 50, primește 20% reducere. Cu cât cumpără mai mult, cu atât economisesc mai mult - și cu atât ocupi mai multe locuri.

Dashboard-ul liderului de grup pune organizatorii în control. Gestionează listele de participanți, colectează detaliile individuale și distribuie biletele membrilor grupului. Ai nevoie de cerințe dietetice sau de accesibilitate? Personalizează formularele de rezervare pentru a colecta exact ce ai nevoie.

Rezervările de blocuri de locuri asigură că grupurile stau împreună. Rezervă o secțiune întreagă sau locuri dispersate în locație - orice funcționează mai bine pentru eveniment.

Fluxurile de aprobare îți protejează inventarul. Solicitările de grupuri mari pot necesita aprobare manuală înainte ca biletele să fie eliberate, oferindu-ți control asupra comenzilor semnificative.

Plățile parțiale fac achizițiile mari gestionabile. Colectează un avans pentru a ține biletele, apoi colectează restul mai aproape de data evenimentului.

Check-in-ul de grup la locație este fără cusur. Procesează întregul grup deodată în loc să scanezi bilete individuale. Perfect pentru autocare turistice care sosesc la ușă.

De la ieșiri corporate de echipă la reuniuni de familie, Rezervările de Grup gestionează complexitatea comenzilor mari cu grație.

---

## Funcționalități

### Gestionare Rezervări
- Interfață comandă bilete în vrac
- Reduceri de grup pe niveluri
- Dashboard lider grup
- Gestionare liste participanți
- Distribuție bilete individuale
- Rezervări blocuri locuri

### Flux de Lucru
- Flux aprobare pentru grupuri mari
- Formulare personalizate rezervare grup
- Colectare cerințe dietetice/accesibilitate
- Instrumente comunicare grup
- Listă așteptare pentru alocări grup sold-out

### Plăți
- Generare factură grup
- Suport plată parțială
- Colectare avans

### Operațiuni Locație
- Check-in grup la locație
- Scanare bilete în lot

---

## Documentație Tehnică

### Endpoint-uri API

```
POST /api/group-booking/request
```
Trimite solicitare rezervare grup.

```
GET /api/group-booking/{bookingId}
```
Obține detalii rezervare.

```
PUT /api/group-booking/{bookingId}/attendees
```
Actualizează lista participanților.

```
POST /api/group-booking/{bookingId}/distribute
```
Distribuie biletele membrilor grupului.

```
GET /api/group-booking/discounts/{eventId}
```
Obține reducerile de grup disponibile.

### Configurare

```php
'group_booking' => [
    'min_group_size' => 10,
    'max_group_size' => 500,
    'payment_split' => true,
    'require_approval' => true,
]
```

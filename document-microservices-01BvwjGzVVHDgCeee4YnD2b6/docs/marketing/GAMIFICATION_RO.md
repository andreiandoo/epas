# ⭐ Microserviciu Gamificare

## Prezentare Generală

Construiește loialitatea clienților și crește achizițiile repetate cu un sistem puternic de recompense bazat pe puncte. Microserviciul Gamificare transformă cumpărătorii ocazionali în fani loiali, răsplătind achizițiile, referințele și ocaziile speciale cu puncte ce pot fi folosite.

**Preț:** €15/lună per tenant

---

## Funcționalități Principale

### Sistem de Câștigare Puncte
- **Recompense la Achiziții**: Clienții câștigă un procent din fiecare comandă ca puncte
- **Bonus de Înscriere**: Întâmpină clienții noi cu puncte bonus
- **Bonus de Ziua de Naștere**: Sărbătorește clienții cu puncte speciale de ziua lor
- **Program de Referințe**: Răsplătește atât pe cel care recomandă cât și pe noul client
- **Acțiuni Personalizate**: Definește activități personalizate pentru câștigarea de puncte

### Răscumpărarea Punctelor
- **Răscumpărare Flexibilă**: Folosește punctele ca plată în timpul checkout-ului
- **Limite Configurabile**: Setează puncte minime pentru răscumpărare și procent maxim de reducere
- **Plafonare pe Comandă**: Limite opționale de răscumpărare per comandă
- **Reducere Instantanee**: Punctele se convertesc în valoare reală în monedă

### Niveluri Clienți
- **Niveluri de Loialitate**: Creează niveluri Bronze, Silver, Gold, Platinum (sau nume personalizate)
- **Multiplicatori de Puncte**: Nivelurile superioare câștigă puncte bonus la achiziții
- **Badge-uri Vizuale**: Fiecare nivel are culori și iconuri personalizabile
- **Progresie Automată**: Clienții avansează în niveluri bazat pe totalul punctelor câștigate

### Sistem de Referințe
- **Coduri de Referință Unice**: Fiecare client primește un cod de referință personal
- **Link-uri de Partajat**: URL-uri de referință ușor de distribuit
- **Recompense Duble**: Atât cel care recomandă cât și clientul recomandat câștigă puncte
- **Panou de Monitorizare**: Urmărește performanța referințelor și conversiile

### Expirarea Punctelor
- **Expirare Configurabilă**: Setează punctele să expire după o perioadă specifică
- **Reguli de Inactivitate**: Expirare opțională bazată pe inactivitatea contului
- **Afișare Transparentă**: Arată datele de expirare clienților
- **Procesare Automată**: Mentenanța zilnică gestionează expirarea punctelor

---

## Cum Funcționează Punctele

### Câștigarea Punctelor

| Acțiune | Puncte Implicite | Configurabil |
|---------|------------------|--------------|
| Achiziție | 5% din valoarea comenzii | Da |
| Înregistrare | 50 puncte | Da |
| Ziua de Naștere | 100 puncte | Da |
| Referință (Cel ce Recomandă) | 200 puncte | Da |
| Referință (Client Nou) | 100 puncte | Da |

### Răscumpărarea Punctelor

Exemplu de configurare:
- 1 punct = 0.01 RON (1 ban)
- Răscumpărare minimă: 100 puncte (1 RON)
- Reducere maximă: 50% din totalul comenzii

Un client cu 500 puncte poate răscumpăra până la 5 RON reducere la următoarea achiziție (limitat la 50% din comandă).

---

## Funcționalități Panou de Administrare

### Setări Gamificare
Configurează toate aspectele programului tău de loialitate:
- **Valoare Punct**: Setează cât valorează fiecare punct
- **Procent de Câștig**: Definește punctele câștigate per achiziție
- **Reguli de Răscumpărare**: Setează valorile minime și maxime de răscumpărare
- **Puncte Bonus**: Configurează bonusurile de înscriere, ziua de naștere și referințe
- **Setări de Expirare**: Setează perioadele de valabilitate a punctelor
- **Setări de Afișare**: Personalizează numele și iconul punctelor

### Gestionarea Clienților
Vizualizează și gestionează datele de loialitate ale clienților:
- Solduri individuale de puncte
- Istoric tranzacții
- Status nivel
- Performanța referințelor

### Panou de Analiză
Urmărește performanța programului tău de loialitate:
- Total puncte emise
- Puncte răscumpărate
- Membri activi în programul de loialitate
- Rate de conversie referințe

---

## Experiența Clientului

### Panou de Puncte
Clienții pot vizualiza:
- Soldul curent de puncte
- Valoarea punctelor în monedă
- Istoricul tranzacțiilor
- Statusul nivelului curent
- Suma disponibilă pentru răscumpărare
- Codul și link-ul de referință

### Integrare Checkout
În timpul checkout-ului, clienții eligibili văd:
- Soldul de puncte disponibil
- Suma maximă ce poate fi răscumpărată
- Valoarea în puncte a comenzii curente
- Opțiune de răscumpărare cu un singur click

### Pagina Cum Să Câștigi
Educă clienții cu o pagină dedicată ce arată:
- Toate oportunitățile de câștigare a punctelor
- Beneficiile nivelului curent
- Detaliile programului de referințe
- Expirările viitoare ale punctelor

---

## Endpoint-uri API

### Configurare
- `GET /api/gamification/config` - Obține setările programului de loialitate

### Sold Client
- `GET /api/gamification/balance` - Obține sumarul punctelor clientului
- `GET /api/gamification/history` - Obține istoricul tranzacțiilor

### Răscumpărare
- `POST /api/gamification/check-redemption` - Verifică eligibilitatea de răscumpărare
- `POST /api/gamification/redeem` - Aplică punctele la checkout

### Referințe
- `GET /api/gamification/referral` - Obține codul de referință și statisticile
- `POST /api/gamification/track-referral/{code}` - Urmărește click-ul de referință

### Informații
- `GET /api/gamification/how-to-earn` - Obține toate modalitățile de a câștiga puncte

---

## Opțiuni de Configurare

### Setări Valoare Puncte

| Setare | Descriere | Implicit |
|--------|-----------|----------|
| Valoare Punct | Valoare în monedă per punct | 0.01 |
| Monedă | Moneda de răscumpărare puncte | RON |
| Procent Câștig | % din comandă convertit în puncte | 5% |
| Câștig pe Subtotal | Calculează pe subtotal vs total | Da |
| Comandă Min pentru Câștig | Comanda minimă pentru a câștiga puncte | 0 |

### Setări Răscumpărare

| Setare | Descriere | Implicit |
|--------|-----------|----------|
| Puncte Min Răscumpărare | Puncte minime pentru răscumpărare | 100 |
| Procent Max Răscumpărare | % maxim din comandă plătibil cu puncte | 50% |
| Puncte Max Per Comandă | Puncte maxime per tranzacție | Nelimitat |

### Setări Bonus

| Setare | Descriere | Implicit |
|--------|-----------|----------|
| Bonus Înregistrare | Puncte pentru înregistrare nouă | 50 |
| Bonus Ziua de Naștere | Puncte de ziua clientului | 100 |
| Bonus Referință (Cel ce Recomandă) | Puncte pentru referință reușită | 200 |
| Bonus Referință (Client Recomandat) | Puncte pentru noul client recomandat | 100 |

### Setări Expirare

| Setare | Descriere | Implicit |
|--------|-----------|----------|
| Zile Expirare Puncte | Zile până la expirarea punctelor | Niciodată |
| Expirare la Inactivitate | Expiră punctele dacă contul e inactiv | Nu |
| Zile Inactivitate | Zile de inactivitate înainte de expirare | 365 |

### Setări Afișare

| Setare | Descriere | Implicit |
|--------|-----------|----------|
| Nume Puncte (Plural) | Numele afișat pentru puncte | puncte |
| Nume Puncte (Singular) | Forma de singular | punct |
| Icon | Iconul afișat | star |

---

## Exemplu Niveluri Clienți

| Nivel | Puncte Min | Multiplicator | Beneficii |
|-------|------------|---------------|-----------|
| Bronze | 0 | 1.0x | Câștig standard |
| Silver | 1,000 | 1.25x | 25% puncte bonus |
| Gold | 5,000 | 1.5x | 50% puncte bonus |
| Platinum | 15,000 | 2.0x | Puncte duble |

---

## Cazuri de Utilizare

### Locații Evenimente
Răsplătește participanții frecvenți și încurajează-i să aducă prieteni prin programul de referințe.

### Festivaluri
Construiește loialitate de la an la an cu puncte care se transferă între edițiile anuale.

### Teatre și Cinematografe
Încurajează vizitele frecvente cu puncte la fiecare bilet și achiziție de la bar.

### Organizatori de Conferințe
Răsplătește înregistrarea timpurie și referințele pentru a crește participarea.

---

## Beneficii pentru Afacere

- **Retenție Crescută**: Clienții loiali revin mai frecvent
- **Valori Mai Mari ale Comenzilor**: Clienții cheltuiesc mai mult pentru a câștiga sau răscumpăra puncte
- **Recomandări**: Programul de referințe stimulează creșterea organică
- **Date Despre Clienți**: Înțelegere mai bună a tiparelor de achiziție
- **Avantaj Competitiv**: Ieși în evidență cu un program modern de loialitate

---

## Cum Să Începi

1. **Activează Microserviciul**: Activează Gamificarea în setările tenant-ului
2. **Configurează Valorile Punctelor**: Setează rata de câștig și valoarea punctului
3. **Setează Regulile de Răscumpărare**: Definește limitele minime și maxime de răscumpărare
4. **Configurează Bonusurile**: Setează bonusurile de înscriere, ziua de naștere și referințe
5. **Creează Niveluri** (Opțional): Definește niveluri de loialitate cu multiplicatori
6. **Lansează**: Începe să-ți răsplătești clienții!

---

## Mentenanță Automată

Sistemul gestionează automat:
- Procesarea zilnică a expirării punctelor
- Distribuirea bonusurilor de ziua de naștere
- Calculele nivelurilor
- Urmărirea și atribuirea referințelor

Rulează mentenanța manual:
```bash
php artisan gamification:maintenance
```

---

## Suport

Pentru asistență cu microserviciul Gamificare, contactează administratorul platformei sau consultă documentația tehnică.

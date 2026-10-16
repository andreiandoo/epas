# Capitolul 28 — Comutarea între organizatori

Dacă lucrezi pentru **mai multe brand-uri / organizatori** (ex. tu ești manager la 3 firme de evenimente diferite), aplicația îți permite să comuti între ele fără să faci logout+login.

Timp de citit: **~2 minute**.

---

## 1. Când apare selectorul

**Doar dacă emailul tău e asociat la 2+ organizatori** din același marketplace (AmBilet, Bilete Online, Tics).

- **1 organizator** → app-ul te loghează direct, fără selector
- **2+ organizatori** → apare selectorul după login sau accesibil din header

---

## 2. Cum arată în header

**Sub logo**, lângă indicatorul Live/Offline, ai un **buton cu numele organizatorului curent** + săgeată jos:

<!-- SCREENSHOT: header cu buton nume organizator + săgeată jos -->
![Selector organizator](./screenshots/28-header.png)

**Tap** → se deschide **modalul Organizer Switcher**.

---

## 3. Modalul Switcher

O listă cu toți organizatorii asociați la contul tău:

- **Nume organizator**
- **Logo** (dacă e setat)
- **Rolul tău acolo**: Proprietar / Admin / Manager / Staff
- **Badge cu status**: Activ / Suspendat
- **Bulinuță verde** pentru organizatorul curent

<!-- SCREENSHOT: modal Switcher cu 3 organizatori listați, 1 marcat curent -->
![Modal Organizator Switcher](./screenshots/28-modal.png)

**Tap pe unul** → aplicația comută instant:
- Toate datele se refresh-uiesc pentru noul organizator
- Evenimentele afișate se schimbă
- Rolul și permisiunile se ajustează
- Contactele urgențe, tema, setările **rămân aceleași** (sunt per device)

---

## 4. Ce se schimbă la comutare

Se **schimbă**:
- Lista evenimentelor
- Cifrele Panoului
- Rapoartele
- Personalul + porțile
- Notificările primite (fiecare organizator are propriile)

**Rămân** (per device):
- Setările tale (aspect, sunet, vibrație, auto-logout)
- Contactele urgențe (numerele tale de telefon)
- Preferințele scanner (auto-confirm setare)

---

## 5. Roluri diferite per organizator

Poți fi:
- **Admin** la Organizator A
- **Staff** la Organizator B
- **Manager** la Organizator C

Fiecare comutare ajustează permisiunile: la A vezi Rapoarte, la B nu vezi tab-ul Rapoarte, etc.

---

## 6. Parola e partajată

**Aceeași parolă pentru toți organizatorii** cu același email. Dacă schimbi parola din unul (Setări → Reset parolă în echipă), se schimbă automat la toți.

---

## 7. Case comune

**Colegă din firmă cu 3 branduri**:
- Organizator 1: Concerte Live SRL (rol Admin)
- Organizator 2: Comedy Pub Show (rol Manager)
- Organizator 3: Kids Fest (rol Staff)

Un login = acces la toți. Comută după evenimentul curent.

**Multi-marketplace** (ex. contul tău e la AmBilet + Bilete Online):
- Selectorul afișează separat, grupate per marketplace
- Fiecare marketplace = un ecosistem izolat

---

## 8. Comutare rapidă = restart soft

La schimbare, aplicația refresh-uiește multe date. Poate dura 1-2 secunde.

**Nu e nevoie să te loghezi din nou** — token-ul de autentificare e valid pentru toți organizatorii asociați.

---

## 9. Limitări

- **Necesită internet** pentru comutare (verifică tokenul + fetch new event data)
- **Nu poți vinde simultan pe 2 organizatori** — pentru fiecare vânzare e organizatorul curent
- **Notificările primite** sunt izolate per organizator — dacă ai urgență la Organizator A, comuți la B → nu mai vezi acea notificare în panou (dar rămâne salvată la A)

---

## 10. Probleme frecvente

**„Nu văd butonul de switcher"**
- Emailul tău e asociat la 1 singur organizator. Cere admin să te adauge la altul.
- Sau delog + login din nou

**„Am comutat, dar cifrele arată tot de la vechi"**
- Trage Panoul în jos (refresh forțat)
- Verifică că indicatorul din header afișează într-adevăr noul organizator

**„Vreau să comut fără să părăsesc un modal deschis"**
- Închide modalul întâi, apoi comută. Sau așteaptă finalul acțiunii.

---

## 11. Testează pe viu

Doar dacă ai cont la 2+ organizatori.

1. Tap pe butonul cu numele organizatorului (header, lângă Live)
2. Vezi lista completă
3. Tap pe alt organizator
4. Observă cum se schimbă:
   - Bara roșie eveniment (poate fi altul)
   - Cifrele Panoului
   - Numele organizatorului sub logo
5. Comută înapoi la primul

---

## Următorul capitol

Am ajuns la finalul manualului! 🎉

📚 [Înapoi la cuprins →](./00_cuprins.md)

Vrei să contribui cu o secțiune nouă / corecție / feedback? Scrie echipei AmBilet la [contact standard].

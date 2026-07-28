# Capitolul 24 — Setări scanner (sunet, vibrație, auto-confirm)

Personalizezi cum se comportă scanner-ul: sunete, vibrație, mod
auto-confirmare pentru bilete valide.

Timp de citit: **~2 minute**.

---

## 1. Cum ajungi

**Setări → Scanner**. 3 toggle-uri:

- **Vibrație**
- **Efecte Sonore**
- **Auto-confirmare Valide** (dacă evenimentul e azi/live — altfel
  automat OFF)

<!-- SCREENSHOT: Setări → Scanner cu 3 toggle-uri -->
![Setări Scanner](./screenshots/24-settings.png)

---

## 2. Vibrație

**ON** (implicit) — telefonul vibrează la fiecare scan:
- Scurt = valid
- Dublu = duplicat
- Triplu lung = invalid

**OFF** — fără vibrație. Util în ședințe / atmosferă liniștită.

---

## 3. Efecte Sonore

**ON** (implicit) — sunete la fiecare scan / notificare:
- „Ding" clar = valid
- „Warning" = duplicat
- „Error" grav = invalid
- Plus sunet la notificări noi

**OFF** — fără sunete. Util în zgomot mare (nu se aude oricum) sau în
locuri liniștite (biserică, teatru).

**Atenție**: opreșrea **sunet-ul de notificări** înseamnă că nu vei
auzi când primești o alertă de urgență de la coleg. Verifică des
panoul de notificări dacă ai sound OFF.

---

## 4. Auto-confirmare Valide

**ON** — biletele valide se scanează **fără card mare de confirmare**:
- Doar flash-ul verde full-screen
- Sunet + vibrație scurte
- Cardul de rezultat nu mai apare
- Aplicația e gata instant pentru următorul scan

**OFF** (implicit) — vezi mereu cardul de rezultat verde pentru fiecare
scan valid. Mai lent, dar sigur (poți verifica manual numele/tipul).

**Când folosești ON**:
- Eveniment cu ritm mare de scanare (>5/min)
- Ai încredere că biletele sunt corecte
- Vrei fluiditate maximă

**Când folosești OFF**:
- Prima dată la un eveniment nou (verifică ce se scanează)
- Rate mici, unde poți da atenție fiecărui client

---

## 5. Auto-confirmare dezactivată forțat pentru evenimente viitoare

**Regulă de siguranță**: dacă selectezi un eveniment **VIITOR** (nu azi,
nu live), aplicația **forțează OFF** auto-confirmarea, indiferent de
setare.

Motiv: nu vrei să lași pe cineva să treacă printr-un bilet valid pentru
un eveniment care abia peste 3 zile începe.

**La schimbarea în „azi" / „live"**, poți reactiva manual din Setări.

---

## 6. Prompt pe Panou pentru evenimente azi/live

Dacă evenimentul selectat e **azi sau live** și **Auto-confirmarea e
OFF**, Panoul afișează un **card verde** care sugerează activarea:

> **Pornești auto-validarea biletelor la intrare?**
> Biletele vândute la intrare se marchează automat ca scanate. Apasă
> pentru a activa din Setări.

<!-- SCREENSHOT: Panou cu card verde de sugestie auto-confirm -->
![Prompt auto-confirm](./screenshots/24-prompt.png)

**Tap** → te duce la Setări. **X-uri dreapta** dacă vrei să-l dismisezi
pentru sesiunea curentă.

---

## 7. Setarea persistă

Alegerile rămân salvate pe telefon. La orice deschidere, se reaplică
automat.

---

## 8. Interacțiune cu sunet notificări

Efectele sonore controlează **ambele**:
- Sunete la scan (ding/warning/error)
- Sunete la notificări noi

Deci OFF-ul unifică toate. Dacă vrei să auzi doar notificările dar nu
scan-ul (sau invers), momentan nu se poate — e un toggle unic.

---

## 9. Limitări

- **Nu poți schimba** sunetele individuale (fișiere .mp3 sunt fixe în
  app)
- **Nu poți customiza volumul** — folosește volumul telefonului
- **Vibrație pattern** nu se poate customiza

---

## 10. Probleme frecvente

**„Am setat sunet OFF, dar tot sună la scan"**
- Închide și redeschide tab-ul Scanare
- Verifică că nu ai activate „Sunete de sistem" din Android care
  suprapun (ex. click de tastatură)

**„Vibrația e prea slabă"**
- Vibrația e fixă. Depinde de motor-ul telefonului tău. Pe telefoane
  vechi e mai slabă.

**„Auto-confirmare e ON dar tot văd card la scan valid"**
- Verifică că evenimentul selectat e „azi" sau „live", nu viitor
- Reintră în Setări și reconfirmă (bug rar de sync stat)

---

## 11. Testează pe viu

1. **Setări → Scanner**
2. Toggle **Efecte Sonore** OFF → deschide Scanare, scanează → fără
   sunet
3. Toggle back ON → scanează → sunet OK
4. Toggle **Auto-confirmare** ON → scanează valid → doar flash, fără
   card
5. Toggle OFF → scanează → card verde apare 3 secunde

---

## Următorul capitol

📖 [Capitolul 25 — Modul offline manual →](./25_mod_offline.md)

📚 [Cuprins →](./00_cuprins.md)

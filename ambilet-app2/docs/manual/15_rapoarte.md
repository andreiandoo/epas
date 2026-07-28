# Capitolul 15 — Rapoarte (cifre, grafice, export CSV)

Tab-ul **Rapoarte** e locul unde vezi analiza detaliată a evenimentului
tău. Rata check-in, ora de vârf, performanța porților, venituri per tip
de bilet, distribuție orară. Plus export CSV pentru contabilitate.

**Vizibil doar pentru admin** (proprietar / admin organizator).

Timp de citit: **~5 minute**.

---

## 1. Cum ajungi la Rapoarte

Din meniul de jos, tab-ul **Rapoarte**. Dacă nu-l vezi, ești logat ca
staff scanner — rolul nu are acces. Roagă admin să-ți schimbe rolul
sau să-ți dea permisiuni ([cap. 16](./16_personal.md)).

---

## 2. Ce afișează (de sus în jos)

1. **Selector eveniment trecut** (opțional) — sar la un eveniment
   încheiat pentru raportul lui
2. **Rata Check-in** — procent + grafic sparkline
3. **Total Vândute** + **Ora de Vârf**
4. **Performanța Porților** — bare per poartă
5. **Detalii Venituri** — sume per tip bilet + bară
6. **Distribuție Orară** — chart cu bare pe ore
7. Buton **Exportă Raport (CSV)**

<!-- SCREENSHOT: ecran Rapoarte complet, scroll de sus până jos -->
![Ecran Rapoarte](./screenshots/15-rapoarte-full.png)

---

## 3. Rata Check-in + sparkline

Cardul mare de sus:

- **Cifra mare**: procent din bilete vândute care au intrat efectiv
  (ex. „78%")
- **Sparkline** dedesubt — o linie mică care arată evoluția
  check-in-urilor pe ore. Vezi „valurile" de sosire.

<!-- SCREENSHOT: card mare Rata Check-in cu procent + sparkline dedesubt -->
![Rata Check-in](./screenshots/15-checkin-rate.png)

**Interpretare**:
- **80%+**: eveniment bun — majoritatea au venit
- **60-80%**: normal — no-show tipic
- **<60%**: mulți bilete vândute dar puțini au venit (poate vreme rea,
  evenimente concurente, comunicare slabă)

---

## 4. Total Vândute + Ora de Vârf

Sub cardul Rata:

- **Total Vândute** — numărul absolut de bilete emise (validate + nu)
- **Ora de Vârf** — ora cu cele mai multe check-in-uri (ex. „19:00")

Ora de Vârf apare doar după ce ai scanuri — dacă evenimentul nu a
început, cardul arată `—`.

---

## 5. Performanța Porților

Dacă evenimentul are **mai multe porți configurate** (Entrance Main,
Entrance VIP, etc.), vezi câte scanări s-au făcut la fiecare:

<!-- SCREENSHOT: Performanța Porților cu 3 bare inegale -->
![Performanța Porților](./screenshots/15-gates.png)

Fiecare rând:
- **Numele porții**
- **Nr. scanări** dreapta
- **Bară proporțională** cu culoare distinctă

Util să vezi:
- Care poartă e supraîncărcată → mai mult staff acolo
- Care e goală → poate poți închide
- Distribuția pe VIP vs. General

**Notă**: dacă nu ai porți multiple, secțiunea afișează un singur rând
sau apare goală.

---

## 6. Detalii Venituri

Card cu **venituri per tip de bilet**:

- Fiecare rând: nume bilet (trunchiat la 70% lățime dacă e lung) +
  sumă în RON + bară proporțională cu culoarea tipului

<!-- SCREENSHOT: Detalii Venituri cu 4 tipuri de bilete + bare colorate -->
![Detalii Venituri](./screenshots/15-revenue.png)

**Ce vezi**:
- Care tip de bilet aduce cei mai mulți bani
- Diferența dintre General vs. VIP (VIP fiind adesea sume mari cu
  puține bucăți)

---

## 7. Distribuție Orară

Chart cu **bare verticale pe ore**:

- Fiecare bară = o oră (ex. 17:00, 18:00, 19:00)
- Înălțimea = numărul de check-in-uri în acea oră
- Culoarea variază ca palet

<!-- SCREENSHOT: chart Distribuție Orară cu 6 bare de înălțimi diferite -->
![Distribuție Orară](./screenshots/15-hourly.png)

**Interpretare**:
- Vârful clar înainte de start = clienți punctuali
- Vârf după ora oficială de start = clienți întârziați (obișnuit)
- Fără date = evenimentul n-a început încă sau n-are check-in-uri

**Fusul orar**: ora se afișează în **Europa/București**, chiar dacă
serverul stochează în UTC.

---

## 8. Selector eveniment trecut

Dacă vrei să vezi rapoarte pentru un eveniment **încheiat**:

**Sus pe ecran** apare bara **„Eveniment trecut"** — tap → deschide un
modal cu **listă evenimente trecute** + **bară de căutare**.

<!-- SCREENSHOT: modal Evenimente Trecute cu search + listă cu date -->
![Selector eveniment trecut](./screenshots/15-past-selector.png)

**Cum funcționează**:
- Sortare: cele mai recente evenimente sus
- Fiecare rând: nume + venue + dată (dd.mm.yyyy)
- **Search**: tastează nume / venue / oraș (aceleași câmpuri ca la
  selectorul principal)

Selectezi unul → toate cifrele din Rapoarte se schimbă pe el.

---

## 9. Exportă Raport (CSV)

Jos de tot, buton `Exportă Raport (CSV)` (violet, cu iconă de download).

**Cum funcționează**:
1. Tap pe buton — apare spinner „Se generează..."
2. Aplicația descarcă în background CSV-ul cu toți participanții
3. Se deschide **share sheet-ul telefonului** — poți trimite pe:
   - Email (Gmail, Outlook)
   - WhatsApp
   - Drive / OneDrive
   - Salvezi în Fișiere / Files

<!-- SCREENSHOT: buton Exportă Raport + share sheet Android cu 3 opțiuni -->
![Export CSV](./screenshots/15-export.png)

**Conținut CSV**:

| Coloană | Ce e |
|---|---|
| Data cumpărare | Când s-a vândut biletul |
| Cod bilet | Codul unic |
| Tip bilet | Ex. „General" |
| Secțiune / Rând / Loc | Dacă e cu locuri |
| Net bilet | Preț fără comision |
| Nume client | |
| Telefon client | |
| Nr. comandă | |
| Check-in | Da / Nu |
| Data check-in | Dacă a intrat |

**Fișierul se numește**: `raport-{nume-eveniment}-{YYYYMMDD}.csv`

**Format**: UTF-8 cu BOM (deschide corect în Excel/Numbers cu diacritice).

**Utilizări**:
- Contabilitate lunară
- Trimitere la managerul evenimentului
- Verificare aderență la buget
- Marketing follow-up (mail către participanți)

---

## 10. Auto-refresh

Rapoartele se **reîmprospătează** la fiecare 30 secunde când e deschis
tab-ul (identic cu Panoul). Pull-to-refresh pentru forțare manuală.

---

## 11. Limitări

- **Doar admin vede tab-ul** (proprietar sau admin de organizator)
- **Necesită internet** pentru export CSV și pentru date proaspete
- **Datele istorice** rămân disponibile după eveniment (se pot exporta
  și după)

---

## 12. Probleme frecvente

**„Nu văd tab-ul Rapoarte"**
- Rolul tău nu are permisiunea. Cere admin să-ți dea „reports" în
  permisiuni ([cap. 16](./16_personal.md))

**„Ora de Vârf arată `—` dar am scanuri"**
- Timezone: verifică că device-ul e pe timezone România. Serverul
  procesează pe București.
- Cache 30s — așteaptă sau refresh

**„Export CSV nu descarcă nimic"**
- Modulele native (`expo-file-system` + `expo-sharing`) nu sunt link-ate.
  Necesită rebuild APK.
- Verifică că ai internet
- Verifică că evenimentul are ce exporta (măcar 1 bilet vândut)

**„CSV se deschide cu caractere ciudate în Excel"**
- Format e UTF-8 cu BOM. În Excel: „Data" → „From Text/CSV" → alege
  „65001: Unicode (UTF-8)". Numbers pe Mac deschide corect direct.

**„Rata Check-in > 100%"**
- Bug — raportează admin. Poate fi bilete emise dublu sau numărătoare
  greșită.

---

## 13. Testează pe viu

1. [**Deschide Rapoarte →**](app://navigate/Reports)
2. Uită-te la Rata Check-in — verifică că ai un procent
3. Uită-te la Distribuție Orară — dacă evenimentul e viitor, e goală
4. Apasă `Exportă Raport (CSV)`
5. Alege „Email" din share sheet → trimite spre tine ca test
6. Deschide CSV-ul pe computer — verifică coloanele
7. **Pentru evenimente trecute**: tap bara „Eveniment trecut" → alege
   unul din listă → toate cifrele se schimbă

---

## Următorul capitol

📖 [Capitolul 16 — Personalul →](./16_personal.md)

📚 [Cuprins →](./00_cuprins.md)

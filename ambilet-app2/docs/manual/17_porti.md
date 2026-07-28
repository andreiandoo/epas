# Capitolul 17 — Porțile de acces

**Porțile** sunt intrările fizice ale venue-ului tău — Entrance Main,
VIP, POS, Ieșire. Le configurezi în app și asignezi personalul pentru
raportare separată per poartă.

**Vizibil doar pentru admin** cu permisiuni.

Timp de citit: **~4 minute**.

---

## 1. Cum ajungi la Porți

**Setări → Comenzi Admin → Administrare Porți** — apasă rândul.

Sau din Panou (dacă apare): **Acțiuni Rapide → Poartă** (nu întotdeauna
vizibilă).

Se deschide **modalul Porți** al venue-ului evenimentului curent.

<!-- SCREENSHOT: modal Administrare Porți cu lista porților + form adăugare -->
![Modal Porți](./screenshots/17-modal.png)

---

## 2. Ce vezi

**Sus**: informații despre venue (nume + adresă + oraș).

**Sub**: lista porților existente. Fiecare **card poartă**:
- Iconă mare cu culoarea tipului (Intrare/VIP/POS/Ieșire)
- **Numele porții** (ex. „Entrance Main")
- **Locația** (ex. „Poarta 1 Stânga")
- **Badge tip** (Intrare / VIP / POS / Ieșire)
- Switch pentru **Activă / Inactivă**
- Butoane: `Asignează-mă`, `Șterge`

**Jos**: formular pentru **adăugare poartă nouă**.

---

## 3. Tipuri de porți

| Tip | Când folosești | Culoare |
|---|---|---|
| **Intrare** | Poarta principală, unde intră toată lumea | Verde |
| **VIP** | Intrare separată pentru bilete VIP | Amber |
| **POS** | Punct de vânzare bilete la ușă (fără scanare) | Cyan |
| **Ieșire** | Poartă de ieșire cu re-check dacă e nevoie | Roșu |

**Diferența**: în rapoarte vezi câte scanări s-au făcut per poartă
(cap. 15). VIP-ul poate avea propria bară.

---

## 4. Adaugă o poartă nouă

Formularul jos:
- **Nume** (obligatoriu, ex. „Entrance Main")
- **Tip**: chip picker (Intrare / VIP / POS / Ieșire)
- **Locație** (opțional, text liber — ex. „Colț sud")

Apasă `Adaugă` → poarta apare în listă instant, ca Activă.

<!-- SCREENSHOT: formular Adăugare Poartă cu tip Intrare selectat -->
![Adaugă poartă](./screenshots/17-add.png)

---

## 5. Editează o poartă (nou în v2.2)

**Tap pe numele porții** din card → se deschide **editorul inline**:

- **Nume** — schimbi textul
- **Tip** — chip picker cu cele 4 opțiuni
- **Locație** — text liber
- Butoane `Anulează` / `Salvează`

<!-- SCREENSHOT: card poartă în modul edit cu 3 câmpuri active -->
![Editare poartă](./screenshots/17-edit.png)

`Salvează` — modificările se aplică instant, poarta se refresh-uiește.
`Anulează` — rămâne cum era.

---

## 6. Toggle Activă / Inactivă

Fiecare card are un **switch verde/gri**:
- **Verde = Activă** — poartă în funcțiune, apare în rapoarte
- **Gri = Inactivă** — poartă închisă temporar, nu apare la personalul
  care asignează

Ex.: dacă închizi poarta VIP pentru 2 ore la mijlocul evenimentului,
faci switch off. Nu trebuie s-o ștergi.

---

## 7. Asignează-mă la poartă

Butonul `Asignează-mă` (violet, cu iconă persoană) leagă contul tău
curent de acea poartă. Util pentru:

- **Rapoarte per personal** — vezi câte a scanat fiecare, per poartă
- **Etichetare check-in-urilor** — la scan, tot ce scanezi tu se
  contabilizează la poarta ta

**Cum se desasignează**: `Asignează-mă` la o altă poartă → prima se
eliberează automat.

**Prin selector de personal**: admin poate asigna orice membru la orice
poartă din ecranul de personal ([cap. 16](./16_personal.md)).

---

## 8. Șterge o poartă

Butonul `Șterge` (roșu, iconă coș) → confirmare → poartă e eliminată.

**Atenție**: dacă poarta are check-in-uri istorice, ele **rămân** în
rapoarte (aparțin evenimentelor deja închise). Doar poarta ca entitate
dispare.

---

## 9. Counter în Setări

**Setări → Comenzi Admin → Administrare Porți** — rândul are un
**counter** cu numărul de porți.

Se actualizează automat la închidere modal.

---

## 10. Cum se leagă porțile de scanare

**Fiecare scan** se etichetează cu poarta operatorului. Dacă:

- Ești asignat la „Poarta VIP" → toate scanurile tale se contabilizează
  acolo
- Nu ești asignat la nimic → scanurile apar sub „Fără poartă" în rapoarte

**Cum vezi**: [cap. 15 — Rapoarte / Performanța Porților](./15_rapoarte.md).

---

## 11. Limitări

- Porțile sunt per **venue**, nu per eveniment — dacă venue-ul găzduiește
  10 evenimente, toate 10 folosesc aceleași porți
- Max ~50 porți per venue (limită practică, nu tehnică)
- **Necesită internet** pentru orice modificare

---

## 12. Probleme frecvente

**„Nu văd modalul Porți"**
- Setări → Comenzi Admin apare doar pentru admin/proprietar
- Verifică că evenimentul selectat are un venue asociat (unele
  evenimente vechi Ambilet n-au)

**„Am schimbat poarta unui casier, dar rapoartele arată tot vechea"**
- Cache 30s. Așteaptă sau refresh Rapoarte.
- Verifică că a fost salvat (badge cyan cu noua poartă în cardul lui)

**„Am șters o poartă și acum vreau înapoi"**
- Nu se poate recupera. Recreez-o cu același nume.
- Check-in-urile istorice rămân, dar apar sub „Fără poartă"

**„Butonul Asignează-mă nu răspunde"**
- Verifică internetul
- Verifică că ești logat ca user care poate opera (nu just admin
  ceremonial)

---

## 13. Testează pe viu

1. Deschide **Setări → Administrare Porți**
2. Adaugă o poartă test: nume „Test Gate", tip „Intrare", locație
   „Colț nord"
3. Verifică apare în listă cu switch verde
4. Tap pe nume → editează, schimbă tipul în „VIP"
5. Salvează, verifică că badge-ul devine amber
6. Toggle off → devine gri
7. Șterge cu iconă coș

---

## Următorul capitol

📖 [Capitolul 18 — Raportarea unei urgențe →](./18_raportare_urgente.md)

📚 [Cuprins →](./00_cuprins.md)

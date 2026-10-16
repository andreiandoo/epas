# Capitolul 14 — Ritmul vânzărilor în timp real

Vezi **cât de repede** vinzi bilete pe minut, cu direcție (crește sau scade). Card mic pe Panou care îți spune „stăm bine sau nu?".

Timp de citit: **~2 minute**.

---

## 1. Cum arată cardul

Pe **Panou**, între Capacitate și Online/ușă, un card mic:

```
RITM VÂNZARE                    ↑ +18%
4.2  bilete / min
ultimele 10 min · vs 3.6/min anterior
```

<!-- SCREENSHOT: card Ritm Vânzare cu cifră 4.2 + săgeată verde +18% -->
![Card Ritm Vânzare](./screenshots/14-rate-card.png)

**Elemente**:
- **Titlu** stânga: „RITM VÂNZARE"
- **Chip color** dreapta cu delta % și săgeată
- **Cifra mare** (bilete/min)
- **Sub-text**: „bilete / min"
- **Descriere**: ferestrele de comparație

---

## 2. Cum se calculează

- **Rata curentă**: bilete vândute în ultimele **10 minute**, împărțit la 10 → bilete/min
- **Rata anterioară**: bilete vândute în intervalul **10-20 min în urmă**, la fel împărțit la 10
- **Delta %**: (curent − anterior) / anterior × 100

Refresh la fiecare 30 secunde — nu trebuie să apeși nimic.

---

## 3. Interpretarea chip-ului color

| Chip | Culoare | Ce înseamnă |
|---|---|---|
| **↑ +X%** | Verde | Ritm crește — coadă mai lungă, cerere mare |
| **↓ −X%** | Roșu | Ritm scade — s-a golit coada, val trecut |
| **stabil** | Gri | Ritm constant, fără schimbare |
| **—** | Gri | Prea puține date pentru comparație (începutul evenimentului) |

---

## 4. Când e util

**Scenarii concrete**:

- **Deschide-poarta, ~30 min**: dacă rata crește constant, adaugă un casier / o cameră de scanare
- **Vârf trecut**: rata scade (verde → roșu), val principal a intrat, poți trage aer
- **Aproape sold-out**: rata rămâne foarte ridicată dar Disponibile scade — comunică cu marketing / oprește promo
- **Fereastră de vânzare târzie**: aproape de startul evenimentului, rata sare vizibil — anticipați valul „late arrivals"

---

## 5. Când NU apare cardul

Cardul e ascuns dacă:

- **Nu ai făcut încă nicio vânzare** pe device-ul curent — nu are ce arăta
- Ești în **modul Reports-Only** (eveniment trecut selectat)

După prima vânzare, cardul apare pentru sesiunea curentă.

---

## 6. Limitări importante

**Rata reflectă doar acest device**. Dacă mai vinzi pe alte casierii / alte telefoane, fiecare are propriul ritm.

**Nu e statistica evenimentului**. Pentru cifre globale (toate casieriile împreună), uită-te la [Rapoarte](./15_rapoarte.md).

**Ferestre fixe de 10 min**. Nu poți schimba la 5 min sau 30 min din setări (deocamdată).

---

## 7. Probleme frecvente

**„Cardul afișează 0.0/min dar tocmai am vândut ceva"**
- Așteaptă 30 secunde (interval refresh)
- Sau trage Panoul în jos (pull-to-refresh)

**„Delta arată «stabil» dar clar cifra a crescut"**
- Sub 5% diferență = considerat stabil (evită oscilații artificiale)
- Dacă crește peste 5%, apare săgeata verde

**„Cifra pare mică — vând mult, dar arată 1.5/min"**
- 1.5 bilete/min = 90 bilete/oră. Verifică dacă îți dorești mai mult.
- Rata e per device — dacă ai 3 casierii, total real e ~4.5/min

---

## 8. Testează pe viu

Doar dacă ai vânzări în ultimele 20 min.

1. [**Deschide Panoul →**](app://navigate/Dashboard)
2. Verifică cardul „RITM VÂNZARE" — dacă nu apare, fă o vânzare mică
3. Uită-te la delta% — verde/roșu/stabil
4. Peste 30 secunde, refresh automat schimbă cifrele
5. Vinde mai multe bilete rapid → rata ar trebui să crească

---

## Următorul capitol

📖 [Capitolul 15 — Rapoarte →](./15_rapoarte.md)

📚 [Cuprins →](./00_cuprins.md)

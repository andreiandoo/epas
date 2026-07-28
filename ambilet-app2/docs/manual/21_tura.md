# Capitolul 21 — Tura de lucru

**Tura** e sesiunea ta de muncă la eveniment. O deschizi la început, o
pauzezi la nevoie, o închizi la final cu sumar. Toate acțiunile tale
(vânzări, scanări) sunt legate de tura activă.

Timp de citit: **~3 minute**.

---

## 1. Cum arată bara turei

**Când tura e pornită**, sus, sub header, apare o **bară roșie** cu:

- **Cronometru** (ex. `00:34:12`) — timpul trecut de la deschidere
- Buton **`Pauză`** — oprește temporar tura
- Buton **⚠️** — raport urgență ([cap. 18](./18_raportare_urgente.md))

<!-- SCREENSHOT: bara turei cu cronometru + Pauză + ⚠️ -->
![Bara turei](./screenshots/21-shift-bar.png)

**Fără tură activă**, bara nu apare.

---

## 2. Deschide tura

**Auto la prima acțiune** — la prima scanare sau vânzare, tura se
deschide automat, fără să apeși ceva.

**Explicit** — dacă vrei să pornești tura fără să faci încă nicio
acțiune:
- Din **Panou → Acțiuni Rapide** poți uneori vedea buton `Deschide
  tură` (dependent de rol)
- Sau automat la prima activitate

Cronometrul începe să curgă instant.

---

## 3. Ce se contabilizează în tura ta

Toate acțiunile făcute pe **acest device** cu **contul tău** logat:

- **Vânzări** — număr + total încasat (cash + card separat)
- **Scanări** — număr de check-in-uri
- **Total încasări** — suma pe metode
- **Durata** — timpul total muncă

Sunt vizibile:
- În **Panou** (dacă ești scanner) — statistici personale ale turei
- În **Rapoarte** (cap. 15) — sub filtru „per personal"

---

## 4. Pauzează tura

**Tap pe `Pauză`** în bara turei:

- Cronometrul se **oprește** (nu curge timpul cât ești în pauză)
- Camera de scanare se dezactivează
- Butonul devine `Reia`
- Bara se colorează diferit (mai discret)

**Util pentru**: masă, apă, coadă de la toaletă, discuții mai lungi
cu clienți fără scanare.

**Reia**: tap pe `Reia` → cronometrul continuă de unde a rămas,
scanarea reactivă.

---

## 5. Închide tura

**De la Panou**, jos, buton mare roșu `Închide tura` (dacă e activă).

**Ce se întâmplă**:
1. Confirmare — „Sigur închizi tura?"
2. **Sumar tură**: total scanări + vânzări cash + vânzări card + durata
3. Confirmare finală → tura se închide
4. Cronometrul dispare
5. Alert: „Tura a fost închisă cu succes"

<!-- SCREENSHOT: modal Sumar Tură cu cifre cash + card + scanări + durată -->
![Sumar tură](./screenshots/21-summary.png)

**Sfat**: fă screenshot la sumar înainte să apeși OK. Rămâne dovadă
pentru contabilitate / raport la superior.

---

## 6. Logout & tură

Butonul `Logout` (Setări, jos, roșu) are text:
- **„Închide Tura & Deconectare"** — dacă ai tura activă
- **„Deconectare"** — dacă n-ai tură

**Închide Tura & Deconectare**: face automat pașii **închidere tură +
logout**. Convenabil la finalul zilei.

---

## 7. Tura și auto-logout

Auto-logout după inactivitate ([cap. 23](./23_securitate.md)) **NU se
declanșează** în timpul turei active dacă:
- Ai un scan/vânzare în ultimele minute
- Ai un modal de plată deschis
- Ai un raport urgență în progres

Deci nu-ți întrerupe activitatea în mijlocul evenimentului. Doar dacă
lași telefonul pe masă timp de X minute cu nimic în lucru.

---

## 8. Multi-device, aceeași tură?

**Nu**. Fiecare device deschide **propria tură**. Dacă ai 3 casierii pe
3 telefoane cu 3 conturi, sunt 3 ture separate cu 3 sumare separate.

**Same login pe 2 devices** e problematic (nu recomandat). Fiecare
tură pe device se închide independent.

---

## 9. Rezumat vs. rapoarte

**Sumarul turei** e per operator, per device. Vezi doar ce ai făcut tu.

**Rapoartele** ([cap. 15](./15_rapoarte.md)) sunt globale — totalurile
evenimentului (toți casierii).

Ambele coexistă:
- Casierul închide tura → primește sumar personal
- Admin verifică rapoartele → vede cifre agregate

---

## 10. Limitări

- **Nu poți vedea sumar retroactive** — odată închisă, tura dispare
  din interfața ta. Cifrele rămân în rapoarte globale, dar nu poți
  reafișa modalul de sumar.
- **Fără edit după închidere** — greșeli găsite ulterior trebuie
  corectate din admin-ul web

---

## 11. Probleme frecvente

**„Cronometrul e la 5:32:11 dar eu am muncit doar 3 ore"**
- Ai uitat să pauzezi la masă? Neînchisă e neînchisă.
- Închide și redeschide-o dimineața următoare.

**„Am închis din greșeală și pierd cifrele"**
- Sumar-ul apare doar 1 dată. Fă screenshot data viitoare.
- Cifrele rămân în rapoarte, dar per-total, nu per-tura ta.

**„Butonul Închide tura nu apare"**
- Tura nu e activă. Fă prima acțiune să o pornești.

**„Vreau să transfer tura la alt casier"**
- Nu se poate. Închide tura ta → colegul se loghează cu contul lui →
  își deschide propria tură.

---

## 12. Testează pe viu

1. Deschide app-ul, mergi pe Panou
2. Fă o **acțiune** (poate un scan test sau vânzare test) → tura
   pornește automat
3. Verifică bara roșie sus cu cronometrul
4. Apasă `Pauză` → cronometrul se oprește
5. Apasă `Reia` → continuă
6. Din Panou jos, apasă `Închide tura`
7. Vezi Sumarul → confirmă → tura închisă

---

## Următorul capitol

📖 [Capitolul 22 — Aspect (temă) →](./22_aspect.md)

📚 [Cuprins →](./00_cuprins.md)

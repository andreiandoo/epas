# Capitolul 12 — Ce faci cu biletele problematice

Nu toate scanările merg smooth. Iată ce faci când vezi 🟡 duplicat, 🔴 invalid, sau alte situații neașteptate.

Timp de citit: **~4 minute**.

---

## 1. 🟢 ACCES APROBAT — dar totuși e ceva neobișnuit

Uneori clientul are un bilet valid, dar te uiți la scan și vezi:

- **Bilet cu loc** (Secțiune·Rând·Loc apar în card) dar clientul zice „nu, eu am bilet la altă secțiune"

Ce faci:
- Verifică pe **bilet fizic / email** ce loc are într-adevăr
- Uneori clientul a cumpărat 2 bilete și a încurcat scanurile
- Dacă crezi că e o fraudă (bilet vândut de cineva „la negru"), raportează la admin

- **Bilet extern** (badge violet „Bilet extern")

E un bilet cumpărat prin **alt sistem** (partener, bilet fizic vechi importat). Ok, lasă-l să intre.

---

## 2. 🟡 DEJA SCANAT — cazuri și soluții

### Caz 1: „Eu abia am ajuns, cum să fie deja scanat?"

**Verifică timestamp-ul** din card („Scanat: dd.mm.yy · HH:MM"):

- Dacă e **de acum 2 minute** — probabil ai scanat de 2 ori (repet accidental). Lasă-l să intre.
- Dacă e **de acum 2 ore** — cineva a intrat deja cu biletul lui. Cere să vadă biletul + actul de identitate.
- Dacă e **de la un alt eveniment** — imposibil (biletele sunt per eveniment); reraportează bug la admin.

### Caz 2: Persoana **a ieșit** și vrea să reintre

Depinde de politica evenimentului:

- **Reintrare permisă**: cere admin să reseteze scanarea. Se face din admin-ul web, tab „Bilete Eveniment" → detaliu bilet → „Anulează
  check-in".
- **Reintrare interzisă**: refuză politicos, arată politica scrisă.

### Caz 3: **Bilet de grup** (2+ persoane la un cod)

Unele evenimente vând bilete „familie" (2 adulți + 2 copii). Aplicația scanează întregul bilet o dată — a 2-a scanare arată „duplicat".

- Verifică pe bilet **câți e** pentru
- Numără persoanele efectiv
- Dacă corespund, lasă-i (poate cu **cod verificare** al biletului cerut adminului)

---

## 3. 🔴 BILET INVALID — cazuri

### „Bilet neinvitat la acest eveniment"

- Ai **selectat greșit evenimentul** din selectorul de sus?
- Biletul e într-adevăr pentru **alt eveniment** (client venit la concert greșit)

Ce faci: schimbă evenimentul din bara roșie ([cap. 3](./03_selectie_eveniment.md)) și rescanează.

### „Bilet anulat"

Admin sau clientul a anulat biletul înainte de eveniment. Refuză intrarea.

Verifică cu clientul dacă a cerut refund — dacă da, i s-au întors banii.

### „Bilet neplătit"

Cumpărătorul a lăsat comanda în așteptare fără să plătească. Biletul nu e activ.

- Verifică cu clientul dacă a plătit
- Dacă a plătit dar arată neplătit, contactează admin (poate fi eroare de sync)

### „Cod invalid / bilet inexistent"

- Codul a fost tastat greșit — încearcă din nou (0 vs O, 1 vs l)
- Biletul a fost cumpărat prin **alt sistem** care nu comunică cu AmBilet — nu poți face check-in aici

---

## 4. Situații ambigue

**„Am scanat de mai multe ori din greșeală, acum arată duplicat"**

Ok, așteaptă ~5 secunde. Ecranul se resetează automat. Prima intrare oricum a fost validă. Persoana **e înăuntru**.

**„Aplicația spune valid, dar clientul zice că biletul lui e altfel"**

- Poate a schimbat cineva ID-ul cu al lui? Verifică actul de identitate vs. numele de pe bilet.
- Poate biletul e cumpărat pe alt nume și dat cadou? Verifică motiv.

**„Bilete cumpărate cu cripto / cu voucher"**

Aplicația nu diferențiază — pentru ea toate biletele valide sunt la fel. Dacă evenimentul are politici speciale pentru anumite tipuri
(bilete complimentar, invitații), verifică cu adminul.

---

## 5. Când să chemi admin

Anumite situații nu le poți rezolva de la casă:

- Bilet valid dar aparent fraudat (poză de pe email vs. bilet clonat)
- Reset check-in cerut de client (pentru reintrare)
- Bilet neplătit care apare la câțiva clienți la rând (probabil bug sync)
- Cifre foarte mari de scanări invalide (probabil evenimentul greșit selectat pe toate device-urile)

**Cum**: butonul ⚠️ de urgență din bara turei ([cap. 18](./18_raportare_urgente.md))
sau contactul direct al admin-ului dacă e la eveniment.

---

## 6. Ce NU faci

- **Nu certă niciodată clientul** — cere politicos, verifică calmă, cere admin dacă e nevoie
- **Nu refuza automat** pe baza „aplicația zice invalid" — verifică cauza reală întâi
- **Nu insista să reintre** pe un „duplicat" fără a verifica actul
- **Nu accepta plată directă la casă pentru reintrare** — merge doar prin flow-ul normal (Vânzare → bilet nou)

---

## 7. Cum să te ferești de probleme comune

**Verifică evenimentul selectat DINAINTE** de a începe scanarea — principala cauză de „invalid la masă" e că sunt pe alt eveniment în app.

**Test înainte de deschidere** — scanează un bilet cunoscut ca valid înainte de sosirea clienților, verifică că totul merge.

**Rezervă baterie** — telefonul la 20% baterie e o rețetă pentru dezastru.

**Rezervă net** — dacă evenimentul e în cort, verifică semnalul înainte și **activează modul offline** din Setări dacă e slab.

---

## 8. Statistici — cum interpretezi cifre bizare

**„Rata scanare 100% și eu nu am scanat nimic"**
- Alt casier la altă intrare a scanat. Statisticile sunt cumulative per eveniment.

**„Rata scanare peste 100%"**
- Rar, dar posibil dacă au fost bilete duplicate emise sau conflict de numărătoare. Raportează admin.

**„Statistica arată X scanați, dar eu văd Y în listă"**
- Cache — trage Panoul în jos (refresh)
- Sau lista arată doar scanările tale locale, statistica e globală

---

## 9. Testează pe viu

1. [**Deschide Scanare →**](app://navigate/CheckIn)
2. Scanează un bilet valid → 🟢
3. **Rescanează IMEDIAT același bilet** → 🟡 DEJA SCANAT
4. Uită-te la timestamp — vezi diferența de secunde
5. Tastează un cod fictiv în `Cod Manual` (ex. `ZZZZ999`) → 🔴 INVALID

---

## Următorul capitol

📖 [Capitolul 13 — Panoul de control →](./13_panou_control.md)

📚 [Cuprins →](./00_cuprins.md)

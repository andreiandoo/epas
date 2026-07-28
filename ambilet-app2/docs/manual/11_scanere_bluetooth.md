# Capitolul 11 — Scanere Bluetooth externe

Dacă ai un **scaner de coduri hardware** (Zebra, Symbol, Honeywell,
sau orice scaner 2D generic), aplicația îl folosește direct. Mai rapid,
mai comod, mai bine în lumină slabă.

Timp de citit: **~3 minute**.

---

## 1. De ce ai vrea un scaner extern

Camera telefonului merge OK, dar are limitări:
- **Lent** pentru cifre mari (>5 scanări/minut)
- **Slab în lumină** (concert seara, cort închis)
- **Distanță mică** (~20cm)
- Se **golește bateria** repede

Un scaner Bluetooth:
- **Instant** — trigger apăsat, cod citit sub 100ms
- **Lumină proprie** — laser sau LED de decodare
- **Distanță mare** — chiar și 1m pentru unele modele
- **Bateria proprie** — nu îți consumă bateria telefonului

---

## 2. Ce scanere merg

**Orice** scaner Bluetooth care se conectează la telefon ca **tastatură
(HID keyboard)**:

- Zebra CS4070, CS6080
- Symbol / Motorola CS3070, LI4278
- Honeywell 8680i, Voyager 1200g Bluetooth
- Datalogic Gryphon GBT4500
- Scanere 2D generice ieftine (~150-300 lei pe emag)

**NU merg** scanerele care necesită SDK proprietar sau app-uri
dedicate — doar cele de tip HID.

**Cum verifici**: după împerechere, dacă poți deschide o notă text și
scaner-ul introduce cod QR ca text, e HID → merge cu AmBilet.

---

## 3. Împerechere scaner

Pe Android:
1. **Setări → Bluetooth**
2. Activează Bluetooth
3. **Pornește scanerul** în mod pairing (de obicei ține apăsat trigger
   sau un buton dedicat)
4. Alege scanerul din lista de dispozitive
5. Confirmă cu PIN dacă cere (`0000` sau `1234`)
6. Scanerul e conectat

**Verifică**: deschide orice câmp de text (ex. o notă), scanează un cod
QR — dacă apare textul codului în câmp, împerecherea a reușit.

<!-- SCREENSHOT: Setări Bluetooth Android cu scaner conectat -->
![Bluetooth Android cu scaner](./screenshots/11-bluetooth-paired.png)

---

## 4. Cum funcționează în aplicație

**Nu trebuie să activezi nimic în aplicație**. Aplicația detectează
automat că vin keystrokes rapide (< 35ms între taste) urmate de Enter
și le tratează ca **scanare**.

**Fluxul**:
1. Ești în tab-ul **Scanare** (sau **Vânzare**, pentru scanare bilete
   deja vândute)
2. Ține telefonul unde ai loc
3. **Apasă trigger-ul scanerului** pointat spre codul biletului
4. Codul se transmite prin BT ca și cum ai tasta rapid
5. Aplicația îl recunoaște și face check-in-ul instant

**Camera telefonului nu e folosită** — poți lăsa telefonul cu ecranul
sus, în doc, sau atașat la casierie. Cadrul de scanner arată gol.

---

## 5. Detecție „scanner vs. tastare umană"

Aplicația e destul de deșteaptă:
- Keystrokes cu **>35ms între ele** = tastare umană (ignoră ca scanare)
- Keystrokes cu **<35ms între ele + Enter la sfârșit** = scaner (procesează)

Deci **nu îți fură** input-ul când tastezi manual într-un câmp. Doar
când vine un burst rapid termina cu Enter, trigger scanare.

---

## 6. Când NU merge

Aplicația **suspendă** scanerul BT în anumite situații pentru a nu
interfera:

- **Softul keyboard e afișat** (tastezi într-un câmp) — scanerul e
  pauzat până închizi tastatura
- **Modul de scanare** e pauzat (ai apăsat `Pauzează`)
- **Ești pe un tab care nu e Scanare / Vânzare** (nu are sens)

---

## 7. Sfaturi din teren

**Sfat 1**: pune telefonul într-un doc / stand fix pe casierie.
Casierul ține scanerul în mână, poate scana mai rapid.

**Sfat 2**: ai grijă la scanere fără **cablu USB de rezervă** — dacă
se descarcă bateria scanerului mid-eveniment, ești blocat.

**Sfat 3**: **testează înainte de eveniment** — împerecherea BT poate
avea capricii pe unele Android-uri. Verifică cu 24h înainte.

**Sfat 4**: dacă ai **multe telefoane** cu scanere, împerecherea 1-la-1
funcționează. NU împerechezi un scaner cu 2 telefoane simultan.

---

## 8. Coduri suportate

Scanerul citește tipul de cod (QR, barcode, etc.), dar aplicația
tratează întotdeauna rezultatul ca text simplu. Deci merge cu:

- **QR code** (2D)
- **Code 128** (barcode)
- **Code 39** (barcode)
- **EAN 13 / EAN 8** (comerț)
- **Data Matrix** (2D)
- **PDF417** (2D)

Practic tot ce recunoaște scanerul, aplicația poate procesa.

---

## 9. Probleme frecvente

**„Scanerul e conectat pe Bluetooth, dar aplicația nu răspunde"**
- Verifică cu o notă text — dacă scanerul introduce text acolo, e HID
  ok. Dacă nu, împerecherea a picat, refă-o.
- Închide și redeschide app-ul complet
- Asigură-te că nu ești pe un câmp text vizibil (tastatura suspendă
  scanerul)

**„Scanerul dă text corect în notă, dar în AmBilet apare Invalid"**
- Verifică că **evenimentul selectat** e cel corect
- Verifică că biletul e într-adevăr valid pentru acest eveniment
- Copiază codul din notă și tastează manual în modalul Cod Manual —
  dacă și așa apare invalid, e problema cu biletul, nu cu scanerul

**„Scanează dar face check-in-ul de mai multe ori"**
- Trigger-ul apăsat prea lung? Unele scanere trimit codul de mai multe
  ori dacă ții trigger apăsat. Apasă scurt.
- Verifică setările scanerului (documentație producător) pentru
  „single-shot mode"

**„Scanează dar cade mereu semnalul BT"**
- Bateria scanerului e slabă — schimbă
- Distanța prea mare între scaner și telefon — apropie-le
- Interferență WiFi 2.4GHz — mută-te sau schimbă canalul router-ului

---

## 10. Testează pe viu

Dacă ai un scaner împerecheat:

1. Verifică că e conectat prin **Setări Android → Bluetooth**
2. [**Deschide Scanare →**](app://navigate/CheckIn)
3. Fără să tastezi în app, apasă trigger-ul scanerului pe un cod bilet
4. Vezi rezultatul instant (verde / portocaliu / roșu)
5. Scanează mai multe la rând — remarcă viteza (mult mai rapidă decât
   camera)

---

## Următorul capitol

📖 [Capitolul 12 — Ce faci cu biletele problematice →](./12_probleme_scanare.md)

📚 [Cuprins →](./00_cuprins.md)

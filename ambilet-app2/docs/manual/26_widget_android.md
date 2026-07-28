# Capitolul 26 — Widget pe ecranul principal Android

Un mini-tile pe home screen-ul telefonului care arată **cifrele
evenimentului activ** — fără să deschizi aplicația.

**Doar Android**. iOS nu are (deocamdată).

Timp de citit: **~2 minute**.

---

## 1. Ce e widget-ul

Un dreptunghi 4×2 celule pe home screen cu:

- **Numele evenimentului** activ (sus)
- **Cifra mare**: bilete vândute azi
- **Etichetă**: „bilete vândute azi"
- **Timestamp update**: „actualizat acum 2 min"

<!-- SCREENSHOT: widget AmBilet pe home screen cu numere -->
![Widget Android](./screenshots/26-widget.png)

**Design**: gradient roșu (identitate AmBilet), colțuri rotunjite.

---

## 2. Cum îl instalezi

**Pe home screen Android**:

1. **Ține apăsat** pe un spațiu gol
2. Din meniul de jos, alege **Widgets** (Widget-uri)
3. Caută „**AmBilet Scan NEXT**"
4. Trage widget-ul unde vrei
5. Îl poți redimensiona (2×2, 4×2, sau mai mare)

<!-- SCREENSHOT: meniul Android Widgets cu AmBilet Scan NEXT evidențiat -->
![Instalare widget](./screenshots/26-install.png)

---

## 3. Ce face tap pe widget

**Un tap** → deschide aplicația AmBilet. La această deschidere te duce
direct pe Panou (nu pe login, dacă ești deja logat).

Deep-link către evenimentul specific = feature viitor.

---

## 4. Când se actualizează

**La fiecare 30 de minute** — Android impune un minim (nu putem update
la fiecare 5s ca să economisim baterie).

**Timp update afișat**: „acum 2 min" / „acum 15 min" / „acum 1 h" / etc.

**Refresh manual**: nu există momentan. Deschizi aplicația → se
sincronizează → widget preia la următoarea actualizare.

---

## 5. Ce vezi când nu ai date

**Prima instalare**, înainte ca app-ul să fi sincronizat vreodată:
- Text: „**Deschide app-ul**"
- Cifră: `—`
- Etichetă: „fără date"

**Fix**: deschide o dată AmBilet Scan NEXT + logat + selectează un
eveniment → widget se completează la următorul refresh.

---

## 6. Cifra „bilete vândute azi"

Cum se calculează:
- Suma vânzărilor de azi (din ora 00:00 până acum)
- Include atât vânzări online (website) cât și la ușă (POS)
- Se actualizează la fiecare sync al aplicației

Nu include:
- Vânzări anulate / refundate
- Bilete de test

---

## 7. Numele evenimentului afișat

Widget-ul arată numele **evenimentului curent selectat în aplicație**.

Dacă schimbi evenimentul selectat din app-ul deschis, widget-ul se
actualizează la următorul refresh (până la 30 min).

**Sfat**: menține pe device un singur eveniment activ pentru a vedea
cifre consistente pe widget.

---

## 8. Widget-ul necesită logat

Widget-ul e util doar dacă ai un cont activ în aplicație. Dacă te
deconectezi, va arăta ultima stare cachă (nu se resetează la logout).

**Sfat siguranță**: dacă vinzi telefonul, șterge datele aplicației
înainte pentru a curăța și widget-ul.

---

## 9. Multiple widget-uri?

Da, poți pune **mai multe instanțe** ale widget-ului. Toate arată
aceleași cifre (evenimentul curent). Nu poți configura widget-uri
separate pentru evenimente diferite.

---

## 10. Dezinstalare widget

**Ține apăsat** pe widget → alege „Elimină" / „Remove". Widget-ul
dispare, aplicația AmBilet rămâne.

---

## 11. Limitări

- **Doar Android** — iOS nu are (deocamdată)
- **Update la 30 min** — Android system limit, nu poate fi redus
- **Fără interactivitate directă** — doar tap → deschide app; nu poți
  scana din widget
- **Un eveniment** — nu poți afișa mai multe simultan

---

## 12. Probleme frecvente

**„Widget arată `—` deși am vândut bilete"**
- Nu s-a sincronizat încă. Așteaptă până la 30 min sau deschide
  aplicația forțat (declanșează sync)

**„Widget arată alt eveniment decât cel selectat curent"**
- Cache 30 min. Va prinde următorul refresh.

**„Nu găsesc AmBilet Scan NEXT în lista de widgets"**
- Verifică versiunea aplicației (min 2.2)
- Restart Android
- Reinstalează aplicația

**„Widget-ul e transparent / nu apare fundal roșu"**
- Bug rar — reinstalează widget-ul (long-press → elimină → reinstalează)

---

## 13. Testează pe viu

1. **Home screen Android** → long-press pe spațiu gol
2. Alege **Widgets** din meniu
3. Caută „AmBilet Scan NEXT"
4. Trage widget-ul pe home screen
5. Vezi cifrele (sau `—` la prima instalare)
6. **Deschide aplicația** o dată → logat + selectat eveniment
7. Așteaptă 30 min sau **repornește dispozitivul** → widget-ul se
   completează

---

## Următorul capitol

📖 [Capitolul 27 — Landscape mode pentru tablete →](./27_landscape_tablete.md)

📚 [Cuprins →](./00_cuprins.md)

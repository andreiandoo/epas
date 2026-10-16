# Capitolul 26 — Widget pe ecranul principal Android

Un mini-tile pe home screen-ul telefonului care arată **cifrele evenimentului activ** — fără să deschizi aplicația.

**Doar Android**. iOS nu are (deocamdată).

Timp de citit: **~2 minute**.

---

## 1. Ce e widget-ul

Un dreptunghi **5×2 celule** (compact, orizontal) pe home screen. **Se poate redimensiona mai mic** (până la ~3×2) prin ținere apăsată + drag de la muchii, dacă vrei să economisești spațiu.

**Conținut** (două coloane):
- **Rând 1 (header, subțire)**: 🎫 **AmBilet** · pill **LIVE / AZI / URMEAZĂ / TRECUT** · **Numele evenimentului** (trunchiat cu „…")
- **Coloana stânga (cifra mare)**: total bilete vândute · „din N" (N = capacitatea)
- **Coloana dreapta (3 rânduri dense)**:
  - **🎟 X · 🌐 Y · 🏛 Z** (scanate · online · la ușă)
  - **💰 X.XXX RON** (total încasări)
  - **N active · acum 2 min** (evenimente active + timestamp update)

<!-- SCREENSHOT: widget AmBilet pe home screen cu numere -->
![Widget Android](./screenshots/26-widget.png)

**Design**: gradient roșu bordo (identitate AmBilet), colțuri rotunjite, pill status subliniat. Font-uri mai mici decât în versiunea inițială pentru densitate mare a informației într-un tile compact.

---

## 2. Cum îl instalezi

**Pe home screen Android**:

1. **Ține apăsat** pe un spațiu gol
2. Din meniul de jos, alege **Widgets** (Widget-uri)
3. Caută „**AmBilet**"
4. Trage widget-ul unde vrei
5. Îl poți redimensiona (min ~3×2, target 5×2, sau mai mare)

<!-- SCREENSHOT: meniul Android Widgets cu AmBilet evidențiat -->
![Instalare widget](./screenshots/26-install.png)

---

## 3. Ce face tap pe widget

**Un tap** → deschide aplicația AmBilet. La această deschidere te duce direct pe Panou (nu pe login, dacă ești deja logat).

Deep-link către evenimentul specific = feature viitor.

---

## 4. Când se actualizează

**Două ritmuri**:

**A) Aplicația e deschisă** (chiar și în fundal): widget-ul se actualizează **în ~30 secunde** de la fiecare sync al aplicației. Când
tu ai app-ul pornit, cifrele din widget sunt aproape live — sub un minut de întârziere.

**B) Aplicația e complet închisă** (swipe out din task manager): widget merge pe **ritmul Android de 30 min** — limită de sistem impusă pentru a proteja bateria. Nu putem coborî sub 30 min din config.

**Timp update afișat**: „acum 2 min" / „acum 15 min" / „acum 1 h" / etc.

**Refresh manual**: nu există buton explicit. Cea mai bună metodă: deschide app-ul → forțează sync → widget preia în ~30s.

**Recomandare**: la evenimente active, lasă app-ul deschis pe device (nu-l închide) — widget-ul rămâne live.

---

## 5. Ce vezi când nu ai date

**Prima instalare**, înainte ca app-ul să fi sincronizat vreodată:
- Text: „**Deschide app-ul**"
- Cifră: `—`
- Etichetă: „fără date"

**Fix rapid**:
1. Deschide **AmBilet**
2. Loghează-te (dacă nu ești)
3. Selectează un eveniment activ (bara roșie sus)
4. Așteaptă ~30 secunde — widget-ul se completează

Dacă widget-ul rămâne „fără date" mai mult de 1 minut chiar cu app-ul deschis, e semn că sync-ul cu serverul n-a mers — verifică
conexiunea la internet.

---

## 6. Cifra „bilete vândute"

Widget-ul arată **total bilete vândute la evenimentul selectat** (cumulativ, nu doar azi).

Cum se calculează:
- Total_sold de la server (agregat pe eveniment)
- Include atât vânzări online (website) cât și la ușă (POS)
- Se actualizează la fiecare sync al aplicației

Nu include:
- Vânzări anulate / refundate
- Bilete de test

**Notă**: eticheta „bilete vândute" e generică — arată totalul, nu diferențiază între „azi" și „istoric". Pentru cifre specifice pe zi, 
deschide Rapoartele ([cap. 15](./15_rapoarte.md)).

---

## 7. Numele evenimentului afișat

Widget-ul arată numele **evenimentului curent selectat în aplicație**.

Dacă schimbi evenimentul selectat din app-ul deschis, widget-ul se actualizează la următorul refresh (până la 30 min).

**Sfat**: menține pe device un singur eveniment activ pentru a vedea cifre consistente pe widget.

---

## 8. Widget-ul necesită logat

Widget-ul e util doar dacă ai un cont activ în aplicație. Dacă te deconectezi, va arăta ultima stare cachă (nu se resetează la logout).

**Sfat siguranță**: dacă vinzi telefonul, șterge datele aplicației înainte pentru a curăța și widget-ul.

---

## 9. Multiple widget-uri?

Da, poți pune **mai multe instanțe** ale widget-ului. Toate arată aceleași cifre (evenimentul curent). Nu poți configura widget-uri 
separate pentru evenimente diferite.

---

## 10. Dezinstalare widget

**Ține apăsat** pe widget → alege „Elimină" / „Remove". Widget-ul dispare, aplicația AmBilet rămâne.

---

## 11. Limitări

- **Doar Android** — iOS nu are (deocamdată)
- **Update la 30 min** — Android system limit, nu poate fi redus
- **Fără interactivitate directă** — doar tap → deschide app; nu poți scana din widget
- **Un eveniment** — nu poți afișa mai multe simultan

---

## 12. Probleme frecvente

**„Widget arată `—` deși am vândut bilete"**
- Nu s-a sincronizat încă. Așteaptă până la 30 min sau deschide aplicația forțat (declanșează sync)

**„Widget arată alt eveniment decât cel selectat curent"**
- Cache 30 min. Va prinde următorul refresh.

**„Nu găsesc AmBilet în lista de widgets"**
- Verifică versiunea aplicației (min 2.2)
- Restart Android
- Reinstalează aplicația

**„Widget-ul e transparent / nu apare fundal roșu"**
- Bug rar — reinstalează widget-ul (long-press → elimină → reinstalează)

---

## 13. Testează pe viu

1. **Home screen Android** → long-press pe spațiu gol
2. Alege **Widgets** din meniu
3. Caută „AmBilet"
4. Trage widget-ul pe home screen
5. Vezi cifrele (sau `—` la prima instalare)
6. **Deschide aplicația** o dată → logat + selectat eveniment
7. Așteaptă 30 min sau **repornește dispozitivul** → widget-ul se completează

---

## Următorul capitol

📖 [Capitolul 27 — Landscape mode pentru tablete →](./27_landscape_tablete.md)

📚 [Cuprins →](./00_cuprins.md)

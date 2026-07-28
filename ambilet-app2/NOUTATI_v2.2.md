# AmBilet — ce e nou (v2.2)

**Versiune**: 2.2 (numită „NEXT")
**Ce trebuie să faceți**: instalați noul APK. Se instalează **alături** de aplicația veche (nu o înlocuiește) — le puteți folosi pe ambele în paralel până când decideți să treceți definitiv pe cea nouă.
**Iconă**: pe ecranul telefonului veți vedea „AmBilet" (versiunea veche rămâne „AmBilet Scan" — le puteți folosi în paralel).

---

## 1. Vindeți bilete și fără internet

**Ce face**: chiar și când semnalul cade complet la eveniment (cort, subsol, festival cu 5000 de oameni pe rețea), puteți încasa în continuare bilete. Vânzările se salvează pe telefon și se sincronizează automat când revine semnalul.

**Unde se folosește**: în ecranul **Vânzare**, exact ca înainte. Nu apare niciun buton nou — pur și simplu, dacă nu ai internet, aplicația își dă seama singură și salvează local.

**Cum se folosește**:
1. Adăugați bilete în coș ca de obicei
2. Apăsați **Numerar** sau **Card POS**
3. Confirmați încasarea
4. Vânzarea se salvează pe telefon
5. Când reveniți la o zonă cu semnal, aplicația trimite automat vânzarea la server

**De unde știți că e ceva „în așteptare"**: sus, lângă indicatorul Online/Offline, apare o pastilă galbenă „X în așteptare" cu numărul de vânzări/scanări nesincronizate. Un tap pe ea forțează sincronizarea manuală.

**Important**: plata cu **Card prin NFC (Stripe Tap)** nu merge fără internet — Stripe are nevoie de conexiune pentru autorizare. Doar Numerar și Card POS (unde confirmați manual) merg offline.

**Grija noastră**: sistemul are protecție împotriva dublei încasări — chiar dacă apăsați „Confirmă" de 5 ori, bilete pentru un singur client se generează o singură dată.

---

## 2. Vezi în timp real cât de repede se vând bilete

**Ce face**: un card nou pe Panou care arată câte bilete vinzi pe minut, în ultimele 10 minute, cu o săgeată care spune dacă ritmul crește sau scade față de intervalul precedent.

**Unde**: **Panou** (Dashboard), între cardul Capacitate și secțiunea Online vs. la ușă.

**Cum arată**: „**4.2 bilete/min ↑ +18%**" — înseamnă că vinzi acum cu 18% mai repede decât în ultimele 10-20 minute în urmă.

**La ce e util**:
- Vezi când s-a golit coada la casă (rata scade brusc → totul e ok)
- Detectezi când e nevoie să deschizi o casă suplimentară (rata crește constant, coadă mare afară)
- Îți dai seama când s-a terminat val-ul de sosire (peak-ul a trecut)

**Notă**: rata reflectă vânzările făcute pe **acest telefon**. Dacă mai vindeți pe alte device-uri, fiecare are propria rată.

---

## 3. Găsești participanții după nume, nu doar după cod

**Ce face**: dacă cineva vine la intrare fără codul biletului („l-am șters, l-am pierdut, nu-l am pe email"), acum poți căuta după numele lor în loc să te blochezi.

**Unde**: în ecranul **Scanare**, buton **Cod Manual** de jos.

**Cum se folosește**:
1. Deschide modalul „Check-in Manual"
2. **Sau** tastează codul dacă îl ai (funcționează ca înainte)
3. **Sau**, sub secțiunea „SAU", tastează numele — de exemplu „Ion Popescu"
4. După 2 caractere aplicația începe să caute automat (mic delay de 300ms)
5. Apar până la 8 rezultate cu numele, tipul biletului și codul
6. Tap pe cel corect → check-in instant
7. Dacă un participant e deja scanat, apare un badge verde „Scanat" și nu poți re-scana (previi duplicatele)

**La ce e util**:
- Client fără cod pe telefon (pierdut/șters)
- Persoane care au primit biletul de la cineva
- Copii cu bilet pe numele părintelui
- Verificarea rapidă dacă o persoană e într-adevăr pe listă

---

## 4. Scanere Bluetooth (hardware) funcționează instant

**Ce face**: dacă folosești un scaner Bluetooth (Zebra, Symbol, Honeywell, sau orice scaner 2D generic care se conectează la telefon prin BT), acum funcționează direct — nu mai trebuie să folosești camera telefonului.

**Cum se conectează scanerul**: prin setările Bluetooth ale telefonului (așa cum conectezi orice căști sau tastatură). Odată împerecheat, aplicația îl detectează automat.

**Cum funcționează**: pointezi scanerul spre codul biletului, apeși trigger-ul, și aplicația face check-in-ul instant. Este mai rapid decât camera și funcționează mai bine în lumină slabă.

**Unde e activ**: în ecranele **Scanare** și **Vânzare** (pentru a scana bilete deja existente).

**Sfaturi**:
- Ține telefonul în buzunar, scaner în mână — flow mult mai rapid
- Dacă tastezi ceva în app (câmp căutare, cod manual), scanerul e temporar dezactivat până termini
- Camera telefonului rămâne disponibilă ca backup

---

## 5. Raportare urgențe îmbogățită: foto + notă vocală + acces mereu disponibil

**Ce face**: când raportezi o problemă (Urgență Medicală, Problemă Tehnică, Incendiu etc.), acum poți atașa:
- **O poză** — de la scaunul spart, la aglomerația din față scenă, la o persoană rănită
- **O notă vocală** de max ~30 secunde — mai rapid decât să tastezi când e zgomot mare

**Unde (2 locuri)**:
- **NOU** — **panoul de notificări** (tap pe clopoțelul din header) → jos e o secțiune „Alertă în aplicație (către admini)" cu butoanele de atașament + grid de tipuri de urgențe. **Merge fără tură activă**. Recomandat — cel mai rapid.
- Bara de tură (⚠️ roșu lângă cronometru) — funcționează doar când ai tura pornită.

Ambele deschid același formular, cu aceleași opțiuni și același efect.

**Cum se folosește** (din Notificări):
1. Tap pe clopoțelul din header
2. Scroll jos, până la secțiunea **„Alertă în aplicație (către admini)"**
3. Opțional, apasă:
   - **📷 Foto** — deschide camera, faci poză, apare thumbnail. `×` pentru a șterge.
   - **🎤 Ține pentru notă** — ține apăsat cât înregistrezi (max 30s), dai drumul → nota se atașează
4. **Apoi** apeși pe tipul de urgență (Medical / Tehnic / Pază etc.)
5. Raportul se trimite cu atașamentele către toți administratorii / proprietarii evenimentului. Vezi flash verde de confirmare.

**La ce e util**: contextul vizual/audio ajută admin-ul care primește alerta să decidă mai rapid dacă e serios sau nu. „Aglomerație la poarta 2" vs. „Aglomerație la poarta 2 + poză cu 200 de oameni împinși în gard" — cu totul altceva.

**Cum apar la admin**: în panoul lor de notificări, notificarea arată **thumbnail-ul foto** (56×56 px) și un **buton „▶ Redă"** pentru nota vocală, direct în listă. Nu trebuie să dea click nicăieri altundeva.

**Fără atașamente**: dacă nu ai timp să atașezi nimic, raportul se trimite ca înainte — doar text.

---

## 6. Deconectare automată după inactivitate (siguranță)

**Ce face**: dacă lași telefonul pe masă și nu-l atingi 5 minute, aplicația te deconectează automat. Protecție în caz că cineva pune mâna pe telefon când tu nu ești atent.

**Unde se configurează**: **Setări → Securitate** — poți alege între:
- **Oprit** (fără auto-logout)
- **5 min** (default)
- **10 min** / **15 min** / **30 min**

**Cum se pauzează inteligent**: timer-ul NU se scurge în timpul:
- Confirmării unei plăți (nu te scoate mid-tranzacție)
- Raportării unei urgențe (nu te întrerupe)
- Modificării personalului sau porților
- Cât selectorul de evenimente e deschis

Cu alte cuvinte, doar dacă chiar nu atingi nimic timp de 5 minute + niciun proces critic nu e activ.

**La ce e util**:
- Ture cu mai mulți casieri care își împrumută dispozitive
- Telefoanele/tabletele care rămân la casă între ture
- Situații gen „mă duc după apă, ține tu 2 minute" — protecție automată

---

## 7. Funcționează pe tablete în format orizontal

**Ce face**: dacă folosești tabletă (Samsung Tab, Lenovo, orice Android), acum aplicația se rotește automat în landscape când întorci tableta. Utilul pe tablete unde textul mai mare și grid-ul dublu-coloană sunt confortabile.

**Unde**: funcționează pe orice ecran — Panou, Scanare, Vânzare, Rapoarte, Setări.

**Cum se activează**: automat, dacă întorci device-ul. Nu ai nimic de făcut manual. Poți continua să folosești vertical dacă preferi.

**Notă**: pe telefoanele normale (portrait natural), landscape e disponibil dar experiența e optimizată pentru vertical. Recomandat mai ales pe tablete 10"+.

---

## 8. Widget pe ecranul principal Android

**Ce face**: adaugi un mini-tile pe ecranul principal al telefonului care arată **evenimentul curent** + **câte bilete au fost vândute azi**, fără să deschizi aplicația.

**Cum se instalează**:
1. Ține apăsat pe un spațiu gol de pe ecranul principal Android
2. Selectează „Widgets" (Widget-uri)
3. Caută „AmBilet"
4. Trage widget-ul unde vrei (mărime 4×2)

**Ce afișează**:
- Numele evenimentului activ
- Numărul mare de bilete vândute (cumulativ pe eveniment, nu doar azi)
- Când s-au actualizat datele („acum 30 sec", „acum 2 min", „acum 15 min")

**Cum funcționează** (2 ritmuri):
- **App-ul deschis** (chiar și în fundal) — widget-ul se actualizează **în ~30 secunde** la fiecare sync al aplicației. Cifrele sunt aproape live.
- **App-ul complet închis** — 30 minute (limită Android de sistem, nu se poate coborî).

**Recomandare**: pentru evenimente active, lasă aplicația deschisă. Tap pe widget oricând → deschide aplicația direct.

**Bun pentru**: monitorizare rapidă între tururi, când vrei să vezi „stăm bine?" fără să deschizi tot dashboardul.

**Notă**: pe iPhone nu există (deocamdată). Doar Android.

---

## 9. Stabilitate mult mai bună (invizibil, dar contează)

**Ce s-a schimbat sub capotă**:
- Fiecare ecran are protecție individuală — dacă unul singur crapă, restul aplicației continuă să funcționeze. Vezi un mesaj „Ecran indisponibil. Reîncearcă" cu buton de recuperare.
- Erorile din aplicație sunt raportate automat către echipa tehnică AmBilet (fără date personale — doar contextul tehnic al erorii)
- Dacă vezi un ecran spart, un simplu tap pe „Reîncearcă" îl repornește fără să pierzi celelalte ecrane / date din memorie

**La ce e util**:
- Nu mai pierzi întreaga sesiune dacă un singur ecran are un bug
- Echipa AmBilet vede automat erorile fără să depindă de screenshot-uri
- Timp de reacție mai rapid la rezolvare de probleme

---

# 📋 Recapitulare rapidă — unde găsești ce

| Funcție nouă | Unde |
|---|---|
| Vânzare offline | ecran **Vânzare** (automat când nu ai net) |
| Ritm vânzare live | ecran **Panou**, între Capacitate și Online/ușă |
| Căutare după nume la check-in | ecran **Scanare**, buton „Cod Manual" |
| Scanere Bluetooth | oricare din **Scanare** / **Vânzare** |
| Foto/voce pe urgență | clopoțel Notificări → „Alertă în aplicație" (recomandat) SAU ⚠️ din bara turei |
| Auto-logout | **Setări → Securitate** |
| Rotire ecran (tablete) | oricare — se rotește automat |
| Widget Android | home screen Android → adaugă widget |

---

# 🆘 Dacă ceva nu merge

- **Nu se sincronizează vânzările offline**: verifică că ai internet + apasă pastila galbenă „X în așteptare" din header pentru forță manuală
- **Widget arată „—" sau „fără date"**: normal la prima instalare. Deschide app-ul → loghează-te → selectează un eveniment → așteaptă ~30 secunde → widget se completează
- **Nu văd butoanele Foto / Voce în modal-ul de urgență**: build vechi (< v2.2). Actualizează APK-ul.
- **Scanerul BT nu prinde**: verifică că e împerecheat prin Setări Bluetooth Android + că LED-ul lui e verde/albastru (nu roșu)
- **M-a deconectat mid-vânzare**: nu se poate, timer-ul se pauzează în timpul plăților. Dacă totuși s-a întâmplat, contactează suport AmBilet — e bug și trebuie remediat

Suport tehnic AmBilet: [contact standard]

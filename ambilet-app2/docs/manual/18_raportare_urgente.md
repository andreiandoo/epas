# Capitolul 18 — Raportarea unei urgențe (foto + notă vocală)

Ceva merge prost la eveniment? Alertezi instant proprietarul și
administratorii, cu poză și/sau notă vocală opțional atașate.

**Accesibil din 2 locuri**: panoul de notificări (mereu disponibil,
nu ai nevoie de tură activă) SAU bara turei (dacă lucrezi în tură).

Timp de citit: **~4 minute**.

---

## 1. Cum ajungi la formularul de raport

### Metoda 1 — din Notificări (recomandat, mereu disponibil)

**Tap pe clopoțelul din header** → se deschide panoul de notificări.
Jos, sub secțiunea de apeluri telefonice, e o zonă titrată
**„Alertă în aplicație (către admini)"** cu butoane de atașament +
grid de tipuri de urgențe.

<!-- SCREENSHOT: panoul de notificări cu Alertă în aplicație vizibilă jos -->
![Alertă în aplicație din Notificări](./screenshots/18-notif-panel.png)

**Avantaje**:
- Merge chiar dacă nu ai tură activă
- Un singur tap pe clopoțel → ești acolo
- Poți raporta imediat, indiferent de tab-ul unde te afli

### Metoda 2 — din bara turei

**Doar când tura ta e pornită**, sus, în bara turei, ai un buton **⚠️
roșu** lângă cronometrul turei:

<!-- SCREENSHOT: bara turei cu cronometru + Pauză + ⚠️ evidențiat -->
![Bara turei cu buton urgență](./screenshots/18-shift-bar.png)

**Tap** → se deschide sheet-ul „Raportează Problemă" de jos, cu același
conținut ca Metoda 1.

---

## 2. Ce vezi în sheet

Sus:
- Iconă roșie ⚠️ + titlu **„Raportează Problemă"**
- **X** dreapta pentru închide

**Nou (v2.2)** — Deasupra grid-ului de urgențe, **2 butoane opționale
de atașament**:

- **📷 Foto** — atașezi o poză de la locul incidentului
- **🎤 Ține pentru notă** — hold-to-record notă vocală (max ~30s)

<!-- SCREENSHOT: sheet cu 2 butoane atașament + grid de tipuri urgențe -->
![Sheet Raportează Problemă](./screenshots/18-sheet.png)

**Sub**: grid cu **8 tipuri de urgențe** codate pe severitate:

| Iconă | Tip | Severitate | Culoare |
|---|---|---|---|
| ❤️ | Urgență Medicală | High | Roșu |
| 🔥 | Incendiu / Evacuare | High | Roșu |
| 🛡️ | Problemă de Securitate | High | Roșu |
| 🔧 | Problemă Tehnică | Medium | Amber |
| 👥 | Control Mulțime | Medium | Amber |
| ⚙️ | Defecțiune Echipament | Medium | Amber |
| ☔ | Alertă Meteo | Low | Gri |
| ❓ | Altele | Low | Gri |

---

## 3. Atașament foto

**Tap `📷 Foto`**:
1. Aplicația cere permisiune la cameră (prima dată)
2. Se deschide camera nativă
3. Faci poza (calitate 70%, ~200-400 KB)
4. Confirmi cu `OK`
5. Thumbnail-ul apare în locul butonului, cu **`×`** pentru șterge

<!-- SCREENSHOT: thumbnail foto atașată + butonul × de eliminat -->
![Foto atașată](./screenshots/18-photo-attached.png)

**Când folosești**:
- Scaun spart → poză a scaunului
- Aglomerație periculoasă → poză a mulțimii
- Persoană rănită → poză a locului (nu a persoanei, respect intimitate)
- Ambianță tulbure (fum, apă, cabluri desfăcute)

---

## 4. Atașament notă vocală

**Ține apăsat `🎤 Ține pentru notă`** cât timp vorbești:
1. Butonul devine roșu cu iconă pulsantă
2. Vorbește (max ~30 secunde recomandat)
3. Dai drumul → nota se salvează automat

Butonul se transformă într-un chip mov: **`Notă 12s`** cu `×` de șters.

<!-- SCREENSHOT: chip Notă vocală 12s cu iconă mic microfon -->
![Notă vocală atașată](./screenshots/18-audio-attached.png)

**Când folosești**:
- Zgomot mare, nu poți tasta
- Situație complexă cerută de explicat rapid („e o bătaie între 5
  oameni la standul cu bere, avem nevoie de bodyguards")
- Muzica prea puternică, hands-free

**Format**: `.m4a`, ~120KB pentru 30s. Se trimite prin internet, deci
verifică semnalul.

---

## 5. Trimite raportul

După ce ai atașat (opțional) foto și/sau notă, **tap pe unul din cele
8 tipuri de urgență** din grid → raportul se trimite instant cu tot
ce ai atașat.

Vezi apoi:
- Ecran verde **„Raport trimis cu succes"**
- După 2 secunde, sheet-ul se închide automat

Poți continua să lucrezi.

---

## 6. Cine primește raportul

**Toți admin și proprietari** ai organizatorului evenimentului primesc
notificarea:
- În **panoul de notificări** al aplicației lor (cap. 20)
- Cu **iconă alertă roșie** (severity high) sau amber (medium)
- Cu **numele tău** ca reporter și **poarta ta** (dacă e asignată)
- Cu **poza (thumbnail)** și **butonul Play** pentru nota vocală (dacă
  le-ai atașat) — vizibile direct în notificare

Ex. text notificare primită de admin:
```
Urgență: Problemă Tehnică
Raportat de Ion Popescu (Manager) — poarta VIP1.
[thumbnail foto 56x56] [▶ Redă · 12s]
acum 30 sec
```

---

## 7. Câmpul „inclusiv pe telefonul tău"

Chiar dacă tu ești logat ca **admin/proprietar**, propriul tău raport
apare și în panoul TĂU de notificări. Vezi și tu că a ajuns.

Motiv: în echipe cu mai mulți admin, oricine poate reacționa.

---

## 8. Notificarea vine cu sunet

La primire, aplicația destinatarilor:
- **Sună** (dacă au sunet activ în Setări scanner)
- **Vibrează** — pattern lung pentru urgențe
- **Apare instant** în panoul de notificări

**Notă**: dacă app-ul e în background sau închis, notificarea nu apare
încă (Push Notifications reale sunt lucrare viitoare — vezi
NOUTATI_v2.2.md → recomandări viitor). Pentru acum, admin trebuie să
aibă app-ul deschis pe fundal.

---

## 9. Limitări

- **Necesită internet** pentru transmisia raportului
- **Foto max 5MB** — se comprimă automat înainte de upload
- **Audio max 2MB** — echivalent ~60 secunde la calitate mică
- **Fără internet**: raportul se afișează local ca notificare la tine,
  dar NU ajunge la admini. Reîncearcă când revine semnalul.
- **Nu poți edita/șterge** un raport trimis. Dacă a fost greșit, trimite
  altul cu explicație.

---

## 10. Probleme frecvente

**„Butonul ⚠️ din bara turei nu apare"**
- Nu ai tura pornită. Deschide tura din Panou. **SAU** folosește
  Metoda 1 (din Notificări) — merge fără tură.

**„Nu văd secțiunea Alertă în aplicație în Notificări"**
- Panoul de notificări e scurt? Trebuie să faci scroll în jos.
- Verifică că build-ul e v2.2 sau mai recent.

**„Camera nu deschide când tap Foto"**
- Verifică permisiunea camerei din Setări Android → Aplicații → AmBilet
- Închide alte apps care folosesc camera

**„Microfonul nu înregistrează"**
- Verifică permisiunea microfon
- Verifică că nu ești pe muted din butoanele de volum
- Redeschide app-ul

**„Am trimis raport, dar admin nu a primit"**
- Verifică internetul tău + al lui
- Verifică că admin are aplicația deschisă (fundal ok)
- Contactează direct prin telefon la contactele urgențe din Setări

**„Vreau să anulez un raport trimis din greșeală"**
- Nu se poate. Trimite altul cu explicație:
  „Raportul anterior a fost o greșeală, nu era o urgență reală".

---

## 11. Testează pe viu

1. Deschide tura ta ([cap. 21](./21_tura.md))
2. Din bara turei, apasă **⚠️**
3. Tap pe **📷 Foto** → fă o poză la ceva random (masa ta, nimic
   sensibil)
4. Ține apăsat pe **🎤 Ține pentru notă** ~5 secunde, spune „test test"
5. Dă drumul → apare chip cu „Notă 5s"
6. Tap pe **🔧 Problemă Tehnică**
7. Vezi mesajul „Raport trimis"
8. Deschide panoul de notificări → verifică că apare acolo (inclusiv
   tu, dacă ești admin)

---

## Următorul capitol

📖 [Capitolul 19 — Contact urgențe →](./19_contact_urgente.md)

📚 [Cuprins →](./00_cuprins.md)

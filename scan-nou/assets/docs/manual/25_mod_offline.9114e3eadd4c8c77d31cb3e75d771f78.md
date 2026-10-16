# Capitolul 25 — Modul offline manual

Aplicația detectează automat când e offline, dar poți **activa manual** modul offline din Setări. Descarcă biletele evenimentului pe telefon în avans, ca să funcționeze scanarea chiar și fără semnal deloc.

Timp de citit: **~3 minute**.

---

## 1. Diferența față de modul offline automat

**Auto-offline** ([cap. 6](./06_vanzare_offline.md)):
- Detectat de aplicație când **pierzi net-ul brusc**
- Vânzările se pun în coadă
- Scanarea nu poate verifica bilete pe care nu le-a mai văzut

**Mod offline manual** (acest capitol):
- Tu **decizi din start** că evenimentul va fi offline
- Aplicația **descarcă toate biletele evenimentului** în avans
- Scanarea funcționează 100% offline — verifică local

**Recomandat pentru**: evenimente în locații cu semnal zero (peșteră, cort la câmp, subsol fără WiFi).

---

## 2. Cum activezi

**Setări → Mod Offline** — toggle switch.

<!-- SCREENSHOT: Setări → Mod Offline cu toggle ON + info download -->
![Setări Mod Offline](./screenshots/25-settings.png)

**Când activezi**:
1. Aplicația **descarcă lista completă de bilete** a evenimentului curent
2. Vezi un status: „Se descarcă biletele..." apoi „X bilete salvate pentru scanare offline"
3. Odată completă, poți scana fără internet

**Cerințe la activare**:
- Ai internet **la momentul activării** (pentru descărcare)
- Evenimentul e selectat corect
- Ai spațiu pe telefon (~1MB per 1000 bilete)

---

## 3. Ce se salvează

Fiecare bilet activ al evenimentului:
- Cod bilet + barcode
- Numele beneficiarului
- Tipul biletului
- Locul (dacă e cu seating)
- Statusul (checked-in sau nu)

**Actualizarea** listei: automat la fiecare 30 min când ești online, sau manual din Setări (buton „Reîmprospătează").

---

## 4. Cum se scanează offline

Din **tab-ul Scanare**, exact ca la scanarea normală:
- Camera QR sau Cod Manual
- Aplicația verifică **local** — nu contactează serverul
- Rezultatul apare instant

**Marcarea „checked-in"**:
- Local pe telefon
- Se adaugă la o **coadă de sincronizare**
- Când revine internet → se trimite la server automat

**Header-ul afișează** counterul galben cu numărul de scanări pending.

---

## 5. Fluxul complet la un eveniment offline

**Cu 1 zi înainte**:
1. Deschide app-ul, cu WiFi bun (acasă / birou)
2. Selectează evenimentul care va fi offline
3. **Setări → Mod Offline → ON**
4. Așteaptă descărcarea (câteva secunde-minute pentru evenimente mari)
5. Verifică că vezi „X bilete salvate"

**La eveniment**:
1. Deschide app-ul — Modul offline e încă activ
2. Scanează bilete normal
3. Fiecare scan e verificat local, marchează checked-in local
4. Counterul galben crește

**După eveniment / la revenirea internetului**:
1. Aplicația detectează online
2. Coadă de sincronizare se golește automat
3. Counterul galben scade → dispare
4. Toate check-in-urile ajung la server

---

## 6. Vânzarea în mod offline

**Merge!** ([cap. 6](./06_vanzare_offline.md) descrie același flux).

Poți vinde bilete + face check-in ambele, offline complet.

---

## 7. Dezactivează Mod Offline

**Setări → Mod Offline → OFF**:
- Aplicația **NU șterge** biletele deja descărcate (rămâne cache)
- Doar oprește descărcarea automată
- Trece pe modul „auto-detect" (cap. 6)

Reactivarea = pornește iar downloadul.

---

## 8. Limitări

- **Se descarcă doar evenimentul curent** — nu toate. Trebuie să reactivezi pentru fiecare eveniment.
- **Bilete noi vândute după descărcare** nu apar în cache-ul offline. Cei care cumpără chiar în ziua evenimentului **NU pot fi validați offline** — necesită internet momentan pentru a-i vedea. 
- **Modificări server-side** (refund, cancel) — se propagă doar la refresh manual. Poți valida offline un bilet care a fost refundat online.

---

## 9. Recomandări din teren

**Evenimente predictibile** (bilete vândute cu 1+ zi înainte):
- Activează cu 24h înainte
- Refresh dimineața evenimentului
- Poți scana offline liniștit

**Vânzare la ușă** + offline:
- Vânzările se pun în coadă (cap. 6)
- Biletele vândute la ușă sunt **verificate local** — și scanabile offline instant (același device știe de ele)
- Dar dacă un client cumpără la Casa 1 și vine la Poarta 2 (device diferit), Poarta 2 nu știe de bilet până la sync → refuză. **Sfat**: Casa 1 emite bon fizic pe care Poarta 2 acceptă manual.

---

## 10. Probleme frecvente

**„Toggle Mod Offline dă eroare"**
- Verifică internetul (activarea NECESITĂ net pentru descărcare inițială)

**„Am activat dar nu vede biletele"**
- Verifică că **evenimentul selectat e corect** înainte de activare
- Reintră în toggle: OFF → ON → refresh forțat

**„Vând la Casa 1 offline, coleg la Casa 2 vede biletul de la mine?"**
- **NU offline**. Sync-ul se face doar la revenirea internetului.
- Pe device-uri diferite fără net = izolate.

**„Vreau să șterg cache-ul offline"**
- Setări → Mod Offline OFF → Setări Android → Storage → Șterge date aplicație. Radical, dar curăță tot.

---

## 11. Testează pe viu

1. **Setări → Mod Offline** → ON
2. Așteaptă descărcarea („Se descarcă..." → „X bilete salvate")
3. Pornește **modul avion** pe telefon
4. Deschide Scanare → scanează un bilet cunoscut valid
5. Verifică că rezultatul e 🟢 valid, offline
6. Oprește modul avion
7. Verifică counterul galben scade după sync

---

## Următorul capitol

📖 [Capitolul 26 — Widget Android →](./26_widget_android.md)

📚 [Cuprins →](./00_cuprins.md)

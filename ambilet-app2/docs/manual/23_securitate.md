# Capitolul 23 — Securitate (auto-logout)

Aplicația te **deconectează automat** dacă lași telefonul neatins un
timp — protecție dacă cineva îl ridică când tu nu ești atent.

Timp de citit: **~2 minute**.

---

## 1. Cum ajungi

**Setări → Securitate**. Vezi opțiunea **„Auto-logout după inactivitate"**
cu 5 chip-uri de selecție:

- **Oprit** — fără auto-logout
- **5 min** (implicit)
- **10 min**
- **15 min**
- **30 min**

<!-- SCREENSHOT: Setări → Securitate cu chip-urile 5/10/15/30/Oprit -->
![Setări Securitate](./screenshots/23-settings.png)

---

## 2. Cum funcționează

**Timer-ul se resetează** la fiecare atingere a ecranului. Cât timp
folosești app-ul activ, nu se scurge.

**Când începe să curgă**:
- Nu atingi ecranul (nu scrolluiești, nu apeși nimic)
- Nu ești în plin proces critic (vezi mai jos)

**Când expiră**:
- Deconectare automată → redirect la ecranul de login
- Cifrele turei rămân salvate — nu pierzi date
- La next login, tura se reia de unde a rămas (dacă era activă)
- **Emailul și parola rămân completate pe ecranul de login** — apeși
  doar `Autentificare` și continui. Nu retasti nimic.

---

## 3. Diferență între auto-logout și „Deconectare"

| Ce faci | Ce se întâmplă cu credentialele |
|---|---|
| **Auto-logout** (după 5-30 min inactivitate) | Rămân salvate în telefon — pre-fill la next login |
| **Setări → „Încheie Tura & Deconectare"** | Sunt **șterse complet** — form-ul e gol data viitoare |

Auto-logout e pentru **pauze scurte** (mergi la baie, ai lăsat telefonul
pe masă). „Încheie Tura" e pentru **predare de tură** către alt operator
sau **sfârșit de eveniment** — credențialele tale nu mai trebuie să
zacă pe device.

Credentialele sunt salvate în **SecureStore** (Android Keystore /
iOS Keychain) — criptate la nivelul sistemului, nu ies niciodată din
telefon.

---

## 3. Se pauzează în timpul plăților / urgențelor

Timer-ul **NU se scurge** în timpul:

- **Modal de confirmare plată** deschis (cash/card)
- **Sheet de raportare urgență** deschis
- **Modal Personal / Porți** deschis
- **Modal Listă Invitați** deschis
- **Selector de evenimente** deschis

Așa **nu te scoate mid-tranzacție** când clientul e la cardul POS.

---

## 4. Cifre reprezentative

| Setare | Când folosești |
|---|---|
| **Oprit** | Testare, dev, event unde nu îți lași telefonul |
| **5 min** | Recomandat pentru majoritatea evenimentelor mari |
| **10 min** | Evenimente cu ritm moderat, ture lungi |
| **15 min** | Evenimente private, echipă mică |
| **30 min** | Ture solo, tu ești mereu langă telefon |

**Default e 5 min** — bun compromis între siguranță și confort.

---

## 5. Cold-start check

Dacă telefonul stă în buzunar 20 de minute cu app-ul închis, la
redeschidere aplicația verifică:

- Cât timp a trecut de la ultima activitate?
- Dacă e peste timeout → deconectare imediată la deschidere

Așa nu poate cineva să ia telefonul de pe masă, să-l deschidă și să
găsească app-ul deschis de acum 2 ore.

---

## 6. Mesaj la deconectare

La expirarea timer-ului apare Alert:

> **Deconectat automat pentru siguranță**

Confirmi → login screen. Te loghezi din nou cu credentialele.

---

## 7. Tura se salvează

**Cifrele turei rămâne persistate** — la relogin, aplicația reia:
- Cronometrul (adăugat timpul cât ai fost logout)? Nu — pauzat.
  Redeschide manual.
- Vânzările și scanările făcute în tură rămân în rapoarte.

**Recomandare**: la relogin, **închide manual tura veche** din Panou și
deschide una nouă. Așa cifrele se atribuie clar (înainte/după login).

---

## 8. Recomandări din teren

- **Ture solo, la casierie fixă**: 15-30 min OK, tu ești acolo mereu
- **Multi-casier cu roll-uri**: 5 min — evită ca colegul să folosească
  accidental contul tău
- **Test / instruire**: Oprit — nu te enerva cu logout-uri repetate
- **Eveniment mare cu presiune**: 5 min — max protecție

---

## 9. Limitări

- **Nu are biometric unlock** — la logout, trebuie tastat parola (nu
  amprentă / față)
- **Nu se comută automat la ora nopții** — tu setezi o valoare, rămâne
- **Nu poți exclude anumite ecrane** — timer-ul se aplică peste tot
  (dar cu pauzele critice descrise)

---

## 10. Probleme frecvente

**„M-a deconectat deși tocmai făceam ceva"**
- Verifică că modalul relevant era deschis (payment / emergency etc.)
- Dacă nu, e bug — raportează la AmBilet cu screenshot al Setări
  Securitate + ora exactă

**„Vreau să nu mă mai deconecteze deloc temporar"**
- Setează **Oprit** pentru sesiunea curentă
- La final de eveniment, revino la 5 min pentru siguranță zilnică

**„Am pierdut cifrele turei după relogin"**
- Cifrele **globale** rămân în rapoarte. Doar sumar-ul personal e
  pierdut. Facem screenshot data viitoare.

---

## 11. Testează pe viu

1. **Setări → Securitate → 5 min** (implicit)
2. Închide Setări
3. **Nu atinge telefonul 5 minute** — pune-l pe masă
4. La ~5:00 → apare Alert „Deconectat automat"
5. Login din nou

Sau, mai rapid pentru test:
1. Alege **5 min**
2. Fă o acțiune (ex. deschide Vânzare)
3. Așteaptă 5 min fără să atingi
4. La expirare → logout automat

---

## Următorul capitol

📖 [Capitolul 24 — Setări scanner →](./24_setari_scanner.md)

📚 [Cuprins →](./00_cuprins.md)

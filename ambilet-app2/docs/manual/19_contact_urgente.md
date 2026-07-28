# Capitolul 19 — Contact urgențe (numere de telefon)

Setezi **3 numere de telefon** pentru butoanele de apel rapid din
panoul de notificări. Un tap = apel telefonic direct, fără dialer,
fără dialog de confirmare (pe Android).

Timp de citit: **~2 minute**.

---

## 1. Ce e asta

**Butoane apel-rapid pentru urgențe fizice** — medic, tehnic, pază.
Nu confuzia cu **raportul de urgență** din bara turei (cap. 18); acela
alertează administratorii, ăsta sună la un număr real de telefon
(112, dispecerat pază, contact echipă tehnică etc.).

---

## 2. Cum le configurezi

**Setări → Contact Urgențe**. Trei câmpuri:

- 🔴 **Urgență Medicală** — telefon medic / 112 / SMURD
- 🟡 **Problemă Tehnică** — telefon inginer / firmă sunet-lumini
- 🔷 **Alertă Pază** — telefon dispecerat pază / bodyguard

<!-- SCREENSHOT: Setări → Contact Urgențe cu 3 câmpuri completate -->
![Setări Contact Urgențe](./screenshots/19-settings.png)

Fiecare câmp:
- Bulinuță colorată la stânga (severitate)
- Label
- Câmp text pentru număr (keyboard: phone-pad, max 20 caractere)

Introdu numărul cu **cifre + opțional `+` prefix internațional**.

Ex. valide: `0722 000 000`, `+40722000000`, `112`.

**Salvarea e automată** la fiecare tastă — nu ai buton Save.

---

## 3. Cum apelezi din app

**Deschide panoul de notificări** (clopoțel din header) → **jos** e
secțiunea **„Raportează Problemă"** cu 3 butoane:

<!-- SCREENSHOT: panou notificări cu secțiunea Raportează Problemă jos -->
![Panou notificări cu butoane apel](./screenshots/19-buttons.png)

Fiecare buton:
- **Iconă alertă** cu culoarea severității
- **Label** (Urgență Medicală / Problemă Tehnică / Alertă Pază)
- **Numărul setat** afișat mic
- Sau **„Nesetat"** dacă nu ai configurat

**Tap pe buton** → **apel telefonic instant**.

---

## 4. Cum se face apelul

### Pe Android

**Apel direct**, fără trecere prin dialer:
1. Prima dată aplicația cere permisiunea `CALL_PHONE`
2. Permite → toate apelurile viitoare merg direct
3. Ecranul telefonului trece instant la modul „apelând"

**De ce direct**: în urgențe, un dialer intermediar cu confirmare pe
buton pierde secunde valoroase. Un tap = suna. Punct.

### Pe iOS

**Restricție Apple**: iOS afișează întotdeauna un dialog „Sună la XXX?
Anulează / Sună". Nu se poate ocoli, e regulă Apple. Un tap suplimentar
de confirmare.

---

## 5. Ce faci dacă numărul e „Nesetat"

Butonul e semi-transparent și dezactivat. **Tap** → apare Alert:

> „Adaugă un număr pentru «Urgență Medicală» din Setări → Contact
> Urgențe."

Du-te în Setări și completează.

---

## 6. Recomandări cu numere

- **Salvează 112 la Urgență Medicală** pentru siguranță maximă —
  dispeceratul unic pentru ambulanță, pompieri, poliție
- **Salvează dispeceratul firmei de pază** dacă ai contract cu una —
  ajung mai repede decât 112 pentru probleme fizice non-fatale
- **Salvează un tehnician cunoscut** pentru avarii tehnice — nu 112 pentru
  o priză stricată

**Persoana concretă vs. dispecerat**: dispeceratul e mai fiabil (dacă
persoana concretă nu răspunde), dar persoana concretă e mai rapidă.
Balansează după cazul tău.

---

## 7. Datele sunt LOCALE pe telefon

Aceste 3 numere sunt salvate **doar pe device-ul tău**, nu se
sincronizează la server. Fiecare operator își setează propriile
contacte.

**De ce**: fiecare echipă / eveniment / venue are contactele lor
specifice. Un dispecerat pentru evenimentul din Cluj e diferit de cel
din București.

**Consecință**: dacă schimbi telefonul sau reinstalezi app-ul, refaci
setările.

---

## 8. Confidențialitate

Numerele nu părăsesc telefonul tău. Nu apar în rapoartele AmBilet, nu
sunt trimise la server, nu apar în ecranele altor colegi. Doar tu le
vezi și le folosești.

---

## 9. Limitări

- **Max 3 categorii** — nu poți adăuga „Coleg X", „Coleg Y" separat.
  Pentru mai multe contacte, folosește agenda telefonului
- **Doar telefoane fixe/mobile** — nu poți suna prin WhatsApp / Signal
- **Fără mesaj** — apelul e voce, nu SMS. Pentru mesaje, ieși din app
  și scrie din altă aplicație

---

## 10. Probleme frecvente

**„Butonul e activ dar apelul nu pornește"**
- Verifică permisiunea `CALL_PHONE` din Setări Android
- Verifică că telefonul are semnal (nu e în mod avion)
- Verifică că numărul e valid (fără litere, minim 3 cifre)

**„Pe iPhone apare mereu dialog «Sună la XXX?»"**
- Normal, restricție Apple. Nu se poate elimina.

**„Am setat un număr greșit, cum să-l șterg"**
- Deschide câmpul, șterge textul complet
- Butonul devine „Nesetat" automat

---

## 11. Testează pe viu

1. Deschide **Setări → Contact Urgențe**
2. La „Urgență Medicală", introdu numărul tău personal (test)
3. Închide Setări
4. Tap pe **clopoțel** (header)
5. Sub notificări, apasă butonul **🔴 Urgență Medicală**
6. Pe Android — apelul pornește instant
7. Pe iOS — confirmi „Sună"
8. **Închide apelul imediat** (a fost test)

---

## Următorul capitol

📖 [Capitolul 20 — Panoul de notificări →](./20_notificari.md)

📚 [Cuprins →](./00_cuprins.md)

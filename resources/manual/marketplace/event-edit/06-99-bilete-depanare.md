---
id: bilete-depanare
chapter: Bilete
title: Scenarii și depanare
tab: bilete
applies_to: toate
covers: []
fields: []
updated: 2026-09-24
---

Întrebările care apar cel mai des la vânzarea biletelor, cu pașii de verificat.

## De ce nu se poate cumpăra biletul X? {#bilete-depanare-cumparare}

Verifică în ordine:

1. **Evenimentul e publicat?** Coloana din dreapta, butonul **Publicat**. Un eveniment nepublicat nu se deschide pe site.
2. **Biletul apare pe pagina evenimentului?** Dacă nu, uită-te la titlul cardului din lista de bilete:
   - **Dezactivat** → pornește **Activ**;
   - **Programat** → așteaptă ora setată (sistemul verifică o dată pe minut);
   - **Expirat** → a trecut data din „Activ până la”: schimbă sau golește data **și** pornește Activ;
   - **Autostart** → așteaptă epuizarea biletului de deasupra;
   - eticheta **App** → biletul se vinde doar la intrare, din aplicație;
   - are cod 🎁 → apare doar după ce clientul introduce codul;
   - se numește „Test POS” → e ascuns intenționat.
   Verifică și dacă ai salvat: butoanele din titlul cardului se aplică doar la salvare.
3. **Biletul apare, dar e gri („Epuizat” / „Indisponibil”)?**
   - ⃠ **Sold Out** e pornit (roșu în titlul cardului);
   - stocul e consumat: vezi „Active: N / stoc” sub câmpul Stoc;
   - stocul a fost coborât sub numărul de bilete vândute, pus pe 0, sau rescris de asocierea rândurilor pe harta de locuri.
4. **Toate biletele sunt gri, cu un mesaj pe tot evenimentul?** Verifică în tab-ul Detalii: **Anulat**, **Amânat**, **Sold out**, **Doar la intrare**. Un eveniment încheiat apare ca „Încheiat”.
5. **Clientul adaugă în coș, dar primește eroare la „Finalizează”?**
   - „Capacitatea totală a evenimentului a fost atinsă” → s-a umplut **Capacitatea generală**;
   - „Au mai rămas doar N bilete…” → stocul s-a consumat între timp;
   - „Locurile alese… au fost achiziționate între timp” → locurile au fost luate de altcineva;
   - „Biletul nu mai este disponibil momentan” → biletul a fost oprit sau a expirat între timp.

## De ce apare „Sold out” deși mai sunt locuri? {#bilete-depanare-soldout}

1. **Sold out** e pornit pe tot evenimentul (tab-ul Detalii). Blochează vânzarea indiferent de stoc.
2. ⃠ **Sold Out** e pornit pe tipul de bilet. Poate fi pus de un operator, de cascada de la nivel de eveniment sau din aplicația organizatorului — de acolo organizatorul nu îl mai poate scoate, doar operatorul.
3. **Comenzi neplătite** țin biletele ocupate: 15 minute de la finalizare, până la 45 de minute după ce clientul ajunge la plată. Verifică în Comenzi comenzile în așteptare.
4. **Invitațiile emise** consumă din stoc și din capacitatea generală.
5. **Stocul** a fost coborât sub vânzări sau rescris de harta de locuri.
6. **Capacitatea generală** s-a umplut: biletele par disponibile, dar comanda e refuzată la plată.
7. **Doar la intrare** e pornit: toate biletele online apar cu „Epuizat”.
8. La evenimentele cu hartă, locurile pot fi ținute temporar de alți clienți (15 minute).

Dacă cifrele nu se potrivesc deloc cu biletele reale, semnalează echipei tehnice.

## Cum marchez un eveniment sold out și cum îl redeschid {#bilete-depanare-eveniment-soldout}

- **Marchezi:** tab-ul Detalii → **Sold out** → salvează. Toate tipurile de bilete sunt marcate automat.
- **Redeschizi:** oprești **Sold out** → salvezi. Se dezmarchează **doar** biletele marcate de eveniment; cele marcate individual rămân epuizate, așa că verifică lista și scoate-le manual cu ⃠.
- După redeschidere, verifică dacă vreun bilet cu **Autostart** a pornit între timp: se poate ajunge ca două tipuri de bilete să se vândă în paralel.
- Marcajul oprește vânzarea online. Nu te baza pe el pentru a opri vânzarea din aplicația de la intrare.

## Opresc vânzarea online, dar continui la intrare {#bilete-depanare-door}

1. Tab-ul Detalii → pornește **Doar la intrare**. Pe site toate biletele online devin gri, cu eticheta „Doar la intrare”.
2. Pentru siguranță, oprește și **Activ** la biletele online: o comandă începută înainte de schimbare ar putea altfel să fie dusă până la plată.
3. Vânzarea din aplicația de la intrare continuă, cu biletele marcate **App**.

## Cât timp țin ocupate biletele o comandă începută? {#bilete-depanare-rezervari}

- 15 minute din momentul în care clientul apasă „Finalizează”.
- Până la 45 de minute dacă a ajuns la pagina de plată.
- Biletele lăsate în coș, fără finalizare, **nu** rezervă nimic: cronometrul din coș e doar informativ.
- După expirare, biletele revin în stoc în câteva minute.

## Ce plătește clientul când se combină mai multe reduceri {#bilete-depanare-combinatii}

| Situație | Rezultat |
|---|---|
| Preț promoțional + cod de reducere | Se cumulează: codul se aplică peste prețul promoțional. |
| Preț promoțional + reduceri la cantitate | Doar prețul promoțional; regulile de cantitate nu au efect. |
| Bilet gratuit cu cod + cod de reducere | Pot fi în aceeași comandă; codul de reducere nu schimbă biletul gratuit. |
| Preț per reprezentație + orice | La plată se încasează prețul tipului de bilet. |
| Comision + preț promoțional | Comisionul se calculează la prețul promoțional. |
| Comision + cod de reducere | Comisionul se calculează la prețul dinainte de cod. |
| Reducerea se termină cât biletul e în coș | Clientul plătește prețul întreg. |
| Cod de reducere expirat sau greșit | Comanda se face fără reducere, fără mesaj de eroare pentru client. |

## Întrebări rapide {#bilete-depanare-rapide}

<details>
<summary>Cum opresc definitiv un tip de bilet care are deja vânzări?</summary>

Nu poate fi șters. Oprește **Activ** (dispare de pe site) sau marchează-l ⃠ **Sold Out** (rămâne vizibil, gri) și salvează.

</details>

<details>
<summary>Am făcut modificări și clienții văd tot varianta veche.</summary>

Pagina evenimentului e ținută în cache câteva minute. Verifică întâi că ai salvat, apoi reîncarcă pagina după câteva minute, ideal într-o fereastră privată.

</details>

<details>
<summary>Vreau să testez vânzarea fără să apară în rapoarte.</summary>

Folosește linkul de comandă de test din coloana din dreapta a paginii, sau biletul **Test POS** pentru aplicația de la intrare. Ambele sunt excluse din vânzări, deconturi și facturi.

</details>

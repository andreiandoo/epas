---
id: bilete-identificare
chapter: Bilete
title: "Tipul de bilet: Identificare"
tab: bilete
applies_to: toate
covers: ["Identificare"]
fields: [name, sku, price_max, capacity, ticket_group, group_name, min_per_order, max_per_order, multiplier, description, admin_notes, color, valid_date]
updated: 2026-09-15
---

Prima secțiune a fiecărui tip de bilet, mereu deschisă: numele, prețul, stocul și limitele pe comandă. Câmpurile speciale ale locațiilor de agrement (categorie de serviciu, sloturi, variante etc.) apar doar la acele evenimente și au capitolul lor.

## Câmpurile {#bilete-identificare-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Nume** (obligatoriu) | Numele biletului pe site, în coș, pe biletul PDF, în e-mailuri și în documentele fiscale. | Numele „Test POS” ascunde biletul de pe site. Numele „Invitatie” îl scoate din documentele fiscale. |
| **SKU** | Cod intern. Gol = se generează din nume. | Clienții nu îl văd. |
| **Preț** (obligatoriu) | Prețul întreg al biletului. | Prețul redus se pune în secțiunea **Reducere**. Biletul gratuit cu cod trebuie să aibă prețul 0. |
| **Stoc** (obligatoriu) | Câte bilete de acest tip se pot vinde. | **-1 = nelimitat. 0 = nimic de vândut.** |
| 🔒 lângă Stoc | Lacăt închis = stoc partajat. Lacăt deschis = stoc independent. | Stocul independent nu consumă din Capacitatea generală. Util pentru parcare, garderobă și altele asemenea. |
| **Grup** | Grupul în care apare biletul pe site. | Apare doar cu **Grupează tipurile de bilete** pornit. Scrierea contează: „VIP” și „Vip” sunt grupuri diferite. |
| **Min bilete/comandă** | Minimul de bilete de acest tip pe comandă. | Primul clic pe „+” sare direct la minim. |
| **Max bilete/comandă** | Maximul pe comandă. Implicit 10. | Nu poate fi sub minim. La biletele gratuite cu cod se setează în secțiunea lor. |
| **Multiplicator** | Pasul butoanelor +/−. 2 = câte 2 bilete la un clic. | Fiecare unitate rămâne un bilet separat. Nu înseamnă „un bilet pentru 2 persoane”. |
| **Descriere** | Textul de sub numele biletului, pe site și pe biletul PDF. | Se completează singur cu „Reducere până la …” când pui o reducere cu dată de sfârșit și descrierea e goală. Textul rămâne și după terminarea reducerii, deci șterge-l manual. |
| **Note interne** | Notițe doar pentru operatori. | Nu apar clienților. Le găsești adunate în tab-ul Observații. |
| **Culoare pe hartă** | Culoarea locurilor acestui bilet pe harta de locuri. | Apare doar la evenimentele cu hartă. Fără culoare, sistemul alege una după poziția în listă, deci culoarea se schimbă când reordonezi biletele. |
| **Bilet de 1 zi — valabil în data** | Ziua în care e valabil biletul. | Apare doar la evenimentele pe un interval de date. Data e tipărită pe bilet în locul intervalului. Nu marca biletul și ca Abonament, altfel pe bilet apare tot intervalul. Scanarea la intrare nu verifică automat ziua. |

## Prețul, pe larg {#bilete-pret}

- Clientul plătește **Prețul**, sau **Prețul promoțional** cât timp reducerea e în desfășurare.
- ⚠️ Unele prețuri cu zecimale se salvează cu 1 ban mai puțin: de exemplu 19,99 devine 19,98, iar 9,95 devine 9,94. După salvare, redeschide biletul și verifică prețul.

## Stocul, pe larg {#bilete-stoc}

- Sub Stoc vezi „Active: N · Anulate: M / stoc”. Active înseamnă bilete valide sau deja scanate.
- La un tip de bilet nou, Stocul se completează singur cu locurile rămase din Capacitatea generală.
- Poți coborî stocul sub numărul de bilete vândute, formularul nu te oprește. Biletul devine „Epuizat”, iar biletele vândute rămân valabile. Dacă ridici stocul la loc, vânzarea repornește singură.
- Când schimbi stocul unui bilet deja salvat, **Serie end** se rescrie automat (vezi capitolul „Serie bilete”).
- La evenimentele cu hartă de locuri:
  - când asociezi rânduri unui tip de bilet, stocul devine numărul de locuri de pe acele rânduri;
  - blocarea sau deblocarea locurilor modifică și ea stocul.
- Stocul e ocupat și de comenzile începute, încă neplătite. Rezervarea ține 15 minute de la „Finalizează” și până la 45 de minute după ce clientul ajunge la plată. Dacă plata nu se face, biletele revin în stoc în câteva minute.
- Invitațiile emise consumă și ele din stoc.

## Limitele pe comandă {#bilete-limite}

- Minimul, maximul și multiplicatorul sunt aplicate pe pagina evenimentului și în coș.
- Limitele sunt pe o singură comandă. Nimic nu împiedică un client să plaseze mai multe comenzi.

## Scenarii {#bilete-identificare-scenarii}

<details>
<summary>Clientul apasă „+” o singură dată și în coș apar 2 bilete.</summary>

Fie **Multiplicator** e 2 (fiecare clic adaugă 2 bilete), fie **Min bilete/comandă** e 2 (primul clic sare direct la minim). Pune ambele pe 1 dacă biletele se vând câte unul.

</details>

<details>
<summary>Am pus prețul 19,99 și după salvare apare 19,98.</summary>

E o problemă cunoscută la salvarea unor prețuri cu zecimale. Corectează prețul, de exemplu la 20,00, sau verifică-l după fiecare salvare.

</details>

<details>
<summary>Tipul de bilet e activ, dar nu apare pe site.</summary>

Verifică, în ordine:

1. Butonul 📱 **App** e pornit? Atunci biletul se vinde doar la intrare.
2. Biletul are cod în „Bilet gratuit cu cod promo”? Atunci apare doar după introducerea codului.
3. Se numește „Test POS”?
4. Ai salvat după ultima modificare? Butoanele din titlul cardului se aplică doar la salvare.
5. Evenimentul e publicat?

</details>

<details>
<summary>Vreau maximum 4 bilete pe comandă.</summary>

Pune **Max bilete/comandă** = 4 și salvează. Pagina evenimentului și coșul nu mai permit mai mult de 4 bilete de acest tip, cu mesajul „Maxim 4 bilete de acest tip per comandă”. Limita e pe comandă: clientul poate face o a doua comandă.

</details>

<details>
<summary>Biletele se vând doar câte două (masă pentru 2 persoane).</summary>

Pune **Multiplicator** = 2, **Min bilete/comandă** = 2 și un **Max** multiplu de 2, de exemplu 10. Fiecare clic adaugă 2 bilete. Dacă stocul rămas e impar, ultimul bilet se poate cumpăra singur, așa că alege un stoc par.

</details>

<details>
<summary>Cum pun stoc nelimitat?</summary>

Scrie **-1** la Stoc (0 înseamnă că nu e nimic de vândut). Pentru limita comună a evenimentului, lasă **Capacitate generală** goală. Excepție: la biletele vândute din aplicația de la intrare (📱 App) pune un număr real, pentru că stocul nelimitat nu e tratat corect acolo.

</details>

<details>
<summary>Am scăzut stocul de la 300 la 200, dar se vânduseră deja 250.</summary>

Salvarea merge. Biletul apare „Epuizat” și nu se mai poate cumpăra, iar cele 250 de bilete vândute rămân valabile. Dacă biletul de sub el are Autostart, acela pornește. Ca să reiei vânzarea, ridică stocul peste 250.

</details>

<details>
<summary>Festivalul are și bilete de o zi. Cum le configurez?</summary>

Evenimentul trebuie să fie pe un interval de date (tab-ul Detalii). La fiecare bilet de o zi completează **Bilet de 1 zi — valabil în data**, fără să pornești 🕒 **Abonament**. Pe bilet se tipărește ziua respectivă. Scanarea nu refuză automat biletul în altă zi, deci anunță personalul de la intrare să verifice data.

</details>

<details>
<summary>Parcarea nu trebuie să consume din capacitatea evenimentului.</summary>

La tipul de bilet „Parcare”, apasă lacătul de lângă **Stoc** până devine deschis (verde, „Independent”) și salvează. Parcarea se vinde până la stocul ei, fără să ocupe locuri din Capacitatea generală.

</details>

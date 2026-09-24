---
id: bilete-disponibilitate
chapter: Bilete
title: "Tipul de bilet: Disponibilitate"
tab: bilete
applies_to: toate
covers: ["Disponibilitate"]
fields: [is_active, scheduled_at, active_until, autostart_when_previous_sold_out]
updated: 2026-09-24
---

Secțiunea stabilește **când** se vinde un tip de bilet: acum, de la o anumită dată, până la o anumită dată sau abia după ce se epuizează biletul de deasupra lui. Secțiunea e strânsă; o deschizi cu un clic pe titlu.

## Câmpurile {#bilete-disponibilitate-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Activ** (pornit implicit) | Pornit = biletul e în vânzare. Oprit = biletul dispare complet de pe site. | Biletul oprit nu apare deloc pe pagina evenimentului. Nu există „în curând”. |
| **Programează activare** | Data și ora la care biletul pornește automat. | **Funcționează doar cu „Activ” oprit.** După pornire, câmpul rămâne gol. |
| **Activ până la** | Data și ora la care biletul se oprește automat. | Biletul **dispare** de pe site; nu apare ca „Epuizat”, deși indicația din formular spune asta. Data rămâne completată. |
| **Autostart când precedentul e sold out** | Biletul pornește singur când se epuizează biletul de deasupra lui în listă. | **Funcționează doar cu „Activ” oprit.** Se aplică o singură dată, apoi bifa se stinge. |

> ⚠️ **Regula cea mai importantă:** programarea și autostartul pornesc doar bilete **oprite**. Dacă lași „Activ” pornit și pui o dată de activare, biletul e deja în vânzare, iar data nu face nimic.

## Cum verifici starea unui bilet {#bilete-disponibilitate-stare}

Titlul cardului îți spune tot, fără să-l deschizi:

- **✓ Nume** — activ, în vânzare;
- **○ Nume + Programat 20.09.2026 10:00** — oprit, pornește la data respectivă;
- **○ Nume + Autostart** — oprit, așteaptă epuizarea biletului de deasupra;
- **○ Nume + Expirat** — oprit pentru că a trecut data din „Activ până la”;
- **○ Nume + Dezactivat** — oprit manual.

## Cum funcționează automatizările {#bilete-disponibilitate-automatizari}

- Sistemul verifică în fiecare minut, deci o pornire sau o oprire poate întârzia până la un minut.
- **Programarea** pornește biletul și golește data. Dacă trebuie amânat din nou, completează data din nou.
- **„Activ până la”** oprește biletul, dar **păstrează data**. Dacă repornești biletul fără să schimbi data, el se oprește din nou într-un minut. Ca să-l repui în vânzare: schimbă sau golește data, apoi pornește „Activ”.
- **Autostartul** se uită doar la biletul **imediat de deasupra** în listă, nu la toate cele dinainte. Acela e considerat epuizat când e marcat Sold Out sau când biletele vândute ajung la stocul lui. Un bilet cu stoc nelimitat nu se epuizează niciodată singur.
- Autostartul nu reacționează la „Activ până la”. Dacă biletul de deasupra expiră prin dată, folosește **Programează activare** pe următorul.
- Cele două mecanisme pot fi puse împreună pe același bilet: pornește primul care se întâmplă.

## Scenarii {#bilete-disponibilitate-scenarii}

<details>
<summary>Early Bird până pe 20.09 la 23:59, apoi pornește automat Standard.</summary>

1. La **Early Bird**: completează **Activ până la** = 20.09.2026 23:59.
2. La **Standard**: oprește **Activ** și completează **Programează activare** = 20.09.2026 23:59.
3. Salvează.

În jurul orei 23:59, Early Bird dispare de pe site, iar Standard apare. Clienții care au început deja plata pot să o termine.

</details>

<details>
<summary>Vreau ca Standard să pornească atunci când se termină stocul de Early Bird, nu la o dată fixă.</summary>

1. Pune **Standard** imediat sub **Early Bird** în listă (se trage cu mouse-ul).
2. La Standard: oprește **Activ** și pornește **Autostart când precedentul e sold out**.
3. Salvează.

Când Early Bird se epuizează (stoc consumat sau marcat Sold Out), Standard pornește singur, într-un minut. Atenție: funcționează doar dacă Early Bird are stoc limitat.

</details>

<details>
<summary>Biletul trebuie să intre în vânzare pe 1 octombrie, la ora 10:00.</summary>

Oprește **Activ**, completează **Programează activare** = 01.10.2026 10:00 și salvează. Până atunci biletul nu apare deloc pe site. Verifică în titlul cardului că scrie **Programat 01.10.2026 10:00**; dacă scrie ✓, ai uitat să oprești „Activ”, iar biletul se vinde deja.

</details>

<details>
<summary>Un client spune că Early Bird a dispărut la ora 18:00, deși mai erau bilete.</summary>

A fost atinsă data din **Activ până la**. Biletul se ascunde, nu apare ca „Epuizat”. Dacă vrei să se vadă în continuare, dar fără să se poată cumpăra, folosește în schimb ⃠ **Sold Out** din titlul cardului.

</details>

<details>
<summary>Am repornit un bilet expirat, dar peste un minut s-a oprit din nou.</summary>

Data din **Activ până la** e încă în trecut, iar sistemul oprește biletul la fiecare verificare. Golește sau schimbă data, pornește **Activ** și salvează.

</details>

<details>
<summary>Am pus Autostart, biletul de deasupra s-a epuizat, dar biletul meu n-a pornit.</summary>

Verifică:

1. **Activ** e oprit la biletul cu Autostart? Pe bilete active autostartul nu face nimic.
2. Biletul de deasupra e chiar cel la care te gândești? Contează ordinea din listă, nu numele.
3. Biletul de deasupra are stoc limitat? Cu stoc nelimitat (-1) nu se epuizează niciodată singur; marchează-l manual ⃠ Sold Out.
4. Autostartul s-a consumat deja o dată? Bifa se stinge după prima pornire; pune-o din nou dacă e nevoie.

</details>

<details>
<summary>Am oprit „Activ”, dar clientul spune că a reușit totuși să cumpere.</summary>

Cel mai probabil comanda fusese începută înainte de oprire. Plata pornită rămâne valabilă până expiră (15–45 de minute). Comenzile noi sunt refuzate cu mesajul „Biletul nu mai este disponibil momentan.”

</details>

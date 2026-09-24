---
id: detalii-status
chapter: Detalii
title: Statusurile evenimentului
tab: detalii
applies_to: toate
covers: ["Detalii", "Alerte operaționale"]
fields: [is_sold_out, door_sales_only, is_cancelled, cancel_reason, is_postponed, postponed_date, postponed_start_time, postponed_door_time, postponed_end_time, postponed_reason, is_promoted, promoted_until]
updated: 2026-09-24
---

Sus, în tab-ul Detalii, sunt cele cinci comutatoare care schimbă starea evenimentului: **Sold out**, **Doar la intrare**, **Anulat**, **Amânat** și **Promovat**. Fiecare schimbă ce vede clientul pe site.

> ⚠️ **Niciun comutator nu trimite vreun email cumpărătorilor.** Nici anularea, nici amânarea, nici schimbarea datei. Dacă oamenii care au deja bilete trebuie anunțați, folosește butonul **Newsletter** din bara de sus: el selectează automat cumpărătorii acestui eveniment.

## Ce face fiecare comutator {#detalii-status-comutatoare}

| Comutator | Ce vede clientul | De reținut |
|---|---|---|
| **Sold out** | Badge gri „SOLD OUT”, banner, toate biletele indisponibile, harta de locuri blocată. | La salvare marchează automat toate tipurile de bilete. Când îl stingi, se dezmarchează doar cele marcate de el. |
| **Doar la intrare** | Biletele rămân vizibile, dar indisponibile, cu eticheta „Doar la intrare” și un banner sub listă. | Nu apare pe cardurile din listări și nu blochează harta de locuri. |
| **Anulat** | Badge roșu „ANULAT”, banner cu motivul, toate biletele blocate, cardul apare estompat în listări. | Stinge automat celelalte comutatoare și le blochează cât timp e pornit. Evenimentul **rămâne** în listări, intenționat. |
| **Amânat** | Badge portocaliu „AMÂNAT”, banner cu data veche, motivul și data nouă. | Cu dată nouă în viitor, **vânzarea continuă**. Fără dată nouă, vânzarea se oprește. |
| **Promovat** | Badge auriu „Promovat” în anumite grile de evenimente. | Nu e același lucru cu „Featured” din secțiunea de promovare. Fără dată, promovarea e permanentă. |

Cât timp **Anulat** e pornit, celelalte patru comutatoare sunt blocate. Invers, dacă pornești „Sold out” sau „Amânat” pe un eveniment anulat, anularea se stinge singură.

## Anularea unui eveniment {#detalii-status-anulare}

1. Pornește **Anulat**.
2. Completează **Motivul anulării** — textul apare public, în bannerul roșu de pe pagina evenimentului.
3. Salvează.

Ce **nu** face anularea, și trebuie făcut manual:

- nu anunță cumpărătorii (folosește **Newsletter**);
- nu anulează comenzile în așteptare și nu eliberează stocul;
- nu rambursează nimic;
- nu oprește vânzarea pe server. Blocarea e doar în pagina publică, deci o comandă poate încă intra dintr-un tab rămas deschis sau din aplicația de la intrare. **Verifică comenzile intrate după anulare**, iar pentru o blocare sigură oprește și **Activ** la tipurile de bilete.

## Amânarea {#detalii-status-amanare}

Pornește **Amânat** și completează, în funcție de ce știi:

| Câmp | Rol |
|---|---|
| **Data nouă** | Apare în banner și devine data afișată pe cardurile din listări. |
| **Ora start / Ora acces / Ora final** | Înlocuiesc orele afișate ale evenimentului. |
| **Motivul amânării** | Text public, în bannerul portocaliu. |

- **Cu dată nouă în viitor:** biletele se vând în continuare, iar evenimentul rămâne în listările de evenimente viitoare, la data nouă.
- **Fără dată nouă (sau cu o dată trecută):** vânzarea se oprește, iar bannerul spune „Noua dată va fi anunțată în curând”.
- La evenimentele **cu hartă de locuri**, harta refuză să se deschidă chiar dacă data nouă e în viitor. Dacă vrei să vinzi în continuare locuri la un eveniment amânat, mai bine schimbă direct data evenimentului în secțiunea Program și anunță clienții prin newsletter.
- Dacă stingi **Amânat**, toate câmpurile de amânare se golesc.

## Promovarea {#detalii-status-promovat}

**Promovat** adaugă badge-ul auriu în grilele care îl afișează (de exemplu recomandările de pe pagina de eveniment) și face evenimentul să apară în listările filtrate după evenimente promovate. **Promovat până la** îl limitează în timp; lăsat gol, promovarea rămâne permanentă. Comutatorul nu se stinge singur după data de expirare — promovarea pur și simplu nu se mai aplică.

Nu confunda cu **Setări Featured** (mai jos în același tab), care decide apariția pe prima pagină sau în categorii.

## Alerte operaționale {#detalii-status-alerte}

În partea de jos a tab-ului apare, când e cazul, o secțiune roșie cu ce lipsește administrativ pentru eveniment. Fiecare alertă are un buton **Rezolvă** care te duce direct unde trebuie.

| Alertă | Când apare |
|---|---|
| **Cerere vizare bilete lipsă** | Evenimentul e publicat, dar documentul nu a fost generat. |
| **Cerere vizare bilete trebuie refăcută** | Ai modificat tipuri de bilete după generarea documentului. |
| **Decont lipsă** | Evenimentul a trecut și nu are decont. |
| **Factură lipsă** | Există decont, dar nu și factura aferentă. |
| **Impozit spectacole lipsă** | Evenimentul a trecut și lipsește declarația. |
| **PV distrugere bilete lipsă** | Evenimentul a trecut și lipsește procesul-verbal. |

Ultimele patru apar abia după ce evenimentul s-a încheiat. Lista se reîmprospătează singură la câteva minute sau imediat după ce generezi documentul care lipsea. Același număr de alerte apare ca bulină roșie în coloana „!” din lista de evenimente.

## Ce se întâmplă automat după eveniment {#detalii-status-incheiat}

La câteva minute după ora de final, evenimentul primește starea „încheiat”: pagina rămâne online cu badge-ul „ÎNCHEIAT”, iar vânzarea se oprește, inclusiv din aplicația de la intrare. Evenimentele anulate nu sunt atinse, iar cele amânate cu o dată viitoare se încheie abia după noua dată.

## Scenarii {#detalii-status-scenarii}

<details>
<summary>Evenimentul se anulează. Ce fac, în ordine?</summary>

1. Tab-ul Detalii → **Anulat** + **Motivul anulării** → salvează.
2. Oprește **Activ** la tipurile de bilete, ca să nu mai poată intra comenzi pe căi ocolite.
3. Trimite un **Newsletter** cumpărătorilor evenimentului (butonul din bara de sus preselectează exact acei clienți).
4. Verifică în Comenzi dacă au intrat comenzi după momentul anulării.
5. Stabilește cu organizatorul cum se fac rambursările — sistemul nu le face automat.

</details>

<details>
<summary>Evenimentul se amână. Ce văd clienții care au deja bilete?</summary>

Pe site apare bannerul portocaliu cu data veche, motivul și data nouă, iar orele afișate devin cele noi. **Prin email nu află nimic** — trimite-le un newsletter. Biletele vândute rămân valabile; dacă cineva vrea banii înapoi, se rezolvă ca o rambursare obișnuită.

</details>

<details>
<summary>Care e diferența dintre „Sold out” și „Doar la intrare”?</summary>

**Sold out** = s-a vândut tot: badge pe card și pe pagină, toate biletele blocate, inclusiv harta de locuri. **Doar la intrare** = se închide vânzarea online, dar mai există bilete la casă: biletele rămân vizibile, marcate „Doar la intrare”, iar sub listă apare un banner care explică asta. „Doar la intrare” nu se vede pe cardurile din listări și nu blochează harta de locuri.

</details>

<details>
<summary>Am bifat „Anulat” și acum nu mai pot bifa „Sold out” sau „Promovat”.</summary>

E normal: cât timp evenimentul e anulat, celelalte comutatoare sunt blocate, iar bifarea „Anulat” le-a și stins. Stinge „Anulat” dacă ai nevoie de ele.

</details>

<details>
<summary>Am pus „Sold out” pe eveniment. Ce se întâmplă cu tipurile de bilete?</summary>

La salvare, toate tipurile care nu erau deja epuizate sunt marcate Sold Out. Când stingi comutatorul, se dezmarchează **doar** acelea; tipurile pe care le marcaseși manual rămân epuizate și trebuie scoase unul câte unul din titlul cardului.

</details>

<details>
<summary>Am bifat „Promovat”, dar nu văd nicio diferență.</summary>

Badge-ul auriu apare doar în grilele care îl afișează, nu pe toate listările. Verifică și **Promovat până la**: dacă data a trecut, promovarea nu se mai aplică, deși comutatorul rămâne pornit. Pentru apariția pe prima pagină folosește **Setări Featured**, nu „Promovat”.

</details>

<details>
<summary>Evenimentul s-a terminat, dar încă apare ca activ.</summary>

Starea se schimbă automat la câteva minute după ora de final. Dacă nu se întâmplă, verifică dacă evenimentul are completată ora de final (fără ea se consideră că se termină la 23:59) și dacă are o dată de amânare în viitor, caz în care se încheie abia după ea.

</details>

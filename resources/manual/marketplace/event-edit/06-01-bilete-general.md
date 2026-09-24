---
id: bilete-general
chapter: Bilete
title: Setările generale și lista tipurilor de bilete
tab: bilete
applies_to: toate
covers: ["Bilete", "Bilete Test POS"]
fields: [ticket_template_id, general_quota, target_price, has_per_performance_pricing, enable_ticket_groups, enable_ticket_perks, ticketTypes]
updated: 2026-09-15
---

Tab-ul **Bilete** stabilește ce se vinde la eveniment: cât, la ce preț, când și prin ce canal. Sus sunt setările valabile pentru tot evenimentul. Dedesubt e lista **Tipuri de bilete**: fiecare tip (Early Bird, Standard, VIP…) are setările lui, împărțite pe secțiuni, iar fiecare secțiune are propriul capitol în manual.

## Setările pentru tot evenimentul {#bilete-setari-eveniment}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Șablon bilet** | Alege designul biletului PDF primit de clienți. Gol = șablonul implicit. | Apare doar dacă modulul de personalizare a biletelor e activ. |
| **Capacitate generală** | Numărul maxim de bilete vândute în total, pentru toate tipurile care împart stocul. La editare, gol = fără limită comună. | Obligatoriu la crearea evenimentului. Sub câmp vezi „Disponibil: X / capacitate”. |
| **Preț la intrare** | Prețul de referință al biletului la intrare. | **Apare pe site**, deși indicația din formular spune altceva (vezi mai jos). Un bilet mai scump primește avertismentul roșu „Depășește prețul la intrare”. |
| **Prețuri diferite per reprezentare** | Permite prețuri pe fiecare reprezentație. | Apare doar la evenimentele cu program pe mai multe zile. Vezi capitolul „Prețuri per reprezentație”. |
| **Grupează tipurile de bilete** | Pe site, biletele apar în grupuri care se deschid și se închid (de exemplu „Acces”, „Parcare”). | Pornit → la fiecare tip de bilet apare câmpul **Grup**. |
| **Condiții / Beneficii per tip bilet** | Permite o listă de beneficii la fiecare tip de bilet, afișată pe site. | Pornit → la fiecare tip apare secțiunea **Condiții & Beneficii**. |

### Preț la intrare pe site

Când **Preț la intrare** e mai mare decât prețul unui bilet, pagina evenimentului arată prețul la intrare tăiat, deasupra prețului biletului, iar lângă nume apare un procent de reducere. Exemplu: preț la intrare 150 lei, bilet 100 lei → pe site apare 150 tăiat și „-33%”. Dacă nu vrei acest efect, lasă câmpul gol.

### Cum funcționează capacitatea generală

- Toate tipurile de bilete consumă din capacitatea generală, în afară de cele cu **stoc independent** (lacătul deschis de lângă câmpul Stoc).
- Un bilet se poate vinde cât timp are stoc propriu **și** mai e loc în capacitatea generală.
- În capacitate intră și invitațiile emise, și comenzile începute, dar neplătite încă.
- **Pagina evenimentului nu arată limita comună.** Când capacitatea generală s-a umplut, biletul pare disponibil pe site, iar clientul primește eroarea abia la finalizarea comenzii: „Capacitatea totală a evenimentului a fost atinsă”.
- Suma stocurilor poate depăși capacitatea generală. Formularul nu te oprește; arată un avertisment roșu doar când stocul unui singur tip e mai mare decât capacitatea.

## Bilete Test POS {#bilete-test-pos}

Un bilet de test de 10 lei, cu 10 bucăți, pentru probarea aplicației Tixello POS: vânzare, printare și scanare.

- Nu apare pe site și nu intră în vânzări, decont sau facturi.
- Există doar dacă organizatorul are activă opțiunea **Bilete Test POS** în setările lui. Altfel secțiunea spune că biletul de test nu există, iar **Reset stoc** nu face nimic.
- Cele trei cifre arată câte bilete de test s-au vândut, câte au rămas și totalul.
- **Reset stoc** șterge biletele de test emise și comenzile lor de test, apoi readuce stocul la 10. Acțiunea se aplică imediat, fără salvare.
- În lista **Tipuri de bilete** biletul apare ca „Test POS”, cu etichetele Offline și App. Nu-l modifica și nu-l șterge.

> Orice tip de bilet numit exact „Test POS” e ascuns de pe site, indiferent de celelalte setări.

## Avertismentul despre Cererea de Avizare {#bilete-avizare}

Dacă s-a generat deja o Cerere de Avizare pentru eveniment, în secțiunea Bilete apare un avertisment galben. Cererea preia stocurile, prețurile și seriile biletelor din momentul generării. Dacă modifici apoi stocul, prețul sau seria unui tip de bilet, generează documentul din nou. Nimic nu se regenerează automat.

## Lista „Tipuri de bilete” {#bilete-lista}

Fiecare tip de bilet e un card strâns. Îl deschizi cu un clic pe titlu. Tipurile noi se adaugă cu **Adaugă tip bilet**, la finalul listei.

### Ce arată titlul cardului

- **✓** în fața numelui: biletul e activ.
- **○**: biletul e oprit și nu apare pe site. Lângă ○ apare motivul:
  - **Programat** + data: pornește automat la data respectivă;
  - **Autostart**: pornește când se epuizează biletul de deasupra lui;
  - **Expirat**: data din „Activ până la” a trecut;
  - **Dezactivat**: oprit manual.
- Etichetele:
  - **SOLD OUT**: marcat manual ca epuizat;
  - **Online**: se vinde pe site; **Offline** + **App**: se vinde doar din aplicația de la intrare;
  - **Declarabil**: intră în documentele fiscale;
  - **Returnabil**: clientul poate cere rambursarea;
  - **Abonament**: tratat ca abonament în documentele fiscale;
  - **🎁 COD**: bilet gratuit cu cod; „(oprit)” înseamnă că codul e dezactivat.

### Butoanele din dreapta titlului

| Buton | Pornit înseamnă | Efect |
|---|---|---|
| 📱 **App** | Bilet vândut la intrare | Biletul **dispare de pe site** și apare în aplicația de vânzare de la intrare. Ca aceeași categorie să se vândă și online, și la intrare, fă două tipuri de bilet. |
| 📄 **Declarabil** (pornit implicit) | Bilet declarat fiscal | Oprit → biletul nu mai apare în Cererea de Avizare, în declarație și în procesul-verbal. |
| ↩ **Returnabil** | Bilet rambursabil | Clientul poate trimite o cerere de rambursare din contul lui. Rambursarea nu se face automat. |
| 🕒 **Abonament** | Abonament | Documentele fiscale îl trec separat, la abonamente. Pe biletul PDF apare toată perioada evenimentului. |
| ⃠ **Sold Out** | Epuizat manual | Biletul rămâne pe site, gri, cu „Epuizat”, și nu se mai poate cumpăra online. Stocul nu se modifică. Poate porni automat biletul de sub el (Autostart). |
| **Duplică** | — | Adaugă la final o copie cu „[DUP]” în fața numelui, fără vânzări și fără serie. Copia păstrează toate celelalte setări, inclusiv Sold Out, App și codul de bilet gratuit. |

> ⚠️ Butoanele schimbă doar formularul. **Nimic nu se aplică până nu salvezi.**

### Ordinea biletelor

Trage cardurile ca să schimbi ordinea. Ordinea din listă e ordinea de pe site. Tot ea stabilește care e biletul „de deasupra” pentru Autostart (vezi capitolul „Disponibilitate”).

### Ștergerea unui tip de bilet

- Un tip de bilet fără nicio vânzare se șterge definitiv la salvare.
- **Un tip de bilet care are sau a avut vânzări nu poate fi șters**, nici după anularea ori rambursarea comenzilor. Sistemul refuză cu mesajul „Nu poți șterge tipul de bilet… există N comenzi și M bilete vândute pe acest tip”. Reîncarcă apoi pagina și verifică dacă restul modificărilor s-au salvat.
- Ca să scoți din vânzare un astfel de bilet: oprește **Activ** (dispare de pe site) sau marchează-l **Sold Out** (rămâne vizibil, gri).

## Scenarii {#bilete-general-scenarii}

<details>
<summary>Am șters un tip de bilet, am salvat și primesc eroare.</summary>

Tipul are sau a avut cel puțin un bilet vândut. Chiar și biletele anulate sau rambursate rămân în istoric, așa că ștergerea e blocată definitiv, deși mesajul sugerează anularea sau rambursarea. Reîncarcă pagina. Apoi oprește **Activ** în secțiunea Disponibilitate (biletul dispare de pe site) sau apasă ⃠ **Sold Out** (biletul rămâne vizibil, gri) și salvează.

</details>

<details>
<summary>Pe site biletul apare disponibil, dar clientul primește „Capacitatea totală a evenimentului a fost atinsă”.</summary>

S-a umplut **Capacitatea generală**, comună tuturor biletelor fără stoc independent. Pagina evenimentului arată doar stocul fiecărui bilet, nu și limita comună. Ai trei variante:

- mărești **Capacitatea generală**;
- o golești, dacă evenimentul nu are limită comună;
- dai stoc independent biletului (lacătul de lângă Stoc), dacă biletul nu trebuie să consume din capacitate (de exemplu parcarea).

</details>

<details>
<summary>În admin scrie „Disponibil: 40”, dar clienții primesc mesajul că s-a atins capacitatea.</summary>

Cifra din admin numără doar biletele valide și cele scanate. Verificarea de la plată mai numără comenzile începute și încă neplătite, rezervate între 15 și 45 de minute, plus invitațiile emise. Așteaptă câteva minute să expire comenzile neplătite sau mărește capacitatea.

</details>

<details>
<summary>Am completat „Preț la intrare” și pe site apare un preț tăiat, cu reducere.</summary>

Așa funcționează, deși indicația din formular spune că prețul nu e afișat public. Când prețul la intrare e mai mare decât prețul biletului, site-ul îl afișează tăiat și calculează procentul de reducere. Golește câmpul dacă nu vrei acest efect.

</details>

<details>
<summary>Capacitatea generală e 500 și am trei tipuri de bilete cu stoc 300 fiecare. E o problemă?</summary>

Nu. Vânzarea totală a celor trei se oprește la 500. Dezavantajul: după ce se ating cele 500 de bilete, unele tipuri par încă disponibile pe site, iar clientul află abia la plată. Dacă un tip nu trebuie să consume din cele 500 (de exemplu parcarea), dă-i stoc independent cu lacătul de lângă Stoc.

</details>

<details>
<summary>Vreau ca pe site biletele de parcare să apară separat de biletele de acces.</summary>

1. Pornește **Grupează tipurile de bilete**.
2. La fiecare tip de bilet alege sau creează un **Grup**, de exemplu „Acces” și „Parcare”.
3. Salvează.

Pe site fiecare grup devine un panou care se deschide. Primul grup e deschis, iar biletele fără grup apar la final. Grupurile apar în ordinea primului bilet din fiecare grup.

</details>

<details>
<summary>Am adăugat beneficii la bilet, dar nu apar pe site.</summary>

Verifică dacă e pornit **Condiții / Beneficii per tip bilet**. Fără el, beneficiile nu apar pe site. Atenție: beneficiile deja salvate pot apărea totuși pe biletul PDF, dacă șablonul biletului le afișează.

</details>

<details>
<summary>Am modificat stocul după ce s-a generat Cererea de Avizare.</summary>

Cererea a fost generată cu stocul și seria vechi. La schimbarea stocului se schimbă automat și **Serie end**, deci documentul nu mai corespunde. Generează din nou Cererea de Avizare.

</details>

<details>
<summary>Am duplicat un tip de bilet și acum salvarea dă eroarea „Același cod e folosit de două tipuri de bilet”.</summary>

Tipul original era bilet gratuit cu cod, iar copia a preluat același cod. Deschide copia și schimbă sau golește **Cod promo** în secțiunea „Bilet gratuit cu cod promo”, apoi salvează. Dacă golești codul, copia devine bilet normal, vizibil pe site; verifică și prețul.

</details>

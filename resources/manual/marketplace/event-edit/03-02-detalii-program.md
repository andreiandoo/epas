---
id: detalii-program
chapter: Detalii
title: Titlu, adresă web și program
tab: detalii
applies_to: toate
covers: ["Detalii"]
fields: [slug, event_series, duration_mode, event_date, start_time, door_time, end_time, range_start_date, range_end_date, range_start_time, range_end_time, multi_slots, date, recurring_start_date, recurring_weekday, recurring_frequency, recurring_count, recurring_week_of_month, recurring_start_time, recurring_door_time, recurring_end_time]
updated: 2026-09-24
---

Sub comutatoarele de status sunt titlul, adresa evenimentului pe site, seria de bilete și programul. Programul e cel mai important: el stabilește cum arată data pe site și dacă evenimentul se sparge în mai multe evenimente-copil.

## Titlu, Slug, Serie eveniment {#detalii-identificare}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Titlu eveniment** (obligatoriu) | Numele evenimentului peste tot: site, bilet, e-mailuri, documente. | La creare, completarea titlului generează automat slug-ul și seria. |
| **Slug** | Partea din adresa paginii: `ambilet.ro/bilete/`**`numele-evenimentului-1234`**. | Doar litere mici, cifre și cratime. La editare nu se mai schimbă singur când schimbi titlul. |
| **Serie eveniment** | Prefixul seriei de bilete, de forma `AMB-1234`. | Se blochează după ce a fost setat, pentru că intră în numărul de serie tipărit pe bilete. |

> ⚠️ **Nu schimba slug-ul unui eveniment deja promovat.** Nu există redirecționare de la adresa veche: toate linkurile din reclame, coduri QR, e-mailuri și rezultate Google duc la o pagină goală. Dacă tot trebuie schimbat, fă-o înainte de publicare, iar dacă nimerești un slug deja folosit de alt eveniment, salvarea dă eroare de server, nu un mesaj de validare.

## Programul: cele patru moduri {#detalii-program-moduri}

Câmpul **Durată** alege modul. Fiecare mod arată alte câmpuri și produce alt rezultat.

| Mod | Când îl folosești | Ce produce |
|---|---|---|
| **O singură zi** | Concert, spectacol, o singură reprezentație. | Un singur eveniment. Data apare ca „15 Mai 2026”. |
| **Interval** | Festival sau expoziție care ține câteva zile la rând, cu un singur bilet. | Un singur eveniment. Data apare ca „15-20 Sep 2026”. |
| **Mai multe zile** | Mai multe reprezentații, fiecare cu ora ei, vândute de pe aceeași pagină. | Un eveniment-părinte, plus câte un eveniment-copil și o reprezentație pentru fiecare zi. |
| **Recurent** | Spectacol care se repetă săptămânal sau lunar. | Câte un eveniment-copil pentru fiecare apariție. |

### O singură zi

**Data**, **Ora start** (singurul câmp de program obligatoriu), **Ora acces**, **Ora final**. Fără ora de final, evenimentul e considerat încheiat la 23:59.

### Interval

**Data început**, **Data final**, **Ora start**, **Ora final**. Atenție: sistemul nu verifică dacă data de final e după cea de început, deci recitește-le înainte de salvare.

### Mai multe zile

În lista **Zile și ore** adaugi câte un rând pentru fiecare zi, cu **Data** (obligatorie), **Ora start**, **Ora acces** și **Ora final**.

La salvare se creează, pentru fiecare rând:

- un **eveniment-copil** cu pagina lui, vizibil în lista de evenimente sub titlul părintelui;
- o **reprezentație**, folosită pe pagina publică pentru alegerea zilei și pentru prețurile pe reprezentație.

### Recurent

**Data inițială** (ziua săptămânii se completează singură), **Recurență** (Săptămânal sau Lunar, a N-a zi), **Ocurențe**, **Ora start** (obligatorie), plus orele de acces și de final. La „Lunar”, alegi și **Săptămâna din lună**.

- Dacă lași **Ocurențe** gol, se creează **un singur** eveniment.
- Dacă nu alegi **Recurență**, nu se creează niciunul.
- La „Lunar, a N-a zi”, o apariție care nu încape în lună (de exemplu „a patra marți”) e sărită, deci poți primi mai puține date decât ai cerut. Pentru un rezultat constant, alege „Ultima”.
- Pe site apar evenimentele-copil, nu părintele.

## Modificarea programului după ce s-au vândut bilete {#detalii-program-modificari}

- **O singură zi** și **Interval**: schimbi datele liniștit, se schimbă doar ce se afișează. Biletele rămân valabile.
- **Mai multe zile**: rândurile sunt potrivite cu evenimentele-copil **după poziția în listă**, nu după dată. Dacă modifici data unui rând, copilul respectiv primește data nouă și își păstrează biletele vândute. **Nu reordona rândurile** după ce s-au vândut bilete: ai muta biletele pe alte zile.
- Tot la „Mai multe zile”: reprezentațiile sunt identificate după dată și ora de start, deci modificarea unei date creează o reprezentație nouă. Dacă pe cea veche există bilete vândute, ea rămâne în sistem. După o astfel de schimbare, verifică lista de reprezentații.
- Dacă ștergi un rând, evenimentul-copil se șterge doar dacă nu are bilete vândute; altfel e păstrat.
- Dacă schimbi modul (de exemplu din „Mai multe zile” în „O singură zi”), evenimentele-copil și reprezentațiile deja create **nu se șterg**. Verifică lista de evenimente după o astfel de schimbare.

## Datele din trecut {#detalii-program-minim}

Câmpurile de dată nu acceptă zile trecute cât timp evenimentul e viitor. Restricția dispare automat la evenimentele care au început deja sau s-au încheiat, ca să poți corecta alte câmpuri fără să fii obligat să muți data. La „Zile și ore”, un rând cu o dată deja trecută rămâne editabil.

## Scenarii {#detalii-program-scenarii}

<details>
<summary>Spectacolul se joacă în trei seri diferite. Ce mod aleg?</summary>

**Mai multe zile**, cu câte un rând pentru fiecare seară. Clientul alege seara direct pe pagina evenimentului. Dacă vrei prețuri diferite pe seri, folosește tipuri de bilete separate („Standard – Vineri” etc.), pentru că prețurile pe reprezentație nu se încasează corect deocamdată.

</details>

<details>
<summary>Festival de 4 zile, cu un singur abonament. Ce mod aleg?</summary>

**Interval**, cu data de început și cea de final. Nu ai nevoie de evenimente-copil, pentru că biletul e unul singur pentru toată perioada. Pentru bilete de o zi, adaugă tipuri de bilete cu „Bilet de 1 zi — valabil în data”.

</details>

<details>
<summary>Am schimbat ordinea zilelor într-un eveniment „Mai multe zile” și acum datele s-au amestecat.</summary>

Rândurile sunt legate de evenimentele-copil după poziție, nu după dată. Readu rândurile în ordinea inițială, salvează și verifică fiecare eveniment-copil din lista de evenimente. Dacă biletele au ajuns pe zile greșite, cere ajutorul echipei tehnice înainte să mai salvezi.

</details>

<details>
<summary>Spectacol în fiecare joi, opt săptămâni la rând.</summary>

**Recurent** → Data inițială = primul joi, Recurență = Săptămânal, Ocurențe = 8, Ora start completată. La salvare se creează opt evenimente, vizibile sub titlul părintelui în lista de evenimente. Tipurile de bilete se configurează pe fiecare eveniment-copil.

</details>

<details>
<summary>De ce nu pot alege o dată din trecut?</summary>

Pentru evenimentele viitoare, datele trecute sunt blocate intenționat. Dacă evenimentul a început deja sau s-a terminat, restricția dispare de la sine și poți corecta datele.

</details>

<details>
<summary>Pot schimba adresa (slug-ul) unui eveniment publicat?</summary>

Poți, dar nu e recomandat: adresa veche nu redirecționează nicăieri, iar toate linkurile existente se rup. Dacă evenimentul a fost promovat, lasă slug-ul așa cum e. La evenimentele „Mai multe zile” și „Recurent”, adresa evenimentelor-copil rămâne oricum pe forma veche.

</details>

<details>
<summary>La ce folosește „Serie eveniment” și de ce nu o pot modifica?</summary>

E prefixul numerelor de serie tipărite pe bilete. Se blochează după prima setare tocmai ca biletele deja emise să rămână coerente. Nu depinde de slug, deci redenumirea adresei nu afectează numerotarea.

</details>

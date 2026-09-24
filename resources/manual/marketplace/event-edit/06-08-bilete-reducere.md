---
id: bilete-reducere
chapter: Bilete
title: "Tipul de bilet: Reducere"
tab: bilete
applies_to: toate
covers: ["Reducere"]
fields: [has_sale, price, discount_percent, sales_start_at, sales_end_at, sale_stock]
updated: 2026-09-24
---

Reducerea e un **preț promoțional** pe un tip de bilet, cu un interval opțional. Cât timp reducerea e în desfășurare, clientul vede prețul întreg tăiat, procentul de reducere și plătește prețul redus.

## Câmpurile {#bilete-reducere-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Activează reducere** | Afișează restul câmpurilor. | Oprit + salvare = **se șterg toate setările reducerii**. Se pornește singur dacă biletul are deja date de reducere. |
| **Preț promoțional** | Prețul plătit efectiv în timpul reducerii. | Nu poate fi 0 (se salvează ca „fără reducere”). Dacă e mai mare decât prețul normal, sistemul îl acceptă: clientul plătește mai mult și nu apare niciun preț tăiat. |
| **Reducere %** | Ajutor de calcul: scrii procentul, se completează prețul promoțional (și invers). | Nu se salvează ca atare. Ce contează e prețul promoțional. |
| **Început reducere** | De când se aplică prețul redus. Gol = imediat. | Vezi avertismentul de mai jos. O oră din trecut e mutată automat la „acum”. |
| **Sfârșit reducere** | Până când se aplică. Gol = fără termen. | Dacă **Descrierea** biletului e goală, se completează singură cu „Reducere până la …”. Textul rămâne și după terminarea reducerii. |
| **Stoc reducere** | Ar trebui să închidă oferta după un număr de bilete. | ⚠️ **Nu funcționează.** Numărătoarea nu se face, deci reducerea ține până la data de sfârșit, indiferent câte bilete se vând. |

## Ce vede clientul {#bilete-reducere-site}

- Prețul întreg apare tăiat deasupra prețului redus, iar lângă numele biletului apare „-X%”.
- Dacă evenimentul are completat **Preț la intrare** și acesta e mai mare, el devine prețul tăiat, în locul prețului normal.
- În coș și la plată apare și totalul economisit.

## Când se aplică prețul redus {#bilete-reducere-aplicare}

- Prețul redus se aplică dacă, **în momentul finalizării comenzii**, suntem în interval. Dacă reducerea se termină cât biletele stau în coș, clientul plătește prețul întreg.
- La un minut după ora de sfârșit, sistemul **șterge** setările reducerii: preț promoțional, interval și stoc de reducere. Nu rămâne un istoric în tipul de bilet.
- Comisionul se calculează pe prețul redus (vezi capitolul „Comision personalizat”).
- Un cod de reducere se poate folosi peste prețul redus: reducerea codului se aplică la prețul deja redus.

> ⚠️ **Evită reducerile programate în viitor.** Până la ora de început, câmpul **Preț promoțional** apare gol în formular, iar orice salvare a evenimentului în acest interval șterge prețul promoțional și lasă doar intervalul. Dacă vrei o ofertă care începe la o anumită oră, folosește în schimb un tip de bilet separat, programat din secțiunea **Disponibilitate**.

## Scenarii {#bilete-reducere-scenarii}

<details>
<summary>Vreau 20% reducere la un bilet de 100 de lei, până duminică seara.</summary>

1. Deschide secțiunea **Reducere** și pornește **Activează reducere**.
2. Scrie 20 la **Reducere %** — prețul promoțional devine 80.
3. Lasă **Început reducere** gol (pornește imediat) și pune **Sfârșit reducere** duminică, ora dorită.
4. Salvează și verifică pe pagina evenimentului că apare prețul tăiat și „-20%”.

</details>

<details>
<summary>Vreau reducere doar pentru primele 100 de bilete.</summary>

**Stoc reducere** nu oprește oferta, deci nu te baza pe el. Fă în schimb două tipuri de bilete:

1. „Early Bird” cu prețul redus și **Stoc** = 100;
2. „Standard” cu prețul întreg, cu **Activ** oprit și **Autostart când precedentul e sold out** pornit, pus imediat sub Early Bird.

Când se termină cele 100 de bilete, Standard pornește singur.

</details>

<details>
<summary>Vreau ca reducerea să înceapă luni la ora 10:00.</summary>

Nu folosi **Început reducere** (vezi avertismentul de mai sus). Creează un tip de bilet separat, cu prețul redus, cu **Activ** oprit și **Programează activare** luni la 10:00. Biletului cu prețul întreg pune-i **Activ până la** aceeași oră, dacă cele două nu trebuie să se vândă în paralel.

</details>

<details>
<summary>Ce se întâmplă exact la ora de sfârșit a reducerii?</summary>

Din acel moment se încasează prețul întreg. Într-un minut, setările reducerii se șterg automat, iar prețul tăiat dispare de pe site. Verifică **Descrierea** biletului: textul „Reducere până la …” rămâne acolo și trebuie șters manual.

</details>

<details>
<summary>Am pus prețul promoțional, dar pe site nu apare niciun preț tăiat.</summary>

Verifică:

1. Prețul promoțional e mai mic decât **Prețul**? Dacă e mai mare, nu apare nimic tăiat, iar clientul plătește prețul mai mare.
2. **Început reducere** e în viitor? Atunci reducerea încă nu e activă — și, atenție, o salvare o poate șterge.
3. Ai salvat evenimentul?
4. Biletul e epuizat? La biletele epuizate nu se afișează prețuri tăiate.

</details>

<details>
<summary>Clientul a pus biletul în coș la preț redus, dar a plătit prețul întreg.</summary>

Reducerea se terminase până la finalizarea comenzii. Prețul se stabilește la crearea comenzii, nu la adăugarea în coș. Dacă a fost o situație nefericită, poți compensa clientul cu un cod de reducere.

</details>

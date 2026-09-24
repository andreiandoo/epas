---
id: bilete-serie
chapter: Bilete
title: "Tipul de bilet: Serie bilete"
tab: bilete
applies_to: toate
covers: ["Serie bilete"]
fields: [series_start, series_end]
updated: 2026-09-24
---

Seria e intervalul de numere alocat unui tip de bilet, folosit în documentele fiscale (Cererea de Avizare, declarația, procesul-verbal) și la numerotarea biletelor.

## Câmpurile {#bilete-serie-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Serie start** | Primul număr din interval, de exemplu `AMB-4942-12345-00001`. | Se completează automat. |
| **Serie end** | Ultimul număr, calculat din stoc, de exemplu `AMB-4942-12345-00500`. | Se **rescrie automat** de fiecare dată când schimbi Stocul. |

Formatul obișnuit e `{seria evenimentului}-{numărul tipului de bilet}-{număr din 5 cifre}`. Seria evenimentului se generează la crearea lui (de exemplu `AMB-4942`).

## De reținut {#bilete-serie-atentie}

- Câmpurile se completează singure după prima salvare a tipului de bilet. Până atunci pot fi goale; e normal.
- La stoc nelimitat (-1), seria se calculează ca pentru 1000 de bilete.
- Dacă scrii manual un alt format, sistemul îl păstrează. Schimbarea stocului rescrie totuși **Serie end**.
- La duplicarea unui tip de bilet, seriile copiei rămân goale și se regenerează la salvare.
- **Documentele fiscale preiau seriile și stocul din momentul generării.** Dacă modifici după aceea stocul sau seria, documentul nu mai corespunde și trebuie generat din nou (vezi avertismentul galben din tab-ul Bilete).

## Scenarii {#bilete-serie-scenarii}

<details>
<summary>Am mărit stocul de la 300 la 500. Ce se întâmplă cu seria?</summary>

**Serie end** devine automat `…-00500`. Dacă exista deja o Cerere de Avizare generată cu 300 de bilete, generează documentul din nou.

</details>

<details>
<summary>Câmpurile de serie sunt goale la un tip de bilet nou.</summary>

E normal înainte de prima salvare. Salvează evenimentul, apoi redeschide tipul de bilet: seriile apar completate.

</details>

<details>
<summary>Organizatorul cere o altă numerotare.</summary>

Poți scrie manual alt format în Serie start și Serie end. Ține minte că orice modificare ulterioară a stocului rescrie Serie end, deci verifică după fiecare schimbare de stoc.

</details>

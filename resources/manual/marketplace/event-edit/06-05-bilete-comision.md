---
id: bilete-comision
chapter: Bilete
title: "Tipul de bilet: Comision personalizat"
tab: bilete
applies_to: toate
covers: ["Comision personalizat"]
fields: [commission_type, commission_rate, commission_fixed, commission_mode]
updated: 2026-09-24
---

Secțiunea stabilește comisionul pe **acest tip de bilet**. Implicit, biletul moștenește comisionul din contractul organizatorului. Folosește secțiunea doar când un anumit bilet are alt comision decât restul.

## Câmpurile {#bilete-comision-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Tip comision** | Moștenește setările / Procentual / Fix / Procentual + Fix. | „Moștenește” = comisionul organizatorului. Când alegi altceva, câmpurile se pre-completează cu valorile moștenite. |
| **Procent %** | Procentul aplicat prețului biletului. | Apare la Procentual și Procentual + Fix. |
| **Sumă fixă** | Suma fixă pe fiecare bilet. | Apare la Fix și Procentual + Fix. Nu poți scrie mai puțin decât minimul organizatorului, dacă acesta e activ. |
| **Mod comision** | Inclus în preț / Adăugat la preț. | Apare doar când ai ales un comision propriu. La „Moștenește” se folosește modul organizatorului. |

Sub câmpuri apare o linie explicativă:

- gri — „Moștenit de la organizator, conform contract: 5% · Inclus în preț”;
- roșu — „Floor comision activ: minim 2,50 RON/bilet”, adică un minim pe bilet stabilit în contractul organizatorului.

## Cele două moduri, cu exemplu {#bilete-comision-moduri}

Bilet de 100 lei, comision 5%:

| Mod | Clientul plătește | Organizatorul primește | Pe biletul PDF scrie |
|---|---|---|---|
| **Inclus în preț** | 100 lei | 95 lei | „Preț: 100,00 lei (taxă procesare inclusă: 5,00 lei)” |
| **Adăugat la preț** | 105 lei | 100 lei | „Preț: 100,00 lei + 5,00 lei taxă procesare (105,00 lei)” |

## Pe ce sumă se calculează {#bilete-comision-baza}

- Pe **prețul plătit pentru bilet**: prețul promoțional dacă reducerea e activă, altfel prețul întreg.
- **Înainte** de codul de reducere. Exemplu: bilet redus de la 100 la 80 lei, plus cod de reducere 10% → clientul plătește 72 lei pentru bilet, iar comisionul de 5% se calculează la 80 lei, deci 4 lei.
- Minimul pe bilet din contractul organizatorului (dacă e activ) se aplică oricum, chiar dacă procentul dă o sumă mai mică.
- La biletele gratuite cu cod, minimul organizatorului nu se aplică; ele au implicit comision fix 0.

> ⚠️ Pe pagina evenimentului, taxa de procesare afișată clientului nu ține cont de minimul pe bilet și poate folosi alt mod decât cel din contractul organizatorului. După ce schimbi comisionul sau modul, deschide pagina evenimentului și verifică suma afișată la „Detalii preț bilet”.

## Scenarii {#bilete-comision-scenarii}

<details>
<summary>Las biletul pe „Moștenește”. De unde se ia comisionul?</summary>

Din contractul organizatorului evenimentului. Dacă organizatorul nu are comision propriu, se folosește cel al marketplace-ului. Linia gri de sub câmpuri îți arată exact ce se aplică.

</details>

<details>
<summary>Organizatorul are minim 2,50 lei pe bilet. Pot pune comision fix 2 lei pe un bilet?</summary>

Nu. Formularul nu acceptă sub 2,50, iar la vânzare comisionul ar fi ridicat oricum la 2,50. Dacă înțelegerea s-a schimbat, modifică întâi contractul organizatorului.

</details>

<details>
<summary>Clientul zice că a plătit mai mult decât scria pe pagina evenimentului.</summary>

Cel mai probabil comisionul e pe „Adăugat la preț”, iar suma afișată pe pagină nu a inclus minimul pe bilet sau modul corect. Verifică în secțiunea Comision ce e setat, compară cu suma din comandă și semnalează echipei tehnice dacă diferența se repetă.

</details>

<details>
<summary>Vreau ca un singur tip de bilet (de exemplu invitațiile de protocol) să nu aibă comision.</summary>

La acel tip de bilet alege **Tip comision** = Fix, **Sumă fixă** = 0 și salvează. Dacă organizatorul are minim pe bilet activ, formularul nu va accepta 0 — atunci discută întâi contractul.

</details>

<details>
<summary>Cum văd cât a rămas organizatorului?</summary>

În tab-ul **Vânzări** și în decontul evenimentului. La modul „Inclus în preț” comisionul se scade din suma încasată; la „Adăugat la preț” clientul îl plătește separat, iar organizatorul primește prețul întreg al biletului.

</details>

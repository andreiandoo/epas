---
id: bilete-reprezentatii
chapter: Bilete
title: "Tipul de bilet: Prețuri per reprezentație"
tab: bilete
applies_to: toate
covers: ["Prețuri per reprezentație"]
fields: [meta.performance_prices, perf_id, price, stock, series_start, series_end]
updated: 2026-09-15
---

O reprezentație e o dată și o oră din programul unui eveniment pe mai multe zile (tab-ul Detalii). Secțiunea permite ca același tip de bilet să aibă alt preț sau alt stoc la fiecare reprezentație.

> ⚠️ **Situația de acum:** pagina evenimentului și coșul arată prețul per reprezentație, dar **la plată clientul plătește prețul tipului de bilet** (sau prețul promoțional). Stocul per reprezentație nu limitează vânzarea, iar un rând cu prețul gol apare pe site cu 0,00 lei. Până la remediere, pentru prețuri sau stocuri diferite pe date folosește **tipuri de bilete separate**, de exemplu „Standard – Vineri” și „Standard – Sâmbătă”.

## Când apare secțiunea {#bilete-reprezentatii-vizibilitate}

- Evenimentul are program pe mai multe zile, cu reprezentații completate în tab-ul Detalii.
- În tab-ul Bilete e pornit **Prețuri diferite per reprezentare**.
- Evenimentul a fost salvat cel puțin o dată după completarea programului. Reprezentațiile se creează la salvare; până atunci secțiunea îți cere să salvezi.

## Cum configurezi {#bilete-reprezentatii-pasi}

1. Completează programul pe mai multe zile în tab-ul Detalii și salvează.
2. În tab-ul Bilete pornește **Prețuri diferite per reprezentare**.
3. Deschide tipul de bilet → secțiunea **Prețuri per reprezentație** → **+ Adaugă preț**.
4. Alege reprezentația și completează **Preț**. Opțional, completează **Stoc**.
5. Salvează.

| Câmp | Ce face |
|---|---|
| **Reprezentația** | Data și ora. Fiecare reprezentație se poate alege o singură dată pe tipul de bilet. |
| **Preț** | Prețul afișat pentru reprezentația aleasă. Nu-l lăsa gol: un preț gol apare pe site ca 0,00 lei. |
| **Stoc** | Gol = stocul tipului de bilet. Folosit acum doar pentru numerotarea seriilor. |
| **Serie start / Serie end** | Se completează singure la salvare. Nu se editează. |

## Atenție {#bilete-reprezentatii-atentie}

- Dacă oprești **Prețuri diferite per reprezentare** și salvezi, **toate prețurile per reprezentație se șterg** definitiv.
- Dacă schimbi data sau ora unei reprezentații în Detalii, ea devine o reprezentație nouă, iar prețurile setate pentru ea se pierd.
- Ce alegi în secțiune înainte de prima salvare a programului nu se păstrează. Salvează întâi, apoi alege reprezentațiile.

## Scenarii {#bilete-reprezentatii-scenarii}

<details>
<summary>Spectacolul are trei seri și vreau ca seara de sâmbătă să fie mai scumpă.</summary>

Din cauza situației de mai sus, nu folosi deocamdată prețurile per reprezentație. Creează tipuri de bilete separate, de exemplu „Standard – Vineri”, „Standard – Sâmbătă” și „Standard – Duminică”, fiecare cu prețul și stocul lui. Clientul plătește exact prețul afișat.

</details>

<details>
<summary>Nu văd secțiunea „Prețuri per reprezentație”.</summary>

Verifică:

1. Evenimentul are program pe mai multe zile (tab-ul Detalii).
2. **Prețuri diferite per reprezentare** e pornit în tab-ul Bilete.
3. Ai salvat evenimentul după completarea programului.

Dacă scrie „Salvează evenimentul pentru a genera reprezentațiile…”, salvează și revino.

</details>

<details>
<summary>Am mutat ora unei reprezentații și prețurile ei au dispărut.</summary>

Schimbarea datei sau a orei creează o reprezentație nouă, iar prețurile legate de cea veche se pierd. Adaugă din nou prețul pentru reprezentația nouă și salvează.

</details>

<details>
<summary>Am pus stoc 100 la fiecare reprezentație. Se vând maximum 100 de bilete pe seară?</summary>

Nu. Acum vânzarea e limitată doar de **Stocul** tipului de bilet, comun tuturor reprezentațiilor. Pentru o limită pe fiecare seară, folosește tipuri de bilete separate pe seară.

</details>

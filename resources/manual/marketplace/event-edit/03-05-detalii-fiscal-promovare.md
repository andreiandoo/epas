---
id: detalii-fiscal-promovare
chapter: Detalii
title: Direcție fiscală și promovarea pe site
tab: detalii
applies_to: toate
covers: ["Direcție fiscală (Tax Registry)", "Setări Featured"]
fields: [marketplace_tax_registry_id, is_homepage_featured, is_general_featured, is_category_featured, generate_fomo, has_custom_related, custom_related_event_ids, homepage_featured_image, featured_image]
updated: 2026-09-24
---

Ultimele două secțiuni din tab-ul Detalii: direcția fiscală folosită pentru declarațiile de impozit pe spectacole și locurile în care evenimentul apare promovat pe site.

## Direcție fiscală {#detalii-fiscal}

Secțiunea apare **doar după ce ai ales o locație**. Direcția fiscală e primăria sau direcția de impozite către care se face declarația de impozit pe spectacole.

- Un banner verde confirmă direcția identificată automat; unul galben te anunță că nu s-a potrivit niciuna și trebuie să alegi manual.
- Potrivirea automată se face după orașul și județul locației și se recalculează **doar** când schimbi locația sau când duplici evenimentul. Nu se recalculează la fiecare salvare.
- Potrivirea e permisivă: o direcție fiscală care are orașul sau județul necompletat se potrivește cu orice eveniment, iar prima găsită câștigă. De aici vin majoritatea greșelilor.
- Dacă evenimentul nu are nicio direcție fiscală, documentele tot se generează, dar pe prima direcție fiscală activă a marketplace-ului — adică, foarte probabil, către instituția greșită.

**Verifică direcția fiscală înainte de a genera documentele** și, dacă e greșită, alege-o manual din listă.

## Setări Featured {#detalii-promovare}

| Comutator | Unde apare evenimentul | Condiții |
|---|---|---|
| **Featured pe Homepage** | Carusel mare pe prima pagină | Evenimentul trebuie să fie publicat, neanulat și în viitor. |
| **Featured General** | Caruselul de evenimente recomandate, refolosit pe prima pagină, pe categorii, pe orașe și pe genuri | La fel. |
| **Featured în Categorie** | Carduri premium pe pagina categoriei | **Obligatoriu cu imagine Featured**, altfel evenimentul e eliminat automat. |

Imaginile:

| Câmp | Dimensiune recomandată | Se folosește |
|---|---|---|
| **Imagine Featured Homepage** | 1920×600 px | Apare doar când e bifat Featured pe Homepage. În acest moment site-ul nu o folosește: pentru carusel se ia tot imaginea Featured General sau imaginea mare a evenimentului. |
| **Imagine Featured (General/Categorie)** | 800×450 px (16:9) | Caruselul general și cardul premium de categorie. |

Dacă le lași goale, la salvare se copiază automat imaginea mare (hero) a evenimentului.

> Listele de evenimente promovate sunt ținute în cache **circa 30 de minute**. După ce bifezi ceva, e normal să nu vezi schimbarea imediat pe site.

### Generate FOMO

Activează pe pagina evenimentului elementele de urgență: „N bilete vândute în ultimele 24h”, „N persoane se uită acum”, o bară de cerere ridicată și notificări care apar în colț.

> ⚠️ **Cifrele nu sunt reale.** Vânzările din ultimele 24 de ore sunt umflate față de realitate, numărul de vizitatori e generat, iar „locurile rămase” pornesc de la aproximativ jumătate din stocul real. Folosește opțiunea cu cap: la un eveniment cu vânzări slabe, cifrele pot fi vizibil nerealiste. Blocul nu apare la evenimentele încheiate.

### Evenimente conexe

Implicit, la finalul paginii evenimentului apar primele 4 evenimente viitoare din aceeași categorie.

Dacă bifezi **Evenimente Conexe Personalizate**, poți alege până la 8 evenimente care apar în blocul „Îți recomandăm”. Acestea **se adaugă** la recomandările automate, nu le înlocuiesc. Evenimentele alese care se anulează sau trec sunt eliminate automat de pe site.

## Scenarii {#detalii-promovare-scenarii}

<details>
<summary>Direcția fiscală e greșită. Ce fac?</summary>

Alege manual direcția corectă din listă și salvează. Valoarea rămâne așa, pentru că se recalculează doar la schimbarea locației. Dacă aceeași greșeală apare la mai multe evenimente, cauza e o direcție fiscală cu orașul sau județul necompletat — semnalează, ca să fie corectată în setări.

</details>

<details>
<summary>Nu văd secțiunea „Direcție fiscală”.</summary>

Apare doar după ce alegi o locație. Fără ea, documentele se vor genera pe direcția implicită a marketplace-ului, deci completează locația înainte de a genera ceva.

</details>

<details>
<summary>Am bifat „Featured pe Homepage”, dar evenimentul nu apare.</summary>

Verifică, în ordine: evenimentul e publicat; nu e anulat; data e în viitor; au trecut ~30 de minute de la bifare (cache). Caruselul afișează un număr limitat de evenimente, ordonate după dată, deci un eveniment îndepărtat în timp poate fi împins în afara listei.

</details>

<details>
<summary>Am bifat „Featured în Categorie” și tot nu apare.</summary>

Pagina de categorie afișează doar evenimentele care au **Imagine Featured (General/Categorie)**. Încarcă imaginea (800×450 px) și salvează.

</details>

<details>
<summary>Ce imagine pun la Featured?</summary>

Pentru carusel și categorie: 800×450 px, adică 16:9, cu subiectul spre centru (marginile pot fi tăiate). Dacă nu încarci nimic, se folosește imaginea mare a evenimentului, care nu are mereu proporția potrivită.

</details>

<details>
<summary>Organizatorul cere să apară „Ultimele bilete” pe pagină.</summary>

Asta face **Generate FOMO**. Explică-i că cifrele afișate sunt parțial generate, nu date reale de vânzare, ca să știe ce promite publicului.

</details>

<details>
<summary>Vreau ca la finalul paginii să apară alte trei evenimente ale aceluiași organizator.</summary>

Bifează **Evenimente Conexe Personalizate** și alege evenimentele dorite. Ține minte că blocul automat cu evenimente din aceeași categorie rămâne afișat și el.

</details>

---
id: detalii-locatie
chapter: Detalii
title: Locație și link-uri
tab: detalii
applies_to: toate
covers: ["Locație și Link-uri"]
fields: [venue_id, suggested_venue_name, seating_layout_id, marketplace_city_id, address, website_url, facebook_url, event_website_url]
updated: 2026-09-24
---

Secțiunea stabilește unde are loc evenimentul, dacă se vinde cu locuri numerotate și ce linkuri apar pe pagina publică.

## Câmpurile {#detalii-locatie-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Locație** | Sala sau spațiul unde are loc evenimentul. | Alegerea unei locații completează automat alte câmpuri (vezi mai jos). Lista conține doar locațiile din țările configurate pentru marketplace. |
| **Locație sugerată de organizator** | Textul scris de organizator în panoul lui, când n-a găsit locația în listă. | Doar informativ, nu se poate edita. Creează locația și alege-o în câmpul de deasupra. |
| **Harta de locuri** | Harta cu locuri numerotate folosită la vânzare. Gol = acces general. | Apare **doar** dacă locația aleasă are cel puțin o hartă publicată. O hartă rămasă în lucru (draft) nu apare. |
| **Oraș** | Orașul sub care apare evenimentul în filtrele și paginile de oraș. | Se completează automat din locație. |
| **Adresă** | Adresa evenimentului. | Dacă locația are deja adresă, pe site se afișează adresa locației, nu ce scrii aici. |
| **Website** | Site-ul locației (se preia automat de la ea). | Nu apare pe pagina evenimentului. Pentru site-ul evenimentului folosește câmpul de mai jos. |
| **Eveniment Facebook** | Link către evenimentul de Facebook. | Apare pe pagina publică, ca buton. |
| **Website Eveniment** | Site-ul propriu al evenimentului. | Apare pe pagina publică, ca buton. |

Lângă locație poate apărea pastila galbenă **„Monument Istoric”**: înseamnă doar că spațiul are marcată taxa de monument istoric, configurată în altă parte.

## Ce se întâmplă când alegi o locație {#detalii-locatie-efecte}

La fiecare schimbare a locației, formularul:

1. **șterge harta de locuri** aleasă (și reprezentația selectată), chiar dacă alegi aceeași locație din nou;
2. suprascrie **Adresa** și **Website** cu datele locației;
3. completează **Orașul** — iar dacă orașul locației nu există încă în marketplace, îl **creează pe loc**;
4. recalculează **Direcția fiscală**.

Butoanele de lângă câmp deschid, într-un tab nou, editarea locației alese sau crearea uneia noi. După ce creezi o locație nouă, reîncarcă pagina evenimentului ca să apară în listă.

## Harta de locuri {#detalii-locatie-harta}

- Alegerea hărții face să apară tab-ul **Harta** și adaugă la fiecare tip de bilet câmpul „Culoare pe hartă”.
- Editorul hărții funcționează abia **după ce salvezi** evenimentul cu harta aleasă.
- Locurile evenimentului se creează la prima deschidere a hărții în admin sau la prima vizitare a paginii publice. Dacă nu s-a întâmplat niciuna, e normal să pară că „nu s-a creat nimic”.

> ⚠️ **Nu schimba harta de locuri după ce s-au vândut bilete.** Harta evenimentului e refăcută de la zero: locurile vândute se recuperează doar dacă au același identificator în harta nouă, iar **toate blocările manuale de locuri se pierd**. Dacă tot trebuie făcut, verifică imediat după aceea harta și reblochează locurile.

> ⚠️ **Golirea câmpului nu transformă evenimentul în acces general.** Site-ul revine la prima hartă publicată a locației. Pentru un eveniment fără locuri numerotate, alege o locație care nu are hartă publicată.

## Scenarii {#detalii-locatie-scenarii}

<details>
<summary>Am schimbat locația după ce s-au vândut bilete. Ce verific?</summary>

1. **Adresa** și **Orașul** — au fost suprascrise automat; verifică dacă sunt corecte.
2. **Harta de locuri** — a fost golită. Dacă evenimentul se vinde cu locuri, alege harta noii locații, salvează și verifică harta.
3. **Direcția fiscală** — a fost recalculată.
4. Anunță cumpărătorii printr-un **Newsletter**: schimbarea locației nu generează niciun email.

</details>

<details>
<summary>Nu găsesc locația în listă.</summary>

Verifică întâi scrierea. Dacă locația chiar nu există, creeaz-o cu butonul „+” de lângă câmp (se deschide într-un tab nou), apoi reîncarcă pagina evenimentului. Dacă locația e în altă țară decât cele configurate pentru marketplace, nu va apărea niciodată în listă — cere ajutorul unui administrator.

</details>

<details>
<summary>Nu văd deloc câmpul „Harta de locuri”.</summary>

Locația aleasă nu are nicio hartă publicată. Cel mai frecvent, harta există, dar e încă în lucru. Verifică în secțiunea de hărți de locuri dacă e publicată.

</details>

<details>
<summary>Am ales harta, dar tab-ul „Harta” îmi cere să salvez.</summary>

Normal: tab-ul apare imediat, dar editorul se încarcă doar după ce salvezi evenimentul cu harta aleasă. Salvează și reintră în tab.

</details>

<details>
<summary>Am modificat adresa, dar pe site apare tot cea veche.</summary>

Dacă evenimentul are o locație cu adresă completată, pagina publică afișează adresa locației. Corectează adresa în locație, nu în eveniment.

</details>

<details>
<summary>Orașul evenimentului e scris greșit sau s-a creat unul nou, duplicat.</summary>

Orașul se creează automat după numele scris în locație. Alege orașul corect din câmpul **Oraș**, apoi corectează orașul în fișa locației, ca să nu se repete la următorul eveniment. Orașul duplicat trebuie curățat de un administrator.

</details>

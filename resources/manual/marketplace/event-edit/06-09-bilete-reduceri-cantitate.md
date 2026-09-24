---
id: bilete-reduceri-cantitate
chapter: Bilete
title: "Tipul de bilet: Reduceri la cantitate"
tab: bilete
applies_to: toate
covers: ["Reduceri la cantitate"]
fields: [bulk_discounts, rule_type, buy_qty, get_qty, min_qty, percent_off, amount_off, bundle_total_price]
updated: 2026-09-24
---

Secțiunea permite reguli de tipul „cumperi 3, primești 1 gratis” sau „de la 5 bilete, 10% reducere”.

> ⚠️ **Regulile nu au efect pe ambilet.ro.** Se salvează, dar nu apar pe pagina evenimentului, nu schimbă prețul la plată și nu apar în comenzi sau rapoarte. Secțiunea e folosită deocamdată doar de site-urile care funcționează pe platformă separat. Nu promite unui organizator astfel de oferte până nu se activează.

## Tipurile de reguli {#bilete-cantitate-reguli}

| Tip regulă | Câmpuri | Ce ar însemna |
|---|---|---|
| **Cumperi X primești Y gratis** | Cumperi, Primești gratis | La fiecare X bilete cumpărate, Y sunt gratuite. |
| **Cumperi X bilete → % reducere** | Cantitate min, % reducere | De la un număr de bilete în sus, procent de reducere pe toate. |
| **Reducere pe bilet (min cantitate)** | Cantitate min, Reducere/bilet | De la un număr de bilete în sus, o sumă fixă mai puțin pe fiecare bilet. |
| **Preț pachet (X bilete la preț total)** | Cantitate min, Preț total pachet | Un număr de bilete la un preț total. |

## Ce poți face între timp {#bilete-cantitate-alternative}

- **Ofertă de grup:** creează un tip de bilet separat, de exemplu „Pachet 4 persoane”, cu prețul per bilet mai mic, cu **Min bilete/comandă** = 4 și **Multiplicator** = 4. Clientul nu poate cumpăra mai puțin de 4 bilete de acest tip, iar biletele rămân individuale (fiecare persoană are biletul ei).
- **Reducere pentru toată lumea:** folosește secțiunea **Reducere**, cu preț promoțional și interval.
- **Reducere pentru un public anume:** folosește un cod de reducere (butonul **Coduri reducere** din bara de sus).

## Scenarii {#bilete-cantitate-scenarii}

<details>
<summary>Organizatorul vrea „Cumperi 3, plătești 2”.</summary>

Nu se poate configura funcțional acum. Variante apropiate:

- un tip de bilet „Pachet 3 bilete” la prețul a două bilete, cu **Min bilete/comandă** = 3 și **Multiplicator** = 3;
- un cod de reducere cu procentul echivalent (aproximativ 33%), dat celor care cumpără 3 bilete.

</details>

<details>
<summary>Am completat deja reguli aici. Trebuie să le șterg?</summary>

Nu e obligatoriu, pentru că nu fac nimic. Dar e mai curat să le ștergi, ca să nu creadă alt coleg că oferta e activă.

</details>

---
id: bilete-reduceri-cantitate
chapter: Bilete
title: "Tipul de bilet: Reduceri la cantitate"
tab: bilete
applies_to: toate
covers: ["Reduceri la cantitate"]
fields: [bulk_discounts, rule_type, buy_qty, get_qty, min_qty, percent_off, amount_off, bundle_total_price]
updated: 2026-11-24
---

Secțiunea permite oferte de tipul „cumperi 2, primești 1 gratis”.

> **Funcționează doar „Cumperi X primești Y gratis”**, și doar dacă marketplace-ul are pornit **Setări → Oferte „Cumperi X primești Y gratis”**. Celelalte tipuri apar în listă cu „(în curând)” și nu se pot alege încă.

## Tipurile de reguli {#bilete-cantitate-reguli}

| Tip regulă | Câmpuri | Stare |
|---|---|---|
| **Cumperi X primești Y gratis** | Cumperi, Primești gratis | Activ |
| **Cumperi X bilete → % reducere** | Cantitate min, % reducere | În curând |
| **Reducere pe bilet (min cantitate)** | Cantitate min, Reducere/bilet | În curând |
| **Preț pachet (X bilete la preț total)** | Cantitate min, Preț total pachet | În curând |

## Cum funcționează „Cumperi X primești Y gratis” {#bilete-cantitate-xy}

Exemplu: tipul **Presale**, regula Cumperi 2, Primești gratis 1.

- Clientul plătește 2 bilete **Presale** la prețul întreg și primește în aceeași comandă 1 bilet gratuit.
- Biletul gratuit se emite pe tipul **Invitatie** al evenimentului, cu 0 lei. Pe bilet și în email apare „Invitatie – ofertă 2+1 Presale”.
- Biletul gratuit nu are comision (nici minimul organizatorului), nu are asigurare și **nu intră în documentele fiscale** (avizare, impozite, declarații), la fel ca orice invitație. Biletele Presale se declară normal.
- **Fără hartă de locuri:** clientul alege câte bilete plătește, iar biletele gratuite se adaugă automat (2 → +1, 4 → +2).
- **Cu hartă de locuri:** clientul alege pe hartă toate locurile. În fiecare grupă de 3 locuri, primele 2 se plătesc, al treilea e gratuit.
- **Coduri de reducere:** nu se aplică pe biletele din ofertă. Se aplică normal pe celelalte bilete din coș, inclusiv la alte evenimente.
- **Stoc:** biletele gratuite nu scad din stocul Presale, dar intră în capacitatea totală a evenimentului, dacă e setată. Cu stoc Presale 100 și oferta 2+1 pot intra până la 150 de persoane, deci ajustează stocul sau capacitatea totală.
- **Rambursare:** oferta se rambursează doar întreagă. Dacă se cere rambursarea unui bilet din ofertă, celelalte bilete ale ei (inclusiv invitația) se adaugă automat la rambursare.

## Scenarii {#bilete-cantitate-scenarii}

<details>
<summary>Organizatorul vrea „Cumperi 3, plătești 2”.</summary>

Setează regula Cumperi 2, Primești gratis 1. Clientul plătește 2 și primește 3 bilete.

</details>

<details>
<summary>Am pus regula, dar pe site nu apare oferta.</summary>

Verifică să fie pornit **Setări → Oferte „Cumperi X primești Y gratis”** și ca tipul de bilet să aibă preț mai mare de 0. Pagina evenimentului de pe site poate păstra datele vechi până la 30 de minute.

</details>

---
id: bilete-gratuit-cod
chapter: Bilete
title: "Tipul de bilet: Bilet gratuit cu cod promo"
tab: bilete
applies_to: standard
covers: ["Bilet gratuit cu cod promo"]
fields: [meta.free_with_code.code, meta.free_with_code.enabled, meta.free_with_code.trigger_ticket_type_ids, free_max_per_order]
updated: 2026-09-15
---

Un bilet gratuit, ascuns pe site, pe care clientul îl deblochează cu un cod (de exemplu de pe un flyer). Se acordă doar împreună cu un bilet plătit, în aceeași comandă. Cazul tipic: bilet gratuit pentru copilul însoțit de un adult cu bilet plătit.

## Cum configurezi {#bilete-gratuit-pasi}

1. Adaugă un tip de bilet nou, de exemplu „Copil însoțit”, și pune-i **Stoc**.
2. Deschide secțiunea **🎁 Bilet gratuit cu cod promo**.
3. Scrie un cod în **Cod promo** sau apasă ✨ ca să generezi unul (de exemplu „FAMILIE4827”). Formularul completează automat:
   - **Preț** = 0;
   - **Cod activ** pornit;
   - maximum 3 bilete gratuite pe comandă;
   - comision fix 0, inclus în preț (doar dacă biletul moștenea comisionul).
4. Opțional, alege în **Se acordă la cumpărarea** biletele plătite care dau dreptul la biletul gratuit. Gol = orice bilet plătit.
5. Salvează.

## Câmpurile {#bilete-gratuit-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Cod promo** | Codul care deblochează biletul. 3–30 caractere: litere, cifre, cratimă. Se salvează cu majuscule. | Evită O/0 și I/1, ușor de confundat pe hârtie. Același cod nu poate fi pus pe două tipuri de bilet la același eveniment. **Golirea codului face biletul vizibil tuturor, la 0 lei.** |
| **Cod activ** | Oprit = codul nu mai funcționează. | Biletele gratuite emise rămân valabile. Tipul de bilet rămâne ascuns. |
| **Se acordă la cumpărarea** | Biletele plătite care deblochează gratuitatea. Gol = orice bilet plătit al evenimentului. | Lista arată doar biletele cu preț, deja salvate. Un bilet nou apare în listă după salvare. |
| **Max. bilete gratuite / comandă** | Câte bilete gratuite poate alege clientul într-o comandă (1–10). | Înlocuiește „Max bilete/comandă” pentru acest tip. |
| „Bilete gratuite emise până acum” | Câte bilete gratuite s-au dat în comenzi plătite. | Comenzile neplătite nu sunt numărate. |

Secțiunea nu apare la locațiile de agrement.

## Ce vede și ce face clientul {#bilete-gratuit-client}

1. Pe pagina evenimentului, deasupra biletelor, apare câmpul **„Ai un cod de pe flyer?”**.
2. Clientul scrie codul și apasă **Aplică**. Poate folosi și un link direct, de exemplu `…/eveniment?cod=FAMILIE4827`, bun pentru codurile QR de pe flyere.
3. Dacă e valid, apare mesajul „Cod aplicat…” și clientul alege câte bilete gratuite vrea, până la maximul setat. Biletul apare cu „Gratuit”. La evenimentele cu hartă, fiecare bilet gratuit are nevoie de loc.
4. Clientul trebuie să adauge și cel puțin un bilet plătit, altfel primește „Adaugă și un bilet plătit pentru a primi biletele gratuite.”

La plată, sistemul verifică din nou toate regulile:

- codul e activ și corect;
- numărul de bilete gratuite nu depășește maximul pe comandă;
- comanda conține cel puțin un bilet plătit la același eveniment (dintre cele alese la „Se acordă la cumpărarea”, dacă ai ales);
- același e-mail nu a mai primit acest bilet gratuit într-o comandă plătită anterior („Ai folosit deja codul…”).

Câmpul pentru cod nu apare la evenimentele încheiate, blocate sau cu vânzare doar la intrare. Codul funcționează doar în acest câmp de pe pagina evenimentului, nu și în câmpul de cod de reducere din coș.

## Unde se mai vede biletul gratuit {#bilete-gratuit-efecte}

- **Stoc**: biletele gratuite consumă din stocul lor și din Capacitatea generală, dacă stocul nu e independent.
- **Comanda**: biletul gratuit e o linie de 0 lei, lângă biletele plătite.
- **Decont**: apare ca rând separat de 0 lei. Dacă pui un comision fix (de exemplu 2 lei pe bilet gratuit), rândul iese negativ: comisionul se scade din banii organizatorului.
- **Rambursare**: dacă se rambursează ultimul bilet plătit din comandă, biletele gratuite se anulează odată cu el. Cât timp rămâne un bilet plătit, biletele gratuite rămân valabile.

## Scenarii {#bilete-gratuit-scenarii}

<details>
<summary>Organizatorul vrea bilet gratuit pentru copilul însoțit, cu cod tipărit pe flyer.</summary>

Urmează pașii din „Cum configurezi”. Pe flyer tipărește codul și, dacă se poate, un QR cu linkul evenimentului plus `?cod=CODUL`. Părintele alege pe site biletul lui plătit și biletele gratuite, în aceeași comandă.

</details>

<details>
<summary>Un client a cumpărat ieri bilet plătit, iar azi vrea biletul gratuit cu codul.</summary>

Nu se poate. Biletul gratuit se acordă doar în aceeași comandă cu un bilet plătit. Dacă e-mailul a mai primit o dată biletul gratuit, codul e blocat pentru el oricum.

</details>

<details>
<summary>Am oprit „Cod activ”. Biletele gratuite deja emise mai sunt valabile?</summary>

Da. Se oprește doar folosirea codului de acum încolo. Biletul rămâne ascuns pe site.

</details>

<details>
<summary>Vreau să nu mai existe bilet gratuit. Șterg codul?</summary>

Nu. Fără cod, tipul devine bilet normal, **vizibil pentru toți, la 0 lei**. Oprește **Cod activ** sau oprește **Activ** în secțiunea Disponibilitate.

</details>

<details>
<summary>Am activat codul, dar clientul nu vede câmpul „Ai un cod de pe flyer?”.</summary>

Verifică:

- ai salvat;
- **Cod activ** e pornit;
- evenimentul nu e încheiat și nu e setat pe „Doar la intrare”.

Pagina evenimentului poate fi ținută în cache câteva minute, așa că reîncearcă puțin mai târziu. Între timp, clientul poate folosi linkul cu `?cod=CODUL`.

</details>

<details>
<summary>Cine plătește comisionul pe biletul gratuit?</summary>

Implicit nimeni: formularul pune comision fix 0, inclus în preț. Dacă AmBilet setează un comision fix pe biletul gratuit (secțiunea Comision personalizat), acesta se scade din decontul organizatorului.

</details>

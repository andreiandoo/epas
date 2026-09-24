---
id: bara-de-sus
chapter: Bara de butoane de sus
title: Butoanele din bara de sus
applies_to: toate
covers: []
fields: []
updated: 2026-09-24
---

Butoanele de deasupra formularului te duc la acțiuni legate de eveniment. Aproape toate te scot din pagină.

> ⚠️ **Salvează înainte să apeși orice buton din bara de sus** (cu excepția „Manual pagină” și „Coduri reducere”). Celelalte te duc pe altă pagină sau reîncarcă pagina, iar modificările nesalvate se pierd fără avertisment.

## Butoanele {#bara-butoane}

| Buton | Ce face | Atenție |
|---|---|---|
| **Manual pagină** | Deschide acest manual peste formular. | Sigur: nu atinge formularul. |
| **Create Invitations** | Pagina de invitații, cu evenimentul deja ales. | Apare doar dacă modulul de invitații e activ. Părăsește pagina. |
| **Bilete Externe (N)** | Importul biletelor vândute pe alte platforme. N = câte există deja. | Vânzările importate apar doar în caseta „Vânzări terți” din dreapta; sunt excluse din rapoarte și deconturi. |
| **Coduri reducere** | Formularul de cod de reducere, cu evenimentul și organizatorul completate. | **Se deschide în tab nou**, deci e singurul sigur cu modificări nesalvate. |
| **Newsletter** | Newsletter către cumpărătorii acestui eveniment. | Părăsește pagina. E calea prin care anunți clienții despre anulări, amânări sau schimbări. |
| **Încarcă Imagini** | Fereastră pentru poster (vertical) și imaginea mare (orizontală). | Salvează imaginile imediat, apoi **reîncarcă pagina** — orice altă modificare nesalvată se pierde. |
| **Capacitate Zilnică** | Capacitățile pe zile. | Doar la locațiile de agrement. |
| **Duplică** | Copie nepublicată a evenimentului, fără vânzări. | Vezi capitolul „Privire de ansamblu”. |
| **Șterge** | Șterge evenimentul. | Vezi mai jos: de obicei e refuzată, iar când trece nu se mai poate anula. |

## Încărcarea imaginilor {#bara-imagini}

Fereastra are două zone: **Poster (vertical)** și **Imagine hero (orizontală)**. Pentru fiecare poți:

- **Încărca un fișier nou** — maximum 10 MB, doar JPEG, PNG sau WebP;
- **Alege din bibliotecă** — caută după numele fișierului. Căutarea ignoră diacriticele și semnele, deci „concerte vara” găsește „Concerte-Vară-2024.jpg”.

După salvare, imaginile se optimizează automat și pot fi convertite în WebP, deci numele final al fișierului poate să difere de cel încărcat. E normal.

## Ștergerea unui eveniment {#bara-stergere}

- Dacă evenimentul are **măcar un bilet emis sau o linie de comandă**, ștergerea e refuzată. Se pune la socoteală orice bilet, inclusiv cele anulate, cele de test POS și cele dintr-o **comandă de test**.
- Mesajul de eroare poate apărea ca o pagină de eroare generică, nu ca o notificare prietenoasă. Dacă vezi asta după ce ai apăsat Șterge, cel mai probabil evenimentul are vânzări.
- Când ștergerea chiar trece (eveniment fără nicio vânzare), dispar definitiv tipurile de bilete, reprezentațiile, harta evenimentului și documentele generate. **Nu există anulare și nu există restaurare.**
- Nu există nicio restricție de rol: orice operator care poate deschide pagina poate apăsa butonul. Pentru evenimente care nu mai trebuie să apară, preferă **Publicat = oprit** în loc de ștergere.

## Scenarii {#bara-scenarii}

<details>
<summary>Am pierdut modificările după ce am încărcat imagini.</summary>

Fereastra de imagini salvează direct imaginile și apoi reîncarcă pagina, iar ce era nesalvat în formular se pierde. Ordinea corectă: completezi formularul → salvezi → încarci imaginile.

</details>

<details>
<summary>Trebuie să anunț cumpărătorii despre o schimbare.</summary>

Folosește **Newsletter**: formularul se deschide cu evenimentul deja selectat, iar mesajul ajunge la toți cumpărătorii cu bilete valide ale acelui eveniment. Un client care a cumpărat la mai multe evenimente primește un singur email. Salvează întâi modificările din eveniment.

</details>

<details>
<summary>Nu pot șterge un eveniment creat din greșeală.</summary>

Probabil are deja bilete emise — chiar și un singur bilet de test sau o comandă de test blochează ștergerea definitiv. Lasă-l nepublicat, eventual redenumește-l ca să se vadă că nu e real, sau cere ajutorul echipei tehnice.

</details>

<details>
<summary>Vreau un cod de reducere pentru evenimentul ăsta.</summary>

Apasă **Coduri reducere**: se deschide în tab nou, cu evenimentul și organizatorul completate automat. Fiind în tab nou, nu pierzi ce ai nesalvat în formular.

</details>

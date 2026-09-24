---
id: ansamblu
chapter: Privire de ansamblu
title: Cum e organizată pagina evenimentului
applies_to: toate
covers: []
fields: []
updated: 2026-09-15
---

Pagina evenimentului adună tot ce ține de un eveniment: data, locația, conținutul, biletele, harta de locuri, documentele și publicarea. Capitolul acesta te orientează în pagină. Fiecare câmp e explicat în capitolul tab-ului din care face parte.

## Cum folosești manualul {#ansamblu-manual}

- Butonul **Manual pagină** din bara de sus deschide manualul peste formular. Ce ai completat și n-ai salvat rămâne neatins.
- Manualul se deschide direct la capitolul tab-ului în care te afli.
- Căutarea arată doar secțiunile care conțin toate cuvintele tastate. Diacriticele nu contează: „reducere cantitate” găsește „Reduceri la cantitate”.
- Întrebările din secțiunile **Scenarii** se deschid cu un clic.
- **Pagină completă** deschide manualul într-un tab nou, ca să-l poți ține deschis lângă formular.
- Închizi manualul cu tasta **Esc**, cu **×** sau cu un clic în afara lui.

## Cum e împărțită pagina {#ansamblu-structura}

Sus apare titlul evenimentului, urmat în paranteză de oraș și dată. La evenimentele pe mai multe zile apare intervalul.

### Bara de butoane de sus

| Buton | Ce face |
|---|---|
| **Manual pagină** | Deschide acest manual. |
| **Create Invitations** | Apare doar dacă modulul de invitații e activ. Te duce în pagina de invitații, cu evenimentul deja ales. |
| **Bilete Externe** | Importul biletelor vândute în afara platformei. Numărul din paranteză arată câte bilete externe există deja. |
| **Coduri reducere** | Deschide într-un tab nou formularul de cod de reducere, cu evenimentul și organizatorul completate. |
| **Newsletter** | Pornește un newsletter adresat cumpărătorilor acestui eveniment. |
| **Încarcă Imagini** | Deschide o fereastră pentru poster (vertical) și imaginea mare. Imaginile se salvează imediat, fără butonul de salvare. |
| **Capacitate Zilnică** | Apare doar la locațiile de agrement. |
| **Duplică** | Creează o copie nepublicată a evenimentului, fără vânzări (vezi scenariul de mai jos). |
| **Șterge** | Șterge evenimentul, după confirmare. |

### Coloana principală: tab-urile

| Tab | Ce găsești | Când apare |
|---|---|---|
| **Detalii** | Statusuri (sold out, anulat, amânat, promovat), data și programul, locația, evenimentul online, direcția fiscală, promovarea pe site, alertele | Mereu |
| **Vânzări** | Cifrele de vânzare ale evenimentului, în timp real | Mereu |
| **Deconturi** | Deconturile pe perioade | Doar la locațiile de agrement |
| **Conținut** | Titlu, descriere, imagini, categorie, genuri, artiști | Mereu |
| **Configurare Locație** | Un buton care te duce în panoul de agrement al organizatorului | Doar la locațiile de agrement |
| **Bilete** | Tipurile de bilete: prețuri, stoc, reduceri, comision, disponibilitate | Mereu |
| **Harta** | Harta de locuri a evenimentului | Doar după ce ai ales o hartă de locuri în Detalii |
| **Grupare** | Includerea evenimentului într-un turneu sau într-o serie | Nu apare la locațiile de agrement |
| **Observații** | Notițe interne despre eveniment și despre tipurile de bilete | Mereu |
| **Documente** | Situația documentelor evenimentului și documentele generate | Mereu |

Sub tab-uri e secțiunea **SEO**, strânsă implicit. O deschizi cu un clic pe titlu.

### Coloana din dreapta

- **Aprobă evenimentul** și **Respinge** apar doar când un organizator a trimis evenimentul spre aprobare și evenimentul nu e încă publicat sau respins.
- Publicarea și accesul: **Publicat**, statusul evenimentului, linkul de previzualizare, linkul de comandă de test, **Parolă acces eveniment**, **Tip pagină**, **Redirect**.
- **Vânzări terți**, **Locuri Blocate**, **Organizator** (cu notificările automate trimise organizatorului), **Activitate recentă**, **Checklist publicare**.

## Salvarea: ce se salvează și când {#ansamblu-salvare}

- Tot ce modifici în tab-uri și în coloana din dreapta se salvează **doar** la butonul verde de salvare, fixat în colțul din dreapta-jos.
- Poți lucra în mai multe tab-uri și salva o singură dată. Trecerea de la un tab la altul nu pierde modificările.
- Câteva butoane acționează imediat, independent de salvare: **Încarcă Imagini**, **Duplică**, **Șterge**, **Aprobă evenimentul**, **Respinge**.

> ⚠️ **Pagina nu te avertizează dacă pleci cu modificări nesalvate.** Butoanele care te duc pe altă pagină în același tab (Create Invitations, Bilete Externe, Newsletter, meniul din stânga) pierd tot ce n-ai salvat. Salvează înainte să apeși pe ele. **Coduri reducere** se deschide într-un tab nou, deci nu pierzi nimic.

## Tab-urile care se încarcă la cerere {#ansamblu-incarcare}

- Vânzări, Conținut, Bilete, Harta, Grupare, Deconturi și Configurare Locație se încarcă abia când dai clic pe ele. La prima deschidere pot dura o secundă-două. E normal.
- La locațiile de agrement, lista tipurilor de bilete poate fi mare, așa că apare doar după butonul **Încarcă biletele**. La evenimentele obișnuite biletele apar direct.
- Adresa din browser se schimbă când schimbi tab-ul. Poți copia adresa ca să trimiți unui coleg un link direct către tab-ul Bilete al evenimentului.

## Doi operatori pe același eveniment {#ansamblu-concurenta}

Dacă un alt operator are deschisă aceeași pagină de eveniment, sus apare o bandă galbenă: „**Atenție:** [nume] este momentan pe această pagină și poate face modificări simultan.” Banda se actualizează cam la fiecare 15 secunde și dispare când celălalt pleacă.

Dacă salvați amândoi, salvarea făcută mai târziu scrie formularul exact cum îl avea deschis acel operator. Modificările făcute între timp de celălalt se pot pierde. Stabiliți cine editează.

## Scenarii {#ansamblu-scenarii}

<details>
<summary>Am schimbat prețul unui bilet, apoi am apăsat pe „Bilete Externe”. Unde e modificarea?</summary>

S-a pierdut. Pagina nu avertizează la plecare, iar butonul te-a dus pe altă pagină fără să salveze. Revino în eveniment, refă modificarea și salvează, apoi mergi la Bilete Externe.

</details>

<details>
<summary>Vreau să-i trimit unui coleg direct tab-ul Bilete al unui eveniment.</summary>

Deschide tab-ul **Bilete** și copiază adresa din bara browserului. Adresa conține tab-ul, așa că pagina se deschide direct acolo.

</details>

<details>
<summary>Nu văd tab-ul „Harta”.</summary>

Tab-ul apare doar după ce alegi o hartă în câmpul **Harta de locuri** (tab-ul Detalii, secțiunea Locație și Link-uri). Câmpul apare doar dacă locația aleasă are cel puțin o hartă de locuri publicată. Dacă locația nu are hartă, evenimentul e cu acces general și nu are tab Harta.

</details>

<details>
<summary>Nu văd butoanele „Aprobă evenimentul” și „Respinge”.</summary>

Apar doar pentru evenimentele trimise spre aprobare de organizator și care nu sunt nici publicate, nici respinse. Evenimentele create direct de operatori nu trec prin aprobare: le publici cu **Publicat** și salvezi.

</details>

<details>
<summary>Ce se copiază când apăs „Duplică”?</summary>

**Se copiază:** data și programul, locația, conținutul, categoriile, genurile, artiștii și tipurile de bilete (cu prețuri, comisioane și limitele pe comandă).

**Nu se copiază:**
- vânzările (tipurile de bilete pornesc de la zero);
- seriile de bilete;
- harta de locuri, pe care o alegi din nou dacă e cazul;
- publicarea și promovarea pe site (featured).

Titlul primește prefixul „[Duplicat]”, evenimentul nou e nepublicat, iar direcția fiscală se recalculează după locație. După duplicare ajungi direct în pagina evenimentului nou. Corectează titlul și data înainte să-l publici.

</details>

<details>
<summary>Un coleg lucrează la același eveniment în același timp. Ce fac?</summary>

Vezi banda galbenă din partea de sus. Stabiliți cine termină modificările. Celălalt reîncarcă pagina după ce primul a salvat, ca să lucreze pe varianta la zi.

</details>

---
id: coloana-dreapta
chapter: Coloana din dreapta
title: Publicare, acces și organizator
applies_to: toate
covers: ["SEO", "Organizator"]
fields: [rejection_reason, is_published, access_password, display_template, redirect_url, marketplace_organizer_id, organizer_notify_enabled, organizer_notifications]
updated: 2026-09-24
---

Coloana din dreapta decide dacă și cum se vede evenimentul public, cine e organizatorul și ce mai lipsește înainte de publicare.

## Aprobarea evenimentelor trimise de organizatori {#dreapta-aprobare}

Butoanele **Aprobă evenimentul** și **Respinge** apar doar când un organizator a trimis evenimentul spre aprobare, iar acesta nu e nici publicat, nici respins deja.

- **Aprobă** publică evenimentul imediat și trimite organizatorului o notificare în cont **și** un email.
- **Respinge** cere un motiv (minimum câteva cuvinte), îl trimite organizatorului și ascunde ambele butoane. Nu există buton de „anulare a respingerii”: fie publici manual din comutatorul **Publicat** (fără notificare către organizator), fie organizatorul retrimite evenimentul din contul lui, moment în care butoanele reapar.

Înainte de aprobare merită verificate: checklistul de publicare, pagina reală prin **Previzualizare**, organizatorul și comisionul, plus ca **Redirect** și **Parolă acces** să fie goale.

> Aprobarea nu reîmprospătează pagina publică. Dacă evenimentul nu apare imediat pe site, deschide linkul **Previzualizare**: el golește memoria temporară a paginii. Altfel, schimbarea apare în câteva minute.

## Publicare și acces {#dreapta-publicare}

| Element | Ce face | De reținut |
|---|---|---|
| **Publicat** | Comutatorul care face evenimentul vizibil public. | Publicarea de aici nu anunță organizatorul. Oprit = evenimentul dispare din listări și nu se mai poate cumpăra. |
| Badge-ul de stare | Arată doar **ANULAT**, **AMÂNAT** sau **ÎNCHEIAT**. | Nu există badge pentru „ciornă”, „în așteptare” sau „sold out”. |
| **Previzualizare** | Deschide pagina publică a evenimentului, chiar dacă nu e publicat. | Linkul funcționează pentru oricine îl are. Deschiderea lui golește memoria temporară a paginii — util după modificări. |
| **Link test** | Copiază un link cu care poți face o comandă de test. | Valabil **24 de ore**. Vezi mai jos. |
| **Parolă acces eveniment** | Cere o parolă la intrarea pe pagina evenimentului. | Ascunde pagina de vizitatorii obișnuiți, dar **nu e o măsură de securitate**: datele evenimentului rămân accesibile tehnic. Nu o folosi pentru informații confidențiale. |
| **Tip pagină** | Standard sau Locație de agrement. | La evenimentele obișnuite lasă „Standard”. Schimbarea afectează pagina publică și modul de calcul al decontului. |
| **Redirect** | Trimite vizitatorii către alt link. | Evenimentul rămâne în listări, dar pagina lui nu mai poate fi deschisă. Se aplică în câteva minute. Cu **Previzualizare** poți vedea pagina oricum. |

**Parolă și Redirect nu merg împreună:** redirecționarea are loc înaintea cererii de parolă, deci parola nu mai e cerută niciodată.

### Comanda de test

Linkul de test permite o comandă completă fără efecte în rapoarte: comanda are numărul prefixat cu `TEST-`, toate sumele 0, nu consumă stoc, nu trimite email de confirmare și e exclusă din rapoarte, deconturi și facturare.

> ⚠️ Comanda de test **creează bilete reale**, iar asta blochează definitiv ștergerea evenimentului. Folosește-o doar pe evenimente care vor rămâne oricum în sistem.

Nu folosi linkul simplu de **Previzualizare** pentru teste de cumpărare: pe un eveniment publicat el produce o comandă adevărată.

## Organizator {#dreapta-organizator}

Câmpul de organizator (etichetat „Marketplace organizer id”) e obligatoriu și listează doar organizatorii activi. Sub el, o casetă arată datele organizatorului: contact, stare, verificare, comision, număr de evenimente și venit.

> ⚠️ **Schimbarea organizatorului e retroactivă.** Toate comenzile evenimentului, inclusiv cele plătite și deja decontate, trec pe noul organizator. Istoricul dispare din contul celui vechi, iar un decont deja emis pentru vechiul organizator **nu mai blochează** un decont nou pentru cel nou — risc real de plată dublă. Dacă evenimentul are vânzări, verifică deconturile existente înainte de a schimba ceva.

Schimbarea organizatorului suprascrie și comisionul evenimentului, modul de comision și termenii biletului cu cele ale noului organizator. Dacă aveai un comision negociat separat, pune-l la loc înainte de salvare.

Comisionul afișat în caseta organizatorului e cel din contractul lui; comisionul care taxează efectiv biletele e cel din tab-ul Bilete, la fiecare tip de bilet.

### Notificări către organizator

Din cele bifate, **doar alerta de stoc scăzut funcționează efectiv**; celelalte tipuri bifate nu au încă efect.

Dacă vrei să oprești notificările, nu e suficient să stingi comutatorul: **debifează tipurile din listă** și abia apoi salvează, altfel selecția rămâne salvată și alertele continuă.

## Celelalte casete {#dreapta-casete}

- **Vânzări terți** — apare doar la evenimentele cu bilete importate de pe alte platforme. Arată platforma, numărul de bilete, venitul brut și numărul de comenzi. Cifrele sunt brute, fără comisioane sau rambursări, și nu intră în rapoarte sau deconturi.
- **Locuri Blocate** — locurile blocate manual pe harta evenimentului, grupate pe secțiuni și rânduri. Locurile marcate violet ar avea invitație, dar potrivirea se face doar după numărul locului, fără secțiune și rând: același număr din altă secțiune poate apărea greșit ca având invitație. Lista de invitații e sursa sigură.
- **Activitate recentă** — ultimele trei modificări ale evenimentului, cu link către istoricul complet.

## Checklist publicare {#dreapta-checklist}

Șase verificări rapide: titlu, imagini, locație, date, organizator și tipuri de bilete.

Checklistul e **doar orientativ** — nimic nu te împiedică să publici un eveniment „Incomplet”. Limitele lui:

- **Imagini** se actualizează abia după o salvare (citește ce e deja salvat, nu ce ai în formular);
- **Date setate** nu recunoaște evenimentele „Mai multe zile”, deci rămâne nebifat chiar dacă programul e complet;
- **Tipuri de bilete** numără și biletul „Test POS”, creat automat.

## Scenarii {#dreapta-scenarii}

<details>
<summary>Organizatorul a trimis un eveniment spre aprobare. Ce verific?</summary>

1. Checklistul de publicare (orientativ, dar util).
2. Deschide **Previzualizare** și citește pagina ca un client: titlu, imagini, descriere, prețuri.
3. Verifică organizatorul și comisionul din tab-ul Bilete.
4. Verifică să fie goale **Redirect** și **Parolă acces**.
5. Apasă **Aprobă evenimentul** — organizatorul primește notificare și email.

</details>

<details>
<summary>Am aprobat, dar pe site nu apare.</summary>

Aprobarea nu golește memoria temporară a paginii publice. Deschide linkul **Previzualizare** (o golește) sau apasă Salvează pe formular. Altfel, schimbarea apare în câteva minute.

</details>

<details>
<summary>Am respins din greșeală.</summary>

Nu există revenire directă. Fie publici manual cu comutatorul **Publicat** (organizatorul nu primește notificare de aprobare, deci anunță-l tu), fie îi ceri să retrimită evenimentul din contul lui, iar apoi apeși Aprobă.

</details>

<details>
<summary>Cum testez cumpărarea fără să stric rapoartele?</summary>

Copiază **Link test** din coloana din dreapta și deschide-l. Comanda rezultată e marcată ca test și nu intră nicăieri în rapoarte. Ține minte că biletele create astfel blochează pentru totdeauna ștergerea evenimentului.

</details>

<details>
<summary>Vreau ca evenimentul să fie vizibil doar pentru cine are linkul.</summary>

Cea mai curată variantă: lasă evenimentul **nepublicat** și trimite linkul de **Previzualizare**. Parola de acces ascunde pagina de vizitatorii obișnuiți, dar nu protejează cu adevărat informațiile.

</details>

<details>
<summary>Am schimbat organizatorul unui eveniment cu vânzări.</summary>

Verifică imediat: (1) deconturile deja emise pentru vechiul organizator — ca să nu se genereze un al doilea decont pentru aceleași bilete; (2) comisionul evenimentului, care a fost suprascris cu cel al noului organizator; (3) documentele deja generate, care păstrează datele vechi.

</details>

<details>
<summary>Checklistul arată „Date setate” nebifat, deși am completat programul.</summary>

Evenimentul e pe modul „Mai multe zile”, pe care checklistul nu îl recunoaște. Dacă zilele sunt completate în tab-ul Detalii, poți ignora acest punct.

</details>

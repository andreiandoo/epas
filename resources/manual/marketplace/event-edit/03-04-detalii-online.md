---
id: detalii-online
chapter: Detalii
title: Eveniment online
tab: detalii
applies_to: toate
covers: ["Eveniment online"]
fields: [is_online, online_provider, online_meeting_url, online_passcode, online_lobby_opens_minutes_before, online_capacity_hint, online_instructions]
updated: 2026-09-24
---

Secțiunea se folosește pentru evenimentele care se țin online (Zoom, Google Meet, Teams sau livestream). Apare doar dacă marketplace-ul are activ modulul de integrare video.

Ideea de bază: **linkul de meeting nu ajunge niciodată direct la client**. Fiecare bilet primește o adresă proprie de acces, iar sistemul dezvăluie linkul doar posesorului biletului, în intervalul potrivit.

## Câmpurile {#detalii-online-campuri}

| Câmp | Ce face | De reținut |
|---|---|---|
| **Acesta este un eveniment online** | Activează accesul online pentru biletele acestui eveniment. | Locația fizică rămâne opțională. |
| **Provider** | Zoom, Google Meet, Teams sau Custom (livestream). Implicit Zoom. | Numele apare pe pagina de acces a clientului. |
| **URL meeting** (obligatoriu) | Linkul real al întâlnirii. | Nu e afișat nicăieri public; se dezvăluie doar prin pagina de acces a biletului. |
| **Parolă meeting** | Parola întâlnirii, dacă există. | Se păstrează criptată și apare pe pagina de acces, lângă link. |
| **Lobby deschide cu (min. înainte)** | Cu câte minute înainte de start se poate intra. Implicit 15. | Dacă evenimentul nu are oră de început completată, accesul **nu se deschide niciodată**. |
| **Capacitate provider** | Câte locuri suportă planul tău (100/300/500/1000). | Doar informativ: dacă vinzi mai multe bilete decât atât, apare un avertisment. |
| **Instrucțiuni de acces** | Text afișat clientului pe pagina de acces. | Util pentru recomandări („intră cu 10 minute înainte”, „ai nevoie de aplicația X”). |

## Cum primește clientul accesul {#detalii-online-acces}

1. Imediat după plată, pe pagina de confirmare apare un buton de intrare pentru fiecare bilet.
2. Același buton apare în contul clientului, pe pagina biletului.
3. Pe biletul PDF, linkul apare doar dacă șablonul de bilet a fost configurat să-l includă.

> ⚠️ **Nu există niciun email automat cu linkul de acces.** Dacă vrei ca oamenii să-l primească pe email, cere echipei tehnice să adauge linkul de acces în șablonul de bilet, sau trimite-l manual printr-un newsletter către cumpărătorii evenimentului.

Pagina de acces a biletului are stări clare, utile când un client reclamă o problemă:

| Ce vede clientul | Cauza |
|---|---|
| Bilet inexistent | Codul e greșit. |
| Bilet anulat sau returnat | Biletul nu mai e valabil. |
| Comandă neplătită | Plata nu a fost finalizată. |
| „Configurare incompletă” | **URL meeting** e gol la eveniment. |
| „Prea devreme”, cu ora de deschidere | Nu s-a deschis încă lobby-ul. |
| „S-a încheiat” | A trecut ora de final (sau 4 ore de la start, dacă nu ai pus oră de final). |

## De reținut {#detalii-online-atentie}

- **Pagina publică nu spune nicăieri că evenimentul e online.** Clientul află abia după cumpărare. Scrie explicit în descrierea evenimentului că e online, iar dacă evenimentul e exclusiv online, **lasă Locația goală**, altfel pe pagină apare o hartă derutantă.
- Completează întotdeauna **data și ora de început**: fără ele, accesul nu se activează niciodată.
- Avertismentul de capacitate nu apare dacă ai măcar un tip de bilet cu stoc nelimitat.

## Scenarii {#detalii-online-scenarii}

<details>
<summary>Cum trimit linkul de Zoom cumpărătorilor?</summary>

Nu-l trimiți tu. Completezi **URL meeting** (și parola, dacă e cazul) și salvezi. Fiecare cumpărător primește un buton de acces pe pagina de confirmare a comenzii și în contul lui, iar linkul real i se arată cu 15 minute (sau cât ai setat) înainte de start.

</details>

<details>
<summary>Un client spune că linkul de acces nu funcționează.</summary>

Întreabă-l ce mesaj vede și compară cu tabelul de mai sus. Cele mai frecvente cauze: evenimentul nu are **URL meeting** completat, sau nu are oră de început, caz în care accesul nu se deschide niciodată.

</details>

<details>
<summary>Evenimentul e și în sală, și online (hibrid).</summary>

Completează normal locația și, în plus, secțiunea de eveniment online. Cine cumpără primește și butonul de acces online. Scrie clar în descriere care bilete dau acces în sală și care online, pentru că pagina publică nu face distincția.

</details>

<details>
<summary>Am schimbat linkul de meeting cu o oră înainte de eveniment.</summary>

Nicio problemă: clienții văd mereu linkul curent, pentru că li se dezvăluie în momentul în care apasă butonul de acces. Nu trebuie retrimis nimic.

</details>

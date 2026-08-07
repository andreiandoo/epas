# Tixello — Ghid complet de construcție a aplicației mobile

> **Scopul acestui document.** Ești o sesiune Claude într-un IDE cu acces complet la codul **EPAS** (backend Laravel/Filament, în acest repo) și la aplicația **tics**. Documentul de față este specificația unică și completă pentru construirea aplicației mobile **Tixello** — o singură aplicație (un build, o publicare în store) care conține **atât aplicația de client, cât și aplicația de organizator**, în toate variantele de cont.
>
> Documentul este autonom: descrie viziunea, arhitectura, design system-ul, **fiecare ecran, modal și stare** din ambele aplicații, modelul de date, fluxurile de plată, strategia offline și planul de implementare. **Sursa de adevăr pentru UX** o reprezintă cele două prototipuri interactive livrate alături (vezi §12). Când construiești un ecran, deschide prototipul corespunzător și replică 1:1 comportamentul.
>
> **Regulă de aur: nu omite și nu simplifica nimic din prototipuri.** Dacă un ecran/modal/stare apare mai jos sau în prototip, trebuie să existe în aplicația finală.

---

## Cuprins

1. [Viziune & context produs](#1-viziune--context-produs)
2. [Arhitectura aplicației (un build, un login)](#2-arhitectura-aplicației-un-build-un-login)
3. [Modelul de identitate & pasul de alegere a contului](#3-modelul-de-identitate--pasul-de-alegere-a-contului)
4. [Design system Tixello](#4-design-system-tixello)
5. [Aplicația de CLIENT — spec complet](#5-aplicația-de-client--spec-complet)
6. [Aplicația de ORGANIZATOR — spec complet](#6-aplicația-de-organizator--spec-complet)
7. [Tipuri de cont organizator (verticale)](#7-tipuri-de-cont-organizator-verticale)
8. [Modul Festival — cashless & brățări](#8-modul-festival--cashless--brățări)
9. [Îmbogățiri operaționale (offline, anti-passback, etc.)](#9-îmbogățiri-operaționale)
10. [Plăți & conformitate Apple (cei 30%)](#10-plăți--conformitate-apple)
11. [Offline-first & sincronizare](#11-offline-first--sincronizare)
12. [Fișiere livrate & cum le folosești](#12-fișiere-livrate--cum-le-folosești)
13. [Modelul de date & API (mapare EPAS)](#13-modelul-de-date--api-mapare-epas)
14. [Stack tehnic & structura proiectului](#14-stack-tehnic--structura-proiectului)
15. [Plan de implementare pe faze](#15-plan-de-implementare-pe-faze)
16. [Backlog & decizii deschise](#16-backlog--decizii-deschise)

---

## 1. Viziune & context produs

**Tixello** este platforma de ticketing & experiențe (rebranding peste EPAS/AmBilet). Ecosistemul are trei piese:

| Piesă | Ce este | Unde trăiește |
|---|---|---|
| **EPAS** (acest repo) | Backend Laravel 12 + Filament 4, multi-tenant, multi-limbă. Evenimente, bilete, comenzi, seating, dynamic pricing, email templates, Facebook CAPI, microservicii. | `epas/core/` |
| **tics** | Aplicația de experiențe (tours/activities). | repo separat |
| **Tixello Mobile** (de construit) | Aplicația mobilă unică: client + organizator. | `tixello-mobile/` |

**Principiu central:** o singură aplicație publicată în App Store & Google Play. Același binar servește:
- **clientul** (cumpără bilete, portofel cashless, experiențe, revânzare, invitații),
- **organizatorul** (scanare la intrare, POS, panou, rapoarte, echipă & porți, urgențe, festival cashless),

iar utilizatorul alege / comută între ele după login.

**Identitate vizuală:** dark-first, indigo/violet, tipografie **Outfit**. Clientul și organizatorul împart **exact** același design system (vezi §4) — o singură identitate, coerentă pe toate tipurile de cont.

---

## 2. Arhitectura aplicației (un build, un login)

```
                       ┌───────────────────────────┐
                       │      Un singur binar       │
                       │  (Capacitor: iOS/Android)  │
                       └─────────────┬─────────────┘
                                     │
                             ┌───────▼────────┐
                             │     LOGIN      │  (un singur ecran)
                             └───────┬────────┘
                                     │  emailul → identityProps()
                        ┌────────────┴─────────────┐
                        │  >1 proprietate?          │
              ┌─────────▼─────────┐        ┌────────▼────────┐
              │  ACCOUNT CHOOSER  │  NU →  │  intrare directă │
              │ (pas intermediar) │        │  în unica propr. │
              └─────────┬─────────┘        └────────┬─────────┘
                        │  alege                    │
        ┌───────────────┼───────────────────────────┘
        │               │
┌───────▼──────┐  ┌─────▼─────────────────────────────┐
│ CLIENT SHELL │  │ ORGANIZER SHELL                    │
│ Home/Explore │  │ (Organizator | Leisure navigator)  │
│ Tickets/     │  │ Panou/Scanare/Vânzare/Rapoarte/    │
│ Wallet/Profil│◄─┤ Setări  (+ Festival: Cashless)     │
└──────┬───────┘  └─────────────┬─────────────────────┘
       │  „Comută la organizator"  │  „Comută tipul de cont"
       └───────────────┬───────────┘
                       ▼
              revine la CHOOSER / toggle direct
```

**Layere:**
- **Web UI** (React recomandat) — tot UI-ul, temele, fluxurile. Aici se fac 95% din modificări → **OTA fără update în store**.
- **Suprafață nativă** (plugin-uri Capacitor) — cameră/QR, NFC, push, secure storage, SQLite, Stripe Terminal (Tap to Pay). Se schimbă rar → doar aici e nevoie de release în store.
- **Backend** — EPAS (tenant API) + Stripe (Connect pentru payout) + webhooks.

Vezi §14 pentru stack concret și §10 pentru de ce modelul acesta **evită cei 30% Apple**.

---

## 3. Modelul de identitate & pasul de alegere a contului

**Cerință:** un email poate avea mai multe „proprietăți" (client și/sau unul-sau-mai-mulți organizatori). După login:
- dacă are **> 1 proprietate** → arată **pasul intermediar „Alege contul"**;
- dacă are **o singură** proprietate → intră **direct** în ea (fără pas intermediar);
- utilizatorul poate **comuta oricând** client ↔ organizator.

Model de referință (din prototipul de organizator, funcția `identityProps()`):

```js
// pseudo-model
type Property =
  | { kind:'client', name, email }
  | { kind:'org', orgId, name, vertical:'organizer'|'theater'|'philharmonic'
        |'agency'|'venuearena'|'festival'|'leisure', role:'admin'|'manager'|'staff' }

// La login:
props = getPropertiesForEmail(email)   // din API
if (props.length > 1) show ACCOUNT_CHOOSER(props)
else applyProperty(props[0])

// Switch:
switchMode(): client ↔ prima proprietate de organizator (sau invers)
goChooser(): reafișează pasul de alegere
```

**Ecranul „Alege contul"** (spec):
- Header cu logo Tixello, salut („Bună, {prenume} 👋").
- Text: „Emailul tău are acces la mai multe conturi. Alege unde vrei să intri — poți comuta oricând din meniul de profil."
- Câte un **card per proprietate**: avatar/iconiță colorată, nume, sub-descriere (+ rol pentru organizatori), badge „Client"/„Organizator", chevron.
- Card informativ: „Cu o singură proprietate pe email, acest pas se sare și intri direct."
- Link jos: „Nu ești tu? Deconectare".

**Puncte de switch în UI:**
- Client: banner „Comută la Organizator" pe Home & Profil (doar dacă emailul are proprietăți de organizator); buton „Schimbă contul" în Profil.
- Organizator & Leisure: rând „Comută tipul de cont" în Setări → Cont.

> Referință de implementare completă: `chooserScreen()`, `clientShell()`, `applyProp()`, `switchMode()`, `goChooser()` în `organizer-app.html`.

---

## 4. Design system Tixello

Clientul și organizatorul folosesc **același** set de tokenuri. Sursa canonică: blocul `:root` din `organizer-app.html` și `tixello-prototype.html` (identice ca intenție). Fontul **Outfit** este inclus ca `@font-face` woff2 base64 (vezi `outfit-fonts.css` livrat) — **nu** încărca fonturi de la CDN.

### 4.1 Tokenuri de culoare — temă implicită (Dark / Tixello)

```css
/* Neutrals */
--bg:#0A0711; --surface:#151122; --surface-2:#1B1630;
--border:rgba(255,255,255,.09); --border-strong:rgba(255,255,255,.17);
--text:#F5F5FA; --text-2:#C4C6D4; --text-3:#8E92A6; --text-4:#5F6379;
--track:#231E39;

/* Brand — indigo/violet, CONSTANT pe toate verticalele */
--accent:#8B5CF6; --accent-700:#7C3AED; --accent-accent:#A996FA;
--accent-tint:rgba(139,92,246,.15); --accent-tint-2:rgba(139,92,246,.24);
--accent-border:rgba(139,92,246,.34); --accent-glow:rgba(124,58,237,.6);
--grad:linear-gradient(135deg,#8B5CF6,#7C3AED);   /* butoane primare, logo, FAB, avatare */

/* Semantic (fixe) */
--green:#22C55E;  --amber:#F59E0B;  --danger:#F0616D;  --cyan:#2DD6EE;
/* + variantele *-tint și *-border (translucide pe dark, solide pe light) */

/* Tipografie / formă */
--font:'Outfit',ui-rounded,-apple-system,"Segoe UI",system-ui,sans-serif;
--radius:18px; --radius-lg:22px; --radius-sm:12px;
--shadow-sm:0 10px 24px -14px rgba(0,0,0,.6);
--shadow:0 22px 46px -22px rgba(0,0,0,.7);
--shadow-btn:0 16px 34px -14px rgba(124,58,237,.7);
```

### 4.2 Teme alternative (organizator)
- **Standard (light):** indigo pe fundal lavandă/alb (`--bg:#F4F2FB; --surface:#FFFFFF; --text:#1A1626; …`).
- **Contrast Mărit:** dark cu borduri/text mai puternice.
- **Noapte:** = tema implicită dark.
Implementare: atribut `data-app-theme="dark|light|lowlight"` pe containerul aplicației; se redefinesc doar tokenurile.

> **Important (regula temelor):** definește paleta light pe `:root`-ul de bază SAU folosește un atribut explicit pe container; nu lăsa nicio culoare definită doar într-un `@media`/`[data-theme]`. Fundalul `body`/container trebuie setat explicit dintr-un token.

### 4.3 Inventar de componente (ambele aplicații)
Card, Row/list-item, Stat tile, Chip/pill (live/off/amber), Icon-button (+badge), Avatar (gradient rounded-square), Progress bar, Segmented bar, Buttons (primary gradient / ghost / green / danger-text), Toggle, Seat (map), Bottom tab bar, App bar, Bottom sheet, Full-screen modal, Center modal, Toast, Flash overlay (scan), Input, Field label, Type-chip (selectabil), QR placeholder, Sparkline/bars (rapoarte).

### 4.4 Iconografie
Set SVG inline (stroke `currentColor`), ~45 iconițe: bell, scan, cart, grid, chart, cog, cal, people, user, ticket, cash, card, check(+circle), x(+circle), plus, minus, clock, chev, back, star, door, in, alert, search, mail, pin, logout, trend, nfc, list, edit, trash, download, phone, camera, mic, pause, play, book, swap, boat, info, band (brățară), wallet. Vezi `<defs>` din `organizer-app.html`.

---

## 5. Aplicația de CLIENT — spec complet

> Sursă de adevăr: **`prototype/client-app.html`** (livrat, ~316KB). Enumerarea de mai jos garantează că nimic nu se pierde.

**Navigare (bottom tab):** `Acasă · Explorează · Bilete · Portofel · Profil`.

### 5.1 Onboarding & auth
- Splash, Register, Login, **Forgot password** (`forgotSend`).
- **Onboarding preferințe:** pași (`obNext`/`obBack`), selecție **genuri muzicale** & interese (`prefsSel`) → alimentează recomandările „Pentru tine".

### 5.2 Acasă (Home)
- „Descoperă" + „Recomandat pentru tine" (card AI, bazat pe preferințe, `prefsEdit`).
- Carduri evenimente (gradient tone), rail-uri orizontale.
- Card portofel cashless (sold, shortcut).
- Secțiune „Biletele tale" (shortcut).
- **Shorts** (video vertical, `openShorts`, `lbNext/lbPrev`) — rail de clipuri pe artiști/evenimente.

### 5.3 Explorează
- Căutare, **categorii** (`catSeat`, `catSort`, `catReset`), filtre.
- **Radar** — marketplace de **revânzare** (resale): oferte de la alți useri, „sellers"/„offers" (`s_radar`, `s_resale`). Include prețuri, stoc, vânzători.
- Liste pe categorii, produse experiențe.

### 5.4 Pagina de eveniment
- Hero + galerie cu **lightbox** (`lb:ev:*`, `lbClose/lbNext/lbPrev`).
- Descriere, **recenzii** (`revSubmit`), rating.
- **Tipuri de bilete** (`ttInc/ttDec`), **seat map** pentru evenimente cu locuri (`catSeat`, legendă Liber/Ales/Ocupat).
- **Discount** (`applyDisc`), **group buy** (cumpărare în grup).
- **Stay22** — cazare lângă eveniment (`stayFlt`, `stayPin`, `stayReset`): hoteluri/apartamente, rating, distanță, preț/noapte, filtre.
- Buton „Cumpără" sticky.

### 5.5 Festival (pagină dedicată)
- Galerie + video, **cashless** highlight, tipuri de pass (General/VIP, `festBuy`), card „Portofel cashless" (sold, plată cu brățara).

### 5.6 Bilete
- Listă bilete active/trecute.
- Detaliu bilet: **QR**, **transfer** către alt user (`transferDone`; QR se regenerează pentru noul beneficiar), **Apple/Google Wallet**.

### 5.7 Portofel (cashless)
- Sold, **reîncărcare/top-up** (`topupDone`) prin Stripe (plată reală, **fără IAP** — vezi §10), **refund sold**.
- Istoric tranzacții (top-up-uri, consum bar/food).
- **Puncte de loialitate** (Tixello points, conversie în reduceri).

### 5.8 Profil
- Avatar, date cont, statistici (bilete/puncte/sold).
- **Comută la Organizator** (banner) — dacă emailul are și acces de organizator.
- **Carduri** salvate (`cardSave`, `cardPrimary`, `cardDel`), **facturi** (`dlInvoice`).
- **Invită prieteni / afiliere** (`inviteSend`, `copyAff`) — cod, prieteni, recompense.
- **Abonament** (`abonament`), preferințe, limbă, termeni.
- **Manual utilizare**, **Delete account** (`delAcc`), Logout.

---

## 6. Aplicația de ORGANIZATOR — spec complet

> Sursă de adevăr: **`prototype/organizer-app.html`** (livrat, ~226KB).

**Două navigatoare:**
- **Standard** (organizer/teatru/filarmonică/agenție/venue/festival): `Panou · Scanare · Vânzare · Rapoarte · Setări`.
- **Leisure / venue-owner:** `Evenimente · Scanare · Vânzare · Setări` (navigator distinct — vezi §7).

**Chrome comun:** App bar (logo Tixello + switch organizator + pastilă Live/Offline + clopoțel cu badge), selector eveniment (pe Panou), **bară de tură** (timer + încasări + Pauză + buton Urgență), bottom tab bar. Rolurile: **admin / manager / staff** (staff nu are acces la Rapoarte).

### 6.1 Panou (Dashboard)
**Varianta Admin/Manager:**
- Header eveniment (dată, nume, locație, vertical).
- Card „Intrați" (X/capacitate + bară + %). Tap → modal „Intrați/Rămase per tip".
- Grid 4 statistici (Vândute, Încasări, Disponibile, Capacitate) — fiecare tap → modal.
- Card „Ritm vânzare" (bilete/min, trend).
- Card „Online vs. la ușă" (segmented bar, expandabil pe tipuri).
- **[Festival] Card Cashless & brățări** (vezi §8).
- **Card „Ocupare pe zone"** (bare per zonă + alertă prag) → modal `occupancy` (§9).
- Acțiuni rapide (Scanare, Vânzare, Listă invitați / [festival] Cashless, Echipă).
- Activitate recentă.
- Buton „Închide Tura" → modal rezumat tură.

**Varianta Scanner/Staff:** încasări (numerar/card), statistici personale (scanările mele, vânzările mele, durata turei), butoane mari Scanare/Vânzare, Închide Tura.

### 6.2 Scanare (Check-in)
- Cameră QR (frame colorat pe stare), statistici live (scanări/min, așteptare, intrați), buton „Cod Manual".
- **Stări de rezultat (obligatoriu toate):**
  1. **ACCES APROBAT** (verde) — nume, tip bilet, loc; acțiuni: **Badge** (print), **Acțiuni** (modal la ușă), „Scanează următorul" (sau auto-confirmare).
  2. **RE-INTRARE BLOCATĂ** (amber) — anti-passback: „Scanat la Poarta 2 · acum 12 min"; acțiuni: „Vezi scanarea", „Permite re-intrare".
  3. **BILET INVALID** (roșu) — cod negăsit.
  4. **BILET BLOCAT** (roșu) — pe lista neagră (motiv); „Vezi lista neagră".
- Flash colorat pe telefon la fiecare scanare; blocare când tura e pe pauză.
- **Cod manual** (modal): după cod sau după nume/email, cu rezultate.

### 6.3 Vânzare (POS)
- Listă tipuri de bilete (stripe colorat, preț, disponibilitate). Pentru evenimente cu locuri → **seat map** (modal).
- Coș cu +/-, subtotal + **comision 3%**, total.
- Metode de plată: **Numerar**, **Card/POS**, **Card prin NFC** (Stripe Tap — dacă e activat în Setări).
- Confirmare plată (modal) → **ecran de succes cu QR** → „Finalizează", „Trimite pe email", **„Printează bon"** (dacă imprimanta e activă).
- Shortcut „Bilete eveniment" (listă + check-in inline), „Vânzări azi".

### 6.4 Rapoarte (doar admin)
- Rata check-in (+ sparkline), Total vândute, Ora de vârf.
- Performanță pe tip bilet (bare), Detalii venituri (per tip), Distribuție orară (bar chart).
- **Card „Decontare & payout"** — Total încasat, Comision Tixello 3%, **Net de decontat**, payout **Stripe Connect T+3**.
- Export CSV.

### 6.5 Setări
- **Cont** (nume, rol, poartă; **„Comută tipul de cont"** → chooser).
- **Scanner** (vibrație, sunet, auto-confirmare, scanner Bluetooth).
- **Vânzare POS** (Card prin NFC / Stripe Tap) — admin.
- **Mod Offline & sincronizare** (activare offline, **coadă de sincronizare** → modal `sync`).
- **Imprimantă** (termică bluetooth; test print badge) — admin.
- **Aspect** (Standard/Contrast/Noapte).
- **Securitate** (auto-logout).
- **Manual utilizare** (ghid, 28 capitole).
- **Comenzi Admin**: Administrare Porți, Asignare Personal, **Difuzare către staff**, **Ocupare pe zone**, **Reconciliere casă**, **Listă neagră**, **[festival] Vendori** / [altfel] Imprimare & acreditări.
- Încheie tura & deconectare; versiune „Tixello · Cont organizator v2.2.0".

### 6.6 Inventar COMPLET de modale (28) — nu omite niciunul
`events` (selector eveniment) · `notifs` (notificări + alertă in-app) · `emergency` (raportare 8 tipuri, foto/voce) · `staff` (echipă & personal, add + rol + poartă) · `gates` (administrare porți, add + toggle) · `guests` (listă invitați, check-in) · `switch` (comută organizator) · `manual` (Manual utilizare, 28 capitole) · `shiftsummary` (rezumat tură + reconciliere) · `breakdown` (online vs POS) · `ticketsales` (vânzări per tip) · `remaining` (intrați/rămase per tip) · `scandetails` (detalii scanare) · `manualentry` (check-in manual) · `payconfirm` (confirmă încasarea) · `emailcapture` (trimite bilete pe email) · `seatmap` (alegere locuri) · `ticketlist` (bilete eveniment + check-in inline) · `export` (export bilete CSV) · **`cashless`** (festival) · **`ticketaction`** (upgrade/re-intrare/refund/void) · **`banlist`** (listă neagră) · **`broadcast`** (difuzare staff) · **`occupancy`** (ocupare zone) · **`cashcount`** (reconciliere casă + Z-report) · **`vendors`** (vendori festival) · **`printbadge`** (print badge/bon/acreditare) · **`sync`** (coadă offline).

---

## 7. Tipuri de cont organizator (verticale)

**Aceeași aplicație, aceeași identitate Tixello (indigo), aceleași ecrane** — diferă **datele, etichetele și modulele specifice**. Verticalele (cheie internă → etichetă):

| Cheie | Etichetă | Specific |
|---|---|---|
| `organizer` | Organizator | concerte/generic (bază) |
| `theater` | Teatru | loc pe scaun, categorii Parter/Balcon/Lojă, „stagiune/abonament", protocol/presă |
| `philharmonic` | Filarmonică | concert simfonic, abonament stagiune, studenți |
| `agency` | Agenție artiști | turneu multi-oraș, Golden Circle, Meet & Greet |
| `venuearena` | Venue (stadion) | capacități mari, Sky Box, tribune |
| `festival` | Festival | **cashless + brățări + vendori** (vezi §8) |
| `leisure` | Leisure / venue-owner | **navigator distinct** (Evenimente/Scanare/Vânzare/Setări): evenimentele sunt ale **locației**, indiferent de organizator; detaliu eveniment cu listă participanți; detaliu bilet cu **mențiuni** & grupare bilete; Export CSV. |

**Notă importantă (coerență):** accentul de brand rămâne **indigo pe toate**. Verticala schimbă doar identitatea afișată + setul de date + modulele active. NU recolora per verticală.

> Referință: obiectul `CTX` (per verticală: org, event, venue, ticket types, capacități) și `LEISURE` (dataset venue-owner) din `organizer-app.html`.

---

## 8. Modul Festival — cashless & brățări

Activ **doar** pentru verticala `festival`. Adaugă:

- **Card pe Panou „Cashless & brățări":** brățări activate, sold încărcat (lei), vendori activi; buton „Gestionează cashless".
- **Modal `cashless`:**
  - Card brățară scanată (NFC) cu ID + sold + beneficiar.
  - **Asociază brățară** (NFC) · **Verifică sold**.
  - **Top-up** (chips 50/100/150/200) → **Numerar** / **Card / NFC** (Stripe).
  - **Refund sold** la ieșire.
  - Notă conformitate: consum **fizic** pe locație → plăți externe (Stripe/Apple Pay/Tap to Pay), în afara IAP.
- **Modal `vendors`** (din Comenzi Admin): listă vendori (bar/food/merch/cafea) cu vânzări cashless, comision platformă, **decontare vendori**.
- În client: pagina de festival + portofelul cashless (top-up, plată cu brățara).

**Fluxul cashless end-to-end:** client top-up (client app / la casă) → sold pe cont/brățară → consum la vendori (POS organizator scanează brățara) → reconciliere vendori → refund sold rămas la ieșire.

---

## 9. Îmbogățiri operaționale

Toate implementate în prototip (mai puțin „Comparație vs. evenimente trecute", exclusă intenționat). Fiecare trebuie portată:

1. **Offline-first real** — cache bilete local + coadă scanări/vânzări + sync la reconectare. Modal `sync` (stare online/offline, elemente în coadă, „Sincronizează acum", simulare offline). Setări → „Mod Offline & sincronizare". Vezi §11.
2. **Anti-passback / re-entry + listă neagră** — stare de scanare „RE-INTRARE BLOCATĂ" (scanat la altă poartă) și „BILET BLOCAT" (pe lista neagră). Modal `banlist` (adaugă/deblochează, motiv). „Permite re-intrare" autorizează o excepție.
3. **Acțiuni la ușă** (`ticketaction`) — **Upgrade** (încasează diferența), **Permite re-intrare**, **Refund** (Stripe), **Anulează/void**.
4. **Difuzare către staff** (`broadcast`) — mesaj push segmentat (toți/rol/poartă), prioritate normal/urgent.
5. **Ocupare pe zone** (`occupancy`) — bare per zonă cu **praguri de alertă** (ex. 90%); card sumar pe Panou; alertă când pragul e depășit; shortcut „Anunță staff".
6. **Reconciliere casă** (`cashcount`) — numărare bancnote pe denominații, numărat vs. așteptat, **diferență**, **Z-report**. Accesibil din rezumat tură + Comenzi Admin.
7. **Vendori & reconciliere** (`vendors`) — festival (vezi §8).
8. **Imprimare & acreditări** (`printbadge`) — badge/bon/acreditare la imprimantă termică Bluetooth; toggle + test în Setări; buton „Printează bon" pe succesul vânzării; „Badge" pe scanare validă.

---

## 10. Plăți & conformitate Apple

**Concluzie: NU plătești cei 30% Apple pentru nimic din ce tranzacționezi**, pentru că totul este „real-world". Apple **interzice** IAP pentru bunuri/servicii fizice (nu doar că îl permite) — ghid **3.1.3(e)**. IAP (și comisionul 30%/15%) se aplică **exclusiv** conținutului pur digital consumat în app.

| Flux | Clasificare | Rută de plată | Comision Apple |
|---|---|---|---|
| Bilete la evenimente | serviciu real-world (intri fizic) | Stripe / Apple Pay | **0%** |
| Portofel / cashless top-up | valoare stocată pt consum fizic pe locație | Stripe / Apple Pay | **0%** |
| NFC organizator (Tap to Pay) | acceptare card-present (POS) | **Stripe Terminal / Tap to Pay on iPhone** | **0%** (doar comision Stripe) |
| Experiențe (tics) | serviciu real-world | Stripe / Apple Pay | **0%** |

**Reguli de aur:**
- **Apple Pay ≠ IAP.** E doar o metodă de card pentru bunuri fizice; oferă-l în PaymentSheet.
- **Nu** introduce abonamente/feature-uri **pur digitale** cumpărate prin Stripe (acelea ar cere IAP). Dacă vrei „premium", fă-l beneficiu real-world sau acceptă IAP doar pentru acea felie.
- Marchează explicit fluxurile ca „bilet fizic / eveniment" în **App Review notes** (au existat respingeri când reviewerul a crezut că e digital).
- Din mai 2025, **US storefront** permite chiar și link-uri externe fără entitlement; UE (DMA) oferă libertate suplimentară — dar la real-world nici nu e nevoie.

**Backend plăți:**
- **Stripe** — PaymentSheet (client: top-up + bilete, cu Apple Pay/Google Pay), **Stripe Terminal / Tap to Pay on iPhone** (organizator, card-present).
- **Stripe Connect** — payout la organizatori + split comision Tixello (3%) → cardul „Decontare & payout".
- **EPAS** — comenzi, bilete, webhooks Stripe, tenant scoping, dynamic pricing, seating.

Surse: [9to5Mac — external links (mai 2025)](https://9to5mac.com/2025/05/01/apple-app-store-guidelines-external-links/) · [Apple Dev Forums — physical QR via Stripe](https://developer.apple.com/forums/thread/791832).

---

## 11. Offline-first & sincronizare

Scanarea la ușă trebuie să funcționeze **fără internet**.

- **Cache local** (SQLite, `@capacitor-community/sqlite`): descarcă lista de bilete valide + lista neagră pentru evenimentul activ.
- **Coadă de scanări/vânzări**: fiecare check-in/POS offline se scrie local cu timestamp + gate + operator, marcat `pending`.
- **Anti-passback offline**: verifică local starea biletului (scanat deja?) în snapshot; la reconectare, rezolvă conflicte (dublă scanare la porți diferite offline → prima câștigă, restul devin „re-intrare blocată" în audit).
- **Sync la reconectare**: trimite coada, actualizează snapshot, afișează progres (modal `sync`).
- **Indicator**: pastila „Offline" în app bar → deschide `sync`; card status în Setări.

Regulă: nicio pierdere silențioasă — orice element necomunicat rămâne vizibil în coadă până la confirmare.

---

## 12. Fișiere livrate & cum le folosești

Toate sunt în `tixello-mobile/prototype/` (și livrate alături în chat):

| Fișier | Ce e | Rol |
|---|---|---|
| `prototype/organizer-app.html` | Prototip interactiv **organizator** (Tixello, toate verticalele + leisure + festival + toate îmbogățirile). Single-file, vanilla JS, font Outfit inline. | **Sursă de adevăr UX organizator.** |
| `prototype/client-app.html` | Prototip interactiv **client** (Tixello, dark indigo, cashless, Stay22, tics, radar/resale, shorts, wallet, invite). | **Sursă de adevăr UX client.** |
| `outfit-fonts.css` | `@font-face` Outfit (woff2 base64, 2 greutăți). | Fontul aplicației. |
| `TIXELLO_BUILD_GUIDE.md` | Acest document. | Specificația de build. |

**Cum le folosești:**
1. Deschide fișierul HTML în browser pentru a **vedea și interacționa** cu fiecare ecran/modal/stare.
2. Prototipurile sunt „state machines" simple: caută `function render()`, obiectul de stare `S`/`ST`, și funcțiile de ecran/modal. Numele funcțiilor din §5/§6 corespund 1:1 codului.
3. La portarea în React: **replică structura de stare și fluxurile**, extrage tokenurile din §4, și mapează fiecare ecran la un component. Nu inventa UX nou — respectă prototipul.
4. Panoul lateral „studio" din prototipul de organizator este **doar unealtă de test** (comută verticală/rol/temă/identitate/modale). NU face parte din aplicație — nu-l porta.

> Există și un **scaffold RN** parțial în `tixello-mobile/src/` (Expo/React Native, din iterații anterioare: navigation, api/client, theme, screens de bază, store/session). Poți refolosi structura, dar **UX-ul de referință rămân prototipurile HTML** (mai complete). Decide între React Native (scaffold existent) și **Capacitor + React web** (recomandat pentru OTA — vezi §14).

---

## 13. Modelul de date & API (mapare EPAS)

Aplicația e client al **tenant API** EPAS. Entități-cheie (deja în EPAS):

- **User / Identity** — un user, mai multe apartenențe: `client` + membru în echipa unuia sau mai multor **Tenant** (organizatori), cu **rol** (spatie/laravel-permission: super-admin/admin/editor/tenant → mapează la admin/manager/staff în app). → alimentează `identityProps()`.
- **Tenant** (organizator) — public name, plan/comision, domenii, verticală (vezi §7). Endpoint necesar: `GET /me/properties` (client + organizatori + roluri).
- **Event** — moduri de dată (single/range/multi-slot `multi_slots`), postponed/cancelled, capacitate, tip (verticală).
- **TicketType / PriceTier / Dynamic pricing** — tipuri, prețuri, `dynamic_pricing_rules` (preț de ultim moment la ușă).
- **Order / Ticket** — status (paid/confirmed/…), QR/cod, check-in status, gate, operator, timestamps. Anti-passback: `checked_in_at`, `checked_in_gate`.
- **Seating** (11 tabele: layouts/sections/rows/seats/event_seats/seat_holds/…) — seat map client + POS.
- **Gates / Staff assignment** — porți per eveniment, asignări operatori.
- **Cashless (festival)** — wallet/brățară (association NFC), balance, top-ups, vendor sales, settlements. (De adăugat în EPAS dacă nu există: `cashless_wallets`, `cashless_topups`, `vendor_sales`.)
- **Ban list** — bilete/persoane blocate per eveniment/tenant.
- **EmailTemplate / EmailLog** — trimitere bilete pe email (există).
- **Facebook CAPI** — tracking (există; nu e UI mobil).

**API surface minim (client + organizator):**
```
POST /auth/login            → token
GET  /me/properties         → [{kind, orgId, vertical, role}]
GET  /events?scope=...      → listă (client discover / organizer active)
GET  /events/{id}           → detaliu + ticket types + seatmap
POST /orders                → creare comandă (client / POS)  → Stripe PaymentIntent
POST /checkin/scan          → {code, gate} → {valid|duplicate|banned|invalid, ...}
POST /checkin/manual        → după cod/nume
POST /tickets/{id}/transfer → transfer client
GET  /reports/{eventId}     → KPI + settlement
GET  /occupancy/{eventId}   → zone + praguri
POST /broadcast             → push staff
POST /cash/reconcile        → Z-report
POST /cashless/associate|topup|verify|refund
GET/POST /banlist
POST /sync/batch            → coadă offline (scanări/vânzări)
Stripe: PaymentSheet (client) · Terminal/Tap to Pay (POS) · Connect (payout)
```
> Verifică în `epas/core/` ce există deja (rute `routes/api.php`, `/v1/public/*`, resurse Filament) și extinde tenant API-ul. NU folosi SQL brut — Eloquent (convenția EPAS).

---

## 14. Stack tehnic & structura proiectului

**Recomandare: Capacitor + React (web) — un build, OTA.**

- **UI:** React + Vite + design system Tixello (§4), font Outfit inline. Router cu shells: `/(auth)`, `/(chooser)`, `/(client)/*`, `/(org)/*`.
- **Shell nativ:** **Capacitor 6**. Plugin-uri:
  - `@capacitor/camera` + `@capacitor-mlkit/barcode-scanning` (QR),
  - NFC (brățări + citire) — plugin community sau modul nativ subțire,
  - `@capacitor/push-notifications` (FCM/APNs),
  - `@capacitor/preferences` + secure storage (token),
  - `@capacitor-community/sqlite` (cache offline + coadă),
  - `@capacitor/network`, `@capacitor/haptics`,
  - **Stripe Terminal / Tap to Pay on iPhone** SDK (POS card-present) — singura piesă mai „nativă".
- **OTA:** **Capgo** (open-source, self-host recomandat) sau Ionic Appflow Live Updates. Împinge bundle-ul web instant, fără review. Doar plugin/permisiune nativă nouă cere release în store. Folosește staged rollout + rollback + version-gating pe API.
- **Plăți:** Stripe (PaymentSheet + Terminal/Tap to Pay + Connect) — vezi §10.
- **Backend:** EPAS (Laravel) tenant API + webhooks Stripe.

**Structură propusă `tixello-mobile/`:**
```
tixello-mobile/
  prototype/            # sursele de adevăr (HTML) — NU se compilează, doar referință
    organizer-app.html
    client-app.html
  src/
    app/                # routing + shells (auth, chooser, client, org)
    design/             # tokens.css (din §4), outfit @font-face, componente UI
    features/
      auth/  identity/  client/{home,explore,event,tickets,wallet,profile}
      org/{dashboard,scan,sales,reports,settings,modals}
      festival/{cashless,vendors}
      offline/{cache,queue,sync}   payments/{stripe,terminal}
    api/                # client + endpoints + types (mapare EPAS)
    store/              # state (identity, session, event activ, cart, offline)
  capacitor.config.ts
  package.json
```
> Scaffold-ul RN existent (`src/screens/*.tsx`, `src/api/*`, `src/theme/colors.ts`, `src/store/*`) poate fi migrat/refolosit. Dacă echipa preferă **React Native**, OTA se face cu Expo Updates/CodePush — dar Capacitor + web reutilizează direct prototipurile și maximizează „modific rapid fără store".

---

## 15. Plan de implementare pe faze

**Faza 0 — Fundație (1 sprint)**
- Setup Capacitor + React + Vite; design system (§4) + Outfit; plugin-uri de bază.
- OTA (Capgo) end-to-end (deploy de test fără store).
- Auth + `identityProps()` + **account chooser** + switch (§3).

**Faza 1 — Client MVP**
- Home/Explore/Event/Tickets/Wallet/Profile (§5).
- Stripe PaymentSheet (bilete + top-up), QR bilete, transfer.
- Seat map (client), preferințe/onboarding, recomandări.

**Faza 2 — Organizator MVP**
- Shell + Panou (admin/scanner) + Scanare (4 stări) + Vânzare (POS) + Rapoarte + Setări (§6).
- Toate modalele de bază (events, staff, gates, guests, breakdowns, manual, etc.).
- Stripe Terminal / Tap to Pay (POS NFC).

**Faza 3 — Offline & operațional (§9, §11)**
- SQLite cache + coadă + sync + anti-passback + listă neagră.
- Acțiuni la ușă, broadcast, ocupare zone, reconciliere casă, print.

**Faza 4 — Festival & verticale (§7, §8)**
- Cashless & brățări (NFC), vendori & decontare.
- Leisure navigator; particularități teatru/filarmonică/agenție/venue.

**Faza 5 — Decontări & polish**
- Stripe Connect payout + „Decontare & payout".
- Teme (light/contrast), securitate (auto-logout), i18n, App Review notes.

---

## 16. Backlog & decizii deschise

- **Comparație vs. evenimente trecute** — exclus intenționat acum (poate reveni ca analytics).
- **NFC pe iOS** — validează suportul de citire brățări (HCE/format) și Tap to Pay (necesită eligibilitate Stripe + entitlement Apple).
- **Cashless în EPAS** — dacă tabelele lipsesc, proiectează-le (wallets/topups/vendor_sales/settlements).
- **Dynamic pricing la ușă** — expune `dynamic_pricing_rules` în POS (preț de ultim moment).
- **i18n** — EPAS are en/ro/de/fr/es; aplicația mobilă ar trebui să respecte aceleași locale.
- **Push provider** — FCM (Android) + APNs (iOS) prin Capacitor; backend de trimitere (broadcast staff + notificări client).

---

### Rezumat pentru sesiunea de dezvoltare
Construiește **o singură aplicație Capacitor** care, după un **login unic**, duce utilizatorul prin **pasul de alegere a contului** către **shell-ul de client** sau **shell-ul de organizator** (cu verticalele și modulul de festival), împărtășind **un design system Tixello** unic. Replică **1:1** cele două prototipuri HTML livrate (nimic omis), folosește **Stripe** (Connect + Terminal) pentru toate plățile real-world (**fără IAP / fără 30%**), și livrează update-uri prin **OTA (Capgo)**. Backend = **EPAS** tenant API. Fazele §15 dau ordinea.

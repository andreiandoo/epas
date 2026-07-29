# Audit end-to-end — Sistemul de Deconturi (Payouts)

**Data:** 2026-07-29
**Scop:** analiză read-only a întregii funcționalități de deconturi (calcul, PDF, UI Filament, lifecycle/automatizări, retururi, facturare). **Nu s-a modificat nimic în cod** — doar observații și direcții de rezolvare.
**Metodă:** 4 audituri paralele pe zone + verificare directă a celor mai critice constatări. Constatările sunt marcate `CONFIRMAT` (trasat în cod) sau `PLAUZIBIL` (necesită verificare la runtime înainte de orice fix).

> ⚠️ **Înainte de orice remediere:** fiecare fix trebuie verificat pe deconturi reale (preview cu transaction+rollback) și trebuie să fie strict non-breaking pentru deconturile deja emise/plătite. Multe constatări sunt interconectate — a repara una fără celelalte poate muta problema.

---

## 1. Cauza-rădăcină structurală (cea mai importantă)

Există **două reconstrucții paralele** ale netului de decont, alimentate din surse de date diferite, nereconciliate nicăieri:

| Cale | Sursă | Formulă | Când e „înghețată" |
|------|-------|---------|--------------------|
| **A. Breakdown înghețat** | JSON `ticket_breakdown` salvat la creare | `Σ(qty·unit_price − qty·comision − discount)` | la salvare |
| **B. Recalcul live** | bilete curente din DB (`Ticket::getEffectivePrice`, „latest-N" pe tip) | `Σ preț efectiv bilete` | niciodată — se recalculează la fiecare afișare |
| **C. SalesBreakdownService** | alocare proporțională discount/extra per comandă | `total_net` | la cerere |

**Cine ce citește:**
- Pagina „Detalii bilete" + rândurile **1a/1c** din PDF → **Calea A** (breakdown înghețat)
- Coloana din listă `final_net_amount` + totalurile **A** și **E** din PDF → **Calea B** (recalcul live)
- Mișcarea reală de bani (`amount`, registru, balanță, factură, notificări) → coloana `amount` (înghețată la creare, ~Calea A/C)

Cât timp cele două căi „nimeresc" același număr, totul pare corect. Când divergează (incidentul de 78 lei pe decontul 3208), PDF-ul își contrazice propriile rânduri. **`net_override` a fost un plasture** care fixează doar 2 consumatori de afișare (accessor + scalarul A/E din PDF), lăsând restul (rânduri PDF, D, registru, balanță, factură, notificări) pe valorile vechi.

**Direcția-cheie recomandată:** desemnează **breakdown-ul înghețat drept sursă unică de adevăr post-salvare**; afișarea/PDF-ul să **însumeze aceleași rânduri pe care le tipărește**, retrăgând recalculul live (`computeOrganizerNetFromTickets`). Aceasta ar elimina din temelie întreaga clasă de bug-uri „78 lei" + majoritatea inconsistențelor rând-vs-total de mai jos.

Fișiere-cheie: [MarketplacePayout.php](app/Models/MarketplacePayout.php) (`buildBreakdownFromSelection:86`, `computeOrganizerNetFromTickets:752`, `getFinalNetAmountAttribute:642`), [MarketplaceTaxTemplate.php](app/Models/MarketplaceTaxTemplate.php) (`:1955-1992`), [SalesBreakdownService.php](app/Services/Marketplace/SalesBreakdownService.php).

---

## 2. CRITIC — bug-uri cu impact pe bani / securitate

### 2.1 [CONFIRMAT] Deconturile manuale nu rezervă balanță, dar complete/reject/reverse o mișcă → corupție de balanță
[ListPayouts.php:906](app/Filament/Marketplace/Resources/PayoutResource/Pages/ListPayouts.php#L906) creează decontul cu `status='approved'` și **nu** apelează niciodată `reserveBalanceForPayout()` (spre deosebire de calea API organizator, `PayoutController.php:446`). Dar:
- `complete()` → `recordPayoutCompleted()` → `decrement('pending_balance', amount)` ([MarketplacePayout.php:1481](app/Models/MarketplacePayout.php#L1481))
- `reject()` / `reversePayout()` → `returnPendingBalance()` → `−pending_balance` + `+available_balance`

**Scenariu:** admin creează decont manual de 3.200 RON (calea dominantă, auto e oprit). Nimic nu a intrat în `pending_balance`. La `complete` → `pending_balance` ajunge **−3.200**. La `reject`/`reverse` → `available_balance` e **umflat cu 3.200 RON nerezervați** (organizatorul poate „cere" bani care nu-s ai lui). `edit_ticket_selection` (ViewPayout.php:520-526) agravează.
**Direcție:** ori rezervă la crearea manuală, ori (mai curat, dat fiind că sistemul a trecut pe deconturi pe eveniment) decuplează complet deconturile manuale admin de contoarele `available/pending_balance`.

### 2.2 [CONFIRMAT] `markRefunded()` nu exclude biletele returnate din netul de decont
[MarketplaceRefundRequest.php](app/Models/MarketplaceRefundRequest.php) `markRefunded()` setează pe bilete doar `is_cancelled=true` — **NU** `tickets.status='refunded'` și **nu** creează rânduri `MarketplaceRefundItem`. Dar tot calculul de net presupune contrariul: `computeOrganizerNetFromTickets()` filtrează `status IN ['valid','used']` ([MarketplacePayout.php:778](app/Models/MarketplacePayout.php#L778)), iar `getDeductibleRefundAmount()` citește `MarketplaceRefundItem`. Cealaltă cale de retur, `PaymentRefundService.php:337-361`, o face **corect** (setează status + creează refund items).
**Scenariu:** un retur procesat prin helper-ul de model lasă biletul `status='valid'` → e cules de „latest-N" în net → organizatorul e plătit pentru un bilet returnat, iar returul nu apare în deducere.
**Direcție:** `markRefunded()` să seteze `status='refunded'` + să emită `MarketplaceRefundItem`, sau tot fluxul să treacă prin `PaymentRefundService`.

### 2.3 [CONFIRMAT] `net_override` onorat doar în 2 din ~8 consumatori de net — banii reali folosesc `amount`
Onorat în: `getFinalNetAmountAttribute:652` (coloană listă + view) și PDF `MarketplaceTaxTemplate:1977`.
**Neonorat** în: `complete()`/`recordPayoutCompleted($this->amount)` + tranzacția de registru `amount => -$this->amount` ([:1481/:1494](app/Models/MarketplacePayout.php#L1481)), `createOrganizerNotification()` (email/in-app, `:1701/:1720`), netul/comisionul facturii (`getCommissionExclPos:1079`, `ViewPayout:703`), API `formatPayoutDetailed` (`PayoutController:590/631/645`), `recalcBreakdownSnapshot:1301`.
**Scenariu:** override 29.175 pe un decont cu `amount`=29.253 → pagina/PDF arată 29.175, dar emailul organizatorului, registrul, decrementul de balanță și factura folosesc 29.253. Override-ul „repară" afișarea, banii rămân greșiți.
**Direcție:** toți consumatorii de net final să treacă printr-un singur accessor (`final_net_amount`); sau, la setarea override-ului, reconciliază și `amount`.

### 2.4 [CONFIRMAT] Deconturile finalizate nu sunt înghețate — `final_net_amount` se recalculează live și driftează după retururi/editări ulterioare
`getFinalNetAmountAttribute()` ([:642-676](app/Models/MarketplacePayout.php#L642)) ignoră coloana înghețată `amount` și recalculează live la fiecare citire (dacă nu e `net_override`).
**Scenariu:** decont finalizat și virat la 3.200 RON. O săptămână mai târziu un bilet de pe eveniment e returnat (status→refunded) sau un preț de ticket_type e editat → coloana din listă și rândul E din PDF arată acum **2.920 RON pentru un decont care a plătit efectiv 3.200** — imposibil de reconciliat cu banca.
**Direcție:** îngheață netul final pe rând la `complete()` (sau la generarea PDF); accessor-ul să întoarcă snapshot-ul pentru deconturile `completed`, recalcul live doar pentru stările editabile.

### 2.5 [CONFIRMAT] `PayoutResource` nu are absolut nicio autorizare
Fișierul nu are `canViewAny/canCreate/canApprove/canDelete` — spre deosebire de `MarketplaceAdminResource.php:322-341` care gatează cu `hasPermission()`, iar permisiunile `payouts.view`/`payouts.process` **există** definite (`MarketplaceAdmin.php:184-185`) dar sunt decorative aici.
**Scenariu:** un `moderator` (rol cu drepturi reduse) poate crea, aproba, finaliza, seta net_override și șterge deconturi — adică marca bani reali ca trimiși.
**Direcție:** `canViewAny/canCreate` → `payouts.view`; approve/process/complete/reject/delete/`set_net_override` → `payouts.process`.

### 2.6 [CONFIRMAT] IDOR cross-marketplace la create (submit + prefill)
Opțiunile Select sunt scope-uite pe client, dar argumentele Livewire și `$data` submise nu sunt re-validate: [ListPayouts.php:804](app/Filament/Marketplace/Resources/PayoutResource/Pages/ListPayouts.php#L804) `Event::find($data['event_id'])`, `:906-909` scrie `event_id`/`marketplace_organizer_id` din `$data` nescope-uit, `:63` și `:1327` `Event::find` din `$arguments` fără verificare de client.
**Scenariu:** un operator fabrică un request `mountAction('create_payout',['event_id'=>‹eveniment alt tenant›])` → se scurg datele financiare ale altui marketplace în modal + se creează un decont legat de eveniment/organizator străin. Citire ȘI scriere peste granița de tenant.
**Direcție:** re-rezolvă `Event`/`MarketplaceOrganizer` cu `where('marketplace_client_id', $admin->marketplace_client_id)->findOrFail()` în `fillForm`, `buildCreatePayoutInitialState` și handler-ul `->action()`. Idem `syncIncludedRefunds` (`MarketplacePayout.php:948-958`) — leagă `included_refund_ids` fără scope de tenant/eveniment.

### 2.7 [CONFIRMAT] PDF: totalul A și rândurile 1a/1c vin din surse diferite; `net_override` nu ajunge la rânduri și la D
Totalul **A** = `{{payout_net_amount}}` (recalcul live, [MarketplaceTaxTemplate:1982](app/Models/MarketplaceTaxTemplate.php#L1982)); rândurile **1a/1c** = `{{sales_breakdown_rows}}` din breakdown-ul înghețat (`:2034/:2452`). `buildPayoutSalesBreakdownRows` nu primește `net_override`, iar **D** (`payout_commission_amount`) rămâne din breakdown-ul brut (`:1651/:1666`).
**Scenariu:** decont multi-regulă cu `net_override` setat → A spune `override+avans+retur`, dar suma rândurilor 1a+1c+1e tipărite rămâne `Σbreakdown+retur`, iar D e din altă sursă. Antetul și rândurile vizibile se contrazic din nou.
**Direcție:** derivă A prin însumarea acelorași rânduri pe care le tipărește PDF-ul (consecință naturală a §1).

### 2.8 [PLAUZIBIL] PDF E vs listă `final_net_amount` diferă cu `deductible_refund_amount` (dublă scădere)
PDF E = `max(0, organizerNet − avans)` (fără termen de retur, `:1989`); lista arată `final_net_amount = organizerNet − avans − deductible_refund_amount` (`PayoutResource:1069`). Cum biletele returnate sunt *deja* excluse din `organizerNet` prin flip de status (pentru retururile prin `PaymentRefundService`), scăderea `deductible_refund` e o **dublă scădere** pentru orice retur `commission_refunded=true`.
**Scenariu:** un bilet de 70 lei returnat → PDF-ul tipărește E cu 70 mai mare decât lista. Un ecran e greșit.
**Notă:** interacționează cu §2.2 — pentru retururile prin `markRefunded` (care NU flipează status) scăderea e corectă; deci inconsistența e reală în ambele sensuri. Necesită verificare pe un decont cu retururi reale.
**Direcție:** o singură definiție a „netului final", partajată de PDF și accessor.

### 2.9 [PLAUZIBIL] Comenzile `partially_refunded` își pierd biletele încă valide din net
Filtrul de comandă e `status IN ['paid','confirmed','completed']` ([MarketplacePayout.php:780](app/Models/MarketplacePayout.php#L780), oglindit `MarketplaceTaxTemplate:2582`). Un retur parțial pune comanda pe `partially_refunded`, care nu e în listă → **toate** biletele comenzii (inclusiv cele nereturnate) sunt excluse din net.
**Scenariu:** comandă cu 3 bilete, 1 returnat → organizatorul pierde creditul pentru celelalte 2; A și E subestimează.
**Direcție:** include `partially_refunded` în statusurile acceptate (filtrul pe bilet `status IN ['valid','used']` scoate oricum locurile efectiv returnate).

### 2.10 [CONFIRMAT] `computeOrganizerNetFromTickets` omite `test_order`/`pos_test` și nu are limită inferioară de perioadă / excludere „deja plătit"
Filtrul ([:779-786](app/Models/MarketplacePayout.php#L779)) exclude `external_import` + `POS_SOURCES`, dar **nu** `test_order`/`pos_test` (spre deosebire de `SalesBreakdownService` și `buildRemainingTicketsItems`). În plus, nu are `period_start` inferior și nu scade biletele deja decontate pe un payout anterior (spre deosebire de bucla `alreadyPaid` din `buildRemainingTicketsItems:354-373`).
**Scenariu:** (a) o vânzare test/POS-test pe eveniment umflă netul live peste `amount` — cauză live, independentă, a clasei „78 lei"; (b) evenimente cu mai multe deconturi: „latest-N" al decontului B reajunge în biletele decontului A → aceiași bani plătiți de două ori.
**Direcție:** extrage un singur set/scope de excludere partajat de toate cele 3 căi; dă Căii B aceeași disciplină „alreadyPaid" — sau (preferabil, §1) retrage Calea B.

---

## 3. MEDIU — riscuri de integritate / consistență

- **[CONFIRMAT] Guard de decont-duplicat non-atomic (TOCTOU).** Verificarea `exists()` e doar pre-modal (`generateEventDecont:1255`) + în blade; handler-ul `->action()` (`:783-935`) **nu** re-verifică înainte de `create`. Doi operatori simultan → 2 deconturi; butonul header „Crează Decont Manual" nu rulează niciodată guard-ul. **Direcție:** re-aserție în tranzacția `->action()` sau index unic parțial pe `event_id` pentru statusuri active.
- **[CONFIRMAT] Editabil după `completed`/plătit.** `edit_ticket_selection`/`recalc_breakdown` sunt gatate, dar `set_net_override` (`PayoutResource:736`), `add_admin_note`, `edit_decont_series_inline` (`:191`), `delete`/reverse (`:1262`) **nu** au guard de status. Override sau ștergere post-finalizare desincronizează de banii deja trimiși; delete pe `completed` rulează `reversePayout` → `returnPendingBalance` + decrement `quota_sold` deși fondurile au plecat. **Direcție:** blochează la `status='completed'`.
- **[CONFIRMAT] Seria de decont fără constrângere de unicitate.** `assignDecontSeries:841-870` e race-safe (lockForUpdate), dar migrarea pune doar index, nu unique; contorul stă în `client.settings` JSON. Calea cu sufix custom (`ListPayouts:885-893`) nu verifică coliziuni și nu avansează contorul → o greșeală de tastare dublează o serie oficială. Seria e atribuită în `creating` → un decont respins/anulat/șters **arde** numărul → goluri (relevant fiscal RO). **Direcție:** `unique(marketplace_client_id, decont_series)`; validează sufixul; decide politica pentru goluri.
- **[CONFIRMAT] `net_override` fără UI și fără audit trail.** Se poate scrie doar prin tinker/DB. E cel mai sensibil câmp de bani și nu captează cine/când/de ce. `activitylog` **nu** e legat deloc de `MarketplacePayout`. **Direcție:** acțiune Filament cu motiv obligatoriu + logare (plus approve/reject/complete).
- **[CONFIRMAT] Mașina de stări e gatată doar la nivel UI.** Guard-urile `canBe*` (`:1404-1428`) sunt aplicate doar în `visible()` al acțiunilor Filament; `markAsProcessing/complete/reject/cancel` nu le re-verifică → un apelant programatic poate forța o tranziție ilegală (`complete()` pe `pending`). **Direcție:** mută guard-urile în metodele de tranziție.
- **[CONFIRMAT] `cancel()` (model) nu întoarce balanța** — doar controllerul API o face în jurul apelului (`PayoutController:491`). Orice alt apelant lasă balanța rezervată blocată. **Direcție:** mută `returnPendingBalance()` în `cancel()`.
- **[CONFIRMAT] `refund_amount` devine stale la re-legarea unui retur** (`syncIncludedRefunds:952-959`, auto-documentat). Retur mutat din decont A în B → A încă îl scade pe 2a în PDF, B îl scade și el → dublă contorizare până la re-salvarea lui A. **Direcție:** recalculează ambele deconturi la re-legare.
- **[CONFIRMAT] Observer regenerează decontul pe `approved` SAU `completed` și înghite toate erorile** (`Observer:22`, `catch:254` doar log). Un lookup de template eșuat produce un decont fără document și fără eroare vizibilă. Observer-ul rulează doar pe `updated`, nu `created` → două căi produc „același" document. **Direcție:** consolidează generarea într-un serviciu unic; expune erorile.
- **[PLAUZIBIL] Numerotarea facturilor fără lock.** `ViewPayout:694-698` derivă următorul număr din `max(id)` + regex, fără lock → generare concurentă poate emite `F-000123` duplicat. Factura nu e garantată (buton manual); un decont finalizat fără factură e stare tăcută normală. VAT hardcodat 19%. **Direcție:** numerotare atomică; auto-creare la generarea decontului; centralizează `recipient_type`.
- **[PLAUZIBIL] Bază de comision inconsistentă:** catalog/pre-promo în `buildRemainingTicketsItems:456` și `buildPayoutSplitTable:778/799`, dar preț efectiv plătit în `SalesBreakdownService:326`. Pentru biletele cu reducere, comisionul din tabelul „Detalii bilete" poate diferi de cel de pe rând. **Direcție:** o singură bază de comision, documentată.
- **[PLAUZIBIL] Granularitate de rotunjire diferită** între căi (per-rând vs sumă curentă `:205/:227`; per-bilet `:341/:802`; per-felie Calea C). Acumulează câțiva bani de dezacord la evenimente cu qty mare. **Direcție:** rotunjire doar la totaluri finale; matematică internă în bani-întregi unde se poate.
- **[PLAUZIBIL] Limite de perioadă amestecă `date` cu datetime `exactBounds`** — `period_start/end` cast `date`, dar `resolveNextPeriodStart` întoarce datetime, iar interogările de discount/promo pentru PDF folosesc `startOfDay/endOfDay` → două deconturi în aceeași zi pot dubla lista de coduri promo. **Direcție:** stochează și compară limitele ca datetime consistent.
- **[PLAUZIBIL] `max(0.0, …)` ascunde un net legitim negativ** (avans/retur > vânzări). Un avans de 5.000 pe un decont de 3.000 ar trebui să arate −2.000 „de recuperat", dar tipărește 0.00 și datoria dispare. **Direcție:** permite negative pe plătibilul final.
- **[PLAUZIBIL] `qty` fără limită superioară** (`create:616`, `edit ViewPayout:291`, doar `minValue(0)`). `qty > available` acceptat → supra-plată + inflație `quota_sold` la reverse. **Direcție:** `maxValue`/clamp server-side.
- **[PLAUZIBIL] VAT per-rând duplicat** — VAT-ul total al comisionului e emis în fiecare rând 1a/1c/1e (`MarketplaceTaxTemplate:2442`); un decont cu 3 reguli îl tipărește de 3 ori. Rata 19% hardcodată vs 21% default în alt loc (`:528`). **Direcție:** VAT pe grup sau un singur rând de totaluri.
- **[PLAUZIBIL] `promo_codes_used` din alte comenzi decât cele de pe decont** — codurile sunt interogate din toate comenzile evenimentului din fereastra de perioadă, independent de breakdown; pentru un decont micșorat sau fără `period_start/end` (scanează tot istoricul) codurile/perioada tipărite nu se potrivesc cu biletele efectiv pe decont. **Direcție:** sursă comună cu breakdown-ul.

---

## 4. MIC — îmbunătățiri / igienă

- **[CONFIRMAT] `OrganizerBalance` „quick payout"** (`:128`) creează decont fără `event_id`/`ticket_breakdown`, status `processing`→`complete` imediat. A doua cale reală de creare admin, în afara pipeline-ului; PDF „Detalii bilete" gol. **Direcție:** marchează `source` distinct / rutează prin builder / restricționează.
- **[CONFIRMAT] `GenerateAutoDeconts` foot-gun.** Schedule confirmat OPRIT (`routes/console.php:852`), dar comanda rămâne auto-descoperită și funcțională; rulată manual creează `source='automated'` cu matematica veche `Order::sum('total')` (pre-`SalesBreakdownService`), divergentă. **Direcție:** șterge comanda sau refuz hard în producție.
- **[CONFIRMAT] Cont bancar lipsă → `payout_method` gol tăcut** → PDF cu IBAN/bancă necompletate. **Direcție:** validează existența unui cont bancar rezolvabil înainte de generare.
- **[CONFIRMAT] `payout_net_amount`/`payout_amount` setate de două ori** (`:1663/:1705` apoi `:1982/:1989`); prima (net-de-comision) e suprascrisă tăcut. Cod mort care maschează discrepanța pe comision-inclus. **Direcție:** o singură calculație autoritară.
- **[PLAUZIBIL] `auto_distribute`** folosește `unit_price` ca „net per bilet", ignorând comisionul/on-top (`:443/449/462`) → distribuția afișată ratează ținta pentru `added_on_top` (salvarea finală e sigură, se re-derivă). **Direcție:** cheie de umplere = `unit_price − comision (+on-top)`.
- **[CONFIRMAT] `venue_owner_pos` exclus din `POS_SOURCES`** doar în `SalesBreakdownService:41-45` (motiv istoric: să nu schimbe retroactiv deconturi). Divergență latentă față de restul rapoartelor POS-aware. **Direcție:** flag documentat în loc de omisiune implicită.
- **[CONFIRMAT] Notificări:** `cancel()` nu notifică pe nimeni (nici organizator, nici admin) și nu are intrare `cancelled` în `typeMap/titleMap` (`:1703-1725`); nicio notificare admin la finalizare. **Direcție:** notificare de anulare + notificări admin.
- **[PLAUZIBIL] Index compus lipsă.** Combinația fierbinte `where(marketplace_organizer_id)->where(event_id)->whereIn(status)` apare în 8+ locuri; de verificat un index compus `marketplace_payouts(marketplace_organizer_id, event_id, status)`. **Direcție:** adaugă indexul + `unique(marketplace_client_id, decont_series)`.
- **[CONFIRMAT] ~19 variante de seturi de status** dispersate în cod (vezi tabelul din raportul de zonă). Două codificări echivalente-dar-diferite ale „claim-ului de felie" — se vor desincroniza la o editare viitoare. **Direcție:** extrage constante `ACTIVE_STATUSES`/`PAID_STATUSES`.
- **[CONFIRMAT] Două implementări ale „eveniment încheiat":** query effective-end (`ListPayouts:1131`) vs `start_date/isPast()` în Select-ul modalului (`:105`). Ambele corecte acum, de unificat. Plus docblock mort `MarketplacePayout:254-266`.
- **[PLAUZIBIL] Fallback `getEffectivePrice` NULL→catalog** și tratarea invitațiilor (`order_id NULL`) diferă între căi (numărate în A/C, nu în B) — încă un motiv structural de divergență a celor 3 neturi. **Direcție:** aliniază tratarea invitațiilor.

---

## 5. Prioritizare recomandată

1. **Securitate (rapid, izolat):** autorizarea pe `PayoutResource` (§2.5) + scope de tenant la create/refund (§2.6). Impact mare, risc mic de regresie.
2. **Integritate de bani:** îngheață netul la finalizare (§2.4) + rutează `net_override` prin toți consumatorii sau reconciliază `amount` (§2.3) + rezervarea de balanță pe deconturi manuale (§2.1). Acestea trei previn corupția reală de bani/balanță.
3. **Retururi:** unifică `markRefunded` cu `PaymentRefundService` (§2.2) + include `partially_refunded` (§2.9) + reconciliere retur post-decont.
4. **Keystone structural (efort mai mare, dar rezolvă o clasă întreagă):** breakdown înghețat = sursă unică; PDF/afișare însumează rândurile tipărite; retrage recalculul live (§1, §2.7, §2.8, §2.10). De abordat cu migrare atentă a deconturilor existente.
5. **Igienă:** guard atomic anti-duplicat (§3), unicitate serie (§3), audit trail net_override (§3), constante de status (§4).

---

## 6. Notă de metodă și limitări

- Analiză **statică** (citire de cod), fără execuție la runtime. Constatările `PLAUZIBIL` trebuie reproduse pe date reale înainte de fix.
- Referințele `fișier:linie` reflectă starea din 2026-07-29; se pot deplasa la editări ulterioare.
- Cele mai critice 3 constatări (§2.1, §2.2, §2.5) au fost **verificate direct** în cod.
- `epas/_archive/` conține o copie veche de resurse payout care **nu** e autoloaded — nu există drift de ierarhie duplicată `/admin` vs `/marketplace` (există doar `/marketplace`).
- Nu s-a modificat niciun fișier de producție. Acest document e singurul artefact.

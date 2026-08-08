# DECISIONS.md — Shorts

Fiecare decizie luată autonom în timpul implementării Shorts (`docs/plans/shorts.md`),
pe branch-ul `claude/shorts`. Format: context → alegere → alternative → impact.

---

## D-001 — Coloane `string` în loc de `enum` în migrații

**Context.** Specificația (`shorts.md` §2.1) cere `enum` pentru `source`, `status`,
`cta_type` și `short_events.type`. Producția rulează pe PostgreSQL; dev/CI pe SQLite.
Fazele ulterioare adaugă valori noi (tipuri de eveniment `progress`, statusuri de
promovare, surse noi de ingestie).

**Alegere.** Coloane `string` cu lungime fixă + constante de validare pe model
(`Short::STATUSES`, `ShortEvent::TYPES`), validare la nivel de aplicație.

**Alternative.** (a) `enum` Laravel — pe Postgres devine `varchar + CHECK`; adăugarea
unei valori cere rescrierea constrângerii, adică o migrație distructivă, exact ce
gardurile din `shorts-START-PROMPT.md` interzic. (b) tipuri enum native Postgres —
și mai rigide, și incompatibile cu SQLite.

**Impact.** Migrațiile rămân aditive pe toate fazele. Costul: integritatea valorilor
e garantată de aplicație, nu de DB. Constantele sunt sursa unică de adevăr și sunt
folosite atât în validarea API, cât și în opțiunile Filament.

---

## D-002 — Schemă de test/dev „scoped" în loc de replay complet al migrațiilor

**Context.** Repo-ul are 747 de migrații. Replay-ul complet pe SQLite se oprește la
`2025_10_31_200100_events_translatables` (SQLite refuză `DROP COLUMN` pe o coloană
indexată). Containerul nu are server PostgreSQL sau MySQL și nici daemon Docker,
deci nu există nicio bază de date pe care istoricul complet să poată rula.

**Alegere.** O schemă redusă, dedicată: `tests/database/migrations/` conține
stub-uri minimale pentru tabelele din amonte pe care Shorts le referențiază
(`marketplace_customers`, `events`, `artists`, `orders`, `ticket_types`, `tenants`,
`marketplace_clients`, listele de contacte folosite de `MarketplaceCustomerObserver`),
peste care se rulează migrațiile Shorts. Folosită de:

- `tests/Feature/Shorts/ShortsTestCase.php` — SQLite `:memory:`, conexiune proprie;
- `scripts/shorts-dev-migrate.sh` — DB de dev la `database/dev-shorts.sqlite`.

**Alternative.** (a) Repararea migrației din amonte — atinge cod nelegat de Shorts și
riscă un efect de domino peste alte migrații SQLite-incompatibile din cele 747.
(b) Rularea fără verificare de migrație — ar încălca „Definition of Done".

**Impact.** Migrațiile Shorts sunt verificate că rulează curat, izolat. NU e verificată
interacțiunea cu schema reală completă — `TODO(owner)`: rulează migrațiile Shorts o
dată pe un dump de dev PostgreSQL înainte de deploy.

**Consecință de convenție.** Fiecare migrație Shorts se numește
`<timestamp>_shorts_<descriere>.php`. Sufixul `_shorts_` e cum le descoperă suita de
teste; fără el, o migrație nouă nu intră în schema de test. (Prima variantă folosea
un glob pe `*short*`, care prindea și
`add_images_and_short_description_to_microservices_table` — de aici convenția strictă.)

---

## D-003 — `NullVideoProvider` ca fallback când Bunny nu e configurat

**Context.** Cheile Bunny sunt placeholder în `.env.example` (gard: fără chei reale).
`VideoProvider` e injectat în feed, în controllerul de upload și în joburi.

**Alegere.** `VideoServiceProvider` construiește `BunnyStreamProvider` și, dacă
`isConfigured()` e fals, îl înlocuiește cu `NullVideoProvider`. Citirile degradează
la `null` (feed-ul continuă să servească shorts externe și self-hosted); scrierile
aruncă excepție explicită.

**Alternative.** (a) Binding direct pe Bunny — orice mediu fără chei crapă la boot-ul
feed-ului. (b) `null` din container — mută verificările `if ($provider)` în toți
consumatorii.

**Impact.** Dev/CI pornesc pe config placeholder. Endpoint-ul de upload răspunde
`503` cu mesaj explicit în loc să eșueze obscur.

---

## D-004 — Webhook-ul Bunny e doar declanșator; starea se recitește autoritar

**Context.** Bunny Stream nu semnează webhook-urile (`shorts.md` §C2).

**Alegere.** Dublă apărare: (1) `verifyWebhook()` compară cu `hash_equals` secretul din
query/header cu `services.bunny.stream_webhook_secret` și respinge când secretul nu e
configurat; (2) payload-ul nu e niciodată crezut pe cuvânt — `SyncShortPlaybackJob`
recitește starea prin `getPlayback()`, care e autoritativă.

**Alternative.** Încrederea în payload — un webhook rejucat sau falsificat ar putea
marca `ready` un asset inexistent.

**Impact.** Un webhook falsificat nu poate afirma nimic despre un asset. Costul: un
apel API în plus per tranziție „ready".

---

## D-005 — Un asset devenit `ready` NU se publică singur

**Context.** `shorts.md` §C5 spune explicit „status rămâne draft/pending_review".

**Alegere.** `SyncShortPlaybackJob` scrie doar `ready`, `duration`, `width`, `height`.
Publicarea e o acțiune umană separată (bulk action în Filament). Acțiunea de publicare
refuză short-urile native cu `ready = false` și raportează câte au fost sărite.

**Impact.** Nimic nu ajunge în feed fără o decizie explicită. Testat în
`BunnyStreamProviderTest::test_sync_job_stamps_playback_metadata_without_publishing`.

---

## D-006 — URL-uri de playback semnate per cerere, niciodată persistate

**Context.** `shorts.md` §C1/§C6.

**Alegere.** `Short::playback_url` / `poster_url` cer providerului un URL semnat la
fiecare acces, cu TTL din `services.video.signed_url_ttl`. Coloanele `hls_url` rămân
în schemă doar ca fallback pentru asset-uri self-hosted / provideri fără semnare.

**Impact.** Zero URL-uri expirate în cache sau în payload-uri salvate. Costul: accessorul
nu poate fi memoizat între cereri.

**Deschis.** `TODO(owner)` în `BunnyStreamProvider::sign()` — schema exactă de token
(ordinea `key + path + expires`) trebuie confirmată în setările pull zone-ului când
apar cheile reale. Codul e izolat într-o singură metodă privată.

---

## D-007 — Paginare cursor keyset, nu offset

**Context.** Feed infinit peste un set care se schimbă sub cititor.

**Alegere.** Cursor opac base64url care poartă `(published_at, id, is_featured)`.
Ordinea: `is_featured DESC, published_at DESC, id DESC`. Odată ce cursorul a ieșit din
blocul „featured", nu se mai întoarce în el.

**Alternative.** Offset — duplică și sare rânduri la fiecare publicare nouă.

**Impact.** Fără duplicate și fără rânduri sărite; testat pe 7 short-uri în 3 pagini.
Când rankerul „For You" apare (Faza 5), contractul cursorului rămâne același — se
schimbă doar ordonarea candidaților.

---

## D-008 — Agregatele denormalizate nu sunt mass-assignable

**Context.** `shorts.impressions/views/likes/...` sunt derivate din `short_events`.

**Alegere.** Excluse din `$fillable`. Se mișcă doar prin `increment()`/`decrement()`
(toggle-uri) sau prin `AggregateShortStatsJob` (recalcul complet, idempotent).

**Impact.** Un `Short::create()` sau un update din formular nu poate falsifica
statisticile. `AggregateShortStatsJob` recalculează de la zero per short, deci o
re-rulare după eșec nu dublează niciodată.

---

## D-009 — Telemetria acceptă și guest, dar filtrează view-urile necredibile

**Context.** Feed-ul se poate parcurge înainte de login (`shorts.md` §5).

**Alegere.** `POST tenant-client/shorts/events` acceptă un `session_id` fără token.
`ShortTelemetryService` respinge: short-uri inexistente, tipuri necunoscute, și
view-uri sub prag (`< 2s` și `< 25%`, configurabil). Impresiile pot fi eșantionate 1/N.
`watch_ratio` e limitat în `[0,1]`; numele de feed necunoscute sunt puse pe `null`.

**Impact.** Statisticile nu pot fi umflate de un client rău-intenționat prin batch-uri
de impresii instantanee. Pragurile sunt în `config/shorts.php`, nu hardcodate.

---

## D-010 — Rute Shorts într-un bloc propriu la finalul `routes/api.php`

**Context.** `routes/api.php` are 4037 de linii și mai multe grupuri `tenant-client`.

**Alegere.** Un bloc delimitat și comentat la final, cu propriile middleware-uri
(`throttle:300,1` doar pentru telemetrie, care e cel mai zgomotos endpoint).

**Alternative.** Inserare în grupurile existente — diff mai greu de citit și un
`throttle` comun nepotrivit pentru telemetrie.

**Impact.** Faza se poate revizui ca un singur hunk. Prefixele fiind distincte, nu
există conflicte de rutare.

---

## D-011 — `MorphToSelect` fără `helperText()`

**Context.** Filament 4.1 nu expune `helperText()` pe `MorphToSelect` (aruncă
`BadMethodCallException` la randare, nu la deploy).

**Alegere.** Explicația a urcat pe `Section::description()`. Am adăugat
`ShortResourceTest`, care compilează schema și tabelul, ca acest gen de greșeală să
apară în CI, nu în producție.

**Impact.** Un test ieftin care prinde derapajele de API Filament la fiecare upgrade.

---

## D-012 — App-ul mobil e în repo; feed live cu fallback pe prototip

**Context.** `shorts-START-PROMPT.md` presupunea că app-ul mobil s-ar putea să nu fie în
repo („dacă nu ai încă app-ul mobil în acest repo, livrează componentele ca modul
separat"). Este: `tics-app/mobile` (React 18 + Capacitor 6 + Vite), cu un ecran
`Shorts.tsx` („Pe val") deja existent — dar construit integral pe datasetul prototipului
(`EV`/`ART`/`VEN`), fără video, fără API, fără telemetrie.

**Alegere.** Am construit feed-ul live ca modul propriu (`features/client/shorts/`) și l-am
pus în fața celui din prototip: `Shorts.tsx` randează `<LiveShorts fallback={<PrototypeShorts/>}>`.
Când EPAS nu are încă short-uri publicate sau e indisponibil, se vede feed-ul din prototip.

**Alternative.** (a) Înlocuirea ecranului — pierdem fallback-ul și ecranul devine gol pe
tenanții demo. (b) Modul separat nefolosit — livrare moartă.

**Impact.** Exact convenția deja stabilită în `src/api/tenantClient.ts` („efectiv cablat
la EPAS, dar rămâne plin când sursa e goală"). Zero regresie vizuală.

---

## D-013 — hls.js prin import dinamic, nu în bundle-ul de pornire

**Context.** hls.js are ~163 kB gzip. `shorts.md` §7 îl cere pentru Android/WebView; iOS
redă HLS nativ.

**Alegere.** `await import('hls.js')` în `ShortVideo`, apelat doar când
`video.canPlayType('application/vnd.apple.mpegurl')` e gol. Vite îl scoate într-un chunk
separat (`hls-*.js`), încărcat la prima deschidere a feed-ului.

**Alternative.** Import static — 163 kB în plus la fiecare pornire, inclusiv pentru
utilizatorii care nu deschid niciodată feed-ul, și inclusiv pe iOS unde e cod mort.

**Impact.** Bundle-ul de pornire rămâne neschimbat. Verificat: build-ul produce
`hls-BhNU2oyu.js` separat de `index-*.js`.

---

## D-014 — Detecția HLS prin `canPlayType`, nu prin user agent

**Context.** Trebuie să știm dacă platforma redă HLS nativ.

**Alegere.** `video.canPlayType('application/vnd.apple.mpegurl') !== ''`.

**Alternative.** Sniffing de user agent — se rupe la fiecare schimbare de WebView și
minte în modurile desktop.

**Impact.** Corect pe iOS, Android WebView și browser desktop, fără listă de excepții.

---

## D-015 — Cel mult 3 elemente `<video>` montate simultan

**Context.** Un feed infinit cu `<video>` per card scurge memorie în WebView.

**Alegere.** `PRELOAD_RADIUS = 1`: doar short-ul activ și vecinii imediați montează
`ShortVideo`; restul afișează posterul. Vecinii au `preloadOnly`, deci încarcă dar nu
redau.

**Impact.** Memoria rămâne constantă indiferent cât se derulează. Costul: un scroll foarte
rapid poate ajunge pe un card care încă încarcă — acoperit de poster, nu de ecran negru.

---

## D-016 — Telemetria pleacă pe `sendBeacon` la moartea paginii

**Context.** Cel mai valoros eveniment (`watch_ratio` la ieșire) e și cel mai expus
pierderii: se produce exact când utilizatorul părăsește ecranul sau trimite app-ul în
fundal.

**Alegere.** Coada se golește la `visibilitychange`→hidden și la `pagehide` prin
`navigator.sendBeacon`; în rest `fetch` cu `keepalive`. Toate erorile sunt înghițite —
telemetria nu are voie să apară în fața utilizatorului.

**Impact.** Loturile finale ajung la server. Costul: `sendBeacon` nu poartă header de
`Authorization`, deci ultimul lot e atribuit doar pe `session_id`. Acceptabil: evenimentele
de identitate (like/save) sunt scrise oricum de server, nu de client.

---

## D-017 — Praguri de „view" oglindite client ↔ server

**Context.** `config/shorts.php` respinge view-urile sub 2s / 25%.

**Alegere.** Clientul folosește aceleași praguri și trimite `skip` în locul unui `view`
sub prag. Serverul revalidează oricum.

**Alternative.** Client permisiv — ar genera trafic pentru evenimente pe care serverul
le aruncă oricum.

**Impact.** Mai puțină bandă irosită. Dacă pragurile se schimbă în config, constantele din
`LiveShorts.tsx` trebuie actualizate — `TODO(owner)`: dacă divergența devine o problemă,
expune pragurile prin `/tenant-client/config`.

---

## D-018 — `PushSender` care loghează, nu care tace

**Context.** EPAS n-are layer de push customer-facing (nici credențiale FCM/APNs, nici
tabel de device tokens). D2 (drop reminders) și D12 (nudge-uri comportamentale) depind de el.

**Alegere.** Contract `PushSender` + `LogPushSender` legat implicit: fiecare trimitere e
scrisă în log cu payload complet, iar `isConfigured()` întoarce `false`.

**Alternative.** (a) No-op tăcut — logica de declanșare devine neverificabilă și eșecul e
invizibil. (b) Amânarea D2/D12 — ar bloca două funcționalități pentru o dependență care nu
ține de Shorts.

**Impact.** Declanșarea e testabilă end-to-end azi (vezi
`test_drop_job_fires_due_reminders_once_and_only_once`); când apare transportul real,
se schimbă un singur binding. `TODO(owner)` marcat în `PushSender`.

---

## D-019 — `remind_at` se copiază la creare, nu se rezolvă la fire time

**Context.** Momentul deschiderii vânzării vine din `TicketType.sales_start_at`, care poate
fi editat sau șters după ce clientul a apăsat „Amintește-mi".

**Alegere.** `remind_at` e copiat pe `short_reminders` la creare.

**Alternative.** Join la fire time — dacă tipul de bilet dispare, reminderul devine orfan;
dacă se mută, clientul primește o notificare pentru un moment pe care nu l-a cerut.

**Impact.** Reminderul supraviețuiește modificărilor din amonte. Costul: o reprogramare a
vânzării nu se propagă automat. `TODO(owner)`: dacă reprogramările devin frecvente, adaugă
un observer pe `TicketType` care rescrie reminderele deschise.

---

## D-020 — Un reminder de drop nu se retrimite niciodată

**Context.** `FireDropRemindersJob` rulează la minut. Fără push real, mesajul doar se
loghează.

**Alegere.** `notified_at` se stampilează indiferent dacă transportul e configurat.

**Alternative.** Marcarea doar la trimitere reușită — când apare push-ul real, joburile ar
inunda utilizatorii cu notificări pentru drop-uri vechi de săptămâni.

**Impact.** Un drop pierdut rămâne pierdut; o notificare „biletele sunt live" la trei
săptămâni după drop e mai rea decât niciuna.

---

## D-021 — Gamification marketplace-side până apare puntea de identitate

**Context.** Ledgerul de puncte (`Gamification\PointsTransaction`) atârnă de `Customer`;
cumpărătorul din app e `MarketplaceCustomer`. Puntea e decizia deschisă din
`friends-social.md` §0.

**Alegere.** `short_streaks` ține streak-ul și punctele marketplace-side.
`ShortGamificationService::forwardToLoyaltyLedger()` scrie în ledgerul real doar când
`IdentityBridge::isAvailable()` (adică există coloana de legătură).

**Alternative.** (a) Ghicirea legăturii după email — creează atribuiri greșite peste
tenanți. (b) Amânarea D11 — blochează bucla de loialitate pentru o coloană lipsă.

**Impact.** Bucla funcționează azi; punctele curg în ledgerul real fără schimbări la
call-site în momentul în care coloana apare. `TODO(owner)` în `IdentityBridge`.

---

## D-022 — Plafon zilnic de puncte, nu doar praguri de validitate

**Context.** Punctele pentru watch/share pot fi farmate cu un script.

**Alegere.** Plafon zilnic (`shorts.gamification.daily_cap`) aplicat în `grant()`, peste
pragurile de credibilitate din D-009. Bonusul de streak e și el plafonat, ca ziua 400 să
nu valoreze absurd.

**Impact.** Testat în `test_daily_points_cap_stops_farming` — a treia acordare peste plafon
întoarce 0.

---

## D-023 — Blurhash aproximat, nu bibliotecă nouă

**Context.** D9 cere un placeholder LQIP. Un BlurHash adevărat cere o implementare DCT.

**Alegere.** `GenerateBlurhashJob` produce `g2x3:<12 culori hex>` — o grilă 2×3 de culori
medii extrasă cu GD; clientul o randează ca gradient radial.

**Alternative.** (a) `kornrunner/blurhash` — o dependență nouă pentru un placeholder.
(b) Fără LQIP — dreptunghi negru pe rețea slabă, exact ce D9 vrea să evite.

**Impact.** Zero dependențe noi. Formatul e prefixat cu tipul, deci un BlurHash adevărat
poate fi adăugat mai târziu fără migrație și fără schimbarea payload-ului.
`TODO(owner)` notat în job.

---

## D-024 — Landing-ul de share nu redirecționează spre store pe temporizator

**Context.** Tiparul obișnuit e: încearcă deep-link-ul, iar după ~2s trimite în store.

**Alegere.** Încercăm deep-link-ul imediat; dacă nu se întâmplă nimic, rămân butoanele.
Fără temporizator.

**Alternative.** Redirect pe temporizator — trimite în store și utilizatorii care AU app-ul,
doar că au răspuns încet la dialogul de sistem. Aterizezi în App Store în loc de conținut.

**Impact.** Un pas manual în plus pentru cine n-are app-ul, zero drumuri greșite pentru cine
îl are.

---

## D-025 — CSS-ul de shorts stă în `shorts.css`, nu în `client.css`

**Context.** `tics-app/mobile/src/design/client.css` e **generat** din
`tics-app/client-app.html` (`node scripts/extract-client-css.cjs`). Prima variantă a Fazei 2
a adăugat regulile de video direct acolo.

**Alegere.** Regulile scrise de mână s-au mutat în `src/design/shorts.css`, importat după
`client.css` în `main.tsx`. `client.css` a fost readus exact la ce produce generatorul
(verificat prin rerulare).

**Alternative.** Editarea prototipului `client-app.html` — ar însemna să inventăm UX în
prototip pentru ceva ce prototipul nu are (video real).

**Impact.** O regenerare a CSS-ului nu mai șterge stilurile feed-ului live.

---

## D-026 — Preferința de autoplay e a dispozitivului, nu a contului

**Context.** D10 cere o setare „Redare automată: mereu / Wi-Fi / niciodată".

**Alegere.** `localStorage`, citită sincron la montarea playerului.

**Alternative.** Pe cont, prin API — ar însemna că prima redare pornește înainte să afle
preferința (exact utilizatorii care au cerut „niciodată"), și că guest-ii nu pot alege deloc.

**Impact.** Setarea e activă din prima redare, inclusiv pentru guest. Costul: nu se sincronizează
între dispozitive — acceptabil, e o preferință legată de ecran și de conexiune.
`prefers-reduced-motion` bate oricând setarea din app.

---

## D-027 — Atribuirea conversiilor trece prin observer pe `Order`, nu prin eveniment

**Context.** B1 cere incrementarea `shorts.conversions`/`revenue_cents` „la plata
confirmată". Există `OrderConfirmed`, dar poartă doar `(tenantId, orderRef, orderData)` —
nu modelul — și nu e emis pe toate căile prin care o comandă ajunge plătită (webhook
Stripe, comandă gratuită, acțiune de admin).

**Alegere.** `ShortAttributionOrderObserver` pe tranziția de `status`, exact acolo unde
stă și sincronizarea de bilete din `Order::saved()`. Observerul nu aruncă niciodată.

**Alternative.** Ascultarea `OrderConfirmed` — ar rata căile care nu-l emit, iar
reconstrucția modelului din `orderRef` e o interogare în plus pentru date pe care
observerul le are deja.

**Impact.** O atribuire eșuată nu poate da înapoi o comandă plătită. Acoperă toate căile.

---

## D-028 — Idempotența atribuirii stă pe comandă, nu pe agregat

**Context.** Retry-urile de webhook sunt normale; o comandă poate parcurge
`pending → paid → confirmed → completed`.

**Alegere.** `orders.short_attributed_at`. `credit()` iese devreme dacă e deja stampilat;
`reverse()` iese devreme dacă nu e. O comandă al cărei short a fost șters e stampilată
oricum, ca să nu se re-verifice la fiecare schimbare de status.

**Alternative.** Deducerea din agregate — imposibil de spus dacă „conversions = 1" vine de
la această comandă sau de la alta.

**Impact.** Testat pe trei tranziții succesive spre plătit: un singur credit.

---

## D-029 — Agregatele de conversie se plafonează la zero la reversare

**Context.** Un refund scade `conversions`/`revenue_cents`. Dacă agregatul a fost între
timp rescris (recalcul, editare manuală), scăderea poate depăși valoarea stocată.

**Alegere.** `CASE WHEN ... THEN ... ELSE 0 END` în SQL: se plafonează la zero, nu trece
în negativ și nu lasă venit fantomă pentru o comandă rambursată.

**Impact.** Un raport poate arăta zero în loc de o valoare mică, dar niciodată un număr
negativ de vânzări. Testat în `test_aggregates_never_go_negative`.

---

## D-030 — CTA click e sincron, nu batched

**Context.** Restul telemetriei pleacă în loturi la 5 secunde.

**Alegere.** `POST tenant-client/shorts/{id}/cta-click` trimis imediat, public, și care
întoarce oferta de onorat (event, tip de bilet, cod promo, `source_short_id`, `source_feed`).

**Alternative.** Batching — tap-ul pe CTA e ultimul lucru dinainte de plecarea din feed;
la flush ecranul e deja închis și evenimentul se pierde exact pentru clicurile care
convertesc.

**Impact.** `cta_clicks` e corect, iar clientul primește într-un singur drum tot ce trebuie
să ducă în checkout. Clientul nu așteaptă răspunsul înainte să navigheze.

---

## D-031 — Nu am atins `CheckoutController` pentru ultimul pas al atribuirii

**Context.** Bucla se închide când `Order::create()` primește `source_short_id`/`source_feed`
din payload-ul de checkout.

**Alegere.** Coloanele sunt `fillable` și observerul e gata; propagarea propriu-zisă e
lăsată ca `TODO(owner)` explicit în `PROGRESS.md`.

**Alternative.** Editarea `CheckoutController` (1700+ linii, două căi de `Order::create()`,
fluxuri reale de plată) fără să pot rula un test de checkout end-to-end — schema de test
redusă (D-002) nu acoperă checkout-ul.

**Impact.** Tot ce ține de Shorts e complet și testat; rămâne o singură linie într-un fișier
pe care nu-l pot verifica aici. Riscul e vizibil în PROGRESS, nu ascuns într-un diff.

---

## D-032 — Graf de urmărire dedicat, nu `favoriteArtists` ca proxy

**Context.** `shorts.md` B2 sugerează că `favoriteArtists` poate ține loc de „following"
la început.

**Alegere.** Tabel polimorf `marketplace_follows` (Artist / Tenant / Venue) de la bun
început. `favoriteArtists` rămâne folosit de ranker, ca semnal **mai slab** decât un follow.

**Alternative.** Doar favorite — acoperă exclusiv artiștii; cine urmărește un organizator
sau o locație n-are unde să pună asta, iar segmentul „Following" e orb la ei.

**Impact.** Segmentul e complet din prima. API-ul vorbește tokenuri scurte („artist"), nu
nume de clase, deci clientul nu depinde de namespace-urile serverului.

---

## D-033 — Rankerul e un scor explicabil, nu un model

**Context.** §6 cere „ține-l explicabil (loghează scorul în dev)".

**Alegere.** Termeni numiți (`affinity`, `popularity`, `watch`, `geo`, `freshness`,
`featured`, `seen`), fiecare cu pondere în `config/shorts.php`; defalcarea per short se
loghează când `shorts.ranker.explain` e pornit (niciodată în producție).

**Impact.** Când un short ajunge pe poziția 1, cineva poate spune de ce. Ponderile se
reglează fără deploy de cod.

---

## D-034 — Cursorul rămâne pe keyset-ul de recență, nu pe ordinea rankată

**Context.** Rankerul reordonează candidații; paginarea trebuie să rămână fără duplicate.

**Alegere.** Interogarea aduce un pool mărginit ordonat pe recență, rankerul reordonează
**doar pagina**, iar `next_cursor` se ia din ultimul rând al ferestrei de recență — nu din
ultimul rând rankat.

**Alternative.** Cursor pe scor — scorul se schimbă între două cereri (prospețime, „deja
văzut"), deci cursorul ar sări sau ar repeta rânduri.

**Impact.** Testat: două pagini rankate consecutive nu repetă niciun short.

---

## D-035 — Diversitatea reordonează, nu scurtează

**Context.** Regula „niciodată două consecutive de la același owner" poate elimina rânduri
dacă un singur owner domină pool-ul.

**Alegere.** Short-urile care ar încălca regula sunt **amânate**, re-admise imediat ce nu
mai se ciocnesc, iar ce rămâne la final se pune la coadă.

**Alternative.** Eliminarea lor — pagina s-ar micșora tăcut și feed-ul infinit s-ar opri
mai devreme decât are date.

**Impact.** Testat: 6 short-uri intrate, 6 ieșite, primele două de la owneri diferiți.

---

## D-036 — „Nearby" filtrează pe orașul VENUE-ului

**Context.** Prima implementare a citit `events.city`. Coloana nu există — orașul stă pe
`venues.city` (evenimentele au `venue_id`).

**Alegere.** Filtrul merge `event → venue`, iar `event.venue` e eager-loaded în
`baseQuery()` ca semnalul geo din ranker să nu declanșeze o interogare per short.

**Alternative.** Radius pe lat/lng — coordonatele nu sunt purtate fiabil de schemă.

**Impact.** Prins de test (`test_nearby_filters_by_the_viewers_city`), nu în producție.
`?city=` din client bate orașul din profil, pentru cine călătorește.

---

## D-037 — Short-urile organizatorilor intră în `pending_review`

**Context.** `shorts.md` §17 lasă deschisă întrebarea: direct în feed sau prin moderare?

**Alegere.** Moderare. `CreateShort` din panoul tenant forțează `pending_review` și
stampilează `tenant_id` din utilizatorul autentificat, nu din formular. Editarea unui short
deja publicat îl trimite înapoi la review.

**Alternative.** Publicare directă — feed-ul global e o suprafață editorială comună; un
singur organizator poate strica experiența pentru toți.

**Impact.** Statusul e read-only în panoul tenant, deci nu poate fi ocolit prin payload.
Costul: moderarea devine o sarcină recurentă pentru core admin.

---

## D-038 — Meta (IG/FB) întoarce `null`, nu un short pe jumătate completat

**Context.** oEmbed Read de la Meta cere app + review; tokenul nu există.

**Alegere.** `ShortIngestService::meta()` verifică tokenul și întoarce `null` când
lipsește. Adminul vede „nu s-a putut citi nimic din link", nu un short cu titlu dar fără
embed.

**Alternative.** Scraping de Open Graph — fragil, și pe muchia ToS-ului.

**Impact.** YouTube și TikTok merg azi. IG/FB sunt o singură variabilă de config distanță.
`TODO(owner)` în serviciu și în config.

---

## D-039 — Thumbnail-urile se copiază local, embed-ul nu

**Context.** Regula de aur: fără re-host de video. Thumbnail-urile sunt altceva —
`social-video-ingestion.md` §0 le marchează explicit ca „uz uzual, acceptat".

**Alegere.** `IngestShortJob` descarcă thumbnail-ul pe disk-ul `public` și
declanșează `GenerateBlurhashJob` pe el. Embed-ul rămâne al platformei.

**Alternative.** Hotlink la CDN-ul platformei — URL-urile se rotesc, iar feed-ul rămâne
cu goluri.

**Impact.** Feed-ul nu se strică atunci când o platformă schimbă CDN-ul. Fișierul video nu
e atins niciodată.

---

## D-040 — Pull-ul de canal filtrează strict la 60 de secunde

**Context.** `getRecentVideos()` întoarce ultimele uploaduri, nu doar Shorts.

**Alegere.** `PullChannelShortsJob` sare peste orice depășește 60s (pragul YouTube pentru
Shorts) și peste orice fără durată cunoscută.

**Alternative.** Import complet + curatoriere manuală — un live set de 40 de minute într-un
feed vertical e datorie de curatoriere, nu acoperire.

**Impact.** Ce ajunge în coada de curatoriere e plauzibil. Deduplicarea pe
`(source, source_video_id)` face re-rularea sigură.

---

## D-041 — Pull programat doar pentru artiștii cu evenimente viitoare

**Context.** YouTube Data API are cotă zilnică.

**Alegere.** Task-ul săptămânal iterează doar artiștii cu `youtube_id` **și** cu un
eveniment viitor.

**Alternative.** Tot rosterul — arde cota pe short-uri de la artiști care nu joacă nicăieri
și pe care nu-i curatorează nimeni.

**Impact.** Cota se cheltuie pe conținut care poate vinde un bilet. Short-urile pulled sunt
legate automat de următorul eveniment al artistului.

---

## D-042 — Reparat un `TypeError` latent în `YouTubeService`

**Context.** Constructorul face `$this->apiKey = $settings->youtube_api_key ?: config(...)`
pe o proprietate tipată `string`. Când `YOUTUBE_API_KEY` lipsește, `config()` întoarce
`null` → `TypeError` la construcție.

**Alegere.** Cast la `string`. Trei linii, plus comentariul care spune de ce.

**De ce acum.** Nu se vedea cât timp serviciul era construit doar explicit cu `new` în
joburi. `ShortIngestService` îl injectează, deci containerul îl rezolvă — și pe orice mediu
fără cheie (inclusiv CI) devenea o eroare de boot a feature-ului.

**Impact.** Serviciul se comportă acum ca `TikTokService`/`FacebookService`, care tratau
deja cheia lipsă ca „neconfigurat" în loc să crape.

---

## D-043 — Trending = velocitate raportată la baseline, nu totaluri

**Context.** D4 cere un `trending_score`.

**Alegere.** `(engagement recent / oră) / (engagement mai vechi / oră + 1)`. Un short
care tocmai a pornit scorează mare chiar de la o bază absolută mică; unul constant stă în
jur de 1. Ce n-are engagement în fereastră e resetat la zero la fiecare rulare.

**Alternative.** Ordonarea pe totaluri — așa osifică un feed în jurul hiturilor lui vechi:
un short cu un milion de vizionări istorice nu e „trending".

**Impact.** Testat că un short în creștere depășește unul mai mare, dar constant. Rulează
la 15 minute: destul de des cât să prindă un short care decolează în aceeași sesiune.

---

## D-044 — Seen-store distilat, care supraviețuiește prune-ului

**Context.** Penalizarea „deja văzut" din ranker citea `short_events`. D6 șterge acele
rânduri după 90 de zile.

**Alegere.** `short_impressions` — un rând per (spectator, short), populat de
`SyncShortImpressionsJob`. Rankerul îl citește primul și cade pe telemetria brută pentru
intervalul dintre două rulări.

**Alternative.** Doar telemetria brută — un short ar reapărea în feed-ul cuiva după 90 de
zile pur și simplu pentru că dovada că l-a văzut a fost ștearsă.

**Impact.** Un rând per pereche, nu unul per redare. Testat exact pe cazul „raw șters,
seen-store rămas".

---

## D-045 — Explorare epsilon-greedy, care deplasează din coada paginii

**Context.** Popularitatea e un termen în scor, deci un short fără impresii nu poate
acumula semnalul de care ar avea nevoie ca să fie clasat. Rich-get-richer.

**Alegere.** O felie configurabilă din fiecare pagină (implicit 15%) e rezervată
short-urilor sub pragul de impresii, indiferent de scor. Locurile se iau **de la coada**
paginii, niciodată din vârf.

**Alternative.** (a) Fără explorare — conținutul nou nu iese niciodată la suprafață.
(b) Deplasare din vârf — explorarea l-ar costa pe spectator cel mai bun lucru pe care
îl aveam pentru el.

**Impact.** Testat: un short fără niciun semnal intră în pagină alături de patru cu
istoric puternic.

---

## D-046 — Prune-ul șterge în bucăți

**Context.** `short_events` e cel mai rapid crescător tabel din feature — o sesiune de
scroll scrie zeci de rânduri.

**Alegere.** `DELETE` în bucăți de 5000, cu plafon de 200 de bucăți per rulare.

**Alternative.** Un singur `DELETE` nemărginit — blochează tabelul și expiră pe worker
exact în ziua în care contează.

**Impact.** Prune-ul e mărginit ca durată. Dacă rămâne restanță, o recuperează a doua zi.

---

## D-047 — Nudge-urile comportamentale sunt opt-in, cu default **oprit**

**Context.** D12 cere trigger-e din comportamentul de vizionare.

**Alegere.** `notification_preferences` cu default per tip. Pentru
`shorts_abandoned` („ai văzut, n-ai cumpărat") default-ul e **oprit** — e nudge-ul cel mai
susceptibil să pară supraveghere. Absența unui rând înseamnă „folosește default-ul", deci
tabelul crește doar când cineva chiar schimbă ceva.

**Garduri suplimentare.** Quiet hours, cooldown de 14 zile per (user, eveniment), și
verificarea că nu a cumpărat deja evenimentul. Când verificarea de comandă nu poate fi
făcută, răspunsul e „a cumpărat" — o stare incertă nu are voie să producă un mesaj care îi
spune cuiva să cumpere ce are deja.

**Impact.** Patru teste acoperă exact aceste garduri.

**Deschis.** `TODO(owner)`: înrolarea în `AutomationWorkflow` (ca marketingul să editeze
copy și cadență fără deploy) nu e cablată — evaluarea trigger-ului și gardurile sunt, și
ele sunt partea care trebuie să fie corectă. Tot `TODO(owner)`: quiet hours folosesc fusul
aplicației, nu al destinatarului — `marketplace_customers` n-are coloană de timezone.

---

## D-048 — Partiționarea lunară a telemetriei rămâne o decizie de owner

**Context.** D6 sugerează partiționarea `short_events` pe lună.

**Alegere.** Nu am făcut-o. Rollup-ul, prune-ul și eșantionarea — care sunt cele care
țin tabelul mic — sunt livrate.

**De ce.** Partiționarea declarativă pe Postgres cere migrarea datelor existente și o
decizie despre cheia de partiționare, ambele luate în funcție de volumul real. Fără acces
la producție, ar fi o migrație riscantă scrisă în orb, exact ce interzic gardurile.

**Impact.** Notat ca `TODO(owner)` în `PROGRESS.md`, cu contextul necesar.

---

## D-049 — Auto-generarea are două moduri, nu unul amânat

**Context.** B3 cere generarea unui short din poster/galerie. Fără serviciu de render nu
există video.

**Alegere.** `GenerateShortFromEventJob` verifică dacă rendererul e configurat:
cu el produce un clip real; fără el produce un **„poster short"** — o imagine verticală
marcată `ready`, pe care feed-ul o redă ca un card. Ambele intră în `draft`.

**Alternative.** Amânarea întregii funcționalități până apar cheile — evenimentele fără
video vertical (adică majoritatea) rămân în continuare invizibile în feed.

**Impact.** Feed-ul se poate umple azi. Când apar cheile Shotstack, aceleași evenimente
încep să producă clipuri reale, fără altă schimbare.

---

## D-050 — O pană de render cade pe poster short, nu pe eroare

**Context.** Apelul de render poate eșua (rețea, cotă, mediu greșit).

**Alegere.** `try/catch`: la eșec, short-ul e marcat `ready` și rămâne poster short.

**Alternative.** Aruncarea excepției — jobul intră în retry și lasă în coada de curatoriere
un short care nu poate fi redat.

**Impact.** Testat cu un răspuns 500 de la Shotstack: short-ul rămâne redabil.

---

## D-051 — Captions: providerul întâi, transcrierea abia apoi, altfel nimic

**Context.** B6 sugerează auto-captions de la provider sau transcriere (Whisper/AssemblyAI).

**Alegere.** Ordine explicită: (1) ce are deja providerul video — gratis, fără serviciu în
plus; (2) un driver de transcriere, dacă e configurat; (3) nimic.

**De ce contează (3).** Subtitrările sunt un plus de accesibilitate, nu o precondiție de
publicare. Un short fără captions se publică; unul blocat de un serviciu de transcriere
indisponibil nu ajunge nicăieri.

**Impact.** `TODO(owner)` pentru driverul de transcriere; cheile de config există deja.

---

## D-052 — Analytics-ul organizatorului citește rollup-ul, nu telemetria brută

**Context.** B5 cere o pagină cu pâlnie, retenție și top shorts.

**Alegere.** `short_analytics_daily`, populat zilnic. Pagina nu atinge `short_events`.

**De ce.** Rândurile brute sunt tăiate de retenție (D6) — o pagină construită pe ele ar
arăta din ce în ce mai puțin în timp — și ar deveni mai lentă în fiecare săptămână.

**Impact.** Pagina are cost constant. Ratele (view rate, CTR, CVR) se calculează la
afișare, nu se stochează: s-ar învechi în clipa în care se mișcă oricare parte a raportului.

---

## D-053 — Payload-ul serializează captions doar când relația e încărcată

**Context.** Feed-ul întoarce 10 short-uri pe pagină.

**Alegere.** `ShortPayload` verifică `relationLoaded('captions')`; `baseQuery()` le
eager-loadează.

**Alternative.** Accesarea directă a relației — un N+1 ascuns în serializator, exact genul
care nu se vede la un test cu un singur short.

**Impact.** Testat în ambele direcții (încărcat → track-uri, neîncărcat → gol).

---

## D-054 — Un story E un short, nu un model separat

**Context.** B8 cere stories efemere de 24h.

**Alegere.** `shorts.is_story` + `expires_at`. `scopeStories()` cere expirare validă —
un story fără `expires_at` nu e story.

**Alternative.** Tabel separat — ar fi însemnat duplicarea redării, telemetriei, moderării
și a întregului pipeline video odată cu el, pentru o diferență care e o fereastră de timp.

**Impact.** Story-urile moștenesc gratis tot ce are un short. Costul: trebuie **excluse
explicit** din feed-ul principal, ceea ce e o linie ușor de uitat — de aceea are test
dedicat (vezi D-055).

---

## D-055 — Un patch aplicat pe o linie mutată = eșec tăcut

**Context.** Excluderea story-urilor din feed-ul principal a fost adăugată printr-o
înlocuire de text în `baseQuery()`. O modificare din Faza 8 mutase linia pe care se baza
patch-ul, deci înlocuirea n-a produs niciun efect — și n-a raportat nimic.

**Ce a prins-o.** `test_stories_stay_out_of_the_main_feed`. Fără el, story-urile ar fi
apărut în infinite scroll în producție, iar tava și feed-ul s-ar fi stricat amândouă.

**Concluzie păstrată aici pentru că se repetă:** testele de fază nu sunt formalitate —
sunt singurul lucru care prinde o editare care a raportat succes fără să facă nimic.

---

## D-056 — Colecțiile fără marketplace sunt editoriale, nu orfane

**Context.** `short_collections.marketplace_client_id` e nullable.

**Alegere.** `null` = colecție editorială, vizibilă pe **orice** marketplace; cu valoare =
vizibilă doar acolo. `scopeForClient()` face `whereNull OR = clientId`.

**Alternative.** Tratarea lui `null` ca „neatribuită, deci ascunsă" — atunci curatorierea
centrală („Weekend în București") n-ar avea unde să existe.

**Impact.** Curatorierea traversează tenanți prin definiție, deci resursa Filament stă
doar în core admin. Testat pe toate patru combinațiile.

---

## D-057 — Tava de stories vine grupată pe owner, cu numărul de segmente

**Context.** Tava randează un avatar per owner; tap-ul redă story-urile acelui owner în ordine.

**Alegere.** `GET tenant-client/stories` întoarce direct grupurile, fiecare cu `count`.

**Alternative.** Listă plată — clientul ar trebui să grupeze și să numere singur, adică să
reimplementeze regula pe fiecare platformă.

**Impact.** Contractul reflectă interfața reală.

---

## D-058 — O eroare de rețea NU înseamnă „video șters"

**Context.** `CheckShortHealthJob` arhivează embed-urile externe moarte.

**Alegere.** Doar un `404` explicit contează ca mort. Orice excepție (timeout, DNS,
conexiune refuzată) întoarce `false`.

**Alternative.** Tratarea oricărui eșec ca moarte — un blip de rețea ar arhiva în masă
short-uri perfect sănătoase, iar recuperarea ar fi manuală.

**Impact.** Testat cu o excepție de conexiune: short-ul rămâne publicat. Sonda folosită
e endpoint-ul de thumbnail YouTube — gratuit și fără autentificare. `TODO(owner)`: o sondă
echivalentă pentru IG/FB, când apare tokenul Meta.

---

## D-059 — Un short promovat se injectează pe slot fix și e mereu etichetat

**Context.** D3 cere shorts plătite în feed.

**Alegere.** Promovatul nu intră în scor — se injectează la un slot fix (unul la 5
organice) și poartă `promoted.label = "Sponsorizat"` în payload, necondiționat.

**Alternative.** Adăugarea unui termen „bid" în ranker — atunci banii devin tăcut
relevanță, iar spectatorul nu mai poate face diferența. Asta e exact ce nu vrei într-un
produs în care încrederea în feed e tot ce ai.

**Impact.** Testat că eticheta există. Licitația e simplă (cel mai mare bid câștigă) —
cu un singur slot și fără preț de rezervă, mecanica second-price ar adăuga aparat fără
să schimbe cine e servit.

---

## D-060 — Pacing: un flight înaintea curbei stă pe bară

**Context.** Un buget generos poate fi ars în prima oră a unui flight de o săptămână.

**Alegere.** `pacedBudgetCents()` calculează cât *ar trebui* să fi cheltuit până acum;
dacă a depășit, promoția nu e eligibilă la cererea curentă.

**De ce.** Pentru advertiser, o prezență mică și constantă valorează mai mult decât o
oră de dominație urmată de șase zile de absență.

**Impact.** Testat: un flight de 10 zile, o zi în, cu 90% cheltuit, nu mai e servit.

---

## D-061 — Dovada de facturare stă separat de telemetrie

**Context.** `short_events` e tăiat de retenție la 90 de zile (D6).

**Alegere.** `short_promotion_events` — tabel propriu, nepruned.

**Alternative.** Un tip de eveniment în `short_events` — dovada pe baza căreia s-a
facturat ar dispărea exact înaintea unei dispute.

**Impact.** Facturarea se poate reconstrui oricând.

---

## D-062 — Drepturile se aplică drept constrângeri, nu post-filtru

**Context.** D7 cere excluderea din feed pe licență, teritoriu și vârstă.

**Alegere.** `ShortRightsGuard::apply()` adaugă condiții la query.

**Alternative.** Filtrarea colecției după interogare — paginile ies mai scurte decât
`limit`, iar cursorul (care numără rânduri) începe să sară.

**Impact.** Paginarea rămâne corectă indiferent câte short-uri sunt excluse.

**Notă de portabilitate.** Verificarea de teritoriu e un `LIKE` pe JSON-ul serializat,
nu un operator JSON — containment-ul nu e portabil între sqlite/pgsql/mysql. Codurile
sunt de două litere și ghilimelate în JSON, deci `"RO"` nu se poate potrivi accidental
în alt cuvânt. `TODO(owner)`: pe Postgres, un index GIN + `@>` ar fi mai rapid dacă
numărul de short-uri cu restricții teritoriale crește.

---

## D-063 — Locație sau vârstă necunoscută = doar conținut nerestricționat

**Context.** Un spectator anonim n-are nici țară, nici dată de naștere.

**Alegere.** Fără țară → doar short-uri fără restricții teritoriale. Fără dată de naștere
verificată → doar `age_rating = 0`.

**Alternative.** Tratarea necunoscutului ca permisiv — exact modul în care un age gate
devine decorativ.

**Impact.** Testat pe toate trei stările (anonim, logat fără dată, adult).

---

## D-064 — Guardrail-ul de cost proiectează, nu raportează

**Context.** D8 cere alertare pe bandwidth.

**Alegere.** `projectedUsagePct()` extrapolează consumul de până acum la sfârșitul lunii.

**De ce.** „Suntem la 60% pe 5 ale lunii" e o problemă; „60% pe 28" nu e. Un prag pe
consumul curent le confundă.

**Impact.** Peste prag → data-saver global (max 480p, prefetch 0), plus un kill switch
manual care nu așteaptă următorul poll. Fără plafon configurat, guardrail-ul e inactiv —
nu presupunem limite pe care nimeni nu le-a cerut.

---

## D-065 — Eligibilitatea UGC e fail-closed

**Context.** B9 cere ca doar participanții cu bilet scanat să poată posta.

**Alegere.** `hasCheckedInTicket()` întoarce `false` când verificarea nu se poate face
(tabel absent, eroare de query).

**Alternative.** Fail-open — ar transforma funcționalitatea într-un endpoint deschis de
upload exact în momentul în care ceva nu merge.

**Impact.** Testat pe patru stări: fără bilet, bilet nescanat, bilet al altcuiva, bilet
scanat propriu.

---

## D-066 — Auto-ascundere la N rapoarte

**Context.** §14 cere o coadă de moderare pentru conținut raportat.

**Alegere.** La pragul din config (implicit 3), un short publicat trece automat în
`pending_review`.

**Compromisul, explicit.** A ascunde câteva ore ceva legitim costă mult mai puțin decât
a lăsa sus ceva dăunător într-un feed cu autoplay. Pragul e configurabil tocmai pentru
că raportul corect depinde de volum.

**Impact.** Raportarea rămâne deschisă și pentru guest: cine nu e logat vede același
feed, iar a cere cont ca să raportezi protejează doar conținutul.

---

## D-067 — Un câștigător de A/B se declară doar cu eșantion pe FIECARE variantă

**Context.** B10 cere alegerea automată a celui mai bun cover.

**Alegere.** `PickPosterWinnerJob` cere ca **minimul** de impresii pe variante să
depășească pragul, nu maximul.

**Alternative.** Decizia pe leader — o variantă cu 100 de impresii și 40% CTR arată
spectaculos și e, statistic, zgomot; promovarea ei aruncă definitiv varianta care poate
era mai bună.

**Impact.** Testat exact pe acest caz. O singură variantă nu e test și nu produce
câștigător.

---

# Addendum — audit pe PostgreSQL înainte de merge

Am pornit un PostgreSQL 16 local (era instalat în imagine, doar nu rula) și am rulat
migrațiile + toată suita pe motorul real de producție. **Cinci bug-uri care treceau pe
SQLite și ar fi picat pe Postgres.** Intrările de mai jos corectează decizii anterioare.

## D-068 — `owner()->first()` pe un short editorial: sintaxă invalidă în Postgres

**Ce era greșit.** `ShortPayload::owner()` cădea pe `$short->owner()->first()` când
relația nu era eager-loaded. Pe un short **editorial** (fără owner — caz suportat explicit
de design) `MorphTo` se construiește cu cheie străină goală și generează
`select * from shorts where "" is null`. Postgres: `zero-length delimited identifier`.
SQLite tolerează.

**Impact real.** Orice payload de feed care conținea un short editorial ar fi dat 500 —
adică feed-ul principal, pe cazul cel mai comun.

**Reparat.** Ieșire devreme când lipsește `owner_type`/`owner_id`, apoi acces prin atribut
(`$short->owner`), care rezolvă morph-ul în siguranță și refolosește relația încărcată.
5 teste au prins-o imediat ce suita a rulat pe Postgres.

## D-069 — `LIKE` pe o coloană `json` nu există în Postgres (corectează D-062)

**Ce era greșit.** Filtrul de teritoriu făcea `where('territories', 'like', '%"RO"%')`.
Postgres: `operator does not exist: json ~~ unknown`.

**Prima reparație, insuficientă.** Cast la `::text`. A funcționat, dar a rămas fragilă.

**Reparația finală.** Operatori JSON reali — `where('territories->mode', ...)` +
`whereJsonContains('territories->codes', $code)`. Laravel îi compilează per driver
(`@>` pe Postgres, `JSON_CONTAINS` pe MySQL, `json_each` pe SQLite). Exact și indexabil.

**Golul de test care a permis-o.** Aveam test pe `allows()` (verificarea PHP), dar
**niciunul** pe constrângerea de query. Adăugat.

## D-070 — `json` → `jsonb` pe coloanele Shorts (precedent deja în repo)

**Context.** Postgres nu poate face `SELECT DISTINCT` / `GROUP BY` peste `json`
(`could not identify an equality operator for type json`) — exact bug-ul pe care
`2026_07_25_170000_tenant_event_categories_json_to_jsonb` l-a reparat deja în acest repo,
pentru că spărgea un Select din Filament cu 500.

**Verificat pe motor:** `SELECT DISTINCT id, hashtags FROM shorts` → aceeași eroare.

**Alegere.** Migrație pgsql-only care convertește `shorts.hashtags/content_flags/territories`,
`short_events.meta`, `short_promotions.targeting` la `jsonb`. Sigură pe date live
(coloanele conțin JSON valid prin construcție; `USING col::jsonb` convertește pe loc).

**Efect secundar prins imediat.** `jsonb` **renormalizează** textul serializat
(`{"mode": "allow"}` cu spațiu), ceea ce a rupt potrivirea pe substring din D-069 — de aici
trecerea la operatori JSON. Un bug care a reparat un bug și a expus o fragilitate.

## D-071 — `tickets` nu are coloană `checked_in`

**Ce era greșit.** Eligibilitatea UGC interoga
`whereNotNull('checked_in_at')->orWhere('checked_in', true)`. Tabelul real are doar
`checked_in_at` / `checked_in_by` / `checked_in_via`.

**Impact real.** Pe producție interogarea ar fi aruncat, iar `catch`-ul fail-closed
(D-065) ar fi transformat asta în **„nimeni nu poate posta UGC, niciodată"** — funcționalitate
moartă, tăcut. Fail-closed a evitat o gaură de securitate, dar a ascuns defectul.

**Reparat.** Doar `checked_in_at`. Stub-ul de test oglindește acum schema reală (fără
boolean), altfel testul nu dovedea nimic.

## D-072 — Favoritele stau într-un singur tabel polimorf (corectează D-033)

**Ce era greșit.** `ShortAffinityProfile` interoga
`marketplace_customer_favorite_artists` / `_venues`. Nu există. Real:
`marketplace_customer_favorites`, cu `favoriteable_type` ('artist'|'venue'|'event') +
`favoriteable_id`.

**Impact real.** `catch` → favoritele nu contribuiau niciodată la ranker. Nu un crash,
dar un semnal de personalizare mort permanent.

**Reparat.** O interogare pe tabelul real, cu maparea tokenului la clasă.

## D-073 — Punctele au deja o cale marketplace-side (corectează D-021)

**Ce era greșit.** `forwardToLoyaltyLedger()` făcea un `INSERT` brut în
`points_transactions` cu coloanele `customer_id, points, type, source`. Tabelul real cere
`tenant_id` (NOT NULL, FK), `balance_after` (NOT NULL), `description` (json NOT NULL), și
**nu are** `source` (are `action_type`). Patru erori într-un singur insert.

**Ce am ratat inițial.** `ExperienceService::awardActionXpForMarketplace()` există deja și
ia direct un `marketplace_customer_id` — **nu are nevoie de puntea de identitate**.
Premisa din D-021 („punctele cer puntea") era doar parțial adevărată.

**Reparat.** Rutat prin serviciul existent, care are propriile rate limits și e no-op sigur
când nu există `ExperienceAction` configurat. Ledgerul `Customer`-side (`points_transactions`)
rămâne dependent de punte — acolo chiar e nevoie de `tenant_id` și de un sold curent.

## D-074 — Oglindirea în watchlist ștearsă, nu „try/catch"-uită

**Ce era greșit.** `addToWatchlist()` insera în `marketplace_customer_watchlist` fără
`marketplace_client_id` și `marketplace_event_id`, ambele NOT NULL. Migrația care a adăugat
`event_id` spune explicit că **nu a putut** relaxa FK-ul existent și că „we'll handle it in
code". Cu un short care poartă doar `event_id`, insertul nu poate reuși niciodată.

**Alegere.** Am șters codul și am lăsat un `TODO(owner)` care spune de ce. Cod care aruncă
mereu și e înghițit arată ca o funcționalitate care merge — e mai rău decât absența lui.

## Lecția, pentru următoarea funcționalitate

O suită verde pe SQLite **nu dovedește** compatibilitate cu Postgres. Diferă: operatorii
`json`, `DISTINCT`/`GROUP BY` pe json, `LIKE` pe json, identificatorii de lungime zero,
limita de 63 de caractere la numele de index, strictețea `GROUP BY`. Suita acceptă acum
`SHORTS_TEST_PGSQL=1` exact pentru asta — vezi `PROGRESS.md`.

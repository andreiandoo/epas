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

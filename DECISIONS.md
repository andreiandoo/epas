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

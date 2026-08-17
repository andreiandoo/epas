# Widget Tixello pentru Android

Aplicație mică, nativă, instalată de mână pe telefonul proprietarului. Pune pe
ecranul telefonului cifrele întregii platforme și sună/vibrează de fiecare dată
când Tixello încasează un comision.

**Fără Firebase.** Nu există push de la Google: telefonul întreabă serverul la
un interval pe care îl alegi tu (implicit 60 s), dintr-un serviciu de fundal.
Detaliile și motivele sunt mai jos, la [„Cum ajung alertele fără
Firebase"](#cum-ajung-alertele-fără-firebase).

---

## Ce afișează

**Widget 1 — cifre** (4×2 pe ecran)

| Coloană  | Cifra mare        | Sub ea         |
|----------|-------------------|----------------|
| Vânzări  | total, all time   | `+X` azi       |
| Bilete   | total, all time   | `+X` azi       |
| Clienți  | total, all time   | `+X` azi       |

Jos, pe toată lățimea: **Venituri Tixello** (comisionul încasat) — total all
time și cât s-a strâns azi.

**Widget 2 — comisioane** (4×3): ultimele 5 comisioane, fiecare cu evenimentul
din care vine, marketplace-ul/tenantul, ora și suma. Jos, totalul de azi.

Toate cifrele sunt **peste toți tenanții și toate marketplace-urile** — nu
există niciun filtru de tenant pe traseul ăsta.

### De unde vin cifrele

| Cifra    | Sursa                                                                   |
|----------|--------------------------------------------------------------------------|
| Vânzări  | valoarea comenzilor în care vânzarea a avut loc                          |
| Comenzi  | numărul acelorași comenzi                                                |
| Bilete   | `tickets` cu `status = valid` dintr-o astfel de comandă                  |
| Clienți  | `customers` + `marketplace_customers`                                    |

**„Venituri Tixello" înseamnă banii care ajung la platforma core** — nu comisionul
pe care un marketplace îl ia de la organizatorii lui. Formulele sunt luate din
locul care chiar facturează (`App\Filament\Pages\BillingOverview` și
`invoices:generate-tenant`):

| Sursă             | Cum se calculează                                                    |
|-------------------|----------------------------------------------------------------------|
| Comision tenant   | `tenants.commission_rate` % × valoarea comenzilor tenantului         |
| Comision marketplace | `marketplace_clients.commission_rate` % × valoarea comenzilor      |
| Servicii          | 50% din `service_orders` plătite (`ServiceOrder::TIXELLO_SHARE`)      |
| Taxe one-time     | `billing_amount` pe microserviciile cu `billing_cycle = one_time`    |
| Abonamente lunare | `billing_amount` lunar — **afișat separat**, nu adunat în total      |

Abonamentul lunar e o *rată* (bani pe lună), nu o sumă acumulată; adunat într-un
„all time" ar da o cifră fără sens. De aceea apare ca „X/lună", pe lângă total.

> **Ce NU e „venitul Tixello":** coloana `orders.commission_amount`. Aceea e
> comisionul marketplace-ului către organizatorii lui — bani care nu ajung
> niciodată la platforma core — și e oricum 0 pe vânzările POS/leisure.

**Retururile nu scad nimic.** Tixello încasează comision pe vânzare; o
restituire, integrală sau parțială, nu i-l ia înapoi. De aceea statusurile
`refunded` și `partially_refunded` intră la fel ca `paid` — la fel face și
`invoices:generate-tenant`, care exclude doar `cancelled`. (Înainte, o comandă de
500 € cu o restituire de 5 € ieșea cu totul din cifre: dispăreau și vânzarea, și
comisionul, și biletele rămase valide.)

Comenzile de tenant țin banii în `total_cents`, cele de marketplace în `total`;
se citesc amândouă, **pe fiecare rând** — nu „dacă suma grupului e zero", care
pierde tăcut comenzile vechi amestecate cu unele noi.

Sumele în alte monede se convertesc prin `exchange_rates`; fără curs, suma
rămâne în afara totalului (nu inventăm un curs).

**Două diferențe față de panoul de admin**, ambele intenționate:

1. „Azi" se taie la miezul nopții în `Europe/Bucharest`, nu în UTC. Pe un telefon
   ținut în România, un „azi" care începe la 03:00 ar fi greșit.
2. Vânzările includ comenzile cu retur (vezi mai sus). Panoul le exclude, deci
   widget-ul poate arăta cifre mai mari — diferența e reală, nu o eroare.

Mai există o inconsistență, în aplicație, pe care widget-ul **nu** o rezolvă:
pagina de facturare a marketplace-ului (`BillingBreakdown`) calculează comisionul
Tixello din *prețul biletelor*, în timp ce panoul core îl calculează din *valoarea
comenzilor*. Widget-ul urmează panoul core, fiindcă acolo se emit facturile. Pe
vânzările POS/leisure cele două pot să nu coincidă.

---

## Instalare — partea de server

### 1. Migrația

```bash
php artisan migrate
```

Adaugă tabela `tixello_widget_tokens`. Nu atinge nimic existent.

### 2. Generează un token pentru telefon

```bash
php artisan tixello:widget-token "Telefonul lui Andrei"
```

Afișează **o singură dată** un token de forma `twg_...`. În baza de date rămâne
doar `sha256` din el.

Alte comenzi:

```bash
php artisan tixello:widget-token --list          # ce token-uri există, când au fost folosite
php artisan tixello:widget-token --revoke=3      # revocă token-ul cu ID 3
php artisan tixello:widget-token "Telefon vechi" --days=90   # expiră singur
```

Revocarea prinde efect în cel mult 5 minute (cât ține cache-ul de
autentificare).

### 3. Opțional — reglaje în `.env`

```env
TIXELLO_WIDGET_TIMEZONE=Europe/Bucharest
TIXELLO_WIDGET_CURRENCY=EUR
TIXELLO_WIDGET_SECONDARY_CURRENCY=RON
TIXELLO_WIDGET_CACHE_TTL=20            # cache pentru cifrele de azi (ieftine)
TIXELLO_WIDGET_CACHE_TTL_ALL_TIME=120  # cache pentru cifrele all time (scumpe)
TIXELLO_WIDGET_POLL_INTERVAL=60        # intervalul recomandat telefoanelor
TIXELLO_WIDGET_TODAY_BASIS=created_at  # sau paid_at, dacă „azi" = când a intrat banul
TIXELLO_WIDGET_NEW_COMMISSIONS_CAP=20  # câte comisioane pot suna într-o rundă
```

Cele două TTL-uri sunt separate intenționat: cifrele „all time" cer scanări
mari (pe `tickets` nu există index pe `status`), pe când cele de azi sunt
mărginite de un interval de dată. Un TTL scurt pe amândouă ar ține scanarea
completă în buclă cât timp există un telefon care întreabă.

`TIXELLO_WIDGET_POLL_INTERVAL` e trimis în fiecare răspuns, deci poți încetini
toate telefoanele dintr-un singur loc dacă serverul are de suferit.

### 4. Verifică pe server, înainte de telefon

```bash
php artisan tixello:widget-preview            # cifrele, formatate în terminal
php artisan tixello:widget-preview --fresh    # fără cache, ca să vezi timpii reali
php artisan tixello:widget-preview --json     # payload-ul brut, exact ca la telefon
php artisan tixello:widget-preview --since=12345   # ce ar declanșa alertă
```

E cel mai rapid mod de a confirma, imediat după deploy, că numerele sunt cele
așteptate. Dacă `--fresh` trece de ~2 secunde, comanda îți spune singură ce să
faci.

### 5. Opțional — indexuri, dacă interogările sunt lente

Migrația de indexuri **nu rulează** la un `php artisan migrate` obișnuit: e
protejată de un flag, tocmai ca să nu prindă un deploy pe nepregătite. O pornești
explicit, când vrei tu:

```bash
TIXELLO_WIDGET_CREATE_INDEXES=true php artisan migrate
```

Adaugă trei indexuri pe care le folosește și dashboard-ul de admin:
`tickets(status, created_at)`, `orders(status, created_at)` și un index parțial
pentru lista de comisioane. Pe PostgreSQL sunt create `CONCURRENTLY`, deci **nu
blochează vânzările** — poate rula în timpul zilei. Pe o tabelă mare durează
minute; e normal. Dacă un `CREATE INDEX CONCURRENTLY` eșuează (conflict de lock),
PostgreSQL lasă în urmă un index marcat INVALID, pe care îl ștergi cu
`DROP INDEX <nume>` și reiei — motivul pentru care pasul ăsta e separat de
deploy.

### API

Două endpoint-uri, ambele cu `Authorization: Bearer twg_...`:

| Metodă | Rută                                                       | Ce face                          |
|--------|------------------------------------------------------------|----------------------------------|
| GET    | `/api/tixello-widget/ping`                                 | verifică token-ul                |
| GET    | `/api/tixello-widget/summary?since_commission_id=N&limit=5`| cifrele + ultimele comisioane    |

`since_commission_id` e cursorul telefonului: comisioanele mai noi decât el ies
separat, în `new_commissions`, și doar ele declanșează alerta. Limita e 200 de
cereri/minut per IP.

Verificare rapidă:

```bash
curl -H "Authorization: Bearer twg_..." https://core.tixello.com/api/tixello-widget/summary
```

---

## Instalare — partea de telefon

### 1. Ia APK-ul

Codul stă în `tixello-widget-android/`. Containerul de dezvoltare nu are Android
SDK, deci APK-ul se construiește în GitHub Actions:

1. Actions → **Tixello Widget (Android APK)** → rularea de pe branch-ul tău
   (sau *Run workflow*, dacă vrei una manuală).
2. Artifacts → `tixello-widget-apk` → descarcă zip-ul.
3. Copiază `app-debug.apk` pe telefon și deschide-l.

Local, cu Android SDK instalat:

```bash
cd tixello-widget-android
./gradlew assembleDebug
# app/build/outputs/apk/debug/app-debug.apk
```

Android va cere permisiunea de a instala din surse necunoscute — e normal
pentru un APK care nu vine din Play Store.

Versiunile următoare se instalează **peste** cea existentă, păstrând token-ul și
setările: APK-ul e semnat cu o cheie de debug fixă, ținută în repo, iar
`versionCode` crește cu fiecare rulare de CI. (Fără cheia fixă, fiecare build ar
fi semnat diferit și Android ar cere dezinstalarea.)

### 2. Configurează

Deschide aplicația **Tixello Widget** și completează:

- **Adresa serverului** — `https://core.tixello.com` (cu `https://`; aplicația
  refuză traficul necriptat)
- **Token** — cel generat la pasul 2 de mai sus
- **Verifică la fiecare** — 15 s … 15 min (implicit 60 s)
- **Alertă la comision nou**, **Sunet**, **Vibrație**

Apasă **Verifică și salvează** (îți spune imediat dacă token-ul e bun), apoi
**Pornește urmărirea**.

Acceptă permisiunea de notificări când o cere — fără ea nu există alerte.

Apasă **Testează alerta**: primești imediat o notificare de probă, pe același
canal, cu același sunet și aceeași vibrație ca la un comision real. Așa afli
acum, nu la prima vânzare, dacă telefonul e pe silențios sau dacă lipsește
permisiunea.

### 3. Pune widget-urile pe ecran

Ține apăsat pe ecran → *Widgets* → **Tixello Widget** → două intrări:

- *Tixello — cifre*
- *Tixello — comisioane*

Trage-le pe ecran. Se pot redimensiona. Iconița de refresh din colț forțează o
sincronizare; apăsarea pe titlu deschide aplicația.

### 4. Scoate aplicația din optimizarea bateriei

Pe telefoanele Xiaomi, Samsung, Huawei și Oppo, sistemul oprește agresiv
serviciile de fundal. Butonul **Setări baterie** din aplicație te duce direct în
ecranul potrivit; alege *Nerestricționat* / *Fără optimizare* pentru Tixello
Widget. Fără asta, alertele pot întârzia minute bune.

---

## Cum ajung alertele fără Firebase

```
  [telefon]                                  [server]
  PollService (foreground)  ──GET summary──►  TixelloWidgetController
       la fiecare N secunde                        │
       │                                    cifre + comisioane
       │◄───────────────────────────────────────────┘
       ├── comisioane mai noi decât cursorul? → notificare cu sunet/vibrație
       └── desenează din nou ambele widget-uri

  WorkManager (la 15 min) ── aceeași sincronizare, ca plasă de siguranță
                             + repornește serviciul dacă a fost oprit
```

Alertarea se bazează pe un cursor (`last_commission_id`), nu pe ceasul
telefonului: serverul primește ce știe telefonul și întoarce el diferența. De
aceea nu poți primi de două ori aceeași alertă și nici nu pierzi una dacă
telefonul a fost offline o oră.

La prima sincronizare după instalare, telefonul doar învață unde s-a ajuns —
altfel ai primi 5 alerte deodată pentru istoric.

**Modul telefonului e respectat automat:** pe „doar vibrații" notificarea
vibrează în loc să sune, iar pe silențios tace. Comutatoarele din aplicație
aleg între patru canale de notificare (sunet+vibrație / doar sunet / doar
vibrație / silențios), pentru că Android nu lasă o aplicație să schimbe
sunetul unui canal deja creat.

**Costul:** o notificare permanentă, cu prioritate minimă („Tixello — urmăresc
vânzările"), pe care Android o cere obligatoriu pentru orice serviciu de
fundal. Din ea vezi cât s-a încasat azi și când a fost ultima verificare, și tot
de acolo poți opri urmărirea.

**Trafic:** un răspuns are câțiva kilobytes. La 60 s înseamnă ~1 440 de cereri
și sub 10 MB pe zi.

---

## Dacă nu merge

| Simptom                                   | Ce verifici                                                                 |
|-------------------------------------------|------------------------------------------------------------------------------|
| „Token respins de server"                 | token-ul e revocat sau expirat — `php artisan tixello:widget-token --list`   |
| „Prea multe cereri"                       | mai multe telefoane pe același IP; mărește intervalul                        |
| Widget-ul arată „configurează"            | aplicația n-a fost configurată sau token-ul a fost șters                     |
| Cifrele stau pe loc                       | serviciul a fost oprit de sistem → *Setări baterie*, apoi *Pornește urmărirea* |
| Nu primești sunet                         | apasă *Testează alerta*; apoi permisiunea de notificări, modul telefonului, comutatorul *Alertă la comision nou* |
| Cifrele se încarcă greu / serverul geme   | `php artisan tixello:widget-preview --fresh` arată timpii; vezi migrația de indexuri |
| Cifrele diferă de panoul de admin         | „azi" e pe ora României aici, pe UTC în panou; restul definițiilor sunt identice |

Log-urile telefonului: `adb logcat -s TixelloSync TixelloNotifier`.

---

## Teste

Partea de server:

```bash
vendor/bin/phpunit --filter TixelloWidgetApiTest
```

Partea de Android (rulează și în CI, la fiecare push pe
`tixello-widget-android/`):

```bash
cd tixello-widget-android && ./gradlew testDebugUnitTest
```

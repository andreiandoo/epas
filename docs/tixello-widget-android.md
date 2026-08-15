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

Definițiile sunt aceleași cu widget-urile din panoul de admin
(`App\Filament\Widgets\StatsOverview`), ca să nu ai două adevăruri:

| Cifra            | Sursa                                                                        |
|------------------|------------------------------------------------------------------------------|
| Vânzări          | `SUM(orders.total)` pentru comenzile `paid` / `confirmed` / `completed`      |
| Comenzi          | numărul acelorași comenzi                                                    |
| Bilete           | `tickets` cu `status = valid` care atârnă de o comandă plătită               |
| Clienți          | `customers` + `marketplace_customers`                                        |
| Venituri Tixello | `SUM(orders.commission_amount)` pe aceleași comenzi                          |
| Comisioane (listă) | ultimele comenzi plătite cu `commission_amount > 0`, cele mai noi primele  |

Comenzile vechi, care țin suma doar în `total_cents`, sunt luate în calcul prin
aceeași cădere ca în panoul de admin. Sumele în alte monede se convertesc în
EUR prin tabela `exchange_rates`; dacă lipsește cursul, suma respectivă rămâne
în afara totalului (nu inventăm un curs), iar comisionul se afișează în moneda
lui originală.

**Diferența față de panoul de admin:** „azi" se taie la miezul nopții în
`Europe/Bucharest`, nu în UTC. Pe un telefon ținut în România, un „azi" care
începe la 03:00 ar fi greșit.

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
TIXELLO_WIDGET_CACHE_TTL=20        # secunde de cache pentru agregate
TIXELLO_WIDGET_POLL_INTERVAL=60    # intervalul recomandat telefoanelor
```

`TIXELLO_WIDGET_POLL_INTERVAL` e trimis în fiecare răspuns, deci poți încetini
toate telefoanele dintr-un singur loc dacă serverul are de suferit.

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
| Nu primești sunet                         | permisiunea de notificări; telefonul pe silențios; comutatorul *Alertă la comision nou* |
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

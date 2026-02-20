# Plan: Database Replication, High Availability & Code Audit

## 1. Situatia Curenta

### Ce baza de date folosim?
- **PostgreSQL 16** — proiectul ruleaza deja pe Postgres.
- `docker-compose.yml` defineste serviciul `pgsql` cu imaginea `postgres:16`
- `.env.example` are `DB_CONNECTION=sqlite` ca default, dar portul comentat e `3306` (ramas de la template-ul Laravel). In realitate, Docker-ul configureaza Postgres pe portul `5432`.
- **Nu folosim MySQL.** Codul are deja migrari PostgreSQL-specifice:
  - `jsonb` nativ in ~10+ migrari (`$table->jsonb(...)`)
  - `jsonb_build_object()` in migrari translatabile
  - `ALTER COLUMN ... TYPE jsonb USING ...::jsonb` (cast-uri PostgreSQL)
  - `information_schema` queries pentru `pg_catalog`
  - Migrarea `convert_json_to_jsonb.php` verifica explicit `DB::getDriverName() === 'pgsql'`

### Stack-ul complet:
- **Laravel 12** + **Filament 4** (admin panel)
- **PHP 8.2+**, **Livewire 3**, **Sanctum** (auth)
- **Redis 7** (cache, sessions, queue, analytics via Upstash)
- **PostgreSQL 16** (baza de date principala)
- **415 migrari** — proiect matur, multi-tenant

---

## 2. Migrare MySQL → PostgreSQL: NU E NECESARA

**Lucram deja cu PostgreSQL.** Nu exista nicio nevoie de migrare.

Portul `3306` din `.env.example` e ramas din template-ul default Laravel. Trebuie curatat.

---

## 3. AUDIT COD: Probleme Gasite la Scrierea in DB

### 3.1 REDUNDANT WRITES — Probleme Identificate

#### A) `OrderObserver` — Scrieri sincrone in cascada la fiecare comanda

**Fisier:** `app/Observers/OrderObserver.php`

**Problema:** Cand o comanda devine `paid/confirmed/completed`, observer-ul face SINCRON:
1. `findOrCreateCoreCustomer()` — face SELECT + potential INSERT in `core_customers` (linia 142-175)
2. `trackPurchaseConversion()` — scrie tracking data sincron (linia 62-102)
3. `trackOrganizerAnalytics()` — milestone attribution + real-time analytics sincron (linia 107-137)
4. `OrganizerNotificationService::notifySale()` — notificare sincron (linia 86-93)
5. `$order->tickets()->count()` — query N+1 in `buildTrackingData()` (linia 188)

**Impact:** La un flux de checkout cu 100 de comenzi simultane, PRIMARY-ul primeste ~500 de write-uri suplimentare sincrone. Userul asteapta pana se termina totul.

**Fix recomandat:** Mutam tot ce nu e critic intr-un Job:
```php
// In OrderObserver::trackPurchaseConversion():
// INAINTE (sincron):
$this->trackingService->trackPurchase($trackingData, $order);
$this->trackOrganizerAnalytics($order, $coreCustomer);
OrganizerNotificationService::notifySale($order);

// DUPA (async):
TrackOrderConversionJob::dispatch($order->id)->onQueue('analytics');
```

#### B) `PromoCodeUsageAnalyzer` — MySQL syntax pe PostgreSQL

**Fisier:** `app/Services/PromoCodes/PromoCodeUsageAnalyzer.php`

**Problema CRITICA:** Foloseste `DATE_FORMAT()` (MySQL-only) in loc de `TO_CHAR()` (PostgreSQL):
- Linia 90: `DATE_FORMAT(used_at, '%Y-%m-%d %H:%i')` — va crapa pe PostgreSQL
- Linia 166-168: `DATE_FORMAT(used_at, ?)` — la fel

**Fix:**
```php
// INAINTE (MySQL):
DATE_FORMAT(used_at, '%Y-%m-%d %H:%i')

// DUPA (PostgreSQL):
TO_CHAR(used_at, 'YYYY-MM-DD HH24:MI')
```

#### C) `EventAnalyticsService` — Query-uri grele repetate

**Fisier:** `app/Services/Analytics/EventAnalyticsService.php`

**Problema:** `getDashboardData()` (linia 70-92) face 7 sub-query-uri mari, fiecare cu multiple COUNT/SUM pe tabele mari:
- `getOverviewStats()` — 6+ query-uri separate
- `getChartData()` — itereaza zi cu zi si face query pe fiecare
- `getTicketPerformance()` — loop pe fiecare ticket type cu 4 query-uri per tip
- `getTrafficSources()` — raw SQL cu CASE WHEN complex + groupBy
- `getFunnelMetrics()` — 5 COUNT DISTINCT query-uri separate

Cache-ul e doar 5 minute (`$cacheTtl = 300`). In perioadele de vanzari intense, aceleasi query-uri grele se executa repetat.

**Fix recomandat:**
- Creste cache TTL la 15-30 min pentru date non-realtime
- Muta aggregarea in job-uri periodice (deja exista `AggregateAnalyticsJob` — foloseste-l mai agresiv)
- `getTicketPerformance()`: inlocuieste loop-ul cu un singur query JOIN

#### D) `computeHourlyChartFromRaw()` — N+1 pe ore

**Fisier:** `app/Services/Analytics/EventAnalyticsService.php:1067-1123`

**Problema:** Face un loop de la ora 0 pana la ora curenta si executa **5 query-uri per ora** (page_views, unique_visitors, purchases, revenue, tickets). La ora 18:00, inseamna **90 de query-uri** pentru un singur request.

**Fix:** Un singur query cu `GROUP BY EXTRACT(HOUR FROM created_at)`.

#### E) `getRecentSales()` — N+1 pe "returning customer"

**Fisier:** `app/Services/Analytics/EventAnalyticsService.php:721-724`

**Problema:** In loop pe fiecare din cele 20 de comenzi, face un `Order::where(...)->exists()` — 20 query-uri extra.

**Fix:** Pre-fetch cu un singur query:
```php
$customerIds = $orders->pluck('marketplace_customer_id')->filter();
$returningIds = Order::whereIn('marketplace_customer_id', $customerIds)
    ->whereIn('status', ['paid', 'confirmed', 'completed'])
    ->groupBy('marketplace_customer_id')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('marketplace_customer_id');
```

---

### 3.2 LAZY WRITES (ASYNC) — Ce Putem Muta pe Queue

| Ce se scrie acum SINCRON | Unde | Propunere | Prioritate |
|--------------------------|------|-----------|------------|
| Tracking conversii (OrderObserver) | `OrderObserver:62-102` | `TrackOrderConversionJob` pe queue `analytics` | **CRITICA** |
| Milestone attribution | `OrderObserver:116` | Include in acelasi job | **CRITICA** |
| Notificare organizator (sale) | `OrderObserver:86-93` | `NotifyOrganizerSaleJob` pe queue `notifications` | **HIGH** |
| CoreCustomer create/update | `OrderObserver:142-175` | Poate ramane sincron (e necesar pt tracking) | LOW |
| Analytics aggregation daily | `EventAnalyticsService:884-994` | Deja async via `AggregateAnalyticsJob` — OK | OK |
| Activity logging (Spatie) | Toate modelele cu `LogsActivity` | Configureaza `->useLogBatch()` sau queue driver | **MEDIUM** |
| Email sending | Diverse controllere | Deja pe queue (`SendCampaignEmailJob`, etc.) — OK | OK |
| Webhook delivery | `DeliverWebhookJob` | Deja pe queue — OK | OK |

**Job-uri noi de creat:**

```php
// app/Jobs/TrackOrderConversionJob.php
class TrackOrderConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public $queue = 'analytics';
    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function handle(
        PlatformTrackingService $tracking,
        MilestoneAttributionService $attribution,
        RealTimeAnalyticsService $realTime
    ): void {
        $order = Order::find($this->orderId);
        if (!$order) return;

        // 1. Track conversion
        $coreCustomer = CoreCustomer::findByEmail($order->customer_email);
        $trackingData = $this->buildTrackingData($order, $coreCustomer);
        $tracking->trackPurchase($trackingData, $order);

        // 2. Milestone attribution
        if ($order->marketplace_event_id) {
            $milestone = $attribution->attributePurchase($order);
            $realTime->trackPurchaseCompleted($order, $milestone);
        }
    }
}
```

---

### 3.3 COMPLEX MULTI-TABLE JOINS — Probleme Gasite

#### A) `getTrafficSources()` — Raw SQL cu CASE WHEN pe 7 conditii

**Fisier:** `EventAnalyticsService.php:431-488`

**Problema:** Query complex cu CASE WHEN pe 7 surse, JOIN implicit prin `clone`, `groupByRaw`, `selectRaw` cu subquery-uri. Pe tabele cu milioane de randuri de tracking, e lent.

**Fix:** Pre-calculeaza sursa la INSERT time (adauga coloana `traffic_source` pe `core_customer_events`), apoi doar `GROUP BY traffic_source`.

#### B) `detectFraud()` — Raw SQL cu JOIN + GROUP BY + HAVING

**Fisier:** `PromoCodeUsageAnalyzer.php:60-149`

**Problema:** 4 query-uri separate, unul cu JOIN explicit (`promo_code_usage JOIN promo_codes`), `havingRaw`. Plus MySQL syntax (`DATE_FORMAT`) care crapa pe Postgres.

**Fix:** Muta fraud detection intr-un job periodic, nu on-demand. Stocheaza rezultatele in cache.

#### C) Analytics queries cu `orWhere` pe 2 coloane

**Fisier:** `EventAnalyticsService.php:61-64`

```php
$q->where('event_id', $event->id)
  ->orWhere('marketplace_event_id', $event->id);
```

**Problema:** `orWhere` pe doua coloane diferite previne folosirea eficienta a index-urilor. Postgres face sequential scan.

**Fix:** Adauga un index compus sau normalizeaza sa foloseasca o singura coloana.

---

### 3.4 RATE LIMITING — Audit Complet

#### Starea curenta:

| Grup de rute | Rate Limit | Rute | Evaluare |
|---|---|---|---|
| `tenant-client/*` | `120/min` | ~200+ rute (bulk) | **PREA PERMISIV** — include checkout, admin, profile |
| `marketplace-client/*` | `120/min` | ~150+ rute (bulk) | **PREA PERMISIV** — acelasi limit pt read si write |
| `tracking/events` | `300/min` | Tracking pixels | OK (e read-heavy) |
| `seating/*` | Custom (`seating_hold`, `seating_query`) | 6 rute | **BINE** — granular |
| `admin/*` | `throttle:api` (default 60/min) | Admin panel | OK |
| `promo-codes/validate` | `60/min` | Validare coduri | **PREA PERMISIV** — brute-force risk |
| `public/ticket/{code}` | `60/min` | Status bilet | OK |
| `organizer/*` | `120/min` | Analytics + management | OK |
| `v1/analytics/*` | `throttle:api` + `api.key` | Platform analytics | OK |
| `health`, `ping` | Fara throttle | Healthcheck | OK |

#### Probleme identificate:

**1. Checkout fara rate limit dedicat**
Ruta `tenant-client/checkout` si `marketplace-client/checkout` mostenesc `120/min` de la grup. Un bot poate incerca 120 de checkouturi pe minut.

**Fix:** Rate limit dedicat `10/min` pe checkout:
```php
Route::post('/checkout', [CheckoutController::class, 'process'])
    ->middleware('throttle:10,1');
```

**2. `promo-codes/validate` — brute force risk**
La `60/min`, un atacator poate incerca 60 de coduri pe minut (86.400/zi).

**Fix:** Scade la `10/min` + adauga delay progresiv dupa 5 incercari esuate.

**3. Write endpoints sub acelasi limit cu read endpoints**
`tenant-client/*` are `120/min` atat pentru `GET /events` (read) cat si `POST /orders` (write). Write-urile ar trebui sa fie mult mai restrictive.

**Fix:** Separa rate limits:
```php
// Read endpoints: 120/min (OK)
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/events', ...);
    Route::get('/products', ...);
});

// Write endpoints: 30/min
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/orders', ...);
    Route::post('/checkout', ...);
    Route::put('/cart', ...);
});
```

---

## 4. Primary Write / Replica Read — De Ce E Bine Si Ce Castigi

### Raspuns la intrebare: DA, exact asa functioneaza, si e foarte bine.

**Cum functioneaza:**
- **PRIMARY** (1 instanta) — primeste TOATE write-urile (INSERT, UPDATE, DELETE)
- **REPLICI** (2+ instante) — servesc TOATE read-urile (SELECT)
- Replicarea se face automat prin PostgreSQL Streaming Replication (WAL logs)

### Ce castigi concret:

#### 1. PERFORMANTA — Load distribution

```
INAINTE (1 server):
  100% reads + 100% writes = 1 server suprasolicitat

DUPA (1 primary + 2 replici):
  Primary:  ~20% load (doar writes)
  Replica 1: ~40% load (reads)
  Replica 2: ~40% load (reads)
```

**In cazul vostru concret:**
- `EventAnalyticsService` face ~50+ SELECT-uri grele per request — toate merg pe replici
- `getDashboardData()`, `getChartData()`, `getFunnelMetrics()` — replici
- Primary-ul ramane liber pentru checkout, creare comenzi, update-uri

**Speedup estimat pe read-heavy pages:** 2-3x (primary-ul nu mai e blocat de SELECT-uri grele)

#### 2. SIGURANTA — Datele exista in 3 copii simultane

```
Primary pica?
  → Replica 1 devine primary in 30 secunde
  → Replica 2 continua sa serveasca reads
  → ZERO data loss (cu synchronous_commit=on)

Disk failure pe primary?
  → Datele sunt intacte pe ambele replici
  → Point-in-time recovery din WAL logs
```

**Fara replici:** Daca primary-ul pica, totul e down + risc de pierdere date.

#### 3. IZOLARE — Write-urile nu blocheaza read-urile

**Problema actuala:** Cand `OrderObserver` face 5 write-uri sincrone pe un checkout, query-urile de analytics ale altor useri asteapta (lock contention pe PostgreSQL).

**Cu replici:** Write-urile pe primary si read-urile pe replici nu se interfereaza NICIODATA. Un checkout greu nu incetineste dashboard-ul altui organizator.

#### 4. MAINTENANCE fara downtime

```
Trebuie sa faci VACUUM FULL, REINDEX, sau ALTER TABLE pe o tabela mare?

Cu replici:
  1. Scoti replica 2 din pool
  2. Faci maintenance pe replica 2
  3. O pui inapoi
  4. Repeti pe replica 1
  5. Repeti pe primary (failover temporar pe replica 1)
  → ZERO downtime pentru useri
```

#### 5. `sticky: true` — Consistenta garantata

Laravel suporta nativ `sticky` connections:
```php
'sticky' => true,
```

**Ce face:** Dupa ce un request face un WRITE, toate READ-urile din ACELASI request merg pe PRIMARY (nu pe replica). Asta previne situatia in care:
1. User creeaza o comanda (write pe primary)
2. Redirect la pagina de confirmare (read de pe replica)
3. Replica nu are inca comanda (replication lag de 50ms)
4. User vede "comanda nu exista" → panica

Cu `sticky: true`, pasul 2 merge tot pe primary → comanda e acolo garantat.

### Trade-offs oneste:

| Avantaj | Cost |
|---------|------|
| Reads 2-3x mai rapide | Infrastructura mai complexa (3 servere in loc de 1) |
| Failover automat | Replication lag (50-200ms) pe reads non-sticky |
| Zero data loss | `synchronous_commit=on` adauga ~5ms la fiecare write |
| Maintenance fara downtime | Configurare initiala mai complexa |

**Verdictul:** Pentru un proiect de ticketing cu trafic de vanzari in spike-uri (lansare bilete = mii de requests simultane), read/write splitting e **esential**, nu optional.

---

## 5. Replicare: 2 Replici Read-Only (Streaming Replication)

### Arhitectura propusa

```
                    ┌─────────────────┐
                    │   App (Laravel)  │
                    │                  │
                    │  Writes → Primary│
                    │  Reads  → Pool   │
                    └────────┬─────────┘
                             │
                ┌────────────┼────────────┐
                │            │            │
         ┌──────▼──┐  ┌──────▼──┐  ┌──────▼──┐
         │ PRIMARY  │  │REPLICA 1│  │REPLICA 2│
         │ (R/W)    │──│ (R/O)   │──│ (R/O)   │
         │ pg:5432  │  │ pg:5433 │  │ pg:5434 │
         └──────────┘  └─────────┘  └─────────┘
              │              ▲            ▲
              │   Streaming  │  Streaming │
              └──────────────┴────────────┘
```

### Pas 5.1: Docker Compose — Adaugam Replici

```yaml
services:
  pgsql:
    image: postgres:16
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: sail
      POSTGRES_USER: sail
      POSTGRES_PASSWORD: password
    volumes:
      - sail-pgsql:/var/lib/postgresql/data
      - ./docker/postgres/primary/postgresql.conf:/etc/postgresql/postgresql.conf
      - ./docker/postgres/primary/pg_hba.conf:/etc/postgresql/pg_hba.conf
      - ./docker/postgres/primary/init-replication.sql:/docker-entrypoint-initdb.d/init-replication.sql
    command: postgres -c config_file=/etc/postgresql/postgresql.conf -c hba_file=/etc/postgresql/pg_hba.conf
    networks:
      - sail
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U sail"]
      interval: 10s
      timeout: 5s
      retries: 5

  pgsql-replica-1:
    image: postgres:16
    ports:
      - "5433:5432"
    environment:
      PGUSER: replicator
      PGPASSWORD: replicator_password
    volumes:
      - sail-pgsql-replica-1:/var/lib/postgresql/data
      - ./docker/postgres/replica/setup-replica.sh:/docker-entrypoint-initdb.d/setup-replica.sh
    depends_on:
      pgsql:
        condition: service_healthy
    networks:
      - sail

  pgsql-replica-2:
    image: postgres:16
    ports:
      - "5434:5432"
    environment:
      PGUSER: replicator
      PGPASSWORD: replicator_password
    volumes:
      - sail-pgsql-replica-2:/var/lib/postgresql/data
      - ./docker/postgres/replica/setup-replica.sh:/docker-entrypoint-initdb.d/setup-replica.sh
    depends_on:
      pgsql:
        condition: service_healthy
    networks:
      - sail
```

### Pas 5.2: Config Primary — WAL & Replication Slots

Fisier: `docker/postgres/primary/postgresql.conf`

```conf
# Replication Settings
wal_level = replica
max_wal_senders = 5
max_replication_slots = 5
wal_keep_size = '1GB'
hot_standby = on
synchronous_commit = on

# Performance
shared_buffers = '256MB'
effective_cache_size = '768MB'
work_mem = '4MB'
maintenance_work_mem = '64MB'

# Logging
log_replication_commands = on
```

Fisier: `docker/postgres/primary/pg_hba.conf`

```conf
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             0.0.0.0/0               md5
host    replication     replicator      0.0.0.0/0               md5
```

Fisier: `docker/postgres/primary/init-replication.sql`

```sql
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'replicator') THEN
        CREATE ROLE replicator WITH REPLICATION LOGIN PASSWORD 'replicator_password';
    END IF;
END
$$;
```

### Pas 5.3: Script Setup Replica

Fisier: `docker/postgres/replica/setup-replica.sh`

```bash
#!/bin/bash
set -e

if [ ! -f "$PGDATA/PG_VERSION" ]; then
    echo "Initializing replica from primary..."
    rm -rf "$PGDATA"/*
    PGPASSWORD=replicator_password pg_basebackup \
        -h pgsql -U replicator -D "$PGDATA" -Fp -Xs -P -R
    echo "Replica initialized successfully."
fi
```

### Pas 5.4: Laravel — Read/Write Splitting

In `config/database.php`:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),
    'write' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
    ],
    'read' => [
        ['host' => env('DB_READ_HOST_1', env('DB_HOST', '127.0.0.1')),
         'port' => env('DB_READ_PORT_1', '5433')],
        ['host' => env('DB_READ_HOST_2', env('DB_HOST', '127.0.0.1')),
         'port' => env('DB_READ_PORT_2', '5434')],
    ],
    'sticky' => true,
    'database' => env('DB_DATABASE', 'sail'),
    'username' => env('DB_USERNAME', 'sail'),
    'password' => env('DB_PASSWORD', 'password'),
    'charset' => env('DB_CHARSET', 'utf8'),
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
],
```

---

## 6. High Availability — "Mereu o versiune stabila si actuala"

### 6.1 Health Check Command

Fisier: `app/Console/Commands/CheckReplicationHealth.php`

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckReplicationHealth extends Command
{
    protected $signature = 'db:replication-health';
    protected $description = 'Check PostgreSQL replication status';

    public function handle(): int
    {
        $replicas = DB::select("
            SELECT client_addr, state, sent_lsn, write_lsn, flush_lsn, replay_lsn,
                   (extract(epoch from now()) - extract(epoch from reply_time))::int AS lag_seconds
            FROM pg_stat_replication
        ");

        if (empty($replicas)) {
            $this->error('No replicas connected!');
            return self::FAILURE;
        }

        $this->info('Connected replicas: ' . count($replicas));
        foreach ($replicas as $r) {
            $lagStatus = $r->lag_seconds > 30 ? 'HIGH LAG' : 'OK';
            $this->line(sprintf('  %s | State: %s | Lag: %ds %s',
                $r->client_addr, $r->state, $r->lag_seconds, $lagStatus));
        }
        return self::SUCCESS;
    }
}
```

### 6.2 Scheduled Monitoring

```php
// routes/console.php
Schedule::command('db:replication-health')->everyFiveMinutes()->runInBackground();
```

### 6.3 Failover Script

Fisier: `docker/postgres/scripts/promote-replica.sh`

```bash
#!/bin/bash
REPLICA_CONTAINER=${1:-pgsql-replica-1}
echo "Promoting $REPLICA_CONTAINER to primary..."
docker exec "$REPLICA_CONTAINER" pg_ctl promote -D /var/lib/postgresql/data
echo "Update .env DB_HOST to point to the new primary."
```

### 6.4 Backup Automat (la fiecare 6 ore)

Fisier: `docker/postgres/scripts/backup.sh`

```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="/backups/epas_${TIMESTAMP}.sql.gz"
PGPASSWORD=password pg_dump -h pgsql -U sail -d sail --format=custom --compress=9 -f "$BACKUP_FILE"
ls -t /backups/epas_*.sql.gz | tail -n +31 | xargs rm -f 2>/dev/null
```

---

## 7. Rezumat Complet — Toate Fisierele

### Fisiere NOI:
| Fisier | Scop |
|--------|------|
| `docker/postgres/primary/postgresql.conf` | Config replicare primary |
| `docker/postgres/primary/pg_hba.conf` | Auth rules |
| `docker/postgres/primary/init-replication.sql` | Initializare replicator user |
| `docker/postgres/replica/setup-replica.sh` | Script initializare replica |
| `docker/postgres/scripts/promote-replica.sh` | Failover manual |
| `docker/postgres/scripts/backup.sh` | Backup automat |
| `app/Console/Commands/CheckReplicationHealth.php` | Health check command |
| `app/Jobs/TrackOrderConversionJob.php` | Async tracking conversions |

### Fisiere MODIFICATE:
| Fisier | Ce modificam |
|--------|--------------|
| `docker-compose.yml` | Adaugam replici + backup service + healthcheck |
| `config/database.php` | Read/Write splitting pe conexiunea `pgsql` |
| `.env.example` | Fix port 5432, adaugam variabile replici |
| `app/Observers/OrderObserver.php` | Mutam tracking/analytics/notifications pe queue |
| `app/Services/PromoCodes/PromoCodeUsageAnalyzer.php` | Fix MySQL syntax → PostgreSQL |
| `app/Services/Analytics/EventAnalyticsService.php` | Fix N+1 pe ore, fix N+1 pe returning customer |
| `routes/api.php` | Rate limits separate pt write endpoints, checkout, promo validate |

---

## 8. Ordinea de Implementare (Prioritizata)

### Faza 1 — Bugfix-uri (nu depind de replicare)
1. Fix `PromoCodeUsageAnalyzer` — MySQL syntax pe PostgreSQL (va crapa in productie)
2. Fix `EventAnalyticsService` N+1 (90 query-uri → 5 query-uri)
3. Rate limits dedicate pe checkout si promo-codes/validate

### Faza 2 — Async writes (pregatire pentru replicare)
4. Cream `TrackOrderConversionJob`
5. Refactorizam `OrderObserver` sa dispatch async
6. Configuram Spatie Activity Log pe queue

### Faza 3 — Replicare
7. Curatam `.env.example`
8. Cream structura `docker/postgres/`
9. Modificam `docker-compose.yml`
10. Modificam `config/database.php` — read/write splitting
11. Cream `CheckReplicationHealth` command

### Faza 4 — Productie
12. PgBouncer (connection pooling)
13. Backup offsite (S3)
14. Monitoring + alertare pe Slack

---

## 9. Consideratii Productie

- **PgBouncer** intre Laravel si Postgres (connection pooling) — reduce overhead-ul de conexiuni
- **Patroni** sau **repmgr** pentru failover automat real
- **Backup offsite** — S3, Google Cloud Storage
- **Monitoring** — `pg_stat_replication` + alertare pe lag > 30s
- `synchronous_commit = on` garanteaza zero data loss (trade-off: +5ms pe fiecare write)
- **Index recomandat:** `CREATE INDEX idx_cce_event_ids ON core_customer_events (event_id, marketplace_event_id)` pentru query-urile cu `orWhere`

# Plan: Database Replication, Migrare & High Availability

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

**Lucram deja cu PostgreSQL.** Nu exista nicio nevoie de migrare. Codul este PostgreSQL-native.

Motivul confuziei probabile: `.env.example` are `DB_PORT=3306` comentat (e ramas din template-ul default Laravel, care pune MySQL ca exemplu). In practica, `docker-compose.yml` ruleaza `postgres:16` pe portul `5432`.

### Recomandare: Curatam `.env.example`
Schimbam portul comentat din `3306` in `5432` si adaugam `pgsql` ca default, ca sa fie clar:

```env
DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=sail
# DB_USERNAME=sail
# DB_PASSWORD=password
```

---

## 3. Replicare: 2+ Replici Read-Only (Streaming Replication)

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

### Pas 3.1: Docker Compose — Adaugam Replici

Adaugam 2 servicii noi in `docker-compose.yml`:

```yaml
services:
  # ... serviciul existent pgsql devine "primary"
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

volumes:
  sail-pgsql:
  sail-pgsql-replica-1:
  sail-pgsql-replica-2:
  sail-redis:
```

### Pas 3.2: Configurare Primary — WAL & Replication Slots

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

### Pas 3.3: Script Initializare Primary

Fisier: `docker/postgres/primary/init-replication.sql`

```sql
-- Cream user-ul de replicare (ruleaza la prima initializare)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'replicator') THEN
        CREATE ROLE replicator WITH REPLICATION LOGIN PASSWORD 'replicator_password';
    END IF;
END
$$;

-- Cream replication slots
SELECT * FROM pg_create_physical_replication_slot('replica_1_slot', true)
    WHERE NOT EXISTS (SELECT 1 FROM pg_replication_slots WHERE slot_name = 'replica_1_slot');

SELECT * FROM pg_create_physical_replication_slot('replica_2_slot', true)
    WHERE NOT EXISTS (SELECT 1 FROM pg_replication_slots WHERE slot_name = 'replica_2_slot');
```

### Pas 3.4: Script Setup Replica

Fisier: `docker/postgres/replica/setup-replica.sh`

```bash
#!/bin/bash
set -e

# Daca data directory-ul e gol, facem base backup de la primary
if [ ! -f "$PGDATA/PG_VERSION" ]; then
    echo "Initializing replica from primary..."

    # Stergem orice fisiere existente
    rm -rf "$PGDATA"/*

    # pg_basebackup de la primary
    PGPASSWORD=replicator_password pg_basebackup \
        -h pgsql \
        -U replicator \
        -D "$PGDATA" \
        -Fp -Xs -P -R

    # -R flag-ul creeaza automat standby.signal si postgresql.auto.conf

    echo "Replica initialized successfully."
fi
```

### Pas 3.5: Laravel — Read/Write Splitting

In `config/database.php`, adaugam configurarea read/write:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),

    // Write operations → Primary
    'write' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
    ],

    // Read operations → Replici (load balanced automat de Laravel)
    'read' => [
        [
            'host' => env('DB_READ_HOST_1', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_READ_PORT_1', '5433'),
        ],
        [
            'host' => env('DB_READ_HOST_2', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_READ_PORT_2', '5434'),
        ],
    ],

    'sticky' => true, // Dupa un write, citirile merg pe primary pana la sfarsitul request-ului

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

### Variabile `.env` noi:

```env
# Primary (Write)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=sail
DB_USERNAME=sail
DB_PASSWORD=password

# Read Replicas
DB_READ_HOST_1=pgsql-replica-1
DB_READ_PORT_1=5432
DB_READ_HOST_2=pgsql-replica-2
DB_READ_PORT_2=5432
```

---

## 4. High Availability — "Mereu o versiune stabila si actuala"

### 4.1 Automated Health Checks (Artisan Command)

Cream o comanda Laravel care verifica starea replicilor:

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
        // Verificam pe primary: cate replici sunt conectate?
        $replicas = DB::select("
            SELECT client_addr, state, sent_lsn, write_lsn, flush_lsn, replay_lsn,
                   (extract(epoch from now()) - extract(epoch from reply_time))::int AS lag_seconds
            FROM pg_stat_replication
        ");

        if (empty($replicas)) {
            $this->error('No replicas connected!');
            // Aici putem trimite alert
            return self::FAILURE;
        }

        $this->info('Connected replicas: ' . count($replicas));

        foreach ($replicas as $r) {
            $lagStatus = $r->lag_seconds > 30 ? '⚠ HIGH LAG' : '✓ OK';
            $this->line(sprintf(
                '  %s | State: %s | Lag: %ds %s',
                $r->client_addr,
                $r->state,
                $r->lag_seconds,
                $lagStatus
            ));

            if ($r->lag_seconds > 60) {
                // Trigger alert (email, Slack, etc.)
                $this->warn("ALERT: Replica {$r->client_addr} lag exceeds 60 seconds!");
            }
        }

        return self::SUCCESS;
    }
}
```

### 4.2 Scheduled Health Monitoring

In `routes/console.php` (sau `app/Console/Kernel.php`):

```php
Schedule::command('db:replication-health')
    ->everyFiveMinutes()
    ->runInBackground();
```

### 4.3 Automatic Failover — Promovare Replica

Daca primary-ul pica, una din replici poate fi promovata:

Fisier: `docker/postgres/scripts/promote-replica.sh`

```bash
#!/bin/bash
# Promoveaza replica la primary
# Folosire: ./promote-replica.sh pgsql-replica-1

REPLICA_CONTAINER=${1:-pgsql-replica-1}

echo "Promoting $REPLICA_CONTAINER to primary..."
docker exec "$REPLICA_CONTAINER" pg_ctl promote -D /var/lib/postgresql/data

echo "Replica promoted. Update .env DB_HOST to point to the new primary."
echo "Don't forget to reconfigure replication from the new primary."
```

### 4.4 Backup Strategy (Point-in-Time Recovery)

Adaugam un serviciu de backup automat:

```yaml
# In docker-compose.yml
  pgbackup:
    image: postgres:16
    volumes:
      - ./backups:/backups
      - ./docker/postgres/scripts/backup.sh:/backup.sh
    entrypoint: ["/bin/bash", "-c"]
    command: ["while true; do /backup.sh; sleep 21600; done"]  # La fiecare 6 ore
    depends_on:
      - pgsql
    networks:
      - sail
```

Fisier: `docker/postgres/scripts/backup.sh`

```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="/backups/epas_${TIMESTAMP}.sql.gz"

echo "Starting backup at $TIMESTAMP..."

PGPASSWORD=password pg_dump \
    -h pgsql -U sail -d sail \
    --format=custom \
    --compress=9 \
    -f "$BACKUP_FILE"

echo "Backup completed: $BACKUP_FILE"

# Pastram ultimele 30 de backup-uri, stergem pe cele vechi
ls -t /backups/epas_*.sql.gz | tail -n +31 | xargs rm -f 2>/dev/null
echo "Old backups cleaned up."
```

---

## 5. Rezumat Fisiere de Creat/Modificat

### Fisiere NOI:
| Fisier | Scop |
|--------|------|
| `docker/postgres/primary/postgresql.conf` | Config replicare primary |
| `docker/postgres/primary/pg_hba.conf` | Auth rules (permite replicator) |
| `docker/postgres/primary/init-replication.sql` | Initializare user + slots |
| `docker/postgres/replica/setup-replica.sh` | Script initializare replica |
| `docker/postgres/scripts/promote-replica.sh` | Failover manual |
| `docker/postgres/scripts/backup.sh` | Backup automat |
| `app/Console/Commands/CheckReplicationHealth.php` | Health check command |

### Fisiere MODIFICATE:
| Fisier | Ce modificam |
|--------|--------------|
| `docker-compose.yml` | Adaugam `pgsql-replica-1`, `pgsql-replica-2`, `pgbackup` |
| `config/database.php` | Read/Write splitting pe conexiunea `pgsql` |
| `.env.example` | Adaugam variabile replici + fix port 5432 |

---

## 6. Ordinea de Implementare

1. **Curatam `.env.example`** — fixam portul si default-ul la pgsql
2. **Cream structura `docker/postgres/`** — config files, scripts
3. **Modificam `docker-compose.yml`** — adaugam replici + backup service
4. **Modificam `config/database.php`** — read/write splitting
5. **Cream `CheckReplicationHealth` command** — monitoring
6. **Updatam `.env.example`** — variabile noi pentru replici
7. **Testam** — `docker compose up`, verificam replicarea functioneaza

---

## 7. Consideratii Productie

- In productie, recomand **PgBouncer** intre Laravel si Postgres (connection pooling)
- Pentru failover automat real, exista **Patroni** (Kubernetes) sau **repmgr**
- Backup-urile ar trebui stocate si offsite (S3, Google Cloud Storage)
- Monitoring: **pg_stat_replication** + alertare pe lag > 30s
- `synchronous_commit = on` garanteaza ca datele sunt pe cel putin o replica inainte de confirmare (trade-off: latenta mai mare pe write)

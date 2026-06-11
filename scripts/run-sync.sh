#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
VENV="/home/stage-rh0h5/pgvenv"
SYNC_SCRIPT="/home/stage-rh0h5/sync-core-to-stage.py"
LOG="/home/stage-rh0h5/sync.log"

echo "=== Sync started at $(date) ===" >> "$LOG"

# 1. Create temp database
sudo -u postgres psql -c "DROP DATABASE IF EXISTS stage_tixello_temp;" 2>&1
sudo -u postgres psql -c "CREATE DATABASE stage_tixello_temp OWNER stage_tixello;" 2>&1
sudo -u postgres psql -c "ALTER USER stage_tixello WITH SUPERUSER;" 2>&1

# 2. Run migrations on temp DB (bypass PgBouncer - connect directly to PG on port 5432)
cd "$APP_DIR"
sudo -u stage-rh0h5 DB_DATABASE=stage_tixello_temp DB_PORT=5432 php artisan migrate --force --no-interaction 2>&1

# 3. Widen varchar columns before import
PGPASSWORD=viHJ41Y86rS9zJVRibeA psql -U stage_tixello -h localhost -p 5432 -d stage_tixello_temp -c "
ALTER TABLE venues ALTER COLUMN name TYPE text;
ALTER TABLE venues ALTER COLUMN slug TYPE text;
ALTER TABLE venues ALTER COLUMN address TYPE text;
ALTER TABLE venues ALTER COLUMN city TYPE text;
" 2>&1

# 4. Import data from MySQL (Python connects directly to PG port 5432)
sed "s/stage_tixello_core/stage_tixello_temp/g" "$SYNC_SCRIPT" | "$VENV/bin/python" 2>&1

# 5. Post-sync fixes (jsonb, admin user, roles)
"$VENV/bin/python" "$SCRIPT_DIR/post-sync-fixes.py" stage_tixello_temp 2>&1

# 6. Swap databases (instant)
sudo -u postgres psql -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname IN ('stage_tixello_core', 'stage_tixello_temp') AND pid <> pg_backend_pid();" 2>&1
sudo -u postgres psql -c "ALTER DATABASE stage_tixello_core RENAME TO stage_tixello_old;" 2>&1
sudo -u postgres psql -c "ALTER DATABASE stage_tixello_temp RENAME TO stage_tixello_core;" 2>&1
sudo -u postgres psql -c "DROP DATABASE IF EXISTS stage_tixello_old;" 2>&1

sudo -u postgres psql -c "ALTER USER stage_tixello WITH NOSUPERUSER;" 2>&1
echo "=== Sync completed at $(date) ===" >> "$LOG"

#!/usr/bin/env bash
# Shorts dev migration runner.
#
# The repository's full migration history cannot replay on SQLite (a
# pre-existing migration drops an indexed column, which SQLite refuses), so this
# builds a scoped dev database instead: the upstream stubs the Shorts feature
# points at, then every shorts migration in order.
#
# Never touches a real database — it only ever writes database/dev-shorts.sqlite.
set -euo pipefail

cd "$(dirname "$0")/.."

DB="$(pwd)/database/dev-shorts.sqlite"

if [ "${1:-}" = "--reset" ]; then
  rm -f "$DB"
fi

touch "$DB"

run() {
  DB_CONNECTION=sqlite DB_DATABASE="$DB" php artisan "$@"
}

run migrate --path=tests/database/migrations --force

for f in database/migrations/[0-9]*_shorts_*.php; do
  run migrate --path="$f" --force
done

echo
echo "Dev schema ready at $DB"
DB_CONNECTION=sqlite DB_DATABASE="$DB" php artisan migrate:status --path=tests/database/migrations --force >/dev/null

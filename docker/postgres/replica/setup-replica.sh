#!/bin/bash
set -e

# Initialize replica from primary via pg_basebackup
# Only runs if the data directory is empty (first boot)

if [ ! -f "$PGDATA/PG_VERSION" ]; then
    echo "Initializing replica from primary..."

    rm -rf "$PGDATA"/*

    # -R flag creates standby.signal and postgresql.auto.conf automatically
    PGPASSWORD=replicator_password pg_basebackup \
        -h pgsql \
        -U replicator \
        -D "$PGDATA" \
        -Fp -Xs -P -R

    # Set hot_standby on the replica
    echo "hot_standby = on" >> "$PGDATA/postgresql.conf"

    echo "Replica initialized successfully."
fi

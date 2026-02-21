#!/bin/bash
# Automated PostgreSQL backup script
# Runs inside the pgbackup container, creates compressed backups
# Retains the last 30 backups

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"
BACKUP_FILE="${BACKUP_DIR}/epas_${TIMESTAMP}.dump"

echo "[$(date)] Starting backup..."

PGPASSWORD=password pg_dump \
    -h pgsql \
    -U sail \
    -d sail \
    --format=custom \
    --compress=9 \
    -f "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
    echo "[$(date)] Backup completed: $BACKUP_FILE ($SIZE)"
else
    echo "[$(date)] ERROR: Backup failed!"
    exit 1
fi

# Retain only the last 30 backups
ls -t ${BACKUP_DIR}/epas_*.dump 2>/dev/null | tail -n +31 | xargs rm -f 2>/dev/null
echo "[$(date)] Cleanup done. Retaining last 30 backups."

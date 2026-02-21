#!/bin/bash
# Promote a replica to primary in case of failover
# Usage: ./promote-replica.sh [container-name]
#
# After promotion:
# 1. Update .env DB_HOST to point to the new primary
# 2. Reconfigure remaining replicas to stream from the new primary
# 3. Rebuild the old primary as a replica

REPLICA_CONTAINER=${1:-pgsql-replica-1}

echo "Promoting $REPLICA_CONTAINER to primary..."
docker exec "$REPLICA_CONTAINER" pg_ctl promote -D /var/lib/postgresql/data

echo ""
echo "Replica promoted successfully."
echo ""
echo "Next steps:"
echo "  1. Update DB_HOST in .env to point to: $REPLICA_CONTAINER"
echo "  2. Reconfigure remaining replicas to stream from $REPLICA_CONTAINER"
echo "  3. Rebuild the old primary as a new replica"

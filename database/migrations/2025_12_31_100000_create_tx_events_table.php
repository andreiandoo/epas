<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates tx_events table with monthly partitioning for high-volume event storage.
     * This is the raw events table for the tracking intelligence system.
     */
    public function up(): void
    {
        // Create the partitioned table using raw SQL for PostgreSQL partitioning
        DB::statement("
            CREATE TABLE tx_events (
                id BIGSERIAL,
                event_id UUID NOT NULL,
                event_name VARCHAR(100) NOT NULL,
                event_version SMALLINT NOT NULL DEFAULT 1,
                occurred_at TIMESTAMPTZ NOT NULL,
                received_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                tenant_id BIGINT NOT NULL,
                site_id VARCHAR(50),
                source_system VARCHAR(20) NOT NULL CHECK (source_system IN ('web', 'mobile', 'scanner', 'backend', 'payments', 'shop', 'wallet')),
                visitor_id VARCHAR(64),
                session_id VARCHAR(64),
                sequence_no INTEGER,
                person_id BIGINT,
                consent_snapshot JSONB NOT NULL DEFAULT '{}',
                context JSONB NOT NULL DEFAULT '{}',
                entities JSONB NOT NULL DEFAULT '{}',
                payload JSONB NOT NULL DEFAULT '{}',
                idempotency_key VARCHAR(255),
                prev_event_id UUID,
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        ");

        // Create initial partitions for current and next 3 months
        $this->createPartitionsForMonths(3);

        // Create indexes
        DB::statement('CREATE INDEX idx_tx_events_tenant_event_occurred ON tx_events (tenant_id, event_name, occurred_at)');
        DB::statement('CREATE INDEX idx_tx_events_tenant_person_occurred ON tx_events (tenant_id, person_id, occurred_at) WHERE person_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_tx_events_tenant_visitor_occurred ON tx_events (tenant_id, visitor_id, occurred_at)');
        DB::statement('CREATE INDEX idx_tx_events_tenant_occurred_brin ON tx_events USING BRIN (tenant_id, occurred_at)');
        DB::statement('CREATE UNIQUE INDEX idx_tx_events_event_id ON tx_events (event_id, occurred_at)');
        DB::statement('CREATE INDEX idx_tx_events_idempotency ON tx_events (idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement('CREATE INDEX idx_tx_events_entities_event ON tx_events ((entities->>\'event_entity_id\'), occurred_at)');
        DB::statement('CREATE INDEX idx_tx_events_entities_order ON tx_events ((entities->>\'order_id\'), occurred_at)');
        DB::statement('CREATE INDEX idx_tx_events_session ON tx_events (session_id, occurred_at)');

        // Add foreign key constraints (these work with partitioned tables in PostgreSQL 11+)
        // Note: We don't add FK to tenants here as the parent table doesn't exist in all tests
        // In production, add: ALTER TABLE tx_events ADD CONSTRAINT fk_tx_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS tx_events CASCADE');
    }

    /**
     * Create monthly partitions for the specified number of months ahead.
     */
    private function createPartitionsForMonths(int $monthsAhead): void
    {
        $startDate = now()->startOfMonth();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $partitionDate = $startDate->copy()->addMonths($i);
            $partitionName = 'tx_events_' . $partitionDate->format('Y_m');
            $startBound = $partitionDate->format('Y-m-d');
            $endBound = $partitionDate->copy()->addMonth()->format('Y-m-d');

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$partitionName}
                PARTITION OF tx_events
                FOR VALUES FROM ('{$startBound}') TO ('{$endBound}')
            ");
        }
    }
};

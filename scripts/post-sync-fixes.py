#!/usr/bin/env python3
"""Post-sync fixes for stage PostgreSQL database.
Run after syncing data from MySQL to fix type mismatches and missing tables."""

import psycopg2
import sys

DB_NAME = sys.argv[1] if len(sys.argv) > 1 else "stage_tixello_core"

pg = psycopg2.connect(
    host="localhost",
    user="stage_tixello",
    password="viHJ41Y86rS9zJVRibeA",
    dbname=DB_NAME
)
pg.autocommit = True
c = pg.cursor()

# 1. Fix notifications.data to jsonb
try:
    c.execute("SELECT data_type FROM information_schema.columns WHERE table_name='notifications' AND column_name='data' AND table_schema='public'")
    row = c.fetchone()
    if row and row[0] != 'jsonb':
        c.execute("UPDATE notifications SET data = '{}' WHERE data IS NOT NULL AND data::text !~ '^[{\\[]'")
        c.execute("""
            ALTER TABLE notifications ALTER COLUMN data TYPE jsonb
            USING CASE WHEN data IS NULL THEN NULL
            WHEN data::text = '' THEN '{}'::jsonb
            ELSE data::text::jsonb END
        """)
        print("  notifications.data -> jsonb OK")
    else:
        print("  notifications.data already jsonb, skipping")
except Exception as e:
    print(f"  notifications.data: {e}")

# 2. Create missing tables
missing_tables = [
    """CREATE TABLE IF NOT EXISTS contract_templates (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, name VARCHAR(255),
        slug VARCHAR(255), content TEXT, is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS contract_custom_variables (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, name VARCHAR(255),
        label VARCHAR(255), type VARCHAR(50) DEFAULT 'text',
        default_value TEXT NULL, is_active BOOLEAN DEFAULT true,
        sort_order INT DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS gdpr_requests (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, customer_id BIGINT NULL,
        type VARCHAR(255) NULL, status VARCHAR(255) NULL, data JSONB NULL,
        notes TEXT NULL, processed_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
]
for sql in missing_tables:
    try:
        c.execute(sql)
        print("  Missing table created OK")
    except Exception as e:
        print(f"  Missing table: {e}")

# 3. Convert varchar/text columns that contain JSON to jsonb
# These columns are used with ->> operator in queries
json_columns = [
    'data', 'properties', 'meta', 'settings', 'features',
    'type_settings', 'donation_settings', 'payment_credentials',
    'stripe_connect_meta', 'config_snapshot', 'options',
    'failed_job_ids', 'custom_fields', 'extra_data'
]

c.execute("""
    SELECT table_name, column_name, data_type
    FROM information_schema.columns
    WHERE table_schema = 'public'
    AND data_type IN ('text', 'character varying')
    AND column_name = ANY(%s)
""", (json_columns,))

for table, col, dtype in c.fetchall():
    try:
        c.execute(f"""
            ALTER TABLE "{table}" ALTER COLUMN "{col}" TYPE jsonb
            USING CASE
                WHEN "{col}" IS NULL THEN NULL
                WHEN "{col}" = '' THEN '{{}}'::jsonb
                WHEN "{col}" ~ '^[{{\\[]' THEN "{col}"::jsonb
                ELSE jsonb_build_object('value', "{col}")
            END
        """)
        print(f"  {table}.{col} ({dtype}) -> jsonb OK")
    except Exception as e:
        pg.rollback()
        pg.autocommit = True
        print(f"  {table}.{col}: SKIP ({str(e)[:60]})")

print("Post-sync fixes complete!")
pg.close()

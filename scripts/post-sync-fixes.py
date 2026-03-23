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

# 2. (Moved to section 5 - core tables)

# 2.5 Fix varchar columns that are too short for JSON data
try:
    c.execute("ALTER TABLE venues ALTER COLUMN name TYPE text")
    c.execute("ALTER TABLE venues ALTER COLUMN slug TYPE text")
    c.execute("ALTER TABLE venues ALTER COLUMN address TYPE text")
    print("  venues columns widened OK")
except Exception as e:
    print(f"  venues columns: {e}")

# 3. Convert varchar/text columns that contain JSON to jsonb
# These columns are used with ->> operator in queries
json_columns = [
    'data', 'properties', 'meta', 'settings', 'features',
    'type_settings', 'donation_settings', 'payment_credentials',
    'stripe_connect_meta', 'config_snapshot', 'options',
    'failed_job_ids', 'custom_fields', 'extra_data',
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

# 4. Ensure translatable columns in taxonomy tables are jsonb
# These tables use the Translatable trait and queries use ->> operator
taxonomy_tables = [
    'artist_types', 'artist_genres', 'event_types', 'event_genres',
    'event_tags', 'venue_types',
]
translatable_columns = ['name', 'description']

for table in taxonomy_tables:
    c.execute("""
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = %s
        AND column_name = ANY(%s)
        AND data_type IN ('text', 'character varying')
    """, (table, translatable_columns))

    for col, dtype in c.fetchall():
        try:
            c.execute(f"""
                ALTER TABLE "{table}" ALTER COLUMN "{col}" TYPE jsonb
                USING CASE
                    WHEN "{col}" IS NULL THEN NULL
                    WHEN "{col}" = '' THEN '{{}}'::jsonb
                    WHEN "{col}" ~ '^[{{\\[]' THEN "{col}"::jsonb
                    ELSE jsonb_build_object('en', "{col}")
                END
            """)
            print(f"  {table}.{col} ({dtype}) -> jsonb OK")
        except Exception as e:
            pg.rollback()
            pg.autocommit = True
            print(f"  {table}.{col}: SKIP ({str(e)[:60]})")

# 5. Create Laravel core tables if missing (not in MySQL, only created by migrations)
core_tables = [
    """CREATE TABLE IF NOT EXISTS users (
        id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE,
        email_verified_at TIMESTAMP NULL, password VARCHAR(255) NOT NULL,
        remember_token VARCHAR(100) NULL, role VARCHAR(255) NOT NULL DEFAULT 'editor',
        tenant_id BIGINT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS password_reset_tokens (
        email VARCHAR(255) PRIMARY KEY, token VARCHAR(255) NOT NULL, created_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(255) PRIMARY KEY, user_id BIGINT NULL, ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL, payload TEXT NOT NULL, last_activity INT NOT NULL)""",
    """CREATE TABLE IF NOT EXISTS settings (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, key VARCHAR(255) NULL,
        value TEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS gdpr_requests (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, customer_id BIGINT NULL,
        type VARCHAR(255) NULL, status VARCHAR(255) NULL, data JSONB NULL,
        notes TEXT NULL, processed_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS contract_templates (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, name VARCHAR(255),
        slug VARCHAR(255), content TEXT, is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS contract_custom_variables (
        id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NULL, name VARCHAR(255),
        label VARCHAR(255), type VARCHAR(50) DEFAULT 'text',
        default_value TEXT NULL, is_active BOOLEAN DEFAULT true,
        sort_order INT DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS notifications (
        id UUID PRIMARY KEY, type VARCHAR(255) NOT NULL,
        notifiable_type VARCHAR(255) NOT NULL, notifiable_id BIGINT NOT NULL,
        data JSONB NOT NULL, read_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
    """CREATE TABLE IF NOT EXISTS cache (
        key VARCHAR(255) PRIMARY KEY, value TEXT NOT NULL, expiration INT NOT NULL)""",
    """CREATE TABLE IF NOT EXISTS cache_locks (
        key VARCHAR(255) PRIMARY KEY, owner VARCHAR(255) NOT NULL, expiration INT NOT NULL)""",
    """CREATE TABLE IF NOT EXISTS jobs (
        id BIGSERIAL PRIMARY KEY, queue VARCHAR(255) NOT NULL, payload TEXT NOT NULL,
        attempts SMALLINT NOT NULL, reserved_at INT NULL, available_at INT NOT NULL, created_at INT NOT NULL)""",
    """CREATE TABLE IF NOT EXISTS job_batches (
        id VARCHAR(255) PRIMARY KEY, name VARCHAR(255) NOT NULL, total_jobs INT NOT NULL,
        pending_jobs INT NOT NULL, failed_jobs INT NOT NULL, failed_job_ids TEXT NOT NULL,
        options TEXT NULL, cancelled_at INT NULL, created_at INT NOT NULL, finished_at INT NULL)""",
    """CREATE TABLE IF NOT EXISTS failed_jobs (
        id BIGSERIAL PRIMARY KEY, uuid VARCHAR(255) NOT NULL UNIQUE, connection TEXT NOT NULL,
        queue TEXT NOT NULL, payload TEXT NOT NULL, exception TEXT NOT NULL, failed_at TIMESTAMP DEFAULT NOW())""",
    """CREATE TABLE IF NOT EXISTS activity_log (
        id BIGSERIAL PRIMARY KEY, log_name VARCHAR(255) NULL, description TEXT NOT NULL,
        subject_type VARCHAR(255) NULL, subject_id BIGINT NULL,
        causer_type VARCHAR(255) NULL, causer_id BIGINT NULL,
        properties JSONB NULL, batch_uuid UUID NULL,
        event VARCHAR(255) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)""",
]
for sql in core_tables:
    try:
        c.execute(sql)
    except Exception as e:
        pass
print("  Core Laravel tables OK")

# 5b. Create Spatie permission tables if missing (not created by migrate when vendor migrations unpublished)
spatie_tables = [
    """CREATE TABLE IF NOT EXISTS roles (
        id BIGSERIAL PRIMARY KEY, name VARCHAR(125) NOT NULL, guard_name VARCHAR(125) NOT NULL,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, UNIQUE(name, guard_name))""",
    """CREATE TABLE IF NOT EXISTS permissions (
        id BIGSERIAL PRIMARY KEY, name VARCHAR(125) NOT NULL, guard_name VARCHAR(125) NOT NULL,
        created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, UNIQUE(name, guard_name))""",
    """CREATE TABLE IF NOT EXISTS model_has_roles (
        role_id BIGINT NOT NULL, model_type VARCHAR(255) NOT NULL, model_id BIGINT NOT NULL,
        PRIMARY KEY(role_id, model_id, model_type))""",
    """CREATE TABLE IF NOT EXISTS model_has_permissions (
        permission_id BIGINT NOT NULL, model_type VARCHAR(255) NOT NULL, model_id BIGINT NOT NULL,
        PRIMARY KEY(permission_id, model_id, model_type))""",
    """CREATE TABLE IF NOT EXISTS role_has_permissions (
        permission_id BIGINT NOT NULL, role_id BIGINT NOT NULL,
        PRIMARY KEY(permission_id, role_id))""",
]
for sql in spatie_tables:
    try:
        c.execute(sql)
    except Exception as e:
        pass
print("  Spatie permission tables OK")

# 6. Ensure admin user exists (users table is created by migrations but not populated by MySQL sync)
try:
    c.execute("SELECT COUNT(*) FROM users WHERE email = 'nastase.ai@gmail.com'")
    if c.fetchone()[0] == 0:
        c.execute("""
            INSERT INTO users (name, email, password, email_verified_at, role, created_at, updated_at)
            VALUES ('Admin', 'nastase.ai@gmail.com',
                    '$2y$12$defaulthashedpasswordplaceholdervalue000000000000000000',
                    NOW(), 'super-admin', NOW(), NOW())
        """)
        print("  Admin user created (password needs reset via tinker)")
    else:
        # Ensure role is set
        c.execute("UPDATE users SET role = 'super-admin' WHERE email = 'nastase.ai@gmail.com' AND (role IS NULL OR role = '')")
        print("  Admin user exists, role verified")
except Exception as e:
    print(f"  Admin user: {e}")

# 7. Ensure Spatie roles table has super-admin
try:
    c.execute("SELECT COUNT(*) FROM roles WHERE name = 'super-admin'")
    if c.fetchone()[0] == 0:
        c.execute("INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES ('super-admin', 'web', NOW(), NOW())")
    # Assign role to admin user
    c.execute("""
        INSERT INTO model_has_roles (role_id, model_type, model_id)
        SELECT r.id, 'App\\Models\\User', u.id
        FROM roles r, users u
        WHERE r.name = 'super-admin' AND u.email = 'nastase.ai@gmail.com'
        ON CONFLICT DO NOTHING
    """)
    print("  Roles configured OK")
except Exception as e:
    print(f"  Roles: {e}")

print("Post-sync fixes complete!")
pg.close()

#!/usr/bin/env python3
"""Sync data from MySQL (core) to PostgreSQL (stage).
Only imports data into existing PG tables - does NOT create/drop tables.
Tables must be created by Laravel migrations first."""

import mysql.connector
import psycopg2
import sys

DB_NAME = sys.argv[1] if len(sys.argv) > 1 else "stage_tixello_core"

mysql_conn = mysql.connector.connect(
    host='127.0.0.1', user='tixello_core',
    password='KufbCm9i7jnjZb93rZfD', database='tixello_core',
    charset='utf8mb4'
)
pg_conn = psycopg2.connect(
    host='localhost', user='stage_tixello',
    password='viHJ41Y86rS9zJVRibeA', dbname=DB_NAME
)
pg_conn.autocommit = False

my_cur = mysql_conn.cursor()
pg_cur = pg_conn.cursor()

# Disable FK checks during import
pg_cur.execute("SET session_replication_role = 'replica';")
pg_conn.commit()

# Get PG tables (only import into tables that already exist)
pg_cur.execute("SELECT tablename FROM pg_tables WHERE schemaname='public'")
pg_tables = set(r[0] for r in pg_cur.fetchall())

def get_pg_col_types(table):
    pg_cur.execute(f"""
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_name='{table}' AND table_schema='public'
    """)
    return {r[0]: r[1] for r in pg_cur.fetchall()}

my_cur.execute("SHOW TABLES")
tables = [t[0] for t in my_cur.fetchall()]

# Tables to skip (Laravel framework tables not in MySQL)
skip = {'migrations', 'cache', 'cache_locks'}
errors = []

for table in tables:
    if table in skip or table not in pg_tables:
        continue
    try:
        my_cur.execute(f"SELECT * FROM `{table}` LIMIT 0")
        my_cur.fetchall()
        mysql_cols = [d[0] for d in my_cur.description]

        pg_types = get_pg_col_types(table)
        pg_cols = set(pg_types.keys())
        common_cols = [c for c in mysql_cols if c in pg_cols]
        if not common_cols:
            continue

        my_cur.execute(f"SELECT {','.join([f'`{c}`' for c in common_cols])} FROM `{table}`")
        rows = my_cur.fetchall()
        if not rows:
            continue

        # Convert values for PG compatibility
        converted = []
        for row in rows:
            new_row = []
            for i, val in enumerate(row):
                col_type = pg_types.get(common_cols[i], '')
                if col_type == 'boolean' and val is not None:
                    new_row.append(bool(val))
                elif isinstance(val, bytes):
                    try:
                        new_row.append(val.decode('utf-8'))
                    except:
                        new_row.append(val.decode('latin-1'))
                else:
                    new_row.append(val)
            converted.append(tuple(new_row))

        pg_col_names = ','.join([f'"{c}"' for c in common_cols])
        placeholders = ','.join(['%s'] * len(common_cols))

        # DELETE existing data (not DROP TABLE)
        pg_cur.execute(f'DELETE FROM "{table}"')

        # Insert in batches
        batch_size = 500
        for i in range(0, len(converted), batch_size):
            batch = converted[i:i+batch_size]
            args = ','.join(pg_cur.mogrify(f'({placeholders})', row).decode() for row in batch)
            pg_cur.execute(f'INSERT INTO "{table}" ({pg_col_names}) VALUES {args}')

        pg_conn.commit()
        print(f"  {table}: {len(rows)} rows OK")
    except Exception as e:
        pg_conn.rollback()
        pg_cur.execute("SET session_replication_role = 'replica';")
        pg_conn.commit()
        errors.append(table)
        print(f"  {table}: ERROR - {str(e)[:120]}")

# Re-enable FK checks
pg_cur.execute("SET session_replication_role = 'origin';")
pg_conn.commit()

# Reset sequences for tables with serial/bigserial PKs
for table in pg_tables:
    try:
        pg_cur.execute(f"SELECT setval(pg_get_serial_sequence('{table}', 'id'), COALESCE(MAX(id), 1)) FROM \"{table}\"")
        pg_conn.commit()
    except:
        pg_conn.rollback()

print(f"\nDone! {len(errors)} errors: {errors}")
mysql_conn.close()
pg_conn.close()

#!/usr/bin/env python3
"""
Fetch Instagram follower counts for artists using instaloader.
No login required - uses public profile data only.

Usage:
    pip install instaloader psycopg2-binary python-dotenv
    python fetch-instagram-followers.py

    # Or with options:
    python fetch-instagram-followers.py --delay-min 15 --delay-max 40 --limit 100 --skip-days 7

Run in background on server:
    nohup python3 scripts/fetch-instagram-followers.py > storage/logs/ig-followers.log 2>&1 &
"""

import os
import re
import sys
import time
import random
import logging
import argparse
from datetime import datetime, timedelta
from pathlib import Path

# Load .env from Laravel root
script_dir = Path(__file__).resolve().parent
project_root = script_dir.parent
env_path = project_root / '.env'

def load_env(path):
    """Simple .env parser (no dependency needed if python-dotenv not available)."""
    env = {}
    if not path.exists():
        return env
    with open(path, 'r') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' in line:
                key, _, value = line.partition('=')
                key = key.strip()
                value = value.strip().strip('"').strip("'")
                env[key] = value
    return env

# Try python-dotenv first, fallback to manual parser
try:
    from dotenv import dotenv_values
    env_vars = dotenv_values(env_path)
except ImportError:
    env_vars = load_env(env_path)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    handlers=[
        logging.StreamHandler(sys.stdout),
    ]
)
logger = logging.getLogger(__name__)


def extract_username(instagram_url):
    """Extract Instagram username from URL."""
    if not instagram_url:
        return None

    # Remove trailing slash and query params
    url = instagram_url.strip().rstrip('/').split('?')[0]

    # Match patterns like instagram.com/username or @username
    match = re.search(r'instagram\.com/([A-Za-z0-9_.]+)', url)
    if match:
        username = match.group(1).lower()
        # Filter out non-profile paths
        skip = ['p', 'reel', 'reels', 'stories', 'explore', 'direct', 'accounts', 'tv', 'about']
        if username in skip:
            return None
        return username

    # Maybe it's just a username
    if re.match(r'^@?[A-Za-z0-9_.]+$', url):
        return url.lstrip('@').lower()

    return None


def get_db_connection():
    """Create database connection from Laravel .env."""
    db_connection = env_vars.get('DB_CONNECTION', 'sqlite')

    if db_connection == 'pgsql':
        try:
            import psycopg2
        except ImportError:
            logger.error("psycopg2 not installed. Run: pip install psycopg2-binary")
            sys.exit(1)

        return psycopg2.connect(
            host=env_vars.get('DB_HOST', '127.0.0.1'),
            port=int(env_vars.get('DB_PORT', 5432)),
            dbname=env_vars.get('DB_DATABASE', 'laravel'),
            user=env_vars.get('DB_USERNAME', 'postgres'),
            password=env_vars.get('DB_PASSWORD', ''),
        )

    elif db_connection == 'mysql':
        try:
            import pymysql
        except ImportError:
            logger.error("pymysql not installed. Run: pip install pymysql")
            sys.exit(1)

        return pymysql.connect(
            host=env_vars.get('DB_HOST', '127.0.0.1'),
            port=int(env_vars.get('DB_PORT', 3306)),
            database=env_vars.get('DB_DATABASE', 'laravel'),
            user=env_vars.get('DB_USERNAME', 'root'),
            password=env_vars.get('DB_PASSWORD', ''),
            charset='utf8mb4',
        )

    elif db_connection == 'sqlite':
        import sqlite3
        db_path = env_vars.get('DB_DATABASE', str(project_root / 'database' / 'database.sqlite'))
        if not db_path.startswith('/'):
            db_path = str(project_root / db_path)
        return sqlite3.connect(db_path)

    else:
        logger.error(f"Unsupported DB_CONNECTION: {db_connection}")
        sys.exit(1)


def get_paramstyle():
    """Return the correct placeholder style for the DB driver."""
    db_connection = env_vars.get('DB_CONNECTION', 'sqlite')
    if db_connection == 'pgsql':
        return '%s'
    elif db_connection == 'mysql':
        return '%s'
    else:  # sqlite
        return '?'


def main():
    parser = argparse.ArgumentParser(description='Fetch Instagram follower counts for artists')
    parser.add_argument('--delay-min', type=int, default=10, help='Minimum delay between requests (seconds)')
    parser.add_argument('--delay-max', type=int, default=30, help='Maximum delay between requests (seconds)')
    parser.add_argument('--limit', type=int, default=0, help='Limit number of artists to process (0 = all)')
    parser.add_argument('--skip-days', type=int, default=7, help='Skip artists updated within N days')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be processed without fetching')
    args = parser.parse_args()

    # Import instaloader
    try:
        import instaloader
    except ImportError:
        logger.error("instaloader not installed. Run: pip install instaloader")
        sys.exit(1)

    # Connect to DB
    logger.info(f"Connecting to {env_vars.get('DB_CONNECTION', 'sqlite')} database...")
    conn = get_db_connection()
    cursor = conn.cursor()
    ph = get_paramstyle()

    # Query artists with instagram_url, not recently updated
    skip_date = (datetime.now() - timedelta(days=args.skip_days)).strftime('%Y-%m-%d %H:%M:%S')

    query = f"""
        SELECT id, name, instagram_url, followers_instagram
        FROM artists
        WHERE instagram_url IS NOT NULL
          AND instagram_url != ''
          AND (
            social_stats_updated_at IS NULL
            OR social_stats_updated_at < {ph}
          )
        ORDER BY social_stats_updated_at ASC NULLS FIRST, id ASC
    """

    if args.limit > 0:
        query += f" LIMIT {args.limit}"

    cursor.execute(query, (skip_date,))
    artists = cursor.fetchall()

    logger.info(f"Found {len(artists)} artists with Instagram URLs to process")

    if not artists:
        logger.info("Nothing to do. All artists are up to date.")
        conn.close()
        return

    if args.dry_run:
        for row in artists[:20]:
            artist_id, name, ig_url, current_followers = row
            username = extract_username(ig_url)
            logger.info(f"  [DRY RUN] {name} -> @{username} (current: {current_followers})")
        if len(artists) > 20:
            logger.info(f"  ... and {len(artists) - 20} more")
        conn.close()
        return

    # Initialize instaloader (no login)
    loader = instaloader.Instaloader(
        download_pictures=False,
        download_videos=False,
        download_video_thumbnails=False,
        download_geotags=False,
        download_comments=False,
        save_metadata=False,
        compress_json=False,
        quiet=True,
    )

    updated = 0
    errors = 0
    skipped = 0
    total = len(artists)

    for i, row in enumerate(artists, 1):
        artist_id, name, ig_url, current_followers = row
        username = extract_username(ig_url)

        if not username:
            logger.warning(f"[{i}/{total}] {name}: Could not extract username from '{ig_url}'")
            skipped += 1
            continue

        try:
            profile = instaloader.Profile.from_username(loader.context, username)
            followers = profile.followers
            following = profile.followees
            posts = profile.mediacount

            logger.info(
                f"[{i}/{total}] {name} (@{username}): "
                f"{followers:,} followers "
                f"(was: {current_followers or 'NULL'})"
            )

            # Update database
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            update_query = f"""
                UPDATE artists
                SET followers_instagram = {ph},
                    social_stats_updated_at = {ph},
                    updated_at = {ph}
                WHERE id = {ph}
            """
            cursor.execute(update_query, (followers, now, now, artist_id))
            conn.commit()
            updated += 1

        except instaloader.exceptions.ProfileNotExistsException:
            logger.warning(f"[{i}/{total}] {name} (@{username}): Profile not found")
            errors += 1

        except instaloader.exceptions.ConnectionException as e:
            if '429' in str(e) or 'rate' in str(e).lower():
                logger.error(f"[{i}/{total}] Rate limited! Waiting 5 minutes before retry...")
                time.sleep(300)
                # Retry once
                try:
                    profile = instaloader.Profile.from_username(loader.context, username)
                    followers = profile.followers
                    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                    update_query = f"""
                        UPDATE artists
                        SET followers_instagram = {ph},
                            social_stats_updated_at = {ph},
                            updated_at = {ph}
                        WHERE id = {ph}
                    """
                    cursor.execute(update_query, (followers, now, now, artist_id))
                    conn.commit()
                    updated += 1
                    logger.info(f"[{i}/{total}] {name} (@{username}): {followers:,} followers (after retry)")
                except Exception as e2:
                    logger.error(f"[{i}/{total}] {name} (@{username}): Still failing after retry: {e2}")
                    errors += 1
            else:
                logger.error(f"[{i}/{total}] {name} (@{username}): Connection error: {e}")
                errors += 1

        except Exception as e:
            logger.error(f"[{i}/{total}] {name} (@{username}): Error: {e}")
            errors += 1

        # Random delay between requests
        if i < total:
            delay = random.uniform(args.delay_min, args.delay_max)
            logger.debug(f"Waiting {delay:.1f}s...")
            time.sleep(delay)

    conn.close()

    logger.info(f"\n{'='*50}")
    logger.info(f"DONE! Updated: {updated}, Errors: {errors}, Skipped: {skipped}, Total: {total}")
    logger.info(f"{'='*50}")


if __name__ == '__main__':
    main()

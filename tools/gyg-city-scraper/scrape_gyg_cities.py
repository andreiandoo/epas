"""
GetYourGuide city-id scraper for bilete.online cities (Playwright edition).

Why Playwright instead of plain requests
-----------------------------------------
GetYourGuide's search page is gated by Cloudflare. A plain `requests`
call gets 403 because Cloudflare reads the TLS fingerprint + JS-challenge
state, neither of which a pure-HTTP client can produce. Playwright runs
a real headless Chromium that passes through Cloudflare's checks at low
request rates.

Pipeline
--------
  1. Export the 264 cities from the DB to a CSV with columns
     `id, slug, name` (see README in the tinker oneliner provided
     by the assistant).
  2. Run this script. It opens a Chromium, browses the GYG search page
     city-by-city, and picks the first hit whose URL matches the
     `[slug]-l<id>/` city-page pattern.
  3. Send the result CSV back; the assistant will produce a Laravel
     seeder.

Install
-------
  pip install playwright
  python -m playwright install chromium

Run
---
  # Sanity check on the first 5 rows (eyes-on, browser visible):
  python scrape_gyg_cities.py bileteonline_cities.csv out.csv --max 5 --headed

  # Full run, headless:
  python scrape_gyg_cities.py bileteonline_cities.csv out.csv

  # Resume: re-run with the same output file — already-processed ids
  # are skipped automatically.

Strategy per city
-----------------
- Three candidate queries: original (București), ASCII-folded
  (Bucuresti), and a hand-curated exonym (Bucharest).
- First city-shaped URL on the search page wins.
- Confidence is "high" when the GYG slug contains the query, "low"
  when we accept the first match anyway, "not_found" otherwise.
- Polite rate-limit: 4-6 s between cities. 264 rows ≈ 25 minutes.
"""

from __future__ import annotations

import argparse
import csv
import random
import re
import sys
import time
import unicodedata
import urllib.parse
from pathlib import Path
from typing import Iterable

try:
    from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout
except ImportError:
    print(
        "Playwright not installed. Run:\n"
        "  pip install playwright\n"
        "  python -m playwright install chromium",
        file=sys.stderr,
    )
    sys.exit(1)

# ─── Config ──────────────────────────────────────────────────────────
DELAY_MIN_SEC = 4.0
DELAY_MAX_SEC = 6.0
PAGE_TIMEOUT_MS = 30_000
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
)

# Romanian → GYG exonym. Anything else relies on the ASCII-fold heuristic.
EXONYMS = {
    "bucuresti": "bucharest",
    "iasi": "iasi",
    "brasov": "brasov",
    "timisoara": "timisoara",
    "constanta": "constanta",
    "galati": "galati",
    "ploiesti": "ploiesti",
    "targu mures": "targu mures",
    "oradea": "oradea",
    "sibiu": "sibiu",
    "cluj-napoca": "cluj-napoca",
}

# /bucharest-l124688/ — slug, then -l, then digits, then a separator.
CITY_URL_RE = re.compile(
    r'/([a-z][a-z0-9-]+)-l(\d+)/(?=["\'?#]|<|\s|$)',
    re.IGNORECASE,
)


def to_ascii(value: str) -> str:
    return (
        unicodedata.normalize("NFKD", value)
        .encode("ASCII", "ignore")
        .decode("ASCII")
    )


def slugify_for_compare(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", to_ascii(value).lower()).strip("-")


def build_query_candidates(name: str) -> list[str]:
    seen: set[str] = set()
    ordered: list[str] = []

    def push(candidate: str) -> None:
        candidate = candidate.strip()
        if candidate and candidate.lower() not in seen:
            seen.add(candidate.lower())
            ordered.append(candidate)

    push(name)
    ascii_name = to_ascii(name)
    if ascii_name != name:
        push(ascii_name)
    exonym = EXONYMS.get(name.lower()) or EXONYMS.get(ascii_name.lower())
    if exonym:
        push(exonym)
    return ordered


def search_gyg(page, name: str) -> tuple[str | None, str | None, str]:
    """Returns (gyg_id, gyg_url, confidence) for one city."""
    target_slug = slugify_for_compare(name)
    first_low: tuple[str, str] | None = None

    for q in build_query_candidates(name):
        url = f"https://www.getyourguide.com/s/?q={urllib.parse.quote(q)}"
        try:
            page.goto(url, wait_until="domcontentloaded", timeout=PAGE_TIMEOUT_MS)
        except PWTimeout:
            print("  page goto timeout", file=sys.stderr)
            continue
        except Exception as exc:
            print(f"  page goto error: {exc}", file=sys.stderr)
            continue

        # GYG renders results client-side after hydration. Give it a
        # short window — `networkidle` is too strict (their telemetry
        # never stops), so we wait for a city URL to appear in the
        # rendered HTML instead.
        try:
            page.wait_for_function(
                "() => /\\/[a-z0-9-]+-l\\d+\\//.test(document.body.innerHTML)",
                timeout=10_000,
            )
        except PWTimeout:
            # No city URL in the page even after 10 s — likely a "no
            # results" response. Skip this candidate.
            continue

        html = page.content()
        for match in CITY_URL_RE.finditer(html):
            slug = match.group(1).lower()
            gid = match.group(2)
            if target_slug and (target_slug in slug or slug in target_slug):
                return gid, f"https://www.getyourguide.com/{slug}-l{gid}/", "high"
            if first_low is None:
                first_low = (slug, gid)

    if first_low:
        slug, gid = first_low
        return gid, f"https://www.getyourguide.com/{slug}-l{gid}/", "low"
    return None, None, "not_found"


def read_processed_ids(output_path: Path) -> set[str]:
    if not output_path.exists() or output_path.stat().st_size == 0:
        return set()
    with output_path.open(encoding="utf-8") as f:
        reader = csv.DictReader(f)
        return {row["id"] for row in reader if row.get("id")}


def normalize_input_row(row: dict) -> dict:
    name = (row.get("name") or "").strip()
    if name.startswith("{") and name.endswith("}"):
        try:
            import json

            data = json.loads(name)
            name = data.get("ro") or data.get("en") or next(iter(data.values()))
        except Exception:
            pass
    return {**row, "name": name}


def main(argv: Iterable[str] | None = None) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("input", help="Input CSV (id,slug,name)")
    parser.add_argument("output", help="Output CSV")
    parser.add_argument("--max", type=int, default=None, help="Process at most N rows")
    parser.add_argument("--headed", action="store_true", help="Show the browser window (default: headless)")
    args = parser.parse_args(argv)

    input_path = Path(args.input)
    output_path = Path(args.output)

    if not input_path.exists():
        print(f"Input file not found: {input_path}", file=sys.stderr)
        return 2

    with input_path.open(encoding="utf-8") as f:
        rows = [normalize_input_row(r) for r in csv.DictReader(f)]

    processed = read_processed_ids(output_path)
    write_header = not output_path.exists() or output_path.stat().st_size == 0
    fieldnames = ["id", "slug", "name", "gyg_id", "gyg_url", "status", "confidence"]
    counters = {"high": 0, "low": 0, "not_found": 0, "skipped": 0}

    with sync_playwright() as p, output_path.open("a", encoding="utf-8", newline="") as out:
        browser = p.chromium.launch(headless=not args.headed)
        context = browser.new_context(
            user_agent=USER_AGENT,
            locale="ro-RO",
            viewport={"width": 1280, "height": 800},
        )
        # Block heavyweight resources we don't need so each page settles
        # faster — image/font/media bytes are pure waste for our use case.
        context.route(
            "**/*",
            lambda route: route.abort()
            if route.request.resource_type in ("image", "font", "media", "stylesheet")
            else route.continue_(),
        )
        page = context.new_page()

        writer = csv.DictWriter(out, fieldnames=fieldnames)
        if write_header:
            writer.writeheader()
            out.flush()

        seen_this_run = 0
        for index, row in enumerate(rows, start=1):
            if args.max is not None and seen_this_run >= args.max:
                break

            if row["id"] in processed:
                counters["skipped"] += 1
                continue

            name = row["name"]
            print(f"[{index:>3}/{len(rows):>3}] {name}...", end=" ", flush=True)

            gid, gurl, confidence = search_gyg(page, name)
            counters[confidence] += 1
            print(f"→ {gid or '-':>10}  ({confidence})")

            writer.writerow({
                "id": row["id"],
                "slug": row.get("slug", ""),
                "name": name,
                "gyg_id": gid or "",
                "gyg_url": gurl or "",
                "status": "ok" if gid else "not_found",
                "confidence": confidence,
            })
            out.flush()
            seen_this_run += 1

            time.sleep(random.uniform(DELAY_MIN_SEC, DELAY_MAX_SEC))

        browser.close()

    print(
        "\nDone.\n"
        f"  high confidence:    {counters['high']:>4}\n"
        f"  low confidence:     {counters['low']:>4}\n"
        f"  not found:          {counters['not_found']:>4}\n"
        f"  skipped (resume):   {counters['skipped']:>4}\n"
        f"Output: {output_path}"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())

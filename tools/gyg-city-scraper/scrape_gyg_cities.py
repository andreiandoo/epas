"""
GetYourGuide city-id scraper for bilete.online cities.

Pipeline
--------
  1. Export your DB cities to a CSV with at least the columns
     `id, slug, name` (see README.md for the tinker oneliner).
  2. Run this script. It iterates city-by-city, queries GYG's public
     search page, picks the first hit whose URL matches the
     `[slug]-l<id>/` city-page pattern, and writes a result CSV with
     a confidence flag per row.
  3. Send the result CSV back to the assistant — it will produce a
     Laravel seeder that fills marketplace_cities.getyourguide_city_id
     in one shot.

Design notes
------------
- Polite rate limit (3 s + 0-1.5 s jitter) so a 264-row run takes
  ~13 minutes and looks like organic browser traffic. Increase
  DELAY_SEC if GYG starts returning 429.
- Tries the Romanian name as-is, then an ASCII-folded variant
  (Brașov → Brasov), then a small dictionary of English exonyms
  (București → Bucharest). First hit wins; confidence is "high"
  when the slug returned by GYG contains the query string,
  "low" when we accept the first city-shaped URL on the page
  anyway, "not_found" when no city URL appears at all.
- Resume: if `output.csv` already exists, the script picks up
  where it left off (skips rows whose `id` is already in there).
  Kill it any time with Ctrl-C — re-running continues from the
  next un-processed city.
- 4xx/5xx responses skip the candidate; a 429 triggers a long
  back-off (60 s) then a retry on the SAME candidate.

Run
---
  python -m venv venv && source venv/bin/activate     # optional
  pip install requests
  python scrape_gyg_cities.py input.csv output.csv

  # Limit to first N rows (for a sanity check):
  python scrape_gyg_cities.py input.csv output.csv --max 5
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

import requests

# ─── Config ──────────────────────────────────────────────────────────
DELAY_SEC = 3.0
JITTER_SEC = 1.5
REQUEST_TIMEOUT_SEC = 20
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
)

# Map Romanian (or otherwise localized) city names to their canonical
# GetYourGuide exonyms when an obvious one exists. Anything else we
# leave to the ASCII-folding heuristic.
EXONYMS = {
    "bucuresti": "bucharest",
    "bucurești": "bucharest",
    "iasi": "iasi",         # GYG keeps the ASCII form
    "iași": "iasi",
    "brasov": "brasov",
    "brașov": "brasov",
    "timisoara": "timisoara",
    "timișoara": "timisoara",
    "constanta": "constanta",
    "constanța": "constanta",
    "galati": "galati",
    "galați": "galati",
    "ploiesti": "ploiesti",
    "ploiești": "ploiesti",
    "targu mures": "targu mures",
    "târgu mureș": "targu mures",
}

# Match a path like `/bucharest-l124688/` (the GYG city page shape).
# The negative-lookahead at the end stops us from picking up things
# like `/bucharest-l124688/some-tour-t123/` halfway through.
CITY_URL_RE = re.compile(
    r'/([a-z][a-z0-9-]+)-l(\d+)/(?=["\'?#]|<|\s|$)',
    re.IGNORECASE,
)


def to_ascii(value: str) -> str:
    """Strip diacritics so 'Brașov' → 'Brasov'."""
    return (
        unicodedata.normalize("NFKD", value)
        .encode("ASCII", "ignore")
        .decode("ASCII")
    )


def build_query_candidates(name: str) -> list[str]:
    """Order of attempts for a city name."""
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


def slugify_for_compare(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", to_ascii(value).lower()).strip("-")


def search_gyg(name: str, session: requests.Session) -> tuple[str | None, str | None, str]:
    """
    Try to find the GYG location-id for `name`.

    Returns (gyg_id, gyg_url, confidence). Confidence is one of:
      'high'      — slug returned by GYG looks like the query
      'low'       — accepted the first city-shaped URL even though
                    the slug doesn't obviously match
      'not_found' — no city-shaped URL on the search page
    """
    target_slug = slugify_for_compare(name)
    first_low: tuple[str, str] | None = None

    for q in build_query_candidates(name):
        url = f"https://www.getyourguide.com/s/?q={urllib.parse.quote(q)}"

        attempt = 0
        while True:
            attempt += 1
            try:
                response = session.get(
                    url,
                    headers={
                        "User-Agent": USER_AGENT,
                        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                        "Accept-Language": "ro-RO,ro;q=0.9,en;q=0.8",
                    },
                    timeout=REQUEST_TIMEOUT_SEC,
                )
            except requests.RequestException as exc:
                print(f"  HTTP error: {exc}", file=sys.stderr)
                break  # next candidate

            if response.status_code == 429:
                # Be polite, GYG asked us to back off.
                back_off = min(60 * attempt, 300)
                print(f"  429, backing off {back_off}s", file=sys.stderr)
                time.sleep(back_off)
                if attempt < 3:
                    continue
                break

            if response.status_code != 200:
                print(f"  HTTP {response.status_code}", file=sys.stderr)
                break  # next candidate

            for match in CITY_URL_RE.finditer(response.text):
                slug = match.group(1).lower()
                gid = match.group(2)

                # Reject obvious non-city hits (footer fluff, popular
                # destinations widget on irrelevant queries).
                if target_slug and (target_slug in slug or slug in target_slug):
                    canonical = f"https://www.getyourguide.com/{slug}-l{gid}/"
                    return gid, canonical, "high"

                if first_low is None:
                    first_low = (slug, gid)

            break  # finished this candidate

    if first_low:
        slug, gid = first_low
        return gid, f"https://www.getyourguide.com/{slug}-l{gid}/", "low"

    return None, None, "not_found"


def read_pending_ids(output_path: Path) -> set[str]:
    """Resume support — skip rows already present in the output file."""
    if not output_path.exists() or output_path.stat().st_size == 0:
        return set()
    with output_path.open(encoding="utf-8") as f:
        reader = csv.DictReader(f)
        return {row["id"] for row in reader if row.get("id")}


def normalize_input_row(row: dict) -> dict:
    """The name column may itself be a JSON-encoded translation map."""
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
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[1] if __doc__ else None)
    parser.add_argument("input", help="Input CSV (id,slug,name)")
    parser.add_argument("output", help="Output CSV (gets gyg_id + confidence appended)")
    parser.add_argument("--max", type=int, default=None, help="Process at most N rows")
    parser.add_argument("--delay", type=float, default=DELAY_SEC, help=f"Seconds between requests (default {DELAY_SEC})")
    args = parser.parse_args(argv)

    input_path = Path(args.input)
    output_path = Path(args.output)

    if not input_path.exists():
        print(f"Input file not found: {input_path}", file=sys.stderr)
        return 2

    with input_path.open(encoding="utf-8") as f:
        rows = [normalize_input_row(r) for r in csv.DictReader(f)]

    processed = read_pending_ids(output_path)
    write_header = not output_path.exists() or output_path.stat().st_size == 0

    fieldnames = ["id", "slug", "name", "gyg_id", "gyg_url", "status", "confidence"]
    session = requests.Session()
    counters = {"high": 0, "low": 0, "not_found": 0, "skipped": 0}

    with output_path.open("a", encoding="utf-8", newline="") as out:
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
            label = f"[{index:>3}/{len(rows):>3}] {name}"
            print(f"{label}...", end=" ", flush=True)

            gid, gurl, confidence = search_gyg(name, session)
            counters[confidence] += 1
            status = "ok" if gid else "not_found"
            print(f"→ {gid or '-':>10}  ({confidence})")

            writer.writerow({
                "id": row["id"],
                "slug": row.get("slug", ""),
                "name": name,
                "gyg_id": gid or "",
                "gyg_url": gurl or "",
                "status": status,
                "confidence": confidence,
            })
            out.flush()
            seen_this_run += 1

            # Politely wait between requests — but not after the last one.
            if (args.max is not None and seen_this_run >= args.max) or index == len(rows):
                continue
            time.sleep(args.delay + random.uniform(0, JITTER_SEC))

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

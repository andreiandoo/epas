"""
Turn the scraper's out.csv into a Laravel seeder + a human-review report.

Decision rules per row
----------------------
  HIGH confidence
    -> auto-seed UNLESS the GYG slug is suspiciously short/generic
      (e.g. Târgu Frumos -> /frumos-l314/ — "frumos" alone is a
      different place). Flagged for review instead.

  LOW confidence
    Bucket by the GYG slug shape:
      * judetul-<county>-l<id>         -> county-level; auto-seed.
        The widget lists tours covering the whole county which always
        includes tours that touch this city.
      * <county-name>-l<id>            -> also county-level; auto-seed
        (some GYG counties don't carry the `judetul-` prefix).
      * romania-l169162                -> country-wide; SKIP.
      * anything else                  -> flagged for review.

  NOT_FOUND
    Not in seeder, listed in the review report for visibility.

Outputs
-------
  AddGetyourguideCityIdsSeeder.php
    Laravel seeder. Runs once, updates marketplace_cities. Idempotent —
    re-running with the same out.csv produces zero new updates.

  review.csv
    Human-eyes summary: every row that wasn't auto-seeded, with the
    reason. Categories:
      - auto       (informational; what made it in)
      - country    (romania-l169162; widget too broad)
      - wrong      (slug clearly unrelated)
      - not_found  (no GYG match)
      - review     (high confidence but suspicious slug)

Usage
-----
  python generate_seeder.py out.csv [--seeder-out PATH] [--review-out PATH]
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
from pathlib import Path


# Romanian counties whose name might appear as a GYG slug without the
# `judetul-` prefix (e.g. Pitești -> /arges-l201945/ is Argeș county).
ROMANIAN_COUNTY_SLUGS = {
    "alba", "arad", "arges", "bacau", "bihor", "bistrita-nasaud",
    "botosani", "brasov", "braila", "buzau", "caras-severin",
    "calarasi", "cluj", "constanta", "covasna", "dambovita", "dolj",
    "galati", "giurgiu", "gorj", "harghita", "hunedoara", "ialomita",
    "iasi", "ilfov", "maramures", "mehedinti", "mures", "neamt", "olt",
    "prahova", "salaj", "satu-mare", "sibiu", "suceava", "teleorman",
    "timis", "tulcea", "vaslui", "valcea", "vrancea",
}

# GYG IDs that we know are too broad to be useful as a "city" widget.
USELESS_GYG_IDS = {
    "169162",  # romania-l169162 — whole-country page
}

# Specific high-confidence matches that look suspicious on review.
# Add the row id here to force them into the review bucket instead of
# auto-seeding. These were flagged by inspection after the initial run.
SUSPECT_HIGH_IDS = {
    "899",  # Slănic Moldova -> slanic-l91935. Two Slănic's in RO (Prahova
            # vs Moldova). Worth a manual check before seeding.
    "838",  # Târgu Frumos -> frumos-l314. "frumos" alone is generic; the
            # match is most likely a different place named "Frumos".
}


SLUG_FROM_URL_RE = re.compile(r"getyourguide\.com/([a-z0-9-]+)-l\d+/?", re.IGNORECASE)


def extract_gyg_slug(url: str) -> str:
    if not url:
        return ""
    m = SLUG_FROM_URL_RE.search(url)
    return m.group(1).lower() if m else ""


def classify(row: dict) -> tuple[str, str]:
    """
    Returns (bucket, reason).

    bucket ∈ {auto, country, wrong, not_found, review}
    """
    status = (row.get("status") or "").strip()
    confidence = (row.get("confidence") or "").strip()
    gyg_id = (row.get("gyg_id") or "").strip()
    gyg_slug = extract_gyg_slug(row.get("gyg_url") or "")

    if status == "not_found" or not gyg_id:
        return "not_found", "no GYG city page found"

    if gyg_id in USELESS_GYG_IDS:
        return "country", f"romania-wide page ({gyg_slug}-l{gyg_id})"

    if confidence == "high":
        if row["id"] in SUSPECT_HIGH_IDS:
            return "review", f"high but suspect slug ({gyg_slug})"
        return "auto", f"high confidence ({gyg_slug})"

    if confidence == "low":
        # county-level pages — useful: widget will show county tours
        # which include tours touching this city
        if gyg_slug.startswith("judetul-"):
            return "auto", f"low -> county page ({gyg_slug})"
        if gyg_slug in ROMANIAN_COUNTY_SLUGS:
            return "auto", f"low -> county page ({gyg_slug})"
        return "wrong", f"low + non-county slug ({gyg_slug})"

    return "review", "unrecognized state"


def build_seeder_php(rows: list[dict]) -> str:
    """Render the Laravel seeder PHP. Rows must be the auto-seed subset."""
    entries: list[str] = []
    for r in rows:
        entries.append(
            f"            ['id' => {int(r['id'])}, 'gyg_id' => "
            f"'{r['gyg_id']}'], // {r['name']} -> {extract_gyg_slug(r['gyg_url'])}"
        )

    body = "\n".join(entries)

    return f"""<?php

namespace Database\\Seeders;

use App\\Models\\MarketplaceCity;
use Illuminate\\Database\\Seeder;

/**
 * Backfill marketplace_cities.getyourguide_city_id for bilete.online's
 * city catalogue. Generated by tools/gyg-city-scraper/generate_seeder.py
 * from out.csv on {{datetime}} — re-run that script if the scrape is
 * refreshed.
 *
 * Idempotent: each row updates by primary key, so running the seeder
 * twice produces no duplicates and no overwrites of any value already
 * matching what's in the script.
 *
 * Coverage of this run: {len(rows)} cities auto-seeded.
 *
 * Rows that DIDN'T make it into this seeder live in
 * tools/gyg-city-scraper/review.csv with a per-row reason:
 *   - country: GYG returned the whole-Romania page (id 169162); widget
 *     would be too broad. SKIPPED — no value in widget.
 *   - wrong:   low-confidence match with a non-county slug (e.g. Bran
 *     hit Tirana). SKIPPED — would surface bad activities.
 *   - review:  high-confidence match but the slug looks suspicious
 *     (e.g. Slănic Moldova -> /slanic-l…/). Verify manually before
 *     adding via tinker.
 *   - not_found: GYG has no page for this city; leave the column NULL,
 *     widget simply doesn't render.
 */
class AddGetyourguideCityIdsSeeder extends Seeder
{{
    public function run(): void
    {{
        $rows = [
{body}
        ];

        foreach ($rows as $row) {{
            MarketplaceCity::where('id', $row['id'])
                ->update(['getyourguide_city_id' => $row['gyg_id']]);
        }}

        $this->command->info(sprintf(
            'Updated GetYourGuide city ids for %d marketplace_cities rows.',
            count($rows)
        ));
    }}
}}
"""


def build_review_csv(rows: list[dict]) -> str:
    """All rows with their bucket + reason — for human review."""
    out = ["id,slug,name,gyg_id,gyg_slug,bucket,reason"]
    for r in rows:
        bucket, reason = classify(r)
        slug = extract_gyg_slug(r.get("gyg_url") or "")
        # CSV-safe: name might contain commas/diacritics
        name = (r.get("name") or "").replace('"', '""')
        out.append(
            f'{r["id"]},{r.get("slug","")},"{name}",{r.get("gyg_id","")},'
            f"{slug},{bucket},{reason}"
        )
    return "\n".join(out) + "\n"


def main(argv=None) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("input", help="Scraper output CSV (out.csv)")
    parser.add_argument(
        "--seeder-out",
        default="AddGetyourguideCityIdsSeeder.php",
        help="Where to write the Laravel seeder",
    )
    parser.add_argument(
        "--review-out",
        default="review.csv",
        help="Where to write the per-row review report",
    )
    args = parser.parse_args(argv)

    input_path = Path(args.input)
    if not input_path.exists():
        print(f"Input not found: {input_path}", file=sys.stderr)
        return 2

    with input_path.open(encoding="utf-8") as f:
        rows = list(csv.DictReader(f))

    buckets: dict[str, list[dict]] = {
        "auto": [], "country": [], "wrong": [], "not_found": [], "review": []
    }
    for r in rows:
        bucket, _ = classify(r)
        buckets[bucket].append(r)

    # Seeder
    from datetime import datetime
    seeder_text = build_seeder_php(buckets["auto"]).replace(
        "{{datetime}}", datetime.now().strftime("%Y-%m-%d %H:%M")
    )
    Path(args.seeder_out).write_text(seeder_text, encoding="utf-8")

    # Review
    Path(args.review_out).write_text(build_review_csv(rows), encoding="utf-8")

    print(f"Seeded:       {len(buckets['auto']):>4}  -> {args.seeder_out}")
    print(f"Review:       {len(buckets['review']):>4}  -> {args.review_out} (high but suspect)")
    print(f"Country page: {len(buckets['country']):>4}  -> skipped")
    print(f"Wrong slug:   {len(buckets['wrong']):>4}  -> skipped")
    print(f"Not found:    {len(buckets['not_found']):>4}  -> skipped")
    print(f"Total rows:   {len(rows):>4}")
    return 0


if __name__ == "__main__":
    sys.exit(main())

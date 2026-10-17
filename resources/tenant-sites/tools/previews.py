"""
Capturile din galeria demo.tics.ro: pagina întreagă a fiecărui demo, 960 px lățime,
în templates/{slug}/assets/preview.jpg. Rulează după ce demo-ul e live, apoi commit.

    pip install playwright pillow          (folosește Chrome-ul instalat)
    python tools/previews.py                       # toate demo-urile din DEMOS
    python tools/previews.py festival https://demo-festival.tics.ro/?ts_shot=1
"""
import io
import sys
from playwright.sync_api import sync_playwright
from PIL import Image

import os
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'templates')
# template => adresa demo-ului live (?ts_shot=1 ascunde bara de cookies și bara de demo)
DEMOS = {'teatru': 'https://demo-teatru.tics.ro/?ts_shot=1', 'competitie': 'https://demo-sport.tics.ro/?ts_shot=1', 'leisure': 'https://demo-parc.tics.ro/?ts_shot=1', 'festival': 'https://demo-festival.tics.ro/?ts_shot=1', 'gala': 'https://demo-mma.tics.ro/?ts_shot=1', 'artist': 'https://demo-artist.tics.ro/?ts_shot=1', 'band': 'https://demo-band.tics.ro/?ts_shot=1'}
if len(sys.argv) > 2:
    DEMOS = {sys.argv[1]: sys.argv[2]}

with sync_playwright() as p:
    browser = p.chromium.launch(channel='chrome', headless=True)
    for slug, url in DEMOS.items():
        ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, device_scale_factor=1, reduced_motion='reduce')
        page = ctx.new_page()
        page.goto(url, wait_until='networkidle', timeout=90000)
        page.evaluate("document.querySelectorAll('img[loading=lazy]').forEach(function(i){i.loading='eager'})")
        page.wait_for_load_state('networkidle')
        page.wait_for_function("Array.from(document.images).every(function(i){return i.complete})", timeout=30000)
        page.wait_for_timeout(2500)
        png = page.screenshot(full_page=True, type='png')
        img = Image.open(io.BytesIO(png)).convert('RGB')
        w, h = img.size
        img = img.crop((0, 0, w, min(h, 5200)))
        img = img.resize((960, int(img.size[1] * 960 / w)), Image.LANCZOS)
        os.makedirs(os.path.join(OUT, slug, 'assets'), exist_ok=True)
        path = os.path.join(OUT, slug, 'assets', 'preview.jpg')
        img.save(path, 'JPEG', quality=78, optimize=True, progressive=True)
        print(slug, (w, h), '->', img.size, path)
        ctx.close()
    browser.close()

"""
Textele marcate pentru traducere care nu au încă intrare în catalog.

    python tools/i18n_missing.py                 # cod comun (shared, app, commerce.js) vs lang/en/*.php
    python tools/i18n_missing.py festival        # + template-ul dat vs templates/festival/lang/en.php

Caută t('…'), te('…'), TS.t('…') și listele foreach ([...]) as $k folosite cu te($k).
Scrie lista pe ecran, câte o cheie pe rând, în formatul de catalog PHP ('cheie' => '',).
"""
import glob
import os
import re
import sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
CALL = re.compile(r"(?:\bTS\.t|\bte|\bt)\(\s*'((?:[^'\\]|\\.)*)'")
LOOP = re.compile(r"foreach \(\[(.*?)\] as \$\w+(?: => \$\w+)?\)", re.S)
STR = re.compile(r"'((?:[^'\\]|\\.)*)'")
CAT = re.compile(r"^\s*'((?:[^'\\]|\\.)*)'\s*=>", re.M)


def files(paths):
    out = []
    for p in paths:
        out += glob.glob(os.path.join(ROOT, p), recursive=True)
    return [f for f in out if os.path.isfile(f)]


def keys_in(paths):
    found = []
    for f in files(paths):
        s = open(f, encoding='utf-8').read()
        for m in CALL.finditer(s):
            k = m.group(1).replace("\\'", "'")
            if k not in found:
                found.append(k)
        for m in LOOP.finditer(s):
            body = m.group(1)
            # doar listele ale căror elemente ajung chiar în te()/t() (nu chei tehnice)
            if not re.search(r"te?\(\$\w+", s[m.end():m.end() + 400]):
                continue
            for k in STR.findall(body):
                k = k.replace("\\'", "'")
                if k not in found and re.search(r'[a-zăâîșț]', k):
                    found.append(k)
    return found


def catalog(paths):
    have = set()
    for f in files(paths):
        have.update(k.replace("\\'", "'") for k in CAT.findall(open(f, encoding='utf-8').read()))
    return have


shared_keys = keys_in(['shared/**/*.php', 'app/*.php', 'public/assets/shared/*.js', 'templates/*/assets/booking.js'])
have = catalog(['lang/en/*.php'])
missing = [k for k in shared_keys if k not in have]
if len(sys.argv) > 1:
    tpl = sys.argv[1]
    tkeys = keys_in([f'templates/{tpl}/**/*.php', f'templates/{tpl}/assets/*.js'])
    have |= catalog([f'templates/{tpl}/lang/en.php'])
    missing += [k for k in tkeys if k not in have and k not in missing]

for k in missing:
    print("    '" + k.replace("'", "\\'") + "' => '',")
print(f'// {len(missing)} lipsă', file=sys.stderr)

# Starter Kit

Framework partajat pentru site-urile **marketplace** și **tenant** ale
platformei Tixello. Un singur kit, două profile. Un template nou = doi
fișieri (`site.config.php` + `theme.css`) + pagini scurte care cheamă
componente. Datele și normalizarea se cablează **o singură dată**, în kit.

> - `docs/STARTER-KIT.md` — ghid complet de operare a FRAMEWORK-ului (arhitectură, rețete, convenții, deploy).
> - `docs/TEMPLATE-AUTHORING.md` — spec pentru sesiunea care **produce template-uri** (ce/cum, plug&play).
> - `docs/TENANT-KINDS.md` — al treilea layer: tipurile de tenant (teatru, filarmonică, agenție, leisure, artist, organizator).
> - `docs/COMPONENTS.md` — catalogul de componente + input-uri.

## Trei layere
`profile` (marketplace | tenant) → `kind` (teatru | filarmonica | agentie |
leisure | artist | organizator) → `site` (config + theme). Kind-ul aduce meniul,
vocabularul, capabilitățile și URL-urile; „10 template-uri per tip” = același
kind, `theme.css` + copy diferite. Vezi `docs/TENANT-KINDS.md`.

## De ce
Skin-urile actuale fuzionează în fiecare fișier: aducerea datelor + markup +
stil. Kit-ul le separă: **date** (adaptoare → view-model canonic) · **markup**
(componente) · **stil** (tokens CSS suprascriși de `theme.css`).

## Start rapid
```bash
cd starter-kit

# preview un exemplu (offline, cu fixtures)
KIT_SITE=teatru KIT_FIXTURES="$(pwd)/fixtures" php -S 127.0.0.1:8899 tools/dev-router.php
#   → http://127.0.0.1:8899/repertoriu   /spectacol/hamlet
KIT_SITE=ambilet KIT_FIXTURES="$(pwd)/fixtures" php -S 127.0.0.1:8898 tools/dev-router.php
#   → http://127.0.0.1:8898/evenimente

# site nou — dă tipul (kind)
php tools/create-template.php teatru opera-cluj "Opera Națională Cluj"
#   editează templates/opera-cluj/{site.config.php, theme.css}
php tools/build.php opera-cluj          # → build/opera-cluj (deployabil)
```

## Structură
```
kit/         framework (core, components, layouts, tokens, js, proxy, deploy)
templates/   site-uri: _starter-*, teatru (tenant), ambilet (marketplace)
tools/       dev-router · build · create-template
fixtures/    răspunsuri API JSON pentru randare offline
docs/        STARTER-KIT.md · COMPONENTS.md
```

## Exemple incluse
- **teatru** (profil `tenant`) — temă dark gold/burgundy — pagini `repertoire`, `show`.
- **ambilet** (profil `marketplace`) — temă light crimson — pagină `events`.

Aceleași componente în ambele; diferă doar `site.config.php` + `theme.css`.

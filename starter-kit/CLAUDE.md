# starter-kit — orientation for Claude

A PHP framework that produces the platform's **marketplace** and **tenant**
storefronts from small, brandable templates. Read this first, then the docs.

## The three layers
```
profile   marketplace | tenant          → auth + API (X-API-Key vs ?tenant=ID)
  kind    teatru | filarmonica | agentie | leisure | artist | organizator
                                          → menu, terminology, features, URLs, page-set
  site    site.config.php + theme.css    → identity (tenant_id, name) + brand
```
Merged at boot: `defaults ← kind ← site` (deep-merge for `terms`/`features`).

## Where things live
- `kit/core/` — engine: `config` (boot + Kit::feature/term), `http` (API + cache
  + fixtures), `data` (kit_events/kit_event/kit_artists/kit_venues → canonical),
  `viewmodel` (canonical shapes + adapters map here), `view` (component/layout/
  seo/head-scripts), `adapters/{tenant,marketplace}`.
- `kit/components/` — 27 pure partials (data in → HTML out). Catalog: `docs/COMPONENTS.md`.
- `kit/pagesets/` — generic, kind-aware full pages the generator instantiates.
- `kit/layouts/` — `public`, `account` (auth-gated).
- `kit/kinds/` — one manifest per tenant kind (terms, features, menu, pages).
- `kit/tokens/tokens.css` — the design-token contract + all `.kit-*` base styles.
- `kit/js/kit.js` (+ vendored `vendor/alpine.min.js`) — client runtime (cart,
  auth, proxy, seat-map holds, checkout, consent, analytics).
- `kit/proxy.php` — allow-listed browser→API gateway.
- `kit/deploy/` — front controller + `.htaccess` (copied into a build).
- `templates/` — `_starter-*` + example sites `teatru`, `ambilet`.
- `tools/` — `create-template.php`, `build.php`, `dev-router.php`, `verify.sh`.
- `fixtures/` — JSON API responses for offline render/preview.
- `docs/` — STARTER-KIT (framework), TEMPLATE-AUTHORING (producing templates),
  TENANT-KINDS (the kind layer + commerce/account flow), COMPONENTS (catalog).

## Golden rules (do not break)
1. **Never hard-code** a colour/font/radius in a component or page — use a
   `--kit-*` token; put base styles in `tokens.css`, brand overrides in `theme.css`.
2. Pages/components **never call the API directly** — use `kit_*` (server) or
   `KitProxy` (client). Never touch a raw API shape; adapters normalize it.
3. No `if (profile)` / `if (kind)` for DATA in pages. Use `kit_feature()` to
   show/hide feature areas and `kit_term()` for nouns.
4. A component **never fetches data** and **never emits `<style>`** (styles → tokens.css).
   Wrap user-facing UI strings in `t('key')` and nouns in `kit_term('key')` —
   never hard-code copy (see `docs/I18N.md`).
5. Watch the docblock trap: `*/` inside a PHP comment closes it early.
6. Dates: use `vm_date()` (avoids timezone drift).
7. Script order matters: `kit_head_scripts()` emits window.KIT → kit.js → Alpine
   (all deferred) so globals exist before Alpine hydrates. Don't reorder.

## Common tasks
- New site:  `php tools/create-template.php <kind> <slug> "Name"` → edit
  `site.config.php` (tenant_id/api_key) + `theme.css` → `php tools/build.php <slug>`.
- New component: `kit/components/<n>.php` + styles in `tokens.css` + document it.
- New pageset / kind / adapter field: see `docs/STARTER-KIT.md §5` and `docs/TENANT-KINDS.md §6`.
- Preview:  `KIT_SITE=<slug> KIT_FIXTURES="$PWD/fixtures" php -S 127.0.0.1:8899 tools/dev-router.php`

## Always verify before committing
```
bash tools/verify.sh
```
Lints all PHP, checks kit.js, renders the examples, and generates + builds every
kind. Green means safe.

## Offline note
This repo can't reach `core.tixello.com`; `fixtures/` back GET requests so pages
render offline. In production set `fixtures => null` in `site.config.php`. The
proxy's assumed request/response contracts are documented in `kit/proxy.php`.

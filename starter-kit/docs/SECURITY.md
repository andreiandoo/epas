# Security model

What the kit does to keep a payment/user flow safe, and what the operator must
still do.

## Built into the kit
- **XSS sanitization.** All "rich text" from the API/CMS (event descriptions,
  blog/CMS bodies) is passed through `kit_html()` — a DOM allowlist that strips
  `script`/`style`/`iframe`/`form`, `on*` handlers and unsafe URL schemes
  (`javascript:` etc.). Everything else is escaped with `e()`. Set
  `trust_api_html => true` only if the content is fully trusted.
- **Proxy is the trust boundary.** The browser never sees the API key; it can
  only call the allow-listed actions in `kit/proxy.php`. On every request the
  proxy enforces:
  - **Method** — the client must use the action's HTTP verb (405 otherwise).
  - **Same-origin (CSRF)** — POST/DELETE must carry an `Origin`/`Referer` whose
    host matches the site (403 otherwise). Blocks classic CSRF.
  - **Rate limiting** — sensitive actions (login, register, checkout,
    review-submit, newsletter, promo, gift-card redeem, …) are capped per IP
    per minute (429 otherwise). Fail-open if the filesystem is unavailable.
  - The Bearer token is forwarded only for account calls; `{slug}`/`{event}` are
    `rawurlencode`d; params go through `http_build_query`.
- **Production-safety build guard.** `tools/build.php` refuses to build a site
  whose `site.config.php` has `fixtures`, `debug`, `trust_api_html`, or a
  placeholder `api_key` (override with `KIT_ALLOW_UNSAFE=1` for a deliberate dev
  build). This stops a dev config from shipping.
- **No source leakage.** `.htaccess` denies `site.config.php`, `routes.php`,
  `/includes/`, and `kit/{core,components,layouts}/`.

## Operator responsibilities (per site)
- **Secrets via env, not committed.** Keep `api_key` (marketplace) and any token
  in the environment — the starters read `getenv(...)`. Never commit a real key
  in `site.config.php`; local overrides go in `site.config.local.php` (gitignored).
- **HTTPS + HSTS** at the web server; the API base is already `https://`.
- **Payments** happen on the backend/PSP; the kit only initiates checkout via
  the proxy and redirects. Never handle card data in the skin.
- **Cookie consent** gates analytics; keep it on where required.
- **Rate limiting at scale** — the file-based limiter is a floor, not a
  replacement for API-side throttling / a WAF.

## Reporting
Security issues in generated sites trace back to either the kit (open an issue)
or the site's own `site.config.php` / custom pages.

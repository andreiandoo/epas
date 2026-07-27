# Deploy

How a generated site reaches its host. The kit mirrors the legacy
`deploy-*.bat` flow: build → force-push to the host's git branch → webhook
pull + opcache reset.

## One command
```bash
KIT_DEPLOY_TOKEN=… bash tools/deploy.sh <site> [branch] [webhook-url]
```
`branch`, remote and `webhook-url` default to the site's `site.config.php`
(`deploy_branch` / `deploy_remote` / `deploy_webhook`). Example:
```bash
KIT_DEPLOY_TOKEN=s3cr3t bash tools/deploy.sh teatru
```
Steps it runs:
1. `php tools/build.php <site>` → `build/<site>/` (**aborts if the config is
   unsafe** — fixtures/debug/placeholder key; see `docs/SECURITY.md`).
2. Clones the host branch (`--orphan` if it doesn't exist yet), wipes it, copies
   the build in as the branch **root**, writes `.deploy-timestamp`.
3. Commits and `git push --force` to that branch.
4. GETs the webhook with `?token=$KIT_DEPLOY_TOKEN`.

## What ships in a build
```
index.php  .htaccess  _webhook-deploy.php  site.config.php  routes.php
includes/  pages/  kit/  theme/{tokens,theme}.css  api/proxy.php
manifest.webmanifest + service-worker.js   (if pwa enabled)
robots.txt  sitemap.xml
```
CSS is minified; `.htaccess` adds gzip + cache headers and denies source files.

## The host side
- **cPanel git deploy** (like ambilet): the host auto-pulls the branch; the
  webhook then only needs to bust opcache. `_webhook-deploy.php` does both a
  `git reset --hard origin/<branch>` and `opcache_reset()`.
- **Webhook auth**: set `KIT_DEPLOY_TOKEN` in the host environment (or a
  `.deploy-token` file next to `_webhook-deploy.php`). The endpoint 403s without
  a matching token.
- Point the subdomain's document root at the branch checkout. `index.php` is the
  front controller; `.htaccess` routes clean URLs to it.

## Integrating with the existing `deploy-*.bat`
Change the `.bat`'s source folder from the hand-written skin to the kit build:
`php tools/build.php <site>` then `xcopy build\<site>\* …` instead of copying
`resources\…\<skin>`. Everything else (branch, webhook) stays the same.

## Per-environment
Set these in `site.config.php` (or pass as args): `deploy_branch`,
`deploy_remote`, `deploy_webhook`. Secrets (`api_key`, `KIT_DEPLOY_TOKEN`) come
from the environment, never committed.

#!/usr/bin/env bash
# Build a site and deploy it to its host's git branch (mirrors the legacy
# deploy-*.bat flow), then trigger the server webhook.
#
#   bash tools/deploy.sh <site> [branch] [webhook-url]
#
# branch / remote / webhook default to the site's site.config.php
# (deploy_branch / deploy_remote / deploy_webhook). The webhook token is read
# from the KIT_DEPLOY_TOKEN environment variable.
#
# The build passes the production-safety guard (no fixtures/debug/etc.).
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"

SITE="${1:-}"
[ -z "$SITE" ] && { echo "usage: bash tools/deploy.sh <site> [branch] [webhook-url]"; exit 1; }
CFG="templates/$SITE/site.config.php"
[ -f "$CFG" ] || { echo "no such site: $CFG"; exit 1; }

# Pull deploy_* defaults from the site config.
read_cfg() { php -r '$c=require $argv[1]; echo $c[$argv[2]] ?? "";' "$ROOT/$CFG" "$1"; }
BRANCH="${2:-$(read_cfg deploy_branch)}"
REMOTE="$(read_cfg deploy_remote)"; REMOTE="${REMOTE:-https://github.com/andreiandoo/epas.git}"
WEBHOOK="${3:-$(read_cfg deploy_webhook)}"
[ -z "$BRANCH" ] && { echo "no deploy branch (arg 2 or deploy_branch in site.config.php)"; exit 1; }

echo "==> Building $SITE"
OUT="$ROOT/build/$SITE"
php tools/build.php "$SITE" "$OUT"          # aborts here if the config is unsafe

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "==> Preparing branch '$BRANCH'"
if git clone --branch "$BRANCH" --single-branch --depth 1 "$REMOTE" "$TMP" 2>/dev/null; then
  find "$TMP" -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +
else
  git clone --depth 1 "$REMOTE" "$TMP"
  ( cd "$TMP" && git checkout --orphan "$BRANCH" && git rm -rf . >/dev/null 2>&1 || true )
fi

echo "==> Copying build → branch root"
cp -a "$OUT/." "$TMP/"
date -u +"%Y-%m-%d %H:%M:%S UTC" > "$TMP/.deploy-timestamp"

echo "==> Commit + force-push"
( cd "$TMP" && git add -A && git -c user.email=deploy@kit -c user.name=kit-deploy \
    commit -m "deploy $SITE $(date -u +%FT%TZ)" >/dev/null && git push -u origin "$BRANCH" --force )

if [ -n "$WEBHOOK" ]; then
  echo "==> Triggering webhook"
  sep='?'; case "$WEBHOOK" in *\?*) sep='&';; esac
  code=$(curl -s -o /dev/null -w '%{http_code}' "${WEBHOOK}${sep}token=${KIT_DEPLOY_TOKEN:-}" || echo "000")
  echo "    webhook HTTP $code"
fi
echo "✅ Deployed $SITE → $BRANCH"

#!/usr/bin/env bash
# Verify the starter-kit: lint all PHP, JS syntax, render the example pages, and
# generate + build every kind. Run from the starter-kit/ directory.
#   bash tools/verify.sh
set -uo pipefail
cd "$(dirname "$0")/.."
export KIT_FIXTURES="$(pwd)/fixtures"
fail=0

echo "== PHP lint =="
while IFS= read -r f; do
  php -l "$f" >/dev/null 2>&1 || { echo "  LINT FAIL: $f"; php -l "$f"; fail=1; }
done < <(find kit templates tools -name '*.php')
echo "  $(find kit templates tools -name '*.php' | wc -l) files"

echo "== JS syntax =="
if command -v node >/dev/null; then node -c kit/js/kit.js && echo "  kit.js OK" || fail=1; else echo "  (node not found, skipped)"; fi

echo "== Render example pages =="
for f in templates/teatru/pages/*.php templates/ambilet/pages/*.php; do
  err=$(php "$f" 2>&1 >/dev/null)
  [ -n "$err" ] && { echo "  RENDER FAIL $f: $err"; fail=1; }
done
echo "  done"

echo "== Generate + build every kind =="
for k in teatru filarmonica agentie leisure artist organizator; do
  php tools/create-template.php "$k" "_verify-$k" "Verify $k" >/dev/null 2>&1
  # render every generated page
  for pg in templates/_verify-$k/pages/*.php; do
    err=$(KIT_SITE="_verify-$k" php "$pg" 2>&1 >/dev/null)
    [ -n "$err" ] && { echo "  PAGE FAIL ${pg}: $err"; fail=1; }
  done
  php tools/build.php "_verify-$k" "/tmp/_verify-build-$k" >/dev/null 2>&1 \
    && echo "  $k: OK ($(ls templates/_verify-$k/pages | wc -l) pages)" \
    || { echo "  $k: BUILD FAIL"; fail=1; }
  rm -rf "templates/_verify-$k" "/tmp/_verify-build-$k"
done

echo ""
[ $fail -eq 0 ] && echo "✅ ALL CHECKS PASSED" || echo "❌ FAILURES ABOVE"
exit $fail

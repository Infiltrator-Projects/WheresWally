#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_ROOT="$PROJECT_ROOT/module/nps_wheres_wally"

echo "[1/5] Validating manifest JSON"
jq -e '.id == "nps_wheres_wally" and .version == "1.1.3" and .author == "Shannon Smith and Carlo Cunanan"' \
    "$MODULE_ROOT/manifest.json" >/dev/null

echo "[2/5] Checking PHP syntax"
while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
done < <(find "$MODULE_ROOT" "$PROJECT_ROOT/tests" -type f -name '*.php' -print0)

echo "[3/5] Checking JavaScript syntax"
node --check "$MODULE_ROOT/assets/js/class.widget.js"

echo "[4/5] Running parser regression tests"
php "$PROJECT_ROOT/tests/NpsEventParserTest.php"

echo "[5/5] Checking installer/build scripts"
while IFS= read -r -d '' file; do
    bash -n "$file"
done < <(find "$PROJECT_ROOT/tools" -type f -name '*.sh' -print0)

echo "All project tests passed."

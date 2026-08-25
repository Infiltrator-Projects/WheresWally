#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_ROOT="$PROJECT_ROOT/module/nps_wheres_wally"

echo "[1/9] Validating manifest JSON"
jq -e '.id == "nps_wheres_wally" and .version == "1.1.4" and .author == "Shannon Smith and Carlo Cunanan"' \
    "$MODULE_ROOT/manifest.json" >/dev/null

echo "[2/9] Checking PHP syntax"
while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
done < <(find "$MODULE_ROOT" "$PROJECT_ROOT/tests" -type f -name '*.php' -print0)

echo "[3/9] Checking JavaScript syntax"
node --check "$MODULE_ROOT/assets/js/class.widget.js"
node --check "$PROJECT_ROOT/tests/WidgetClientTest.js"

echo "[4/9] Running parser regression tests"
php "$PROJECT_ROOT/tests/NpsEventParserTest.php"

echo "[5/9] Running History API query regression tests"
php "$PROJECT_ROOT/tests/HistoryQueryBuilderTest.php"

echo "[6/9] Running receipt-date/timezone regression tests"
php "$PROJECT_ROOT/tests/ReceiptDateRangeTest.php"

echo "[7/9] Running client export/request regression tests"
node "$PROJECT_ROOT/tests/WidgetClientTest.js"

echo "[8/9] Checking installer/build scripts"
while IFS= read -r -d '' file; do
    bash -n "$file"
done < <(find "$PROJECT_ROOT/tools" -type f -name '*.sh' -print0)

echo "[9/9] Building Debian package when dpkg-deb is available"
if command -v dpkg-deb >/dev/null 2>&1; then
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"' EXIT
    "$PROJECT_ROOT/tools/build-deb.sh" "$tmp" >/dev/null
    deb="$tmp/nps-wheres-wally-zabbix_1.1.4_all.deb"
    dpkg-deb --info "$deb" >/dev/null
    dpkg-deb --contents "$deb" | grep -q 'usr/share/zabbix/modules/nps_wheres_wally/manifest.json'
    rm -rf "$tmp"
    trap - EXIT
else
    echo "dpkg-deb not installed; package build check skipped."
fi

echo "All project tests passed."

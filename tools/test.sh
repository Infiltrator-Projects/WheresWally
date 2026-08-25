#!/usr/bin/env bash
# Execute the portable regression and packaging checks for WHERE'S WALLY.
set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly MODULE_ROOT="$PROJECT_ROOT/module/nps_wheres_wally"
readonly MANIFEST="$MODULE_ROOT/manifest.json"
readonly EXPECTED_AUTHOR="Shannon Smith and Carlo Cunanan"

for command in jq php node bash grep; do
    command -v "$command" >/dev/null 2>&1 || {
        printf 'ERROR: required test command not found: %s\n' "$command" >&2
        exit 1
    }
done

readonly VERSION="$(jq -r '.version // empty' "$MANIFEST")"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
    printf 'ERROR: manifest version is missing or is not semantic versioning: %s\n' "$VERSION" >&2
    exit 1
}

printf '[1/11] Validating manifest identity and release version (%s)\n' "$VERSION"
jq -e --arg author "$EXPECTED_AUTHOR" '
    .id == "nps_wheres_wally"
    and .type == "widget"
    and .version != ""
    and .author == $author
' "$MANIFEST" >/dev/null

# Documentation checks deliberately derive the version from the manifest. This
# prevents release packaging from succeeding while the public install commands
# still name an older artifact.
grep -Fq "**Release:** $VERSION" "$PROJECT_ROOT/README.md"
grep -Fq "**Version:** $VERSION" "$MODULE_ROOT/README.md"
grep -Fq "## $VERSION —" "$PROJECT_ROOT/CHANGELOG.md"
grep -Fq "## $VERSION —" "$MODULE_ROOT/CHANGELOG.md"

echo '[2/11] Checking PHP syntax'
while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
done < <(find "$MODULE_ROOT" "$PROJECT_ROOT/tests" -type f -name '*.php' -print0)

echo '[3/11] Checking JavaScript syntax'
node --check "$MODULE_ROOT/assets/js/class.widget.js"
node --check "$PROJECT_ROOT/tests/WidgetClientTest.js"

echo '[4/11] Running parser regression tests'
php "$PROJECT_ROOT/tests/NpsEventParserTest.php"

echo '[5/11] Running History API query regression tests'
php "$PROJECT_ROOT/tests/HistoryQueryBuilderTest.php"

echo '[6/11] Running receipt-date/timezone regression tests'
php "$PROJECT_ROOT/tests/ReceiptDateRangeTest.php"

echo '[7/11] Running client live/hold/export regression tests'
node "$PROJECT_ROOT/tests/WidgetClientTest.js"

echo '[8/11] Checking installer/build script syntax'
while IFS= read -r -d '' file; do
    bash -n "$file"
done < <(find "$PROJECT_ROOT/tools" -type f -name '*.sh' -print0)

echo '[9/11] Building portable installer'
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
"$PROJECT_ROOT/tools/build-installer.sh" "$tmp" >/dev/null
run_file="$tmp/nps-wheres-wally-zabbix-${VERSION}.run"
[[ -x "$run_file" ]]
grep -aFq "# Version: $VERSION" "$run_file"

echo '[10/11] Building and inspecting Debian package'
if command -v dpkg-deb >/dev/null 2>&1; then
    "$PROJECT_ROOT/tools/build-deb.sh" "$tmp" >/dev/null
    deb="$tmp/nps-wheres-wally-zabbix_${VERSION}_all.deb"

    [[ "$(dpkg-deb --field "$deb" Package)" == 'nps-wheres-wally-zabbix' ]]
    [[ "$(dpkg-deb --field "$deb" Version)" == "$VERSION" ]]
    [[ "$(dpkg-deb --field "$deb" Architecture)" == 'all' ]]
    dpkg-deb --field "$deb" Depends | grep -Fq 'zabbix-frontend-php (>= 7.0)'

    extract="$tmp/extracted"
    dpkg-deb -x "$deb" "$extract"
    installed_manifest="$extract/usr/share/zabbix/modules/nps_wheres_wally/manifest.json"
    [[ -f "$installed_manifest" ]]
    [[ "$(jq -r '.version' "$installed_manifest")" == "$VERSION" ]]
else
    echo 'dpkg-deb not installed; Debian package build check skipped.'
fi

echo '[11/11] Checking for release-version drift in build logic'
# The release number belongs in manifest/docs/changelog, not as a hard-coded
# package version inside builders, tests or CI. Builders are allowed to mention
# semantic-version syntax but not the current literal release.
if grep -R -F "$VERSION" "$PROJECT_ROOT/tools" "$PROJECT_ROOT/.github" >/dev/null; then
    printf 'ERROR: current release version is hard-coded in tools or CI.\n' >&2
    grep -R -n -F "$VERSION" "$PROJECT_ROOT/tools" "$PROJECT_ROOT/.github" >&2 || true
    exit 1
fi

rm -rf "$tmp"
trap - EXIT
printf 'All project tests passed for WHERE\x27S WALLY %s.\n' "$VERSION"

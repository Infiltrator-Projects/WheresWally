#!/usr/bin/env bash
# Build the self-extracting WHERE'S WALLY 1.1.5 installer.
set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly MODULE_PARENT="$PROJECT_ROOT/module"
readonly VERSION="1.1.5"
readonly OUTPUT_DIR="${1:-$PROJECT_ROOT/dist}"
readonly OUTPUT_FILE="$OUTPUT_DIR/nps-wheres-wally-zabbix-${VERSION}.run"
readonly PAYLOAD_FILE="$(mktemp)"

cleanup() { rm -f "$PAYLOAD_FILE"; }
trap cleanup EXIT

mkdir -p "$OUTPUT_DIR"
tar -czf "$PAYLOAD_FILE" -C "$MODULE_PARENT" nps_wheres_wally

cat > "$OUTPUT_FILE" <<'INSTALLER'
#!/usr/bin/env bash
#
# WHERE'S WALLY — NPS Event Monitor installer
# Authors: Shannon Smith and Carlo Cunanan
#
# Compatible with Zabbix appliances that use PHP-FPM without PHP CLI.

set -Eeuo pipefail

readonly MODULE_ROOT="${ZABBIX_MODULE_ROOT:-/usr/share/zabbix/modules}"
readonly MODULE_NAME="nps_wheres_wally"
readonly MODULE_VERSION="1.1.5"
readonly MODULE_AUTHOR="Shannon Smith and Carlo Cunanan"
readonly PAYLOAD_MARKER="__NPS_WALLY_PAYLOAD_BELOW__"
readonly SELF="$0"

stage_dir=""
backup_dir=""

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

cleanup() {
    if [[ -n "$stage_dir" && -d "$stage_dir" ]]; then
        rm -rf "$stage_dir"
    fi
}

rollback_on_error() {
    local status=$?

    if (( status != 0 )) && [[ -n "$backup_dir" && -d "$backup_dir" ]] \
            && [[ ! -d "$MODULE_ROOT/$MODULE_NAME" ]]; then
        mv "$backup_dir" "$MODULE_ROOT/$MODULE_NAME" || true
        printf 'Previous module restored after installation failure.\n' >&2
    fi

    cleanup
    exit "$status"
}
trap rollback_on_error ERR INT TERM
trap cleanup EXIT

[[ ${EUID:-$(id -u)} -eq 0 ]] || fail 'Run this installer as root.'
[[ -d "$MODULE_ROOT" ]] || fail "Zabbix module directory not found: $MODULE_ROOT"

for command in awk tail tar find grep mktemp chown chmod mv date; do
    command -v "$command" >/dev/null 2>&1 || fail "Required command not found: $command"
done

payload_line="$(awk -v marker="$PAYLOAD_MARKER" '$0 == marker {print NR + 1; exit}' "$SELF")"
[[ -n "$payload_line" ]] || fail 'Embedded installer payload marker was not found.'

stage_dir="$(mktemp -d "$MODULE_ROOT/.${MODULE_NAME}.install.XXXXXX")"
tail -n +"$payload_line" "$SELF" | tar -xzf - -C "$stage_dir"

candidate="$stage_dir/$MODULE_NAME"
manifest="$candidate/manifest.json"

[[ -d "$candidate" ]] || fail 'Payload does not contain the expected module directory.'
[[ -f "$manifest" ]] || fail 'Payload does not contain manifest.json.'

grep -Eq '^[[:space:]]*"id"[[:space:]]*:[[:space:]]*"nps_wheres_wally"[[:space:]]*,?[[:space:]]*$' "$manifest" \
    || fail 'Invalid manifest id.'
escaped_module_version="${MODULE_VERSION//./\\.}"
grep -Eq "^[[:space:]]*\"version\"[[:space:]]*:[[:space:]]*\"${escaped_module_version}\"[[:space:]]*,?[[:space:]]*$" "$manifest" \
    || fail 'Invalid manifest version.'
grep -Eq '^[[:space:]]*"author"[[:space:]]*:[[:space:]]*"Shannon Smith and Carlo Cunanan"[[:space:]]*,?[[:space:]]*$' "$manifest" \
    || fail 'Invalid manifest author.'

if command -v php >/dev/null 2>&1; then
    while IFS= read -r -d '' php_file; do
        php -l "$php_file" >/dev/null
    done < <(find "$candidate" -type f -name '*.php' -print0)
    printf 'PHP syntax validation: passed.\n'
else
    printf 'PHP syntax validation: skipped (PHP CLI is not installed; PHP-FPM operation is unaffected).\n'
fi

chown -R root:root "$candidate"
find "$candidate" -type d -exec chmod 755 {} + -exec chmod a-s {} +
find "$candidate" -type f -exec chmod 644 {} +

if [[ -d "$MODULE_ROOT/$MODULE_NAME" ]]; then
    backup_dir="$MODULE_ROOT/${MODULE_NAME}.backup.$(date +%Y%m%d-%H%M%S)"
    mv "$MODULE_ROOT/$MODULE_NAME" "$backup_dir"
    printf 'Existing module backed up to: %s\n' "$backup_dir"
fi

mv "$candidate" "$MODULE_ROOT/$MODULE_NAME"
restorecon -RF "$MODULE_ROOT/$MODULE_NAME" >/dev/null 2>&1 || true

printf '\nWHERE\x27S WALLY — NPS Event Monitor %s installed successfully.\n' "$MODULE_VERSION"
printf 'Author: %s\n' "$MODULE_AUTHOR"
printf 'Open Zabbix: Administration -> General -> Modules -> Scan directory\n'
printf 'Confirm the module is enabled, then press Ctrl+F5 in the browser.\n'
exit 0
INSTALLER

printf '%s\n' '__NPS_WALLY_PAYLOAD_BELOW__' >> "$OUTPUT_FILE"
cat "$PAYLOAD_FILE" >> "$OUTPUT_FILE"
chmod 755 "$OUTPUT_FILE"
printf 'Built %s\n' "$OUTPUT_FILE"

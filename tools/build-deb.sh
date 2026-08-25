#!/usr/bin/env bash
# Build a native Debian package for WHERE'S WALLY.
set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly MODULE_SOURCE="$PROJECT_ROOT/module/nps_wheres_wally"
readonly PACKAGE_NAME="nps-wheres-wally-zabbix"
readonly VERSION="1.1.5"
readonly ARCHITECTURE="all"
readonly OUTPUT_DIR="${1:-$PROJECT_ROOT/dist}"
readonly OUTPUT_FILE="$OUTPUT_DIR/${PACKAGE_NAME}_${VERSION}_${ARCHITECTURE}.deb"

command -v dpkg-deb >/dev/null 2>&1 || {
    printf 'ERROR: dpkg-deb is required to build the Debian package.\n' >&2
    exit 1
}

[[ -f "$MODULE_SOURCE/manifest.json" ]] || {
    printf 'ERROR: module source not found: %s\n' "$MODULE_SOURCE" >&2
    exit 1
}

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

package_root="$stage/${PACKAGE_NAME}_${VERSION}_${ARCHITECTURE}"
module_target="$package_root/usr/share/zabbix/modules/nps_wheres_wally"

mkdir -p "$package_root/DEBIAN" "$package_root/usr/share/zabbix/modules"
cp -a "$MODULE_SOURCE" "$module_target"

cat > "$package_root/DEBIAN/control" <<CONTROL
Package: $PACKAGE_NAME
Version: $VERSION
Section: admin
Priority: optional
Architecture: $ARCHITECTURE
Maintainer: Shannon Smith and Carlo Cunanan
Depends: zabbix-frontend-php (>= 7.0)
Homepage: https://github.com/The-First-Infiltrator/WheresWally
Description: Zabbix NPS 6272/6273 authentication event monitor
 WHERE'S WALLY is a Zabbix 7.0 LTS dashboard widget for Microsoft
 Network Policy Server authentication events 6272 and 6273.
CONTROL

cat > "$package_root/DEBIAN/postinst" <<'POSTINST'
#!/bin/sh
set -e

if [ "$1" = "configure" ]; then
    printf '%s\n' "WHERE'S WALLY installed."
    printf '%s\n' "Open Zabbix: Administration -> General -> Modules -> Scan directory"
    printf '%s\n' "Enable the module, then refresh the browser."
fi

exit 0
POSTINST

find "$module_target" -type d -exec chmod 755 {} + -exec chmod a-s {} +
find "$module_target" -type f -exec chmod 644 {} +
chmod 755 "$package_root/DEBIAN/postinst"
find "$package_root" -type d -exec chmod a-s {} +

mkdir -p "$OUTPUT_DIR"
dpkg-deb --root-owner-group --build "$package_root" "$OUTPUT_FILE" >/dev/null

printf 'Built %s\n' "$OUTPUT_FILE"

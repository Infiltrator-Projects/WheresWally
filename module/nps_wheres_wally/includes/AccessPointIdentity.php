<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — access-point identity helpers.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Pure helpers for comparing NPS BSSIDs with Zabbix host inventory.
 *
 * NPS emits a Called-Station-Identifier as a BSSID, while Zabbix inventory can
 * store MAC addresses with hyphens, colons or no separators. Comparisons must
 * therefore be formatting-neutral, but they must never guess across different
 * MAC values: a radio/BSSID that is merely "close" to a chassis MAC is not an
 * identity match.
 */
final class AccessPointIdentity {

    /** Return exactly twelve hexadecimal digits, or an empty string if invalid. */
    public static function normaliseMac(string $value): string {
        $hex = strtoupper((string) preg_replace('/[^0-9A-F]/i', '', trim($value)));

        return preg_match('/^[0-9A-F]{12}$/', $hex) === 1 ? $hex : '';
    }

    /**
     * Return common textual forms used by host inventory searches.
     *
     * @return list<string>
     */
    public static function macVariants(string $value): array {
        $hex = self::normaliseMac($value);

        if ($hex === '') {
            return [];
        }

        $octets = str_split($hex, 2);

        return [
            implode('-', $octets),
            implode(':', $octets),
            $hex
        ];
    }

    /** Return whether either standard Zabbix inventory MAC exactly matches. */
    public static function hostHasMac(array $host, string $mac): bool {
        $needle = self::normaliseMac($mac);

        if ($needle === '') {
            return false;
        }

        $inventory = is_array($host['inventory'] ?? null)
            ? $host['inventory']
            : [];

        foreach (['macaddress_a', 'macaddress_b'] as $field) {
            if (self::normaliseMac((string) ($inventory[$field] ?? '')) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** Return whether the host has any usable inventory MAC for conflict checks. */
    public static function hostHasInventoryMac(array $host): bool {
        $inventory = is_array($host['inventory'] ?? null)
            ? $host['inventory']
            : [];

        return self::normaliseMac((string) ($inventory['macaddress_a'] ?? '')) !== ''
            || self::normaliseMac((string) ($inventory['macaddress_b'] ?? '')) !== '';
    }

    /** Prefer the Zabbix visible name, falling back to the technical host name. */
    public static function displayName(array $host): string {
        $name = trim((string) ($host['name'] ?? ''));

        return $name !== '' ? $name : trim((string) ($host['host'] ?? ''));
    }

    /**
     * Prefer explicit Zabbix inventory location; otherwise use the current host
     * name so the main table still replaces stale NPS friendly-name metadata.
     */
    public static function displayLocation(array $host): string {
        $inventory = is_array($host['inventory'] ?? null)
            ? $host['inventory']
            : [];
        $location = trim((string) ($inventory['location'] ?? ''));

        return $location !== '' ? $location : self::displayName($host);
    }
}

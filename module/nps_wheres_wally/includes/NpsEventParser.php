<?php declare(strict_types = 1);

/**
 * Microsoft Network Policy Server event parser.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Converts the human-readable Windows Security event message produced for NPS
 * events 6272 and 6273 into a stable, presentation-neutral data structure.
 *
 * Architectural rationale
 * -----------------------
 * Zabbix stores a Windows event-log record as a log history value. The value is
 * a formatted message rather than a structured JSON document, so the widget
 * must recover individual fields from labelled lines. Keeping that process in
 * a dedicated class gives the controller one responsibility: acquiring data.
 * It also makes the parsing rules independently testable without a running
 * Zabbix frontend.
 *
 * Scope and assumptions
 * ---------------------
 * - Event 6272 represents granted network access.
 * - Event 6273 represents denied network access.
 * - The parser targets the English labels emitted by Windows Server. Localised
 *   Windows installations require a translated label map or XML-based source.
 * - A missing field is represented by an empty string, never by a fabricated
 *   value. This preserves evidentiary integrity in administrative reporting.
 */
final class NpsEventParser {

    public const EVENT_GRANTED = 6272;
    public const EVENT_DENIED = 6273;

    private const EMPTY_FIELD_MARKER = '-';

    /**
     * Return whether the parser recognises the supplied Windows Event ID.
     */
    public function supportsEventId(int $event_id): bool {
        return $event_id === self::EVENT_GRANTED || $event_id === self::EVENT_DENIED;
    }

    /**
     * Parse one Zabbix log-history entry.
     *
     * @param array<string, mixed> $entry    Zabbix history row.
     * @param int                  $event_id Windows Event ID from logeventid.
     *
     * @return array<string, int|string> Normalised event fields suitable for
     *                                   rendering, filtering and CSV export.
     */
    public function parse(array $entry, int $event_id): array {
        if (!$this->supportsEventId($event_id)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported NPS event ID %d; expected 6272 or 6273.',
                $event_id
            ));
        }

        $message = $this->normaliseLineEndings($this->stringValue($entry, 'value'));

        // The first Account Name/Domain/FQAN group belongs to the User section.
        // Later repetitions belong to the client computer and must not replace
        // the authenticating user identity shown in the primary table.
        $account = $this->extractField($message, 'Account Name');
        $domain = $this->extractField($message, 'Account Domain');
        $qualified_account = $this->extractField($message, 'Fully Qualified Account Name');

        $called_station = $this->extractField($message, 'Called Station Identifier');
        $calling_station = $this->extractField($message, 'Calling Station Identifier');
        $client_name = $this->extractField($message, 'Client Friendly Name');
        $client_ip = $this->extractField($message, 'Client IP Address');
        $nas_ip = $this->extractField($message, 'NAS IPv4 Address');
        $nas_identifier = $this->extractField($message, 'NAS Identifier');

        $reason_code = $this->extractField($message, 'Reason Code');
        $reason = $this->extractField($message, 'Reason');
        $network_policy = $this->extractField($message, 'Network Policy Name');
        $connection_request_policy = $this->extractField($message, 'Connection Request Policy Name');
        $authentication_type = $this->extractField($message, 'Authentication Type');
        $eap_type = $this->extractField($message, 'EAP Type');

        [$access_point, $ssid] = $this->splitCalledStation($called_station);

        $person = $this->personFromQualifiedName($qualified_account, $account);
        $location = $this->firstNonEmpty([$client_name, $nas_identifier, $ssid]);
        $ip_address = $this->firstNonEmpty([$client_ip, $nas_ip]);
        $status = $event_id === self::EVENT_GRANTED ? 'Grant' : 'Deny';
        $status_long = $this->formatStatus($status, $reason_code);
        $event_clock = $this->eventClock($entry);

        $search_text = implode(' ', [
            (string) $event_id,
            $account,
            $domain,
            $person,
            $access_point,
            $ssid,
            $location,
            $calling_station,
            $ip_address,
            $status,
            $reason_code,
            $reason,
            $network_policy,
            $connection_request_policy,
            $authentication_type,
            $eap_type
        ]);

        return [
            'event_id' => $event_id,
            'event_clock' => $event_clock,
            'account' => $account,
            'domain' => $domain,
            'person' => $person,
            'access_point' => $access_point,
            'ssid' => $ssid,
            'location' => $location,
            'device_mac' => $calling_station,
            'ip_address' => $ip_address,
            'status' => $status,
            'status_long' => $status_long,
            'reason_code' => $reason_code,
            'reason' => $reason,
            'network_policy' => $network_policy,
            'connection_request_policy' => $connection_request_policy,
            'authentication_type' => $authentication_type,
            'eap_type' => $eap_type,
            'message' => $message,
            'search' => $this->lowercase($search_text),
            'received_key' => $this->receivedKey($entry)
        ];
    }

    /**
     * Extract the first value associated with an exact event-message label.
     *
     * The expression is anchored to a complete line so a short label cannot
     * accidentally match a longer label. preg_quote() prevents punctuation in
     * future label names from changing the regular-expression semantics.
     */
    private function extractField(string $message, string $label): string {
        $pattern = '/^[\\t ]*'.preg_quote($label, '/').'[\\t ]*:[\\t ]*(.*?)[\\t ]*$/mi';

        if (preg_match($pattern, $message, $matches) !== 1) {
            return '';
        }

        $value = trim((string) ($matches[1] ?? ''));

        return $value === self::EMPTY_FIELD_MARKER ? '' : $value;
    }

    /**
     * Split the Called-Station-Identifier into access-point BSSID and suffix.
     *
     * Microsoft NPS commonly receives values such as:
     *     BC-A9-93-E0-15-D2:St Augustine
     * The suffix is conventionally the SSID. When a vendor sends another
     * format, the full value is retained rather than silently discarded.
     *
     * @return array{0: string, 1: string}
     */
    private function splitCalledStation(string $called_station): array {
        if ($called_station === '') {
            return ['', ''];
        }

        $pattern = '/^([0-9A-F]{2}(?:-[0-9A-F]{2}){5})(?::(.*))?$/i';

        if (preg_match($pattern, $called_station, $matches) === 1) {
            return [
                strtoupper((string) $matches[1]),
                trim((string) ($matches[2] ?? ''))
            ];
        }

        return [$called_station, ''];
    }

    /**
     * Derive the display name from the final distinguished-name component.
     *
     * The FQAN emitted by NPS may use either slash or backslash separators.
     * When no meaningful final component is available, the account identifier
     * is retained as the truthful fallback.
     */
    private function personFromQualifiedName(string $qualified_name, string $fallback): string {
        if ($qualified_name === '') {
            return $fallback;
        }

        $parts = preg_split('~[\\\\/]~', $qualified_name);
        $person = trim((string) end($parts));

        if ($person === '' || strcasecmp($person, $fallback) === 0) {
            return $fallback;
        }

        return $person;
    }

    /**
     * Select the first non-empty value according to an explicit precedence
     * order. This is used where NPS deployments expose equivalent information
     * through different vendor-dependent fields.
     *
     * @param list<string> $values
     */
    private function firstNonEmpty(array $values): string {
        foreach ($values as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Prefer the source event timestamp supplied by the Windows event-log item.
     * Fall back to Zabbix's receipt time when the source timestamp is absent.
     *
     * @param array<string, mixed> $entry
     */
    private function eventClock(array $entry): int {
        $source_timestamp = (int) ($entry['timestamp'] ?? 0);

        return $source_timestamp > 0
            ? $source_timestamp
            : (int) ($entry['clock'] ?? 0);
    }

    /**
     * Construct a lexicographically sortable identity from Zabbix clock and
     * nanosecond values. JavaScript uses this key to implement non-destructive
     * "Clear" behaviour without deleting monitoring history.
     *
     * @param array<string, mixed> $entry
     */
    private function receivedKey(array $entry): string {
        return sprintf(
            '%010d%09d',
            (int) ($entry['clock'] ?? 0),
            (int) ($entry['ns'] ?? 0)
        );
    }

    private function formatStatus(string $status, string $reason_code): string {
        if ($status === 'Deny' && $reason_code !== '') {
            return sprintf('%s (%s)', $status, $reason_code);
        }

        return $status;
    }

    /**
     * Lowercase search text using multibyte support when the PHP extension is
     * available, while retaining compatibility with a minimal PHP CLI used by
     * the standalone test harness.
     */
    private function lowercase(string $value): string {
        return function_exists('mb_strtolower')
            ? \mb_strtolower($value)
            : strtolower($value);
    }

    private function normaliseLineEndings(string $message): string {
        return str_replace(["\r\n", "\r"], "\n", $message);
    }

    /**
     * Read a scalar entry value without generating PHP notices for malformed
     * history rows.
     *
     * @param array<string, mixed> $entry
     */
    private function stringValue(array $entry, string $key): string {
        $value = $entry[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}

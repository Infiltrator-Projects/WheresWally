<?php declare(strict_types = 1);

/**
 * Microsoft Network Policy Server event parser.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Convert Windows NPS Security events 6272/6273 into a stable row model.
 *
 * Why this parser exists
 * ----------------------
 * Zabbix stores a Windows event-log entry as a log-history value containing the
 * human-readable Windows message. The message is not structured JSON, and NPS
 * repeats some labels in different sections. Parsing therefore needs explicit
 * precedence rules rather than an ad-hoc collection of string searches in the
 * controller or view.
 *
 * Integrity rules
 * ---------------
 * - Only event IDs 6272 (Grant) and 6273 (Deny) are accepted.
 * - Missing fields become an empty string; the parser never fabricates values.
 * - The first Account Name/Domain/FQAN occurrence is the authenticating user.
 *   Later repetitions normally describe the client computer and must not
 *   overwrite the user identity shown in the primary table.
 * - Unknown Called-Station-Identifier formats are preserved verbatim instead of
 *   being discarded merely because they do not match the common BSSID:SSID form.
 * - The raw normalised message is retained for Details/evidence inspection.
 *
 * Search is intentionally absent here. Retained-history search is performed by
 * Zabbix's History API before rows reach this parser, so constructing a second
 * parser-side search string would be dead data and could drift from API search
 * semantics.
 */
final class NpsEventParser {

    public const EVENT_GRANTED = 6272;
    public const EVENT_DENIED = 6273;

    /** Windows uses a single hyphen for many unavailable NPS values. */
    private const EMPTY_FIELD_MARKER = '-';

    /** Return whether this parser owns the supplied Windows Security Event ID. */
    public function supportsEventId(int $event_id): bool {
        return $event_id === self::EVENT_GRANTED || $event_id === self::EVENT_DENIED;
    }

    /**
     * Parse one Zabbix log-history row.
     *
     * The returned structure is presentation-neutral. The controller adds
     * formatted display times because date formatting belongs to the active
     * Zabbix frontend timezone, not to the parser.
     *
     * @param array<string, mixed> $entry    Zabbix log-history row.
     * @param int                  $event_id Windows Event ID from `logeventid`.
     *
     * @return array<string, int|string> Normalised event fields for rendering,
     *                                   Details and CSV export.
     */
    public function parse(array $entry, int $event_id): array {
        if (!$this->supportsEventId($event_id)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported NPS event ID %d; expected 6272 or 6273.',
                $event_id
            ));
        }

        $message = $this->normaliseLineEndings($this->stringValue($entry, 'value'));

        // These labels repeat later in a typical NPS message. `extractField()`
        // deliberately returns the first complete-line match, which belongs to
        // the User section and therefore represents the authenticating account.
        $account = $this->extractField($message, 'Account Name');
        $domain = $this->extractField($message, 'Account Domain');
        $qualified_account = $this->extractField($message, 'Fully Qualified Account Name');

        // Network/client identity has deployment-dependent fallbacks. Preserve
        // each raw field first; precedence is applied explicitly below.
        $called_station = $this->extractField($message, 'Called Station Identifier');
        $calling_station = $this->extractField($message, 'Calling Station Identifier');
        $client_name = $this->extractField($message, 'Client Friendly Name');
        $client_ip = $this->extractField($message, 'Client IP Address');
        $nas_ip = $this->extractField($message, 'NAS IPv4 Address');
        $nas_identifier = $this->extractField($message, 'NAS Identifier');

        // Policy/result metadata is retained even when not shown in the compact
        // primary row because it is valuable in the expanded Details panel.
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

        return [
            'event_id' => $event_id,
            'event_clock' => $this->eventClock($entry),
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
            'received_key' => $this->receivedKey($entry)
        ];
    }

    /**
     * Extract the first value associated with an exact event-message label.
     *
     * Anchoring to a complete line prevents short labels from matching text in a
     * longer label. `preg_quote()` also keeps future punctuation in label names
     * from changing the regular-expression meaning.
     */
    private function extractField(string $message, string $label): string {
        $pattern = '/^[\t ]*'.preg_quote($label, '/').'[\t ]*:[\t ]*(.*?)[\t ]*$/mi';

        if (preg_match($pattern, $message, $matches) !== 1) {
            return '';
        }

        $value = trim((string) ($matches[1] ?? ''));

        return $value === self::EMPTY_FIELD_MARKER ? '' : $value;
    }

    /**
     * Split the common Called-Station-Identifier `BSSID:SSID` representation.
     *
     * A vendor may emit another format. In that case the complete source value
     * remains the access-point field and SSID is left blank; silently throwing
     * away an unfamiliar identifier would make troubleshooting harder.
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
     * Derive a friendly person value from NPS's qualified-account representation.
     *
     * NPS commonly supplies `DOMAIN\\account` (and some sources use `/`). If the
     * final component is empty or merely repeats Account Name, the ordinary
     * account value is the truthful and least surprising fallback.
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
     * Return the first populated candidate from an explicit precedence order.
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
     * Prefer the Windows source timestamp and fall back to Zabbix receipt time.
     *
     * The two clocks are intentionally kept distinct: source time answers when
     * Windows says the authentication occurred; receipt time answers when Zabbix
     * stored it and is the clock used by `history.get` date bounds.
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
     * Build a lexicographically sortable receipt identity from clock+nanoseconds.
     *
     * JavaScript uses this fixed-width key only for non-destructive Clear state.
     * It is not a database key and is never written back to Zabbix.
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

    /** Add the NPS reason code to Deny rows without inventing a Grant reason. */
    private function formatStatus(string $status, string $reason_code): string {
        if ($status === 'Deny' && $reason_code !== '') {
            return sprintf('%s (%s)', $status, $reason_code);
        }

        return $status;
    }

    /** Normalise Windows CRLF/CR input once so all later parsing sees LF lines. */
    private function normaliseLineEndings(string $message): string {
        return str_replace(["\r\n", "\r"], "\n", $message);
    }

    /**
     * Read a scalar history value defensively without emitting PHP notices for a
     * malformed or future API row shape.
     *
     * @param array<string, mixed> $entry
     */
    private function stringValue(array $entry, string $key): string {
        $value = $entry[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}

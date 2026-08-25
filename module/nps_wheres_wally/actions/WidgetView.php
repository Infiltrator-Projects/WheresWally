<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — dashboard widget data controller.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\NpsWheresWally\Includes\AccessPointIdentity;
use Modules\NpsWheresWally\Includes\HistoryQueryBuilder;
use Modules\NpsWheresWally\Includes\NpsEventParser;
use Modules\NpsWheresWally\Includes\ReceiptDateRange;
use Modules\NpsWheresWally\Includes\WidgetForm;

/**
 * Acquire NPS log history through Zabbix and prepare a view-safe row model.
 *
 * Responsibility boundary
 * -----------------------
 * This class is deliberately the only module component that talks to Zabbix's
 * runtime API. Parsing, date-boundary calculation, query construction and MAC
 * normalisation live in dependency-light helper classes so their rules can be
 * regression tested without booting a Zabbix frontend.
 *
 * Access-point identity
 * ---------------------
 * NPS Client Friendly Name is configuration metadata, not authoritative current
 * inventory. After parsing, the controller correlates the NPS AP-side BSSID and
 * NAS/client IP with monitored Zabbix hosts. Exact BSSID/MAC inventory matches
 * win; current host-interface IP is the bounded fallback. If no current Zabbix
 * host can be resolved, the main Location cell says so instead of presenting a
 * stale NPS friendly name as fact. The complete original NPS event remains in
 * Details for evidence.
 *
 * Time semantics
 * --------------
 * `history.get` applies `time_from` and `time_till` to Zabbix receipt time
 * (`clock`). The Windows event's source timestamp (`timestamp`) is independent
 * and remains the primary Time value shown to operators. Zabbix switches PHP's
 * default timezone to the active frontend/user timezone during authentication,
 * therefore date-only controls are converted with `date_default_timezone_get()`
 * instead of the browser's timezone.
 */
final class WidgetView extends CControllerDashboardWidgetView {

    /** Canonical item used by automatic discovery when the widget is unconfigured. */
    private const AUTO_ITEM_NAME = 'NPS authentication events 6272 and 6273';

    /** Prefer the school's conventional NPS host if several exact-name items exist. */
    private const PREFERRED_HOST_NAME = 'Server NPS';

    /** Server-side upper bound mirrored by the HTML search control. */
    private const MAXIMUM_SEARCH_LENGTH = 256;

    /**
     * Bound exceptional MAC-inventory searches per refresh. The normal path is
     * one batched interface-IP lookup; MAC searches are only required when IP is
     * unavailable or conflicts with a populated inventory MAC.
     */
    private const MAXIMUM_MAC_FALLBACK_LOOKUPS = 16;

    /**
     * Register only transient request fields. Persistent widget fields are
     * validated by WidgetForm and supplied separately in `$fields_values`.
     */
    protected function init(): void {
        parent::init();

        $this->addValidationRules([
            'search_text' => 'string',
            'date_from' => 'string',
            'date_to' => 'string'
        ]);
    }

    /**
     * Resolve the source item, normalise operator criteria and build the view
     * model for one live snapshot or one explicit retained-history search.
     */
    protected function doAction(): void {
        $item = $this->findSourceItem();
        $rows = [];
        $error = null;
        $row_limit = $this->rowLimit();
        $search_text = $this->searchText();
        $date_range = (new ReceiptDateRange())->parse(
            (string) $this->getInput('date_from', ''),
            (string) $this->getInput('date_to', ''),
            $this->frontendTimezone()
        );
        $query_active = $search_text !== ''
            || $date_range['date_from'] !== ''
            || $date_range['date_to'] !== '';

        if ($item === null) {
            $error = _(
                'NPS log item was not found. Edit the widget and select the item named '
                .'"NPS authentication events 6272 and 6273".'
            );
        }
        else {
            $rows = $this->loadRows(
                (string) $item['itemid'],
                $row_limit,
                $search_text,
                $date_range['time_from'],
                $date_range['time_till']
            );
        }

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'rows' => $rows,
            'error' => $error,
            'item_name' => (string) ($item['name'] ?? ''),
            'query_active' => $query_active,
            'result_limit' => $row_limit,
            'maximum_search_length' => self::MAXIMUM_SEARCH_LENGTH,
            'user' => [
                'debug_mode' => $this->getDebugMode()
            ]
        ]));
    }

    /**
     * Execute one bounded History API request and convert accepted log rows into
     * presentation-neutral NPS events, then enrich AP identity from Zabbix.
     *
     * The query builder already constrains `logeventid` to 6272/6273 before the
     * result limit is applied. The parser check remains intentionally redundant:
     * an unexpected or malformed API row must never leak into the rendered table
     * merely because an upstream assumption changes in a later Zabbix release.
     *
     * @return list<array<string, int|string>>
     */
    private function loadRows(
        string $item_id,
        int $row_limit,
        string $search_text,
        ?int $time_from,
        ?int $time_till
    ): array {
        $options = (new HistoryQueryBuilder())->build(
            ITEM_VALUE_TYPE_LOG,
            $item_id,
            $row_limit,
            $search_text,
            $time_from,
            $time_till
        );

        $history = API::History()->get($options);
        $parser = new NpsEventParser();
        $rows = [];

        foreach ($history as $entry) {
            $event_id = (int) ($entry['logeventid'] ?? 0);

            if (!$parser->supportsEventId($event_id)) {
                continue;
            }

            $row = $parser->parse($entry, $event_id);

            // Render both clocks explicitly. `event_clock` is the Windows event
            // timestamp when present; `clock` is when Zabbix received the row.
            $row['time'] = zbx_date2str(
                DATE_TIME_FORMAT_SECONDS,
                (int) $row['event_clock']
            );
            $row['received_time'] = zbx_date2str(
                DATE_TIME_FORMAT_SECONDS,
                (int) ($entry['clock'] ?? 0)
            );

            $rows[] = $row;
        }

        return $this->enrichAccessPointRows($rows);
    }

    /**
     * Replace NPS's potentially stale friendly-name location with current Zabbix
     * identity. The original event message is untouched and remains available in
     * Details, so enrichment never destroys evidence supplied by NPS.
     *
     * Matching order is deliberately conservative:
     * 1. BSSID equals MAC inventory on the host currently owning the NPS IP;
     * 2. exact BSSID search in MAC inventory when IP is absent/conflicting;
     * 3. exact current Zabbix host-interface IP fallback;
     * 4. unresolved — never guess from a stale Client Friendly Name.
     *
     * @param list<array<string, int|string>> $rows
     * @return list<array<string, int|string>>
     */
    private function enrichAccessPointRows(array $rows): array {
        if ($rows === []) {
            return [];
        }

        $ips = [];
        foreach ($rows as $row) {
            $ip = trim((string) ($row['ip_address'] ?? ''));

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $ips[$ip] = true;
            }
        }

        $hosts_by_ip = $this->hostsByInterfaceIp(array_keys($ips));
        $hosts_by_mac = [];
        $mac_lookups = 0;

        foreach ($rows as &$row) {
            $ip = trim((string) ($row['ip_address'] ?? ''));
            $bssid = AccessPointIdentity::normaliseMac((string) ($row['access_point'] ?? ''));
            $ip_host = $hosts_by_ip[$ip] ?? null;
            $matched_host = null;

            // The strongest inexpensive case is agreement between the event IP
            // and a MAC explicitly stored on that current Zabbix host.
            if ($ip_host !== null && $bssid !== ''
                    && AccessPointIdentity::hostHasMac($ip_host, $bssid)) {
                $matched_host = $ip_host;
            }
            else {
                // Search inventory by MAC only when it adds information: either
                // the event IP no longer resolves, or the resolved host has a
                // populated MAC that conflicts with the event BSSID. This keeps
                // the one-second LIVE path bounded on installations where MAC
                // inventory is not populated.
                $needs_mac_search = $bssid !== '' && (
                    $ip_host === null
                    || AccessPointIdentity::hostHasInventoryMac($ip_host)
                );

                if ($needs_mac_search && !array_key_exists($bssid, $hosts_by_mac)
                        && $mac_lookups < self::MAXIMUM_MAC_FALLBACK_LOOKUPS) {
                    $hosts_by_mac[$bssid] = $this->findHostByInventoryMac($bssid);
                    $mac_lookups++;
                }

                if ($bssid !== '' && ($hosts_by_mac[$bssid] ?? null) !== null) {
                    $matched_host = $hosts_by_mac[$bssid];
                }
                elseif ($ip_host !== null) {
                    $matched_host = $ip_host;
                }
            }

            if ($matched_host !== null) {
                $row['location'] = AccessPointIdentity::displayLocation($matched_host);
            }
            else {
                $row['location'] = _('Not found in Zabbix');
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Batch-resolve current monitored hosts by exact interface IP. `host.get`
     * supports host-interface properties in `filter`, so one request resolves all
     * unique AP/NAS IPs represented in the current result set.
     *
     * @param list<string> $ips
     * @return array<string, array<string, mixed>> keyed by exact interface IP
     */
    private function hostsByInterfaceIp(array $ips): array {
        if ($ips === []) {
            return [];
        }

        $hosts = API::Host()->get([
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectInterfaces' => ['interfaceid', 'ip', 'main', 'type', 'useip'],
            'selectInventory' => ['location', 'macaddress_a', 'macaddress_b'],
            'filter' => [
                'status' => 0,
                'ip' => $ips
            ],
            'sortfield' => 'name'
        ]);

        $wanted = array_fill_keys($ips, true);
        $resolved = [];
        $scores = [];

        foreach ($hosts as $host) {
            foreach ($host['interfaces'] ?? [] as $interface) {
                $ip = trim((string) ($interface['ip'] ?? ''));

                if (!isset($wanted[$ip])) {
                    continue;
                }

                $score = (int) ($interface['main'] ?? 0);
                if (!isset($resolved[$ip]) || $score > ($scores[$ip] ?? -1)) {
                    $resolved[$ip] = $host;
                    $scores[$ip] = $score;
                }
            }
        }

        return $resolved;
    }

    /**
     * Find a current monitored host whose standard inventory MAC exactly equals
     * the NPS BSSID after separator/case normalisation.
     *
     * Zabbix inventory search is textual, so each common representation is tried
     * and every returned candidate is rechecked locally for exact equality. This
     * prevents a partial LIKE match from becoming an identity match.
     *
     * @return array<string, mixed>|null
     */
    private function findHostByInventoryMac(string $bssid): ?array {
        foreach (AccessPointIdentity::macVariants($bssid) as $variant) {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'host', 'name', 'status'],
                'selectInterfaces' => ['interfaceid', 'ip', 'main', 'type', 'useip'],
                'selectInventory' => ['location', 'macaddress_a', 'macaddress_b'],
                'filter' => [
                    'status' => 0
                ],
                'searchInventory' => [
                    'macaddress_a' => $variant,
                    'macaddress_b' => $variant
                ],
                'searchByAny' => true,
                'sortfield' => 'name',
                'limit' => 10
            ]);

            foreach ($hosts as $host) {
                if (AccessPointIdentity::hostHasMac($host, $bssid)) {
                    return $host;
                }
            }
        }

        return null;
    }

    /**
     * Resolve the log item using a deterministic precedence order:
     *
     * 1. an explicitly configured, currently accessible log item;
     * 2. an exact-name item on the preferred `Server NPS` host;
     * 3. the first accessible exact-name match.
     *
     * All lookups remain inside the Zabbix API so normal frontend permissions
     * continue to govern what a user is allowed to see.
     *
     * @return array<string, mixed>|null
     */
    private function findSourceItem(): ?array {
        $configured_item_ids = $this->normaliseItemIds(
            $this->fields_values['itemid'] ?? []
        );

        if ($configured_item_ids !== []) {
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'value_type'],
                'itemids' => $configured_item_ids,
                'webitems' => true,
                'filter' => [
                    'value_type' => ITEM_VALUE_TYPE_LOG
                ],
                'limit' => 1
            ]);

            return $items !== [] ? $items[0] : null;
        }

        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'value_type'],
            'selectHosts' => ['host', 'name'],
            'webitems' => true,
            'filter' => [
                'name' => self::AUTO_ITEM_NAME,
                'value_type' => ITEM_VALUE_TYPE_LOG
            ]
        ]);

        if ($items === []) {
            return null;
        }

        foreach ($items as $candidate) {
            foreach ($candidate['hosts'] ?? [] as $host) {
                if (($host['host'] ?? '') === self::PREFERRED_HOST_NAME
                        || ($host['name'] ?? '') === self::PREFERRED_HOST_NAME) {
                    return $candidate;
                }
            }
        }

        return $items[0];
    }

    /**
     * Zabbix multi-select fields can be represented as a scalar or array across
     * framework paths. Flatten that boundary into a unique list of string IDs.
     *
     * @param mixed $value
     * @return list<string>
     */
    private function normaliseItemIds(mixed $value): array {
        $values = is_array($value) ? $value : [$value];
        $item_ids = [];

        foreach ($values as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                $item_ids[] = (string) $candidate;
            }
        }

        return array_values(array_unique($item_ids));
    }

    /**
     * Trim and cap free text before it reaches the History API.
     *
     * `mb_substr()` is preferred, but Zabbix can operate without mbstring. The
     * Unicode regular-expression fallback avoids cutting a normal UTF-8 search
     * term inside a multibyte sequence; byte `substr()` is the last-resort path
     * only for malformed input that cannot be interpreted as UTF-8.
     */
    private function searchText(): string {
        $search_text = trim((string) $this->getInput('search_text', ''));

        if (function_exists('mb_substr')) {
            return \mb_substr($search_text, 0, self::MAXIMUM_SEARCH_LENGTH);
        }

        if (preg_match(
            '/^.{0,'.self::MAXIMUM_SEARCH_LENGTH.'}/us',
            $search_text,
            $matches
        ) === 1) {
            return (string) $matches[0];
        }

        return substr($search_text, 0, self::MAXIMUM_SEARCH_LENGTH);
    }

    /**
     * Return the timezone Zabbix currently uses for frontend date rendering.
     * Zabbix applies the authenticated user's override via PHP's default timezone
     * and otherwise leaves the configured frontend/system default in effect.
     */
    private function frontendTimezone(): \DateTimeZone {
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Treat the form value as untrusted even though WidgetForm already applies
     * bounds. This keeps the API request bounded if malformed saved data exists.
     */
    private function rowLimit(): int {
        $configured = (int) (
            $this->fields_values['show_lines'] ?? WidgetForm::DEFAULT_ROW_LIMIT
        );

        return max(
            WidgetForm::MINIMUM_ROW_LIMIT,
            min(WidgetForm::MAXIMUM_ROW_LIMIT, $configured)
        );
    }
}

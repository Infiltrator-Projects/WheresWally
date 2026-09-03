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
 * Parsing, date-boundary calculation, query construction and MAC normalisation
 * live in dependency-light helpers. This controller owns only Zabbix API access.
 *
 * AP correlation is intentionally fail-closed. A BSSID or interface IP must
 * resolve to one current monitored host; duplicate host inventory is reported as
 * ambiguous instead of silently choosing whichever host Zabbix returned first.
 * The live path is bounded to one History request, one batched IP lookup and, only
 * when needed, one batched inventory scan for all unresolved BSSIDs in the view.
 */
final class WidgetView extends CControllerDashboardWidgetView {

    private const AUTO_ITEM_NAME = 'NPS authentication events 6272 and 6273';
    private const PREFERRED_HOST_NAME = 'Server NPS';
    private const MAXIMUM_SEARCH_LENGTH = 256;

    protected function init(): void {
        parent::init();

        $this->addValidationRules([
            'search_text' => 'string',
            'date_from' => 'string',
            'date_to' => 'string'
        ]);
    }

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

    /** @return list<array<string, int|string>> */
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
     * Resolve AP identity without either query explosions or silent ambiguity.
     *
     * 1. One exact BSSID match among current hosts on the event IP wins.
     * 2. Otherwise one global inventory scan resolves every requested BSSID.
     * 3. A unique IP host is a fallback only when it has no conflicting MAC.
     * 4. Duplicate IP/MAC identities are labelled ambiguous and never guessed.
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

        $hosts_by_ip = $this->hostsByInterfaceIpCandidates(array_keys($ips));
        $bssids_needing_inventory = [];

        foreach ($rows as $row) {
            $ip = trim((string) ($row['ip_address'] ?? ''));
            $bssid = AccessPointIdentity::normaliseMac((string) ($row['access_point'] ?? ''));
            if ($bssid === '') {
                continue;
            }

            $candidates = $hosts_by_ip[$ip] ?? [];
            $exact = array_values(array_filter(
                $candidates,
                static fn(array $host): bool => AccessPointIdentity::hostHasMac($host, $bssid)
            ));

            if (count($exact) !== 1) {
                $bssids_needing_inventory[$bssid] = true;
            }
        }

        $mac_resolution = $this->uniqueHostsByInventoryMac(array_keys($bssids_needing_inventory));

        foreach ($rows as &$row) {
            $ip = trim((string) ($row['ip_address'] ?? ''));
            $bssid = AccessPointIdentity::normaliseMac((string) ($row['access_point'] ?? ''));
            $candidates = $hosts_by_ip[$ip] ?? [];
            $matched_host = null;
            $ambiguous = count($candidates) > 1;

            if ($bssid !== '') {
                $exact = array_values(array_filter(
                    $candidates,
                    static fn(array $host): bool => AccessPointIdentity::hostHasMac($host, $bssid)
                ));

                if (count($exact) === 1) {
                    $matched_host = $exact[0];
                    $ambiguous = false;
                }
                elseif (count($exact) > 1) {
                    $ambiguous = true;
                }
                else {
                    $resolution = $mac_resolution[$bssid] ?? null;
                    if (is_array($resolution) && isset($resolution['host'])) {
                        $matched_host = $resolution['host'];
                        $ambiguous = false;
                    }
                    elseif ($resolution === false) {
                        $ambiguous = true;
                    }
                }
            }

            if ($matched_host === null && count($candidates) === 1) {
                $candidate = $candidates[0];
                $has_conflicting_inventory = $bssid !== ''
                    && AccessPointIdentity::hostHasInventoryMac($candidate)
                    && !AccessPointIdentity::hostHasMac($candidate, $bssid);

                if (!$has_conflicting_inventory) {
                    $matched_host = $candidate;
                    $ambiguous = false;
                }
            }

            if ($matched_host !== null) {
                $row['location'] = AccessPointIdentity::displayLocation($matched_host);
            }
            elseif ($ambiguous) {
                $row['location'] = _('Ambiguous in Zabbix');
            }
            else {
                $row['location'] = _('Not found in Zabbix');
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Return every current monitored host owning each requested interface IP.
     * Duplicate IPs stay explicit; this method never selects an arbitrary winner.
     *
     * @param list<string> $ips
     * @return array<string, list<array<string, mixed>>>
     */
    private function hostsByInterfaceIpCandidates(array $ips): array {
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
        $seen = [];

        foreach ($hosts as $host) {
            $host_id = (string) ($host['hostid'] ?? '');
            foreach ($host['interfaces'] ?? [] as $interface) {
                $ip = trim((string) ($interface['ip'] ?? ''));
                if (!isset($wanted[$ip])) {
                    continue;
                }

                $identity = $host_id !== ''
                    ? $host_id
                    : AccessPointIdentity::displayName($host);
                if (isset($seen[$ip][$identity])) {
                    continue;
                }

                $resolved[$ip][] = $host;
                $seen[$ip][$identity] = true;
            }
        }

        return $resolved;
    }

    /**
     * Resolve all requested BSSIDs with one monitored-host inventory scan.
     * A MAC found on multiple hosts is represented as false and must not be used.
     *
     * @param list<string> $bssids
     * @return array<string, array{host: array<string, mixed>}|false>
     */
    private function uniqueHostsByInventoryMac(array $bssids): array {
        if ($bssids === []) {
            return [];
        }

        $wanted = array_fill_keys($bssids, true);
        $matches = [];
        $hosts = API::Host()->get([
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectInventory' => ['location', 'macaddress_a', 'macaddress_b'],
            'filter' => ['status' => 0],
            'sortfield' => 'name'
        ]);

        foreach ($hosts as $host) {
            $inventory = is_array($host['inventory'] ?? null) ? $host['inventory'] : [];
            foreach (['macaddress_a', 'macaddress_b'] as $field) {
                $mac = AccessPointIdentity::normaliseMac((string) ($inventory[$field] ?? ''));
                if ($mac === '' || !isset($wanted[$mac])) {
                    continue;
                }

                $host_id = (string) ($host['hostid'] ?? AccessPointIdentity::displayName($host));
                $matches[$mac][$host_id] = $host;
            }
        }

        $resolved = [];
        foreach ($bssids as $bssid) {
            $hosts_for_mac = array_values($matches[$bssid] ?? []);
            if (count($hosts_for_mac) === 1) {
                $resolved[$bssid] = ['host' => $hosts_for_mac[0]];
            }
            elseif (count($hosts_for_mac) > 1) {
                $resolved[$bssid] = false;
            }
        }

        return $resolved;
    }

    /** @return array<string, mixed>|null */
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

    /** @return list<string> */
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

    private function frontendTimezone(): \DateTimeZone {
        return new \DateTimeZone(date_default_timezone_get());
    }

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

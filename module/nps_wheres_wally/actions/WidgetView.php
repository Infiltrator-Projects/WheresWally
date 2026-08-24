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
use Modules\NpsWheresWally\Includes\NpsEventParser;
use Modules\NpsWheresWally\Includes\WidgetForm;

/**
 * Acquires NPS log history from the Zabbix API and prepares it for rendering.
 *
 * Normal dashboard operation retrieves only the newest bounded set. Historical
 * searching is also server-side: the browser supplies search/date criteria and
 * the Zabbix History API applies them to retained history before returning at
 * most the configured number of rows.
 */
final class WidgetView extends CControllerDashboardWidgetView {

    private const AUTO_ITEM_NAME = 'NPS authentication events 6272 and 6273';
    private const PREFERRED_HOST_NAME = 'Server NPS';
    private const MINIMUM_ROW_LIMIT = 10;
    private const MAXIMUM_ROW_LIMIT = 200;
    private const MAXIMUM_SEARCH_LENGTH = 256;

    /**
     * Register transient request fields used by the live search controls.
     */
    protected function init(): void {
        parent::init();

        $this->addValidationRules([
            'search_text' => 'string',
            'time_from' => 'int32',
            'time_till' => 'int32'
        ]);
    }

    /**
     * Build the response consumed by views/widget.view.php.
     */
    protected function doAction(): void {
        $item = $this->findSourceItem();
        $rows = [];
        $error = null;
        $search_text = $this->searchText();
        [$time_from, $time_till] = $this->timeRange();

        if ($item === null) {
            $error = _(
                'NPS log item was not found. Edit the widget and select the item named '
                .'"NPS authentication events 6272 and 6273".'
            );
        }
        else {
            $rows = $this->loadRows(
                (string) $item['itemid'],
                $this->rowLimit(),
                $search_text,
                $time_from,
                $time_till
            );
        }

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'rows' => $rows,
            'error' => $error,
            'item_name' => (string) ($item['name'] ?? ''),
            'query_active' => $search_text !== '' || $time_from !== null || $time_till !== null,
            'result_limit' => $this->rowLimit(),
            'user' => [
                'debug_mode' => $this->getDebugMode()
            ]
        ]));
    }

    /**
     * Retrieve, parse and format supported NPS event-log records.
     *
     * Search and date constraints are applied inside history.get before rows are
     * returned to PHP. On the standard SQL history backend this is translated by
     * Zabbix into a database query against the appropriate history table. This
     * keeps Zabbix permission and storage abstraction intact while avoiding a
     * browser-side scan of every retained record.
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
        $options = [
            'output' => [
                'itemid',
                'clock',
                'ns',
                'timestamp',
                'source',
                'severity',
                'logeventid',
                'value'
            ],
            'itemids' => [$item_id],
            'history' => ITEM_VALUE_TYPE_LOG,
            'sortfield' => ['clock', 'ns'],
            'sortorder' => ZBX_SORT_DOWN,
            'limit' => $row_limit
        ];

        if ($time_from !== null) {
            $options['time_from'] = $time_from;
        }

        if ($time_till !== null) {
            $options['time_till'] = $time_till;
        }

        if ($search_text !== '') {
            $event_id = $this->eventIdSearch($search_text);

            if ($event_id !== null) {
                $options['filter'] = ['logeventid' => $event_id];
            }
            else {
                $options['search'] = ['value' => $search_text];
            }
        }

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

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Resolve friendly result/event searches to exact NPS Event IDs.
     */
    private function eventIdSearch(string $search_text): ?int {
        return match (strtolower($search_text)) {
            '6272', 'grant', 'granted' => 6272,
            '6273', 'deny', 'denied' => 6273,
            default => null
        };
    }

    /**
     * Resolve the source log item.
     *
     * Resolution order:
     * 1. Explicit dashboard-widget selection.
     * 2. Exact canonical item name on the preferred host.
     * 3. Exact canonical item name on any accessible host.
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
     * Accept scalar-or-array item IDs from different widget serialisation paths.
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
     * Read and bound the transient server-side search string.
     */
    private function searchText(): string {
        $search_text = trim((string) $this->getInput('search_text', ''));

        return substr($search_text, 0, self::MAXIMUM_SEARCH_LENGTH);
    }

    /**
     * Return optional request timestamps, normalising an accidentally reversed
     * range defensively. The browser normally prevents a reversed range.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function timeRange(): array {
        $time_from = $this->hasInput('time_from')
            ? max(0, (int) $this->getInput('time_from'))
            : null;
        $time_till = $this->hasInput('time_till')
            ? max(0, (int) $this->getInput('time_till'))
            : null;

        if ($time_from !== null && $time_till !== null && $time_from > $time_till) {
            [$time_from, $time_till] = [$time_till, $time_from];
        }

        return [$time_from, $time_till];
    }

    /**
     * Bound every response even when a stored dashboard definition was manually
     * altered. Search scope is unbounded within retained history; response size
     * is deliberately bounded to protect PHP, the browser and dashboard refresh.
     */
    private function rowLimit(): int {
        $configured = (int) (
            $this->fields_values['show_lines'] ?? WidgetForm::DEFAULT_ROW_LIMIT
        );

        return max(
            self::MINIMUM_ROW_LIMIT,
            min(self::MAXIMUM_ROW_LIMIT, $configured)
        );
    }
}

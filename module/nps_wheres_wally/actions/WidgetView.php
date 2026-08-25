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
use Modules\NpsWheresWally\Includes\HistoryQueryBuilder;
use Modules\NpsWheresWally\Includes\NpsEventParser;
use Modules\NpsWheresWally\Includes\ReceiptDateRange;
use Modules\NpsWheresWally\Includes\WidgetForm;

/**
 * Acquires NPS log history from the Zabbix API and prepares it for rendering.
 *
 * Date criteria deliberately apply to Zabbix receipt time because that is the
 * clock supported by history.get time_from/time_till. The Windows event source
 * timestamp is still preserved and displayed as the event time.
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
        $search_text = $this->searchText();
        $date_range = (new ReceiptDateRange())->parse(
            (string) $this->getInput('date_from', ''),
            (string) $this->getInput('date_to', ''),
            new \DateTimeZone(date_default_timezone_get())
        );

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
                $date_range['time_from'],
                $date_range['time_till']
            );
        }

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'rows' => $rows,
            'error' => $error,
            'item_name' => (string) ($item['name'] ?? ''),
            'query_active' => $search_text !== ''
                || $date_range['date_from'] !== ''
                || $date_range['date_to'] !== '',
            'result_limit' => $this->rowLimit(),
            'user' => [
                'debug_mode' => $this->getDebugMode()
            ]
        ]));
    }

    /**
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

            // The API query already filters to 6272/6273. Keep this defensive
            // check so malformed or unexpected API rows can never enter output.
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

        return $rows;
    }

    /**
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

    private function searchText(): string {
        $search_text = trim((string) $this->getInput('search_text', ''));

        return function_exists('mb_substr')
            ? \mb_substr($search_text, 0, self::MAXIMUM_SEARCH_LENGTH)
            : substr($search_text, 0, self::MAXIMUM_SEARCH_LENGTH);
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

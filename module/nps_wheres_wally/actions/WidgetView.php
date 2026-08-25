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
 * Acquire NPS log history through Zabbix and prepare a view-safe row model.
 *
 * Responsibility boundary
 * -----------------------
 * This class is deliberately the only module component that talks to Zabbix's
 * runtime API. Parsing, date-boundary calculation and query construction live
 * in dependency-light helper classes so their rules can be regression tested
 * without booting a Zabbix frontend.
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
     * presentation-neutral NPS events.
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

        return $rows;
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

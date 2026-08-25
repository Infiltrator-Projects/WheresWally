<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — Zabbix History API query builder.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Builds the bounded History API request used by the widget.
 *
 * This class deliberately contains no Zabbix runtime dependencies, which keeps
 * the query semantics independently testable. The caller supplies the Zabbix
 * history type constant.
 */
final class HistoryQueryBuilder {

    public const EVENT_GRANTED = 6272;
    public const EVENT_DENIED = 6273;

    /**
     * @return array<string, mixed>
     */
    public function build(
        int $history_type,
        string $item_id,
        int $row_limit,
        string $search_text,
        ?int $time_from,
        ?int $time_till
    ): array {
        $event_id = $this->eventIdSearch($search_text);

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
            'history' => $history_type,
            'filter' => [
                'logeventid' => $event_id ?? [
                    self::EVENT_GRANTED,
                    self::EVENT_DENIED
                ]
            ],
            'sortfield' => ['clock', 'ns'],
            'sortorder' => 'DESC',
            'limit' => $row_limit
        ];

        if ($time_from !== null) {
            $options['time_from'] = $time_from;
        }

        if ($time_till !== null) {
            $options['time_till'] = $time_till;
        }

        if ($search_text !== '' && $event_id === null) {
            $options['search'] = ['value' => $search_text];
        }

        return $options;
    }

    public function eventIdSearch(string $search_text): ?int {
        return match (strtolower(trim($search_text))) {
            '6272', 'grant', 'granted' => self::EVENT_GRANTED,
            '6273', 'deny', 'denied' => self::EVENT_DENIED,
            default => null
        };
    }
}

<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — Zabbix History API query builder.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Build the bounded `history.get` request used by live and historical modes.
 *
 * Design invariants
 * -----------------
 * - Event IDs 6272/6273 are always filtered server-side before `limit` applies.
 * - Exact Grant/Deny shortcuts become indexed equality filters rather than text
 *   scans over the retained message body.
 * - Free text is delegated to Zabbix's case-insensitive History API search so
 *   the browser never downloads an unbounded history set and filters it locally.
 * - Receipt-time boundaries are passed through unchanged; timezone conversion is
 *   a separate concern owned by ReceiptDateRange.
 *
 * The class intentionally contains no Zabbix runtime references. The controller
 * supplies the numeric history type constant, which keeps query construction
 * independently executable in the test harness.
 */
final class HistoryQueryBuilder {

    public const EVENT_GRANTED = 6272;
    public const EVENT_DENIED = 6273;

    /**
     * @return array<string, mixed> Options accepted by Zabbix `history.get`.
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

        // Zabbix implements ordinary History API text search as a
        // case-insensitive LIKE-style search. Exact Grant/Deny terms bypass it
        // above because filtering on logeventid is both clearer and cheaper.
        if ($search_text !== '' && $event_id === null) {
            $options['search'] = ['value' => $search_text];
        }

        return $options;
    }

    /**
     * Convert the small operator vocabulary that means "event type" into an
     * exact Windows Event ID. All other text remains a raw-message search.
     */
    public function eventIdSearch(string $search_text): ?int {
        return match (strtolower(trim($search_text))) {
            '6272', 'grant', 'granted' => self::EVENT_GRANTED,
            '6273', 'deny', 'denied' => self::EVENT_DENIED,
            default => null
        };
    }
}

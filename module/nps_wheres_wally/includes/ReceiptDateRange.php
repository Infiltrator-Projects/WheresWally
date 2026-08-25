<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — receipt-date range normalisation.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Converts YYYY-MM-DD controls into inclusive Zabbix receipt-time boundaries.
 *
 * The timezone is supplied by the caller so tests can verify DST behaviour and
 * the production controller can use Zabbix's active frontend timezone.
 */
final class ReceiptDateRange {

    /**
     * @return array{date_from: string, date_to: string, time_from: ?int, time_till: ?int}
     */
    public function parse(string $date_from, string $date_to, \DateTimeZone $timezone): array {
        $date_from = $this->normaliseDate($date_from, $timezone);
        $date_to = $this->normaliseDate($date_to, $timezone);

        if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
            [$date_from, $date_to] = [$date_to, $date_from];
        }

        return [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'time_from' => $this->boundary($date_from, false, $timezone),
            'time_till' => $this->boundary($date_to, true, $timezone)
        ];
    }

    private function normaliseDate(string $value, \DateTimeZone $timezone): string {
        $value = trim($value);

        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false
                || ($errors !== false
                    && ((int) $errors['warning_count'] !== 0 || (int) $errors['error_count'] !== 0))
                || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private function boundary(string $value, bool $end_of_day, \DateTimeZone $timezone): ?int {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        if ($date === false) {
            return null;
        }

        if ($end_of_day) {
            $date = $date->modify('+1 day')->modify('-1 second');
        }

        return $date->getTimestamp();
    }
}

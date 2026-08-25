<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — receipt-date range normalisation.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

/**
 * Convert date-only controls into inclusive Zabbix receipt-time boundaries.
 *
 * Why this helper exists
 * ----------------------
 * HTML date inputs contain calendar dates but no timezone. Zabbix `history.get`
 * accepts Unix timestamps and interprets them against the receipt clock. The
 * caller therefore supplies the active frontend timezone and this class owns all
 * calendar-to-epoch conversion, including daylight-saving transitions.
 *
 * End-of-day is calculated as "next local midnight minus one second" rather than
 * by adding 86,399 seconds. That distinction is essential on 23-hour and 25-hour
 * DST transition days.
 */
final class ReceiptDateRange {

    /**
     * Invalid or blank controls are normalised to an empty string/null boundary.
     * If both dates are valid but reversed, they are swapped for operator-friendly
     * behaviour instead of silently returning an empty result set.
     *
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

    /**
     * Accept only a real Gregorian date in the browser's canonical YYYY-MM-DD
     * form. `createFromFormat()` alone can normalise impossible dates, so warning
     * counts and a round-trip format comparison are both required.
     */
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

    /** Return the first or last receipt second belonging to one local date. */
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

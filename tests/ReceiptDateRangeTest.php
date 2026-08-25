<?php declare(strict_types = 1);

use Modules\NpsWheresWally\Includes\ReceiptDateRange;

require_once __DIR__.'/../module/nps_wheres_wally/includes/ReceiptDateRange.php';

function sameDate(string $label, mixed $actual, mixed $expected): void {
    if ($actual !== $expected) {
        throw new RuntimeException($label."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true));
    }
}

$parser = new ReceiptDateRange();
$melbourne = new DateTimeZone('Australia/Melbourne');

$normal = $parser->parse('2026-08-25', '2026-08-25', $melbourne);
sameDate('Date remains normalised.', $normal['date_from'], '2026-08-25');
sameDate('Single normal day is inclusive to 23:59:59.', $normal['time_till'] - $normal['time_from'], 86399);

$reversed = $parser->parse('2026-08-26', '2026-08-24', $melbourne);
sameDate('Reversed lower date is swapped.', $reversed['date_from'], '2026-08-24');
sameDate('Reversed upper date is swapped.', $reversed['date_to'], '2026-08-26');

$invalid = $parser->parse('2026-02-30', 'garbage', $melbourne);
sameDate('Invalid from date is rejected.', $invalid['date_from'], '');
sameDate('Invalid to date is rejected.', $invalid['date_to'], '');
sameDate('Invalid from boundary is null.', $invalid['time_from'], null);
sameDate('Invalid to boundary is null.', $invalid['time_till'], null);

// Melbourne enters daylight saving on 4 October 2026. Using DateTime modification
// rather than adding a fixed 86400 seconds must therefore produce a 23-hour day.
$dst = $parser->parse('2026-10-04', '2026-10-04', $melbourne);
sameDate('DST-start day uses timezone-aware 23-hour boundary.', $dst['time_till'] - $dst['time_from'], 82799);

fwrite(STDOUT, "ReceiptDateRangeTest: all assertions passed.\n");

<?php declare(strict_types = 1);

/**
 * Lightweight regression tests for NpsEventParser.
 *
 * The test deliberately avoids PHPUnit so it can run on a standard Zabbix
 * appliance or development workstation with only the PHP CLI installed.
 */

use Modules\NpsWheresWally\Includes\NpsEventParser;

require_once __DIR__.'/../module/nps_wheres_wally/includes/NpsEventParser.php';

final class TestFailure extends RuntimeException {}

/** @param mixed $actual @param mixed $expected */
function assertSameValue(string $label, mixed $actual, mixed $expected): void {
    if ($actual !== $expected) {
        throw new TestFailure(sprintf(
            "%s\nExpected: %s\nActual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrueValue(string $label, bool $actual): void {
    if (!$actual) {
        throw new TestFailure($label);
    }
}

function fixture(string $name): string {
    $contents = file_get_contents(__DIR__.'/fixtures/'.$name);

    if ($contents === false) {
        throw new RuntimeException('Unable to read fixture '.$name);
    }

    return $contents;
}

$parser = new NpsEventParser();

assertTrueValue('6272 must be supported.', $parser->supportsEventId(6272));
assertTrueValue('6273 must be supported.', $parser->supportsEventId(6273));
assertSameValue('Unrelated event IDs must be rejected.', $parser->supportsEventId(4624), false);

$grant = $parser->parse([
    'value' => fixture('event-6272.txt'),
    'timestamp' => 1785459567,
    'clock' => 1785459572,
    'ns' => 42
], 6272);

assertSameValue('Grant account uses first User occurrence.', $grant['account'], '3762');
assertSameValue('Grant domain parsed.', $grant['domain'], 'STAK');
assertSameValue('Display name derived from FQAN.', $grant['person'], 'Charli Hart');
assertSameValue('Access point BSSID split.', $grant['access_point'], 'BC-A9-93-E0-15-D2');
assertSameValue('SSID suffix split.', $grant['ssid'], 'St Augustine');
assertSameValue('Client friendly name has location precedence.', $grant['location'], 'Old-Staffroom');
assertSameValue('Calling station becomes device MAC.', $grant['device_mac'], '8C-1D-55-10-10-D2');
assertSameValue('Client IP has precedence.', $grant['ip_address'], '10.129.29.211');
assertSameValue('6272 status.', $grant['status'], 'Grant');
assertSameValue('6272 long status.', $grant['status_long'], 'Grant');
assertSameValue('Source timestamp has precedence.', $grant['event_clock'], 1785459567);
assertSameValue('Receipt key is fixed width.', $grant['received_key'], '1785459572000000042');
assertSameValue('Connection policy parsed.', $grant['connection_request_policy'], 'Secure Wireless');
assertTrueValue('Search text includes person in lower case.', str_contains($grant['search'], 'charli hart'));

$deny = $parser->parse([
    'value' => fixture('event-6273.txt'),
    'timestamp' => 0,
    'clock' => 1785459600,
    'ns' => 900000001
], 6273);

assertSameValue('Deny account parsed.', $deny['account'], '3076');
assertSameValue('Deny person parsed.', $deny['person'], 'Toby Mckiernan');
assertSameValue('Deny location parsed.', $deny['location'], 'staffroomnew-Office');
assertSameValue('NAS IP used when Client IP is empty marker.', $deny['ip_address'], '10.129.29.199');
assertSameValue('Deny status.', $deny['status'], 'Deny');
assertSameValue('Reason code appended to long status.', $deny['status_long'], 'Deny (16)');
assertSameValue('Reason preserved.', $deny['reason'], 'Authentication failed because of a user credentials mismatch.');
assertSameValue('Receipt time used when source timestamp absent.', $deny['event_clock'], 1785459600);

$unknown_format = $parser->parse([
    'value' => "Account Name: test\nAccount Domain: LAB\nCalled Station Identifier: vendor-specific-value\n",
    'clock' => 10,
    'ns' => 2
], 6272);
assertSameValue('Unknown called-station format is preserved.', $unknown_format['access_point'], 'vendor-specific-value');
assertSameValue('Unknown called-station format has no fabricated SSID.', $unknown_format['ssid'], '');
assertSameValue('Account is display-name fallback.', $unknown_format['person'], 'test');

$exception_seen = false;
try {
    $parser->parse(['value' => ''], 4624);
}
catch (InvalidArgumentException) {
    $exception_seen = true;
}
assertTrueValue('Unsupported event ID must raise an exception.', $exception_seen);

fwrite(STDOUT, "NpsEventParserTest: all assertions passed.\n");

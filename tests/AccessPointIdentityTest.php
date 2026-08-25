<?php declare(strict_types = 1);

require_once __DIR__.'/../module/nps_wheres_wally/includes/AccessPointIdentity.php';

use Modules\NpsWheresWally\Includes\AccessPointIdentity;

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
        exit(1);
    }
}

assertSameValue('BCA9930E96C4', AccessPointIdentity::normaliseMac('BC-A9-93-0E-96-C4'), 'hyphenated MAC normalises');
assertSameValue('BCA9930E96C4', AccessPointIdentity::normaliseMac('bc:a9:93:0e:96:c4'), 'colon MAC normalises case-insensitively');
assertSameValue('', AccessPointIdentity::normaliseMac('not-a-mac'), 'invalid MAC is rejected');
assertSameValue(
    ['BC-A9-93-0E-96-C4', 'BC:A9:93:0E:96:C4', 'BCA9930E96C4'],
    AccessPointIdentity::macVariants('bc-a9-93-0e-96-c4'),
    'search variants are deterministic'
);

$host = [
    'host' => 'ap-shared-area-c2',
    'name' => 'AP-Shared-Area-C2',
    'inventory' => [
        'location' => 'Shared Area C2',
        'macaddress_a' => 'BC:A9:93:0E:96:C4',
        'macaddress_b' => ''
    ]
];

assertSameValue(true, AccessPointIdentity::hostHasMac($host, 'BC-A9-93-0E-96-C4'), 'inventory MAC matches BSSID independent of separators');
assertSameValue(true, AccessPointIdentity::hostHasInventoryMac($host), 'host reports usable inventory MAC');
assertSameValue('AP-Shared-Area-C2', AccessPointIdentity::displayName($host), 'visible Zabbix name is preferred');
assertSameValue('Shared Area C2', AccessPointIdentity::displayLocation($host), 'inventory location is preferred');

$host['inventory']['location'] = '';
assertSameValue('AP-Shared-Area-C2', AccessPointIdentity::displayLocation($host), 'host name backs empty inventory location');

fwrite(STDOUT, "Access-point identity tests passed.\n");

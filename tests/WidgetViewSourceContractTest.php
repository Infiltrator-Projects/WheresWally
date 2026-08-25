<?php declare(strict_types = 1);

require_once __DIR__.'/../module/nps_wheres_wally/includes/AccessPointIdentity.php';

use Modules\NpsWheresWally\Includes\AccessPointIdentity;

$source_path = __DIR__.'/../module/nps_wheres_wally/actions/WidgetView.php';
$source = file_get_contents($source_path);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read WidgetView.php\n");
    exit(1);
}

/*
 * WidgetView depends on Zabbix frontend classes and cannot be instantiated in
 * this portable unit-test process. Static source-contract checks therefore
 * validate the two symbol classes that PHP's syntax checker cannot catch:
 * helper-method references and private self constants.
 */
preg_match_all('/AccessPointIdentity::([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $method_matches);
$available_methods = get_class_methods(AccessPointIdentity::class);

foreach (array_unique($method_matches[1] ?? []) as $method) {
    if (!in_array($method, $available_methods, true)) {
        fwrite(STDERR, "FAIL: WidgetView references undefined AccessPointIdentity::{$method}()\n");
        exit(1);
    }
}

preg_match_all('/\b(?:private|protected|public)\s+const\s+([A-Z][A-Z0-9_]*)\b/', $source, $declared_matches);
preg_match_all('/\bself::([A-Z][A-Z0-9_]*)\b/', $source, $used_matches);
$declared = array_unique($declared_matches[1] ?? []);

foreach (array_unique($used_matches[1] ?? []) as $constant) {
    if (!in_array($constant, $declared, true)) {
        fwrite(STDERR, "FAIL: WidgetView references undefined self::{$constant}\n");
        exit(1);
    }
}

fwrite(STDOUT, "WidgetView source-contract tests passed.\n");

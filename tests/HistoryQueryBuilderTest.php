<?php declare(strict_types = 1);

use Modules\NpsWheresWally\Includes\HistoryQueryBuilder;

require_once __DIR__.'/../module/nps_wheres_wally/includes/HistoryQueryBuilder.php';

function same(string $label, mixed $actual, mixed $expected): void {
    if ($actual !== $expected) {
        throw new RuntimeException($label."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true));
    }
}

$builder = new HistoryQueryBuilder();

$live = $builder->build(2, '85172', 200, '', null, null);
same('Live query must filter to both NPS event IDs.', $live['filter']['logeventid'], [6272, 6273]);
same('Live query limit.', $live['limit'], 200);
same('Live query must not contain a text search.', array_key_exists('search', $live), false);

$grant = $builder->build(2, '85172', 100, 'grant', 1000, 2000);
same('Grant shortcut becomes exact event filter.', $grant['filter']['logeventid'], 6272);
same('Grant shortcut must not use LIKE search.', array_key_exists('search', $grant), false);
same('From bound passed through.', $grant['time_from'], 1000);
same('Till bound passed through.', $grant['time_till'], 2000);

$deny = $builder->build(2, '85172', 100, '6273', null, null);
same('6273 shortcut becomes exact event filter.', $deny['filter']['logeventid'], 6273);

$text = $builder->build(2, '85172', 50, 'Charli Hart', null, null);
same('Text query still filters to NPS event IDs.', $text['filter']['logeventid'], [6272, 6273]);
same('Text query targets retained log value.', $text['search'], ['value' => 'Charli Hart']);

fwrite(STDOUT, "HistoryQueryBuilderTest: all assertions passed.\n");

<?php

declare(strict_types=1);

/**
 * Fails when the tests cover less than a minimum share of the lines of src/.
 *
 * PHPUnit has no line threshold of its own: this reads the project's metrics in its Clover
 * report (coverage.xml, written by composer coverage), where the statements are the lines.
 *
 * Usage: php tools/coverage-minimum.php <clover.xml> <minimum percentage of lines>
 */

$file = $argv[1] ?? '';
$minimum = $argv[2] ?? '';

if ($file === '' || !is_numeric($minimum)) {
    fwrite(STDERR, data: "Usage: php tools/coverage-minimum.php <clover.xml> <minimum percentage of lines>\n");
    exit(2);
}

$clover = is_file($file) ? simplexml_load_file($file) : false;
$metrics = $clover === false ? [] : $clover->xpath('/coverage/project/metrics');

if ($metrics === null || $metrics === false || $metrics === []) {
    fwrite(STDERR, data: "{$file}: no Clover coverage report, or no metrics in it\n");
    exit(2);
}

$lines = (int) $metrics[0]['statements'];
$covered = (int) $metrics[0]['coveredstatements'];
$percentage = $lines === 0 ? 100.0 : (100 * $covered) / $lines;
$summary = sprintf('Lines covered: %.2f%% (%d/%d), minimum %s%%', $percentage, $covered, $lines, $minimum);

if ($percentage < (float) $minimum) {
    fwrite(STDERR, data: "{$summary}: below the minimum\n");
    exit(1);
}

echo "{$summary}\n";

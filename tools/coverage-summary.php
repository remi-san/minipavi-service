<?php

declare(strict_types=1);

/**
 * Write a GitHub Actions Markdown summary from PHPUnit's Clover coverage report.
 *
 * Usage: php tools/coverage-summary.php <clover.xml>
 */

$file = $argv[1] ?? '';

if ($file === '') {
    fwrite(STDERR, data: "Usage: php tools/coverage-summary.php <clover.xml>\n");
    exit(2);
}

$clover = is_file($file) ? simplexml_load_file($file) : false;

if (!$clover instanceof SimpleXMLElement) {
    fwrite(STDERR, data: "{$file}: no Clover coverage report, or no metrics in it\n");
    exit(2);
}

$metrics = $clover->xpath('/coverage/project/metrics');

if ($metrics === null || $metrics === false || $metrics === []) {
    fwrite(STDERR, data: "{$file}: no Clover coverage report, or no metrics in it\n");
    exit(2);
}

$lines = (int) $metrics[0]['statements'];
$covered = (int) $metrics[0]['coveredstatements'];
$methods = (int) $metrics[0]['methods'];
$coveredMethods = (int) $metrics[0]['coveredmethods'];
$classes = (int) $metrics[0]['classes'];
$coveredClasses = 0;
$classMetrics = $clover->xpath('/coverage/project/package/file/class/metrics');

if ($classMetrics !== false && $classMetrics !== null) {
    foreach ($classMetrics as $classMetric) {
        $classMethods = (int) $classMetric['methods'];
        $classCoveredMethods = (int) $classMetric['coveredmethods'];

        if ($classMethods > 0 && $classCoveredMethods === $classMethods) {
            $coveredClasses++;
        }
    }
}
$linePercentage = $lines === 0 ? 100.0 : (100 * $covered) / $lines;
$methodPercentage = $methods === 0 ? 100.0 : (100 * $coveredMethods) / $methods;
$classPercentage = $classes === 0 ? 100.0 : (100 * $coveredClasses) / $classes;

echo "| Lines | Methods | Classes |\n";
echo "|---|---|---|\n";
echo sprintf(
    "| %.2f%% (%d/%d) | %.2f%% (%d/%d) | %.2f%% (%d/%d) |\n",
    $linePercentage,
    $covered,
    $lines,
    $methodPercentage,
    $coveredMethods,
    $methods,
    $classPercentage,
    $coveredClasses,
    $classes,
);

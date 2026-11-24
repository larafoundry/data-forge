<?php

declare(strict_types=1);

/*
 * Fails unless a Clover report shows 100% line and method coverage.
 *
 * Usage: php tools/coverage-check.php build/coverage/clover.xml
 */

$path = $argv[1] ?? 'build/coverage/clover.xml';

if (! is_file($path)) {
    fwrite(STDERR, "Coverage report not found: $path\n");
    exit(1);
}

$report = simplexml_load_file($path);
if ($report === false) {
    fwrite(STDERR, "Coverage report is not valid XML: $path\n");
    exit(1);
}

$metrics = $report->project->metrics;
$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$methods = (int) $metrics['methods'];
$coveredMethods = (int) $metrics['coveredmethods'];

printf("Lines:   %d/%d\n", $coveredStatements, $statements);
printf("Methods: %d/%d\n", $coveredMethods, $methods);

if ($statements > 0 && $coveredStatements === $statements && $coveredMethods === $methods) {
    echo "Coverage is 100%.\n";
    exit(0);
}

$root = getcwd().DIRECTORY_SEPARATOR;

foreach ($report->xpath('//file') ?: [] as $file) {
    $uncovered = [];
    foreach ($file->line as $line) {
        if ((string) $line['type'] !== 'stmt' && (string) $line['type'] !== 'method') {
            continue;
        }

        if ((int) $line['count'] === 0) {
            $uncovered[] = (int) $line['num'];
        }
    }

    if ($uncovered !== []) {
        $name = str_replace($root, '', (string) $file['name']);
        fwrite(STDERR, $name.': '.implode(', ', array_unique($uncovered)).PHP_EOL);
    }
}

fwrite(STDERR, "Coverage must be 100%.\n");
exit(1);

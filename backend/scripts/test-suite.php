<?php

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

ini_set('memory_limit', '512M');

if (array_diff(array_slice($argv, 1), ['--coverage']) !== []) {
    fwrite(STDERR, "Usage: php scripts/test-suite.php [--coverage]\n");
    exit(2);
}

chdir(dirname(__DIR__));
$withCoverage = in_array('--coverage', $argv, true);
$reports = 'build/reports';
if (! is_dir($reports)) {
    mkdir($reports, 0775, true);
}

/** @param list<string> $arguments */
function runSuiteCommand(array $arguments): void
{
    $process = new Process([PHP_BINARY, ...$arguments]);
    $process->setTimeout(null);
    $exitCode = $process->run(function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });

    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

/** @param list<string> $selection */
function runTestGroup(string $name, array $selection, bool $withCoverage): void
{
    $arguments = [
        '-d', 'memory_limit=512M',
        '-d', 'pcov.enabled=1', '-d', 'pcov.directory='.getcwd().'/app',
        'vendor/bin/pest', ...$selection,
        '--compact', '--display-warnings', '--fail-on-warning',
        '--log-junit', 'build/reports/'.$name.'-junit.xml',
    ];
    if ($withCoverage) {
        $arguments = [...$arguments, '--coverage-php', 'build/reports/'.$name.'-coverage.php'];
    }

    fwrite(STDOUT, "\n=== {$name} ===\n");
    runSuiteCommand($arguments);
}

// Each preparation command verifies the configured and connected test identity.
runSuiteCommand(['scripts/test-console.php', 'check']);
runSuiteCommand(['scripts/test-console.php', 'd04:legacy']);
runTestGroup('d04-preflight', ['tests/Feature/BeD04MigrationPreflightTest.php'], $withCoverage);

runSuiteCommand(['scripts/test-console.php', 'migrate:fresh']);
runTestGroup('regression', ['--filter', '/^(?!.*BeD04)/'], $withCoverage);

runSuiteCommand(['scripts/test-console.php', 'd04:seed']);
runTestGroup('d04', ['--filter', '/BeD04(?!MigrationPreflight|ConcurrencyRace)/'], $withCoverage);

runSuiteCommand(['scripts/test-console.php', 'd04:seed']);
runTestGroup('d04-race', ['tests/Feature/BeD04ConcurrencyRaceTest.php'], $withCoverage);

if ($withCoverage) {
    $coverage = null;
    foreach (['d04-preflight', 'regression', 'd04', 'd04-race'] as $name) {
        $groupCoverage = require $reports.'/'.$name.'-coverage.php';
        if (! $groupCoverage instanceof CodeCoverage) {
            throw new RuntimeException('Invalid coverage for '.$name);
        }
        if ($coverage === null) {
            $coverage = $groupCoverage;
        } else {
            $coverage->merge($groupCoverage);
        }
    }

    (new Clover)->process($coverage, $reports.'/clover.xml');
    (new Facade)->process($coverage, $reports.'/html');
    file_put_contents($reports.'/coverage.txt', (new Text(Thresholds::default()))->process($coverage));
}

#!/usr/bin/env php
<?php

/*
  Benchmark: PHP log parsing vs bash pipeline (shell_exec)
  Compares CrawlCommand::parseUrlsFromLog() against the equivalent
  bash pipeline: cat | grep | awk | sort | uniq

  Usage:
    php tooling/scripts/benchmark-log-parsing.php [log-file] [iterations]

    Defaults: tests/fixtures/crawl.log, 1000 iterations
    Example:  php tooling/scripts/benchmark-log-parsing.php tests/fixtures/crawl-10k.log 500

  Larger log fixtures are not committed. To regenerate:
    php -r "
      \$n=(int)(\$argv[1]??10000);
      \$p=['node','page','article','blog','user','category'];
      srand(42);
      for(\$i=1;\$i<=\$n;\$i++){
        \$px=\$p[\$i%count(\$p)]; \$id=(\$i%5===0)?rand(1,(int)(\$n*.8)):\$i;
        \$sz=rand(1000,50000);
        \$h=str_pad((int)(\$i/3600)%24,2,'0',STR_PAD_LEFT);
        \$m=str_pad((int)(\$i/60)%60,2,'0',STR_PAD_LEFT);
        \$s=str_pad(\$i%60,2,'0',STR_PAD_LEFT);
        echo \"2026-03-25 {\$h}:{\$m}:{\$s} URL:https://example.com/{\$px}/{\$id} [{\$sz}] -> \\\"/clone/{\$px}/{\$id}\\\" [1]\n\";
      }" -- 10000 > tests/fixtures/crawl-10k.log
  Adjust the trailing number for other sizes (e.g. 100000).

  Results
  -------
  All outputs matched across every run.

  | Lines   | Iterations | PHP (per iter) | Bash (per iter) | Ratio           |
  |---------|------------|----------------|-----------------|-----------------|
  |      95 |      1,000 |      0.193 ms  |      1.442 ms   | PHP 7.5× faster |
  |  10,000 |      1,000 |     11.868 ms  |     18.023 ms   | PHP 1.5× faster |
  | 100,000 |        100 |    131.209 ms  |    173.161 ms   | PHP 1.3× faster |

  Analysis
  --------
  PHP beats bash at every scale, but the source of the advantage shifts:

  - Small files (95 lines): PHP is ~7.5× faster. The gap is almost entirely
    process-spawning overhead — bash forks 5 subprocesses (cat, grep, awk,
    sort, uniq) on every call, regardless of input size.

  - Medium files (10k lines): PHP is ~1.5× faster. The actual text processing
    work now dominates, but PHP's tight foreach loop still outpaces the
    pipeline even after startup costs are amortised.

  - Large files (100k lines): PHP is ~1.3× faster. The per-call fork cost
    is negligible compared to I/O, but PHP's file() + str_contains loop
    remains more efficient than the multi-process pipeline.

  PHP wins at all scales. The margin shrinks with file size but never inverts.
  Real crawl logs are typically in the hundreds to low thousands of lines, so
  the practical speedup for inVRT usage is in the 2–7× range.
 */

$logFile     = $argv[1] ?? __DIR__ . '/../../tests/fixtures/crawl.log';
$baseUrl     = 'https://example.com';
$iterations  = (int) ($argv[2] ?? 1000);

// ---------------------------------------------------------------------------
// PHP implementation — direct port of CrawlCommand::parseUrlsFromLog()
// ---------------------------------------------------------------------------

function parseWithPhp(string $logFile, string $baseUrl): array
{
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $marker = "URL:$baseUrl";
    $paths = [];

    foreach ($lines as $line) {
        if (!str_contains($line, $marker)) {
            continue;
        }
        $rest = substr($line, strpos($line, $marker) + strlen($marker));
        $path = strtok($rest, " \t");
        if ($path !== false) {
            $paths[] = $path;
        }
    }

    $paths = array_unique($paths);
    sort($paths);
    return $paths;
}

// ---------------------------------------------------------------------------
// Bash pipeline via shell_exec — original approach
// ---------------------------------------------------------------------------

function parseWithBash(string $logFile, string $baseUrl): array
{
    $cmd = 'cat ' . escapeshellarg($logFile)
        . ' | grep ' . escapeshellarg("URL:$baseUrl")
        . ' | awk ' . escapeshellarg('//{gsub("URL:' . $baseUrl . '", ""); print $3}')
        . ' | sort | uniq';

    $raw = shell_exec($cmd) ?? '';
    $lines = array_filter(explode("\n", trim($raw)));
    sort($lines);
    return array_values($lines);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function benchmark(callable $fn, int $iterations): float
{
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }
    return (hrtime(true) - $start) / 1e6; // milliseconds
}

function fmt(float $ms, int $decimals = 3): string
{
    return number_format($ms, $decimals) . ' ms';
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

// Verify both produce the same output before timing
$phpResult  = parseWithPhp($logFile, $baseUrl);
$bashResult = parseWithBash($logFile, $baseUrl);
$match = ($phpResult === $bashResult);

if (!$match) {
    echo "⚠️  Output mismatch — diff:\n";
    $missing = array_diff($bashResult, $phpResult);
    $extra   = array_diff($phpResult, $bashResult);
    foreach ($missing as $v) {
        echo "  bash only: $v\n";
    }
    foreach ($extra as $v) {
        echo "  php only:  $v\n";
    }
    echo "\n";
}

// Warm up (avoids file cache cold-start skewing first run)
parseWithPhp($logFile, $baseUrl);
parseWithBash($logFile, $baseUrl);

echo "\n";
echo "Benchmark: PHP vs bash log parsing\n";
echo str_repeat('─', 42) . "\n";
echo "Log file:    $logFile\n";
echo "Lines:       " . count(file($logFile, FILE_SKIP_EMPTY_LINES)) . "\n";
echo "Paths found: " . count($phpResult) . "\n";
echo "Iterations:  $iterations\n";
echo str_repeat('─', 42) . "\n";

$phpMs  = benchmark(fn() => parseWithPhp($logFile, $baseUrl), $iterations);
$bashMs = benchmark(fn() => parseWithBash($logFile, $baseUrl), $iterations);

$phpPer  = $phpMs  / $iterations;
$bashPer = $bashMs / $iterations;
$ratio   = $bashMs / $phpMs;

echo sprintf("%-14s %12s  %12s\n", '', 'total', 'per iteration');
echo sprintf("%-14s %12s  %12s\n", 'PHP', fmt($phpMs, 1), fmt($phpPer));
echo sprintf("%-14s %12s  %12s\n", 'Bash pipeline', fmt($bashMs, 1), fmt($bashPer));
echo str_repeat('─', 42) . "\n";
echo sprintf("Bash is %sx slower\n", number_format($ratio, 1));
echo sprintf("Outputs match: %s\n", $match ? '✅ yes' : '❌ NO');
echo "\n";
echo "Note: bash timings include spawning 5 subprocesses per call\n";
echo "      (cat, grep, awk, sort, uniq).\n";
echo "\n";

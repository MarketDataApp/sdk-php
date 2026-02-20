#!/usr/bin/env php
<?php

/**
 * Historical Data Exporter
 *
 * Download years of historical price data for backtesting and analysis.
 *
 * Usage:
 *   php exporter.php AAPL --from=2020-01-01 --to=2024-12-31
 *   php exporter.php AAPL,MSFT --from=2023-01-01 --resolution=D
 *   php exporter.php AAPL --from=2024-01-01 --resolution=5 --extended
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

// Parse command line arguments
$symbols = [];
$fromDate = null;
$toDate = date('Y-m-d');
$resolution = 'D';
$extended = false;
$adjustSplits = true;
$outputDir = __DIR__ . '/exports';

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif (str_starts_with($arg, '--from=')) {
        $fromDate = substr($arg, 7);
    } elseif (str_starts_with($arg, '--to=')) {
        $toDate = substr($arg, 5);
    } elseif (str_starts_with($arg, '--resolution=')) {
        $resolution = substr($arg, 13);
    } elseif ($arg === '--extended' || $arg === '-e') {
        $extended = true;
    } elseif ($arg === '--no-adjust' || $arg === '--raw') {
        $adjustSplits = false;
    } elseif (str_starts_with($arg, '--output=')) {
        $outputDir = substr($arg, 9);
    } elseif (!str_starts_with($arg, '-')) {
        $symbols = array_merge($symbols, array_map('trim', explode(',', strtoupper($arg))));
    }
}

if (empty($symbols)) {
    fprintf(STDERR, "Error: Please provide at least one symbol\n");
    fprintf(STDERR, "Usage: php exporter.php SYMBOL --from=DATE [options]\n");
    exit(1);
}

if (!$fromDate) {
    fprintf(STDERR, "Error: Please provide a start date with --from=YYYY-MM-DD\n");
    exit(1);
}

function showHelp(): void
{
    echo <<<HELP
Historical Data Exporter - Download bulk historical price data

Usage:
  php exporter.php SYMBOL --from=DATE [options]

Arguments:
  SYMBOL              Ticker symbol(s), comma-separated (e.g., AAPL or AAPL,MSFT)

Required:
  --from=DATE         Start date (YYYY-MM-DD format)

Options:
  --to=DATE           End date (default: today)
  --resolution=RES    Candle resolution (default: D)
                      Daily: D, 1D, 2D, daily
                      Weekly: W, 1W, weekly
                      Monthly: M, 1M, monthly
                      Intraday: 1, 5, 15, 30, 1H, etc.
  --extended, -e      Include extended hours (intraday only)
  --no-adjust, --raw  Don't adjust for splits
  --output=DIR        Output directory (default: ./exports)
  --help, -h          Show this help message

Examples:
  php exporter.php AAPL --from=2020-01-01
  php exporter.php AAPL,MSFT --from=2020-01-01 --to=2024-12-31
  php exporter.php AAPL --from=2024-01-01 --resolution=5
  php exporter.php AAPL --from=2024-01-01 --resolution=1H --extended

Output:
  Files saved to exports/ directory as:
    SYMBOL_YYYY-YYYY_resolution.csv

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Format file size
 */
function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Generate filename for export
 */
function generateFilename(string $symbol, string $from, string $to, string $resolution): string
{
    $fromYear = substr($from, 0, 4);
    $toYear = substr($to, 0, 4);
    $yearRange = $fromYear === $toYear ? $fromYear : "{$fromYear}-{$toYear}";

    $resLabel = strtolower($resolution);
    if (preg_match('/^\d+$/', $resolution)) {
        $resLabel = "{$resolution}min";
    } elseif (preg_match('/^\d+h$/i', $resolution)) {
        $resLabel = strtolower($resolution);
    }

    return "{$symbol}_{$yearRange}_{$resLabel}.csv";
}

// Ensure output directory exists
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        fprintf(STDERR, "Error: Could not create output directory: %s\n", $outputDir);
        exit(1);
    }
}

echo "=== Historical Data Exporter ===\n\n";
echo "Symbols: " . implode(', ', $symbols) . "\n";
echo "Date Range: {$fromDate} to {$toDate}\n";
echo "Resolution: {$resolution}\n";
echo "Extended Hours: " . ($extended ? 'Yes' : 'No') . "\n";
echo "Split Adjusted: " . ($adjustSplits ? 'Yes' : 'No') . "\n";
echo "Output Directory: {$outputDir}\n\n";

try {
    $client = new Client();

    $totalFiles = 0;
    $totalBytes = 0;
    $errors = [];

    foreach ($symbols as $symbol) {
        echo "Exporting {$symbol}... ";

        try {
            $startTime = microtime(true);

            // Use CSV format for direct export
            $params = new Parameters(format: Format::CSV, add_headers: true);

            $candles = $client->stocks->candles(
                symbol: $symbol,
                from: $fromDate,
                to: $toDate,
                resolution: $resolution,
                extended: $extended,
                adjust_splits: $adjustSplits,
                parameters: $params
            );

            // Check if we got CSV data
            $csv = $candles->getCsv();
            if (empty($csv)) {
                echo "No data\n";
                continue;
            }

            // Generate filename and save
            $filename = generateFilename($symbol, $fromDate, $toDate, $resolution);
            $filepath = $outputDir . '/' . $filename;

            file_put_contents($filepath, $csv);

            $fileSize = strlen($csv);
            $elapsed = microtime(true) - $startTime;

            // Count rows (lines - 1 for header)
            $rowCount = substr_count($csv, "\n");
            if (str_ends_with($csv, "\n")) $rowCount--;

            echo "Done! ";
            echo "{$rowCount} candles, ";
            echo formatSize($fileSize) . ", ";
            echo number_format($elapsed, 1) . "s\n";
            echo "  -> {$filename}\n";

            $totalFiles++;
            $totalBytes += $fileSize;

        } catch (ApiException $e) {
            echo "Error: {$e->getMessage()}\n";
            $errors[$symbol] = $e->getMessage();
        }
    }

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Export Complete\n";
    echo "  Files Created: {$totalFiles}\n";
    echo "  Total Size: " . formatSize($totalBytes) . "\n";
    echo "  Output Directory: {$outputDir}\n";

    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $symbol => $error) {
            echo "  {$symbol}: {$error}\n";
        }
    }

} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

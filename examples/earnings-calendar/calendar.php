#!/usr/bin/env php
<?php

/**
 * Earnings Calendar
 *
 * Generate an earnings calendar for a watchlist, identifying upcoming volatility events.
 *
 * Usage:
 *   php calendar.php                              # Use sample watchlist
 *   php calendar.php /path/to/watchlist.txt       # Custom watchlist
 *   php calendar.php --days=60                    # Look ahead 60 days
 *   php calendar.php --csv                        # Export to CSV
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

// Autoload the SDK
require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;

// Parse command line arguments
$watchlistFile = __DIR__ . '/sample-watchlist.txt';
$daysAhead = 30;
$exportCsv = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--csv' || $arg === '-c') {
        $exportCsv = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif (str_starts_with($arg, '--days=')) {
        $daysAhead = (int) substr($arg, 7);
    } elseif (!str_starts_with($arg, '-')) {
        $watchlistFile = $arg;
    }
}

/**
 * Display help information
 */
function showHelp(): void
{
    echo <<<HELP
Earnings Calendar - Track upcoming earnings for your watchlist

Usage:
  php calendar.php [options] [watchlist-file]

Options:
  --days=N        Look ahead N days (default: 30)
  --csv, -c       Export results to CSV file
  --help, -h      Show this help message

Examples:
  php calendar.php                            Use sample watchlist
  php calendar.php my-watchlist.txt           Use custom watchlist
  php calendar.php --days=60                  Look ahead 60 days
  php calendar.php --csv                      Export to CSV

Watchlist format (one symbol per line):
  AAPL
  MSFT
  # Lines starting with # are comments

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Parse a watchlist file into an array of symbols
 */
function parseWatchlist(string $filepath): array
{
    if (!file_exists($filepath)) {
        throw new \RuntimeException("Watchlist file not found: {$filepath}");
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $symbols = [];

    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        // Take the first word (in case there are notes after)
        $parts = preg_split('/\s+/', $line);
        $symbol = strtoupper($parts[0]);
        if ($symbol !== '') {
            $symbols[] = $symbol;
        }
    }

    return array_unique($symbols);
}

/**
 * Get week number label for grouping
 */
function getWeekLabel(\Carbon\Carbon $date): string
{
    $now = \Carbon\Carbon::now();
    $startOfThisWeek = $now->copy()->startOfWeek();
    $startOfNextWeek = $startOfThisWeek->copy()->addWeek();

    if ($date->lt($startOfNextWeek)) {
        return 'This Week';
    } elseif ($date->lt($startOfNextWeek->copy()->addWeek())) {
        return 'Next Week';
    } else {
        return 'Week of ' . $date->copy()->startOfWeek()->format('M j');
    }
}

/**
 * Format report timing
 */
function formatReportTime(string $time): string
{
    return match (strtolower($time)) {
        'before market open', 'bmo' => 'Before Open',
        'after market close', 'amc' => 'After Close',
        'during market hours', 'dmh' => 'During Hours',
        default => $time,
    };
}

// Load watchlist
try {
    $symbols = parseWatchlist($watchlistFile);
} catch (\RuntimeException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

if (empty($symbols)) {
    fprintf(STDERR, "Error: No symbols found in watchlist\n");
    exit(1);
}

echo "=== Earnings Calendar ===\n\n";
echo "Watchlist: " . count($symbols) . " symbols\n";
echo "Looking ahead: {$daysAhead} days\n\n";

// Calculate date range
$fromDate = date('Y-m-d');
$toDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

try {
    // Initialize the client
    $client = new Client();

    $allEarnings = [];
    $errors = [];

    echo "Fetching earnings data";

    // Fetch earnings for each symbol
    foreach ($symbols as $symbol) {
        echo ".";

        try {
            $earnings = $client->stocks->earnings(
                symbol: $symbol,
                from: $fromDate,
                to: $toDate
            );

            if ($earnings->status === 'ok' && !empty($earnings->earnings)) {
                foreach ($earnings->earnings as $earning) {
                    // Only include future earnings
                    if ($earning->report_date->gte(\Carbon\Carbon::today())) {
                        $allEarnings[] = $earning;
                    }
                }
            }
        } catch (ApiException $e) {
            // Some symbols may not have earnings data
            $errors[$symbol] = $e->getMessage();
        }
    }

    echo " Done!\n\n";

    if (empty($allEarnings)) {
        echo "No upcoming earnings found for the watchlist in the next {$daysAhead} days.\n";

        if (!empty($errors)) {
            echo "\nNote: Some symbols had errors:\n";
            foreach ($errors as $symbol => $error) {
                echo "  {$symbol}: {$error}\n";
            }
        }
        exit(0);
    }

    // Sort by report date
    usort($allEarnings, function ($a, $b) {
        return $a->report_date->timestamp <=> $b->report_date->timestamp;
    });

    // Group by week
    $groupedEarnings = [];
    foreach ($allEarnings as $earning) {
        $weekLabel = getWeekLabel($earning->report_date);
        $groupedEarnings[$weekLabel][] = $earning;
    }

    // Display results
    echo str_repeat('=', 80) . "\n";
    printf("%-10s %-12s %-15s %-8s %-8s %-10s %s\n",
        'Symbol', 'Report Date', 'Time', 'Q', 'FY', 'Est EPS', 'Prior EPS');
    echo str_repeat('-', 80) . "\n";

    $currentWeek = '';
    foreach ($groupedEarnings as $weekLabel => $earnings) {
        if ($weekLabel !== $currentWeek) {
            if ($currentWeek !== '') {
                echo "\n";
            }
            echo "\033[1m{$weekLabel}\033[0m\n";
            $currentWeek = $weekLabel;
        }

        foreach ($earnings as $e) {
            $estEps = $e->estimated_eps !== null ? sprintf('$%.2f', $e->estimated_eps) : 'N/A';
            $priorEps = $e->reported_eps !== null ? sprintf('$%.2f', $e->reported_eps) : 'N/A';

            printf("  %-8s %-12s %-15s Q%-7d %-8d %-10s %s\n",
                $e->symbol,
                $e->report_date->format('M j, Y'),
                formatReportTime($e->report_time),
                $e->fiscal_quarter,
                $e->fiscal_year,
                $estEps,
                $priorEps
            );
        }
    }

    echo str_repeat('=', 80) . "\n";

    // Summary
    echo "\nSummary:\n";
    echo "  Total upcoming earnings: " . count($allEarnings) . "\n";

    foreach ($groupedEarnings as $weekLabel => $earnings) {
        echo "  {$weekLabel}: " . count($earnings) . " reports\n";
    }

    // Show errors if any
    if (!empty($errors)) {
        echo "\nSymbols with no earnings data:\n";
        foreach ($errors as $symbol => $error) {
            echo "  {$symbol}\n";
        }
    }

    // Export to CSV if requested
    if ($exportCsv) {
        $csvFilename = 'earnings-calendar-' . date('Y-m-d') . '.csv';
        $csvPath = __DIR__ . '/' . $csvFilename;

        $fp = fopen($csvPath, 'w');

        // Header row - Google Calendar compatible
        fputcsv($fp, [
            'Subject', 'Start Date', 'Start Time', 'End Time',
            'Description', 'Location'
        ]);

        foreach ($allEarnings as $e) {
            $reportTime = strtolower($e->report_time);
            $startTime = '09:30 AM';
            $endTime = '10:00 AM';

            if (str_contains($reportTime, 'before') || str_contains($reportTime, 'bmo')) {
                $startTime = '07:00 AM';
                $endTime = '09:30 AM';
            } elseif (str_contains($reportTime, 'after') || str_contains($reportTime, 'amc')) {
                $startTime = '04:00 PM';
                $endTime = '05:00 PM';
            }

            $description = sprintf(
                "Q%d FY%d Earnings\nEstimated EPS: %s\nReport Time: %s",
                $e->fiscal_quarter,
                $e->fiscal_year,
                $e->estimated_eps !== null ? sprintf('$%.2f', $e->estimated_eps) : 'N/A',
                $e->report_time
            );

            fputcsv($fp, [
                "{$e->symbol} Earnings",
                $e->report_date->format('m/d/Y'),
                $startTime,
                $endTime,
                $description,
                'Market Data',
            ]);
        }

        fclose($fp);

        echo "\nCSV exported to: {$csvPath}\n";
        echo "  (Compatible with Google Calendar import)\n";
    }

} catch (\Exception $e) {
    fprintf(STDERR, "\nError: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

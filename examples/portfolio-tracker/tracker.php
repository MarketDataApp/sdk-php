#!/usr/bin/env php
<?php

/**
 * Portfolio Tracker
 *
 * Track the real-time value of a stock portfolio with daily P&L and 52-week range analysis.
 *
 * Usage:
 *   php tracker.php                              # Use sample portfolio
 *   php tracker.php /path/to/portfolio.json      # Custom portfolio
 *   php tracker.php --csv                        # Export to CSV
 *   php tracker.php portfolio.json --csv         # Custom portfolio + CSV export
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

// Autoload the SDK
require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;

// Parse command line arguments
$portfolioFile = __DIR__ . '/sample-portfolio.json';
$exportCsv = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--csv' || $arg === '-c') {
        $exportCsv = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif (!str_starts_with($arg, '-')) {
        $portfolioFile = $arg;
    }
}

/**
 * Display help information
 */
function showHelp(): void
{
    echo <<<HELP
Portfolio Tracker - Real-time stock portfolio monitoring

Usage:
  php tracker.php [options] [portfolio-file]

Options:
  --csv, -c       Export results to CSV file
  --help, -h      Show this help message

Examples:
  php tracker.php                            Use sample portfolio
  php tracker.php my-portfolio.json          Use custom portfolio file
  php tracker.php --csv                      Export to CSV
  php tracker.php portfolio.json --csv       Custom portfolio + CSV

Portfolio JSON format:
  {
    "name": "My Portfolio",
    "holdings": [
      {"symbol": "AAPL", "shares": 100, "cost_basis": 150.00}
    ]
  }

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Format a number as currency
 */
function formatCurrency(float $value): string
{
    return '$' . number_format($value, 2);
}

/**
 * Format a number as percentage
 */
function formatPercent(float $value): string
{
    $sign = $value >= 0 ? '+' : '';
    return $sign . number_format($value * 100, 2) . '%';
}

/**
 * Format P&L with color indicators (for terminals that support it)
 */
function formatPnL(float $value): string
{
    $formatted = formatCurrency($value);
    if ($value > 0) {
        return "\033[32m+{$formatted}\033[0m"; // Green
    } elseif ($value < 0) {
        return "\033[31m{$formatted}\033[0m"; // Red
    }
    return $formatted;
}

/**
 * Calculate 52-week position as percentage (0% = at low, 100% = at high)
 */
function calculate52WeekPosition(?float $current, ?float $low, ?float $high): ?float
{
    if ($current === null || $low === null || $high === null) {
        return null;
    }
    if ($high === $low) {
        return 50.0; // At both high and low
    }
    return (($current - $low) / ($high - $low)) * 100;
}

// Load portfolio
if (!file_exists($portfolioFile)) {
    fprintf(STDERR, "Error: Portfolio file not found: %s\n", $portfolioFile);
    exit(1);
}

$portfolioJson = file_get_contents($portfolioFile);
$portfolio = json_decode($portfolioJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    fprintf(STDERR, "Error: Invalid JSON in portfolio file: %s\n", json_last_error_msg());
    exit(1);
}

if (empty($portfolio['holdings'])) {
    fprintf(STDERR, "Error: No holdings found in portfolio file\n");
    exit(1);
}

$portfolioName = $portfolio['name'] ?? 'Portfolio';
$holdings = $portfolio['holdings'];

// Extract symbols
$symbols = array_column($holdings, 'symbol');

// Create holdings lookup by symbol
$holdingsLookup = [];
foreach ($holdings as $holding) {
    $holdingsLookup[$holding['symbol']] = $holding;
}

echo "=== {$portfolioName} Tracker ===\n\n";
echo "Loading quotes for " . count($symbols) . " symbols...\n\n";

try {
    // Initialize the client (token from environment or .env file)
    $client = new Client();

    // Fetch quotes for all symbols with 52-week data
    $response = $client->stocks->quotes($symbols, fifty_two_week: true);

    if (empty($response->quotes)) {
        fprintf(STDERR, "Error: No quote data available\n");
        exit(1);
    }

    // Process results
    $results = [];
    $totalCostBasis = 0;
    $totalMarketValue = 0;
    $totalPnL = 0;

    foreach ($response->quotes as $quote) {
        $symbol = $quote->symbol;
        $holding = $holdingsLookup[$symbol] ?? null;

        if (!$holding) {
            continue;
        }

        $shares = $holding['shares'];
        $costBasis = $holding['cost_basis'];
        $currentPrice = $quote->last;

        $positionCost = $shares * $costBasis;
        $marketValue = $shares * $currentPrice;
        $unrealizedPnL = $marketValue - $positionCost;
        $pnlPercent = ($unrealizedPnL / $positionCost);

        $position52Week = calculate52WeekPosition(
            $currentPrice,
            $quote->fifty_two_week_low,
            $quote->fifty_two_week_high
        );

        $results[] = [
            'symbol' => $symbol,
            'shares' => $shares,
            'cost_basis' => $costBasis,
            'current_price' => $currentPrice,
            'change' => $quote->change,
            'change_percent' => $quote->change_percent,
            'market_value' => $marketValue,
            'unrealized_pnl' => $unrealizedPnL,
            'pnl_percent' => $pnlPercent,
            'fifty_two_week_high' => $quote->fifty_two_week_high,
            'fifty_two_week_low' => $quote->fifty_two_week_low,
            'position_52week' => $position52Week,
            'volume' => $quote->volume,
            'updated' => $quote->updated,
        ];

        $totalCostBasis += $positionCost;
        $totalMarketValue += $marketValue;
        $totalPnL += $unrealizedPnL;
    }

    // Display results
    echo str_repeat('-', 100) . "\n";
    printf("%-8s %10s %12s %12s %12s %14s %12s %10s\n",
        'Symbol', 'Shares', 'Cost', 'Price', 'Day Chg', 'Market Value', 'P&L', '52W Pos');
    echo str_repeat('-', 100) . "\n";

    foreach ($results as $r) {
        $dayChange = $r['change'] !== null
            ? sprintf('%+.2f (%.1f%%)', $r['change'], $r['change_percent'] * 100)
            : 'N/A';

        $position52w = $r['position_52week'] !== null
            ? sprintf('%.0f%%', $r['position_52week'])
            : 'N/A';

        printf("%-8s %10d %12s %12s %12s %14s %12s %10s\n",
            $r['symbol'],
            $r['shares'],
            formatCurrency($r['cost_basis']),
            formatCurrency($r['current_price']),
            $dayChange,
            formatCurrency($r['market_value']),
            sprintf('%+.2f', $r['unrealized_pnl']),
            $position52w
        );
    }

    echo str_repeat('-', 100) . "\n";

    // Portfolio totals
    $totalPnLPercent = $totalCostBasis > 0 ? ($totalPnL / $totalCostBasis) : 0;

    echo "\n=== Portfolio Summary ===\n";
    printf("Total Cost Basis:    %s\n", formatCurrency($totalCostBasis));
    printf("Total Market Value:  %s\n", formatCurrency($totalMarketValue));
    printf("Total Unrealized P&L: %s (%s)\n",
        formatPnL($totalPnL),
        formatPercent($totalPnLPercent)
    );

    // Show last update time
    if (!empty($results)) {
        $lastUpdate = $results[0]['updated'];
        printf("\nLast Updated: %s\n", $lastUpdate->format('Y-m-d H:i:s T'));
    }

    // Export to CSV if requested
    if ($exportCsv) {
        $csvFilename = 'portfolio-' . date('Y-m-d-His') . '.csv';
        $csvPath = __DIR__ . '/' . $csvFilename;

        $fp = fopen($csvPath, 'w');

        // Header row
        fputcsv($fp, [
            'Symbol', 'Shares', 'Cost Basis', 'Current Price', 'Day Change',
            'Day Change %', 'Market Value', 'Unrealized P&L', 'P&L %',
            '52W High', '52W Low', '52W Position %', 'Volume', 'Updated'
        ]);

        // Data rows
        foreach ($results as $r) {
            fputcsv($fp, [
                $r['symbol'],
                $r['shares'],
                $r['cost_basis'],
                $r['current_price'],
                $r['change'],
                $r['change_percent'],
                $r['market_value'],
                $r['unrealized_pnl'],
                $r['pnl_percent'],
                $r['fifty_two_week_high'],
                $r['fifty_two_week_low'],
                $r['position_52week'],
                $r['volume'],
                $r['updated']->toIso8601String(),
            ]);
        }

        // Summary row
        fputcsv($fp, []);
        fputcsv($fp, ['TOTAL', '', $totalCostBasis, '', '', '',
            $totalMarketValue, $totalPnL, $totalPnLPercent, '', '', '', '', '']);

        fclose($fp);

        echo "\nCSV exported to: {$csvPath}\n";
    }

} catch (ApiException $e) {
    fprintf(STDERR, "API Error: %s\n", $e->getMessage());
    exit(1);
} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

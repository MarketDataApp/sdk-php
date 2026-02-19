<?php

/**
 * Pre-Market Scan Job
 *
 * This job runs during pre-market hours (4:00 AM - 9:30 AM ET).
 * It scans a watchlist for pre-market price movements.
 *
 * Typical use cases:
 * - Identify gap ups/downs
 * - Check for earnings movers
 * - Prepare trading plans
 */

declare(strict_types=1);

// This script is included by scheduler.php, so Client is already available
// If running standalone, uncomment:
// require_once __DIR__ . '/../../../vendor/autoload.php';

use MarketDataApp\Client;

echo "=== PRE-MARKET SCAN ===\n\n";

// Sample watchlist - in production, load from file
$watchlist = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'TSLA', 'NVDA', 'META', 'SPY', 'QQQ'];

try {
    // Note: $client may already exist from scheduler.php
    // Create if not exists (for standalone testing)
    if (!isset($client)) {
        $client = new Client();
    }

    echo "Scanning " . count($watchlist) . " symbols for pre-market activity...\n\n";

    // Fetch quotes with extended hours data
    $quotes = $client->stocks->quotes($watchlist, fifty_two_week: false, extended: true);

    if ($quotes->status !== 'ok') {
        echo "No quote data available\n";
        return;
    }

    // Analyze and display movers
    $movers = [];
    foreach ($quotes->quotes as $quote) {
        if ($quote->change_percent !== null) {
            $movers[] = [
                'symbol' => $quote->symbol,
                'last' => $quote->last,
                'change' => $quote->change,
                'change_pct' => $quote->change_percent,
                'volume' => $quote->volume,
            ];
        }
    }

    // Sort by absolute change percentage
    usort($movers, fn($a, $b) => abs($b['change_pct']) <=> abs($a['change_pct']));

    echo "PRE-MARKET MOVERS (sorted by |change %|)\n";
    echo str_repeat('-', 60) . "\n";
    printf("%-8s %10s %10s %10s %12s\n", 'Symbol', 'Price', 'Change', 'Change %', 'Volume');
    echo str_repeat('-', 60) . "\n";

    foreach ($movers as $m) {
        $changeSign = $m['change'] >= 0 ? '+' : '';
        $pctSign = $m['change_pct'] >= 0 ? '+' : '';

        printf("%-8s %10.2f %10s %10s %12s\n",
            $m['symbol'],
            $m['last'],
            $changeSign . number_format($m['change'], 2),
            $pctSign . number_format($m['change_pct'] * 100, 2) . '%',
            number_format($m['volume'])
        );
    }

    // Highlight significant moves (>2%)
    $significantMovers = array_filter($movers, fn($m) => abs($m['change_pct']) > 0.02);

    if (!empty($significantMovers)) {
        echo "\n*** SIGNIFICANT MOVES (>2%) ***\n";
        foreach ($significantMovers as $m) {
            $direction = $m['change_pct'] > 0 ? 'UP' : 'DOWN';
            echo "  {$m['symbol']}: {$direction} " . number_format(abs($m['change_pct']) * 100, 1) . "%\n";
        }
    }

    echo "\nPre-market scan completed at " . date('H:i:s') . "\n";

} catch (\Exception $e) {
    echo "Error during pre-market scan: " . $e->getMessage() . "\n";
}

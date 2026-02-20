<?php

/**
 * Market Close Job
 *
 * This job runs after market close (4:00 PM ET).
 * It generates end-of-day summaries and reports.
 *
 * Typical use cases:
 * - Daily P&L reports
 * - Record closing prices
 * - Generate daily digest emails
 */

declare(strict_types=1);

// This script is included by scheduler.php, so Client is already available
// If running standalone, uncomment:
// require_once __DIR__ . '/../../../vendor/autoload.php';

use MarketDataApp\Client;

echo "=== MARKET CLOSE SUMMARY ===\n\n";

// Key indices and sectors to track
$indices = ['SPY', 'QQQ', 'IWM', 'DIA'];
$sectors = ['XLK', 'XLF', 'XLE', 'XLV', 'XLI', 'XLC', 'XLY', 'XLP', 'XLB', 'XLU', 'XLRE'];

try {
    // Note: $client may already exist from scheduler.php
    if (!isset($client)) {
        $client = new Client();
    }

    // Fetch index data
    echo "MAJOR INDICES\n";
    echo str_repeat('-', 50) . "\n";

    $indexQuotes = $client->stocks->quotes($indices, extended: false);

    if ($indexQuotes->status === 'ok') {
        printf("%-8s %12s %10s %10s\n", 'Index', 'Close', 'Change', 'Change %');
        echo str_repeat('-', 50) . "\n";

        foreach ($indexQuotes->quotes as $q) {
            $changeSign = $q->change >= 0 ? '+' : '';
            $pctSign = $q->change_percent >= 0 ? '+' : '';

            printf("%-8s %12.2f %10s %10s\n",
                $q->symbol,
                $q->last,
                $changeSign . number_format($q->change, 2),
                $pctSign . number_format($q->change_percent * 100, 2) . '%'
            );
        }
    }

    // Fetch sector data
    echo "\nSECTOR PERFORMANCE\n";
    echo str_repeat('-', 60) . "\n";

    $sectorQuotes = $client->stocks->quotes($sectors, extended: false);

    if ($sectorQuotes->status === 'ok') {
        // Sort by performance
        $sectorData = [];
        foreach ($sectorQuotes->quotes as $q) {
            $sectorData[] = [
                'symbol' => $q->symbol,
                'name' => getSectorName($q->symbol),
                'last' => $q->last,
                'change_pct' => $q->change_percent ?? 0,
            ];
        }

        usort($sectorData, fn($a, $b) => $b['change_pct'] <=> $a['change_pct']);

        printf("%-6s %-20s %10s %10s\n", 'ETF', 'Sector', 'Close', 'Change %');
        echo str_repeat('-', 60) . "\n";

        foreach ($sectorData as $s) {
            $pctSign = $s['change_pct'] >= 0 ? '+' : '';

            printf("%-6s %-20s %10.2f %10s\n",
                $s['symbol'],
                $s['name'],
                $s['last'],
                $pctSign . number_format($s['change_pct'] * 100, 2) . '%'
            );
        }

        // Summary
        $gainers = array_filter($sectorData, fn($s) => $s['change_pct'] > 0);
        $losers = array_filter($sectorData, fn($s) => $s['change_pct'] < 0);

        echo "\nSummary:\n";
        echo "  Advancing sectors: " . count($gainers) . "\n";
        echo "  Declining sectors: " . count($losers) . "\n";

        if (!empty($sectorData)) {
            $best = $sectorData[0];
            $worst = end($sectorData);
            echo "  Best performer: {$best['name']} (" . sprintf('%+.2f%%', $best['change_pct'] * 100) . ")\n";
            echo "  Worst performer: {$worst['name']} (" . sprintf('%+.2f%%', $worst['change_pct'] * 100) . ")\n";
        }
    }

    echo "\nMarket close summary generated at " . date('H:i:s') . "\n";

} catch (\Exception $e) {
    echo "Error during market close summary: " . $e->getMessage() . "\n";
}

/**
 * Get sector name from ETF symbol
 */
function getSectorName(string $symbol): string
{
    return match ($symbol) {
        'XLK' => 'Technology',
        'XLF' => 'Financials',
        'XLE' => 'Energy',
        'XLV' => 'Healthcare',
        'XLI' => 'Industrials',
        'XLC' => 'Communication',
        'XLY' => 'Consumer Disc.',
        'XLP' => 'Consumer Staples',
        'XLB' => 'Materials',
        'XLU' => 'Utilities',
        'XLRE' => 'Real Estate',
        default => $symbol,
    };
}

#!/usr/bin/env php
<?php

/**
 * Covered Call Strategy Screener
 *
 * Find optimal covered call opportunities for income generation on existing positions.
 *
 * Usage:
 *   php covered-call.php AAPL                    # Screen for covered calls
 *   php covered-call.php AAPL --shares=100       # Specify position size
 *   php covered-call.php AAPL --delta=0.30       # Target delta
 *   php covered-call.php AAPL --min-premium=1.00 # Minimum premium
 *
 * Strategy Overview:
 *   - Sell OTM calls against existing stock position
 *   - Target delta 0.20-0.35 (probability of assignment ~20-35%)
 *   - Optimal DTE: 30-45 days for theta decay
 *   - Goal: Generate income while capping upside
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Enums\Side;
use MarketDataApp\Enums\Range;
use MarketDataApp\Exceptions\ApiException;

// Parse arguments
$symbol = null;
$shares = 100;
$targetDelta = 0.30;
$minPremium = 0.50;
$targetDte = 35;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif (str_starts_with($arg, '--shares=')) {
        $shares = (int) substr($arg, 9);
    } elseif (str_starts_with($arg, '--delta=')) {
        $targetDelta = (float) substr($arg, 8);
    } elseif (str_starts_with($arg, '--min-premium=')) {
        $minPremium = (float) substr($arg, 14);
    } elseif (str_starts_with($arg, '--dte=')) {
        $targetDte = (int) substr($arg, 6);
    } elseif (!str_starts_with($arg, '-')) {
        $symbol = strtoupper(trim($arg));
    }
}

if (!$symbol) {
    fprintf(STDERR, "Error: Please provide a symbol\n");
    fprintf(STDERR, "Usage: php covered-call.php SYMBOL [options]\n");
    exit(1);
}

function showHelp(): void
{
    echo <<<HELP
Covered Call Strategy Screener

Usage:
  php covered-call.php SYMBOL [options]

Arguments:
  SYMBOL              Ticker symbol (e.g., AAPL)

Options:
  --shares=N          Number of shares owned (default: 100)
  --delta=N           Target delta, e.g., 0.30 (default: 0.30)
  --min-premium=N     Minimum premium per share (default: 0.50)
  --dte=N             Target days to expiration (default: 35)
  --help, -h          Show this help message

Strategy Parameters:
  - Sells OTM calls against your stock position
  - Delta 0.20-0.35 = 65-80% probability of keeping shares
  - 30-45 DTE optimal for theta decay
  - Focus on liquid options (high OI, tight spreads)

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

function formatCurrency(float $value): string
{
    return '$' . number_format($value, 2);
}

try {
    $client = new Client();

    echo "\n=== COVERED CALL SCREENER ===\n";
    echo "Symbol: {$symbol}\n";
    echo "Position: {$shares} shares\n";
    echo "Target Delta: {$targetDelta}\n";
    echo "Target DTE: ~{$targetDte} days\n\n";

    // Get current stock price
    $quote = $client->stocks->quote($symbol, fifty_two_week: true);
    if ($quote->status !== 'ok') {
        throw new \RuntimeException("Could not fetch quote for {$symbol}");
    }

    $stockPrice = $quote->last;
    $positionValue = $shares * $stockPrice;

    echo "Current Price: " . formatCurrency($stockPrice) . "\n";
    echo "Position Value: " . formatCurrency($positionValue) . "\n";

    if ($quote->fifty_two_week_high && $quote->fifty_two_week_low) {
        $range = $quote->fifty_two_week_high - $quote->fifty_two_week_low;
        $position = ($stockPrice - $quote->fifty_two_week_low) / $range * 100;
        echo "52-Week Range: " . formatCurrency($quote->fifty_two_week_low) .
             " - " . formatCurrency($quote->fifty_two_week_high) .
             " (" . number_format($position, 0) . "% position)\n";
    }
    echo "\n";

    // Fetch OTM calls
    $chain = $client->options->option_chain(
        symbol: $symbol,
        dte: $targetDte,
        side: Side::CALL,
        range: Range::OUT_OF_THE_MONEY,
        min_bid: $minPremium,
        min_open_interest: 50
    );

    if ($chain->status !== 'ok' || empty($chain->option_chains)) {
        echo "No covered call candidates found matching criteria.\n";
        exit(0);
    }

    // Filter and sort by delta proximity
    $candidates = [];
    foreach ($chain->getAllQuotes() as $option) {
        // Must have delta
        if ($option->delta === null) continue;

        // Calculate metrics
        $premium = $option->bid;
        $totalPremium = $premium * $shares;
        $maxGain = ($option->strike - $stockPrice) * $shares + $totalPremium;
        $annualizedReturn = ($premium / $stockPrice) * (365 / $option->dte);
        $spreadPct = $option->bid > 0 ? ($option->ask - $option->bid) / $option->bid : 1;

        // Skip wide spreads
        if ($spreadPct > 0.25) continue;

        $candidates[] = [
            'option' => $option,
            'premium' => $premium,
            'totalPremium' => $totalPremium,
            'maxGain' => $maxGain,
            'annualizedReturn' => $annualizedReturn,
            'spreadPct' => $spreadPct,
            'deltaDiff' => abs($option->delta - $targetDelta),
        ];
    }

    // Sort by delta proximity
    usort($candidates, fn($a, $b) => $a['deltaDiff'] <=> $b['deltaDiff']);

    echo "TOP COVERED CALL CANDIDATES\n";
    echo str_repeat('-', 90) . "\n";
    printf("%-18s %7s %5s %8s %10s %8s %8s %10s\n",
        'Contract', 'Strike', 'DTE', 'Bid', 'Total $', 'Delta', 'IV', 'Ann.Ret');
    echo str_repeat('-', 90) . "\n";

    $count = 0;
    foreach ($candidates as $c) {
        if ($count >= 10) break;

        $opt = $c['option'];
        printf("%-18s %7s %5d %8s %10s %8.2f %7.0f%% %9.1f%%\n",
            $opt->option_symbol,
            formatCurrency($opt->strike),
            $opt->dte,
            formatCurrency($opt->bid),
            formatCurrency($c['totalPremium']),
            $opt->delta,
            ($opt->implied_volatility ?? 0) * 100,
            $c['annualizedReturn'] * 100
        );
        $count++;
    }

    echo str_repeat('-', 90) . "\n";

    // Show best candidate details
    if (!empty($candidates)) {
        $best = $candidates[0];
        $opt = $best['option'];

        echo "\nBEST MATCH (closest to target delta {$targetDelta}):\n";
        echo "  Contract: {$opt->option_symbol}\n";
        echo "  Strike: " . formatCurrency($opt->strike) . " (+" .
             number_format(($opt->strike / $stockPrice - 1) * 100, 1) . "% from current)\n";
        echo "  Premium: " . formatCurrency($best['premium']) . " x {$shares} = " .
             formatCurrency($best['totalPremium']) . "\n";
        echo "  Delta: {$opt->delta} (~" . number_format((1 - $opt->delta) * 100, 0) .
             "% probability of profit)\n";
        echo "  Annualized Return: " . number_format($best['annualizedReturn'] * 100, 1) . "%\n";
        echo "  Max Profit if Called: " . formatCurrency($best['maxGain']) . "\n";
        echo "  Expiration: {$opt->expiration->format('M j, Y')} ({$opt->dte} days)\n";
    }

} catch (ApiException $e) {
    fprintf(STDERR, "API Error: %s\n", $e->getMessage());
    exit(1);
} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

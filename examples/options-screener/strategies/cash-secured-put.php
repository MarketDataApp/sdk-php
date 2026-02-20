#!/usr/bin/env php
<?php

/**
 * Cash-Secured Put Strategy Screener
 *
 * Find optimal cash-secured put opportunities for income generation.
 *
 * Usage:
 *   php cash-secured-put.php AAPL                   # Screen for CSPs
 *   php cash-secured-put.php AAPL --budget=10000    # Max capital to deploy
 *   php cash-secured-put.php AAPL --delta=-0.25     # Target delta
 *   php cash-secured-put.php AAPL --min-premium=0.50 # Minimum premium
 *
 * Strategy Overview:
 *   - Sell OTM puts, secured by cash to cover potential assignment
 *   - Target delta -0.20 to -0.30 (probability of assignment ~20-30%)
 *   - Optimal DTE: 30-45 days for theta decay
 *   - Goal: Generate income; if assigned, acquire stock at discount
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Enums\Side;
use MarketDataApp\Enums\Range;
use MarketDataApp\Exceptions\ApiException;

// Parse arguments
$symbol = null;
$budget = null; // null = no budget filter
$targetDelta = -0.25;
$minPremium = 0.50;
$targetDte = 35;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif (str_starts_with($arg, '--budget=')) {
        $budget = (float) substr($arg, 9);
    } elseif (str_starts_with($arg, '--delta=')) {
        $targetDelta = (float) substr($arg, 8);
        if ($targetDelta > 0) $targetDelta = -$targetDelta; // Ensure negative
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
    fprintf(STDERR, "Usage: php cash-secured-put.php SYMBOL [options]\n");
    exit(1);
}

function showHelp(): void
{
    echo <<<HELP
Cash-Secured Put Strategy Screener

Usage:
  php cash-secured-put.php SYMBOL [options]

Arguments:
  SYMBOL              Ticker symbol (e.g., AAPL)

Options:
  --budget=N          Maximum cash to secure puts (default: no limit)
  --delta=N           Target delta, e.g., -0.25 (default: -0.25)
  --min-premium=N     Minimum premium per share (default: 0.50)
  --dte=N             Target days to expiration (default: 35)
  --help, -h          Show this help message

Strategy Parameters:
  - Sells OTM puts secured by cash (strike x 100 per contract)
  - Delta -0.20 to -0.30 = 70-80% probability of profit
  - If assigned, you buy stock at strike - premium received
  - Focus on stocks you'd want to own at the strike price

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

    echo "\n=== CASH-SECURED PUT SCREENER ===\n";
    echo "Symbol: {$symbol}\n";
    echo "Target Delta: {$targetDelta}\n";
    echo "Target DTE: ~{$targetDte} days\n";
    if ($budget) {
        echo "Budget: " . formatCurrency($budget) . "\n";
    }
    echo "\n";

    // Get current stock price
    $quote = $client->stocks->quote($symbol, fifty_two_week: true);
    if ($quote->status !== 'ok') {
        throw new \RuntimeException("Could not fetch quote for {$symbol}");
    }

    $stockPrice = $quote->last;

    echo "Current Price: " . formatCurrency($stockPrice) . "\n";

    if ($quote->fifty_two_week_high && $quote->fifty_two_week_low) {
        $range = $quote->fifty_two_week_high - $quote->fifty_two_week_low;
        $position = ($stockPrice - $quote->fifty_two_week_low) / $range * 100;
        echo "52-Week Range: " . formatCurrency($quote->fifty_two_week_low) .
             " - " . formatCurrency($quote->fifty_two_week_high) .
             " (" . number_format($position, 0) . "% position)\n";
    }
    echo "\n";

    // Fetch OTM puts
    $chain = $client->options->option_chain(
        symbol: $symbol,
        dte: $targetDte,
        side: Side::PUT,
        range: Range::OUT_OF_THE_MONEY,
        min_bid: $minPremium,
        min_open_interest: 50
    );

    if ($chain->status !== 'ok' || empty($chain->option_chains)) {
        echo "No cash-secured put candidates found matching criteria.\n";
        exit(0);
    }

    // Filter and sort by delta proximity
    $candidates = [];
    foreach ($chain->getAllQuotes() as $option) {
        // Must have delta
        if ($option->delta === null) continue;

        // Calculate metrics
        $premium = $option->bid;
        $cashRequired = $option->strike * 100; // Per contract

        // Filter by budget if specified
        if ($budget && $cashRequired > $budget) continue;

        $effectiveBuyPrice = $option->strike - $premium;
        $discountPct = ($stockPrice - $effectiveBuyPrice) / $stockPrice;
        $returnOnCash = $premium * 100 / $cashRequired;
        $annualizedReturn = $returnOnCash * (365 / $option->dte);
        $spreadPct = $option->bid > 0 ? ($option->ask - $option->bid) / $option->bid : 1;

        // Skip wide spreads
        if ($spreadPct > 0.25) continue;

        $candidates[] = [
            'option' => $option,
            'premium' => $premium,
            'cashRequired' => $cashRequired,
            'effectiveBuyPrice' => $effectiveBuyPrice,
            'discountPct' => $discountPct,
            'returnOnCash' => $returnOnCash,
            'annualizedReturn' => $annualizedReturn,
            'spreadPct' => $spreadPct,
            'deltaDiff' => abs($option->delta - $targetDelta),
        ];
    }

    // Sort by delta proximity
    usort($candidates, fn($a, $b) => $a['deltaDiff'] <=> $b['deltaDiff']);

    echo "TOP CASH-SECURED PUT CANDIDATES\n";
    echo str_repeat('-', 95) . "\n";
    printf("%-18s %7s %5s %7s %10s %8s %8s %9s %8s\n",
        'Contract', 'Strike', 'DTE', 'Bid', 'Cash Req', 'Delta', 'IV', 'Discount', 'Ann.Ret');
    echo str_repeat('-', 95) . "\n";

    $count = 0;
    foreach ($candidates as $c) {
        if ($count >= 10) break;

        $opt = $c['option'];
        printf("%-18s %7s %5d %7s %10s %8.2f %7.0f%% %8.1f%% %7.1f%%\n",
            $opt->option_symbol,
            formatCurrency($opt->strike),
            $opt->dte,
            formatCurrency($opt->bid),
            formatCurrency($c['cashRequired']),
            $opt->delta,
            ($opt->implied_volatility ?? 0) * 100,
            $c['discountPct'] * 100,
            $c['annualizedReturn'] * 100
        );
        $count++;
    }

    echo str_repeat('-', 95) . "\n";

    // Show best candidate details
    if (!empty($candidates)) {
        $best = $candidates[0];
        $opt = $best['option'];

        echo "\nBEST MATCH (closest to target delta {$targetDelta}):\n";
        echo "  Contract: {$opt->option_symbol}\n";
        echo "  Strike: " . formatCurrency($opt->strike) . " (" .
             number_format(($opt->strike / $stockPrice - 1) * 100, 1) . "% from current)\n";
        echo "  Premium: " . formatCurrency($best['premium']) . " per share (" .
             formatCurrency($best['premium'] * 100) . " per contract)\n";
        echo "  Cash Required: " . formatCurrency($best['cashRequired']) . "\n";
        echo "  Delta: {$opt->delta} (~" . number_format((1 + $opt->delta) * 100, 0) .
             "% probability of profit)\n";
        echo "  If Assigned:\n";
        echo "    - Effective Buy Price: " . formatCurrency($best['effectiveBuyPrice']) . "\n";
        echo "    - Discount vs Current: " . number_format($best['discountPct'] * 100, 1) . "%\n";
        echo "  Return on Cash: " . number_format($best['returnOnCash'] * 100, 2) . "% (" .
             number_format($best['annualizedReturn'] * 100, 1) . "% annualized)\n";
        echo "  Expiration: {$opt->expiration->format('M j, Y')} ({$opt->dte} days)\n";

        // Show multiple contract scenarios if budget allows
        if ($budget) {
            $maxContracts = floor($budget / $best['cashRequired']);
            if ($maxContracts > 1) {
                $totalPremium = $best['premium'] * 100 * $maxContracts;
                $totalCash = $best['cashRequired'] * $maxContracts;
                echo "\n  With Budget " . formatCurrency($budget) . ":\n";
                echo "    - Max Contracts: " . $maxContracts . "\n";
                echo "    - Total Premium: " . formatCurrency($totalPremium) . "\n";
                echo "    - Cash Secured: " . formatCurrency($totalCash) . "\n";
            }
        }
    }

} catch (ApiException $e) {
    fprintf(STDERR, "API Error: %s\n", $e->getMessage());
    exit(1);
} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

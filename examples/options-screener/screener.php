#!/usr/bin/env php
<?php

/**
 * Options Screener
 *
 * Screen for liquid options opportunities based on strategy criteria.
 *
 * Usage:
 *   php screener.php AAPL                    # Screen single symbol
 *   php screener.php AAPL,MSFT,GOOGL         # Screen multiple symbols
 *   php screener.php AAPL --strategy=call    # Covered call focus
 *   php screener.php AAPL --strategy=put     # Cash-secured put focus
 *   php screener.php AAPL --dte=45           # Specific DTE
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

// Autoload the SDK
require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Enums\Side;
use MarketDataApp\Enums\Range;
use MarketDataApp\Exceptions\ApiException;

// Parse command line arguments
$symbols = [];
$strategy = 'both'; // 'call', 'put', or 'both'
$targetDte = 30;
$minVolume = 10;
$minOpenInterest = 100;
$maxBidAskSpread = 0.20; // 20% max spread

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif (str_starts_with($arg, '--strategy=')) {
        $strategy = substr($arg, 11);
    } elseif (str_starts_with($arg, '--dte=')) {
        $targetDte = (int) substr($arg, 6);
    } elseif (str_starts_with($arg, '--min-volume=')) {
        $minVolume = (int) substr($arg, 13);
    } elseif (str_starts_with($arg, '--min-oi=')) {
        $minOpenInterest = (int) substr($arg, 9);
    } elseif (!str_starts_with($arg, '-')) {
        // Parse comma-separated symbols
        $symbols = array_merge($symbols, array_map('trim', explode(',', strtoupper($arg))));
    }
}

if (empty($symbols)) {
    fprintf(STDERR, "Error: Please provide at least one symbol\n");
    fprintf(STDERR, "Usage: php screener.php SYMBOL [options]\n");
    exit(1);
}

/**
 * Display help information
 */
function showHelp(): void
{
    echo <<<HELP
Options Screener - Find liquid options opportunities

Usage:
  php screener.php SYMBOL [options]

Arguments:
  SYMBOL            Ticker symbol(s), comma-separated (e.g., AAPL or AAPL,MSFT)

Options:
  --strategy=TYPE   Screen for 'call', 'put', or 'both' (default: both)
  --dte=N           Target days to expiration (default: 30)
  --min-volume=N    Minimum volume (default: 10)
  --min-oi=N        Minimum open interest (default: 100)
  --help, -h        Show this help message

Examples:
  php screener.php AAPL                    Screen AAPL for calls and puts
  php screener.php AAPL --strategy=call    Covered call candidates
  php screener.php AAPL --strategy=put     Cash-secured put candidates
  php screener.php AAPL,MSFT --dte=45      Multiple symbols, 45 DTE

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Format currency
 */
function formatCurrency(float $value): string
{
    return '$' . number_format($value, 2);
}

/**
 * Format percentage
 */
function formatPercent(float $value, int $decimals = 1): string
{
    return number_format($value * 100, $decimals) . '%';
}

/**
 * Calculate bid-ask spread percentage
 */
function calcSpreadPct(float $bid, float $ask): float
{
    if ($bid <= 0) return 1.0;
    return ($ask - $bid) / $bid;
}

/**
 * Calculate annualized return from premium
 */
function calcAnnualizedReturn(float $premium, float $strike, int $dte): float
{
    if ($strike <= 0 || $dte <= 0) return 0;
    $returnPct = $premium / $strike;
    return $returnPct * (365 / $dte);
}

try {
    // Initialize the client
    $client = new Client();

    foreach ($symbols as $symbol) {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "OPTIONS SCREENER: {$symbol}\n";
        echo str_repeat('=', 80) . "\n";

        // First, get stock quote for current price
        $quote = $client->stocks->quote($symbol);
        if ($quote->status !== 'ok') {
            echo "Could not fetch quote for {$symbol}\n";
            continue;
        }

        $stockPrice = $quote->last;
        echo "\nStock Price: " . formatCurrency($stockPrice) . "\n";
        echo "Target DTE: {$targetDte} days\n";
        echo "Min Volume: {$minVolume} | Min OI: {$minOpenInterest}\n\n";

        // Fetch option chain with filters
        $side = match ($strategy) {
            'call' => Side::CALL,
            'put' => Side::PUT,
            default => null,
        };

        $chain = $client->options->option_chain(
            symbol: $symbol,
            dte: $targetDte,
            side: $side,
            range: Range::OUT_OF_THE_MONEY, // Focus on OTM for income strategies
            min_volume: $minVolume,
            min_open_interest: $minOpenInterest
        );

        if ($chain->status !== 'ok' || empty($chain->option_chains)) {
            echo "No options matching criteria found for {$symbol}\n";
            continue;
        }

        // Separate calls and puts using getAllQuotes() to flatten the nested structure
        $calls = [];
        $puts = [];

        foreach ($chain->getAllQuotes() as $option) {
            $spreadPct = calcSpreadPct($option->bid, $option->ask);
            if ($spreadPct > $maxBidAskSpread) {
                continue; // Skip illiquid options
            }

            if ($option->side === Side::CALL) {
                $calls[] = $option;
            } else {
                $puts[] = $option;
            }
        }

        // Display calls (covered call candidates)
        if ($strategy !== 'put' && !empty($calls)) {
            echo "COVERED CALL CANDIDATES (Sell OTM Calls)\n";
            echo str_repeat('-', 80) . "\n";
            printf("%-20s %8s %8s %8s %6s %8s %10s %8s\n",
                'Contract', 'Strike', 'Bid', 'Ask', 'Delta', 'IV', 'Ann.Return', 'OI');
            echo str_repeat('-', 80) . "\n";

            // Sort by delta (closest to 0.30)
            usort($calls, function ($a, $b) {
                $targetDelta = 0.30;
                $aDiff = abs(abs($a->delta ?? 0) - $targetDelta);
                $bDiff = abs(abs($b->delta ?? 0) - $targetDelta);
                return $aDiff <=> $bDiff;
            });

            $shown = 0;
            foreach ($calls as $call) {
                if ($shown >= 10) break;

                $annReturn = calcAnnualizedReturn($call->bid, $call->strike, $call->dte);

                printf("%-20s %8s %8s %8s %6.2f %7.0f%% %9.1f%% %8s\n",
                    $call->option_symbol,
                    formatCurrency($call->strike),
                    formatCurrency($call->bid),
                    formatCurrency($call->ask),
                    $call->delta ?? 0,
                    ($call->implied_volatility ?? 0) * 100,
                    $annReturn * 100,
                    number_format($call->open_interest)
                );
                $shown++;
            }
            echo "\n";
        }

        // Display puts (cash-secured put candidates)
        if ($strategy !== 'call' && !empty($puts)) {
            echo "CASH-SECURED PUT CANDIDATES (Sell OTM Puts)\n";
            echo str_repeat('-', 80) . "\n";
            printf("%-20s %8s %8s %8s %6s %8s %10s %8s\n",
                'Contract', 'Strike', 'Bid', 'Ask', 'Delta', 'IV', 'Ann.Return', 'OI');
            echo str_repeat('-', 80) . "\n";

            // Sort by delta (closest to -0.25)
            usort($puts, function ($a, $b) {
                $targetDelta = -0.25;
                $aDiff = abs(($a->delta ?? 0) - $targetDelta);
                $bDiff = abs(($b->delta ?? 0) - $targetDelta);
                return $aDiff <=> $bDiff;
            });

            $shown = 0;
            foreach ($puts as $put) {
                if ($shown >= 10) break;

                $annReturn = calcAnnualizedReturn($put->bid, $put->strike, $put->dte);

                printf("%-20s %8s %8s %8s %6.2f %7.0f%% %9.1f%% %8s\n",
                    $put->option_symbol,
                    formatCurrency($put->strike),
                    formatCurrency($put->bid),
                    formatCurrency($put->ask),
                    $put->delta ?? 0,
                    ($put->implied_volatility ?? 0) * 100,
                    $annReturn * 100,
                    number_format($put->open_interest)
                );
                $shown++;
            }
            echo "\n";
        }

        // Summary
        echo "Summary:\n";
        echo "  Calls found: " . count($calls) . "\n";
        echo "  Puts found: " . count($puts) . "\n";
    }

} catch (ApiException $e) {
    fprintf(STDERR, "API Error: %s\n", $e->getMessage());
    exit(1);
} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

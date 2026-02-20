<?php

/**
 * Quick Start Guide
 *
 * @see quick_start.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Single stock quote
$quote = $client->stocks->quote('AAPL');
echo "AAPL: \${$quote->last} (Bid: \${$quote->bid} x {$quote->bid_size}, Ask: \${$quote->ask} x {$quote->ask_size})\n";
echo "Volume: " . number_format($quote->volume) . ", Updated: {$quote->updated->format('Y-m-d H:i:s')}\n\n";

// Historical candles
$candles = $client->stocks->candles('AAPL', '-5 days', 'today', 'D');
echo "Daily Candles:\n";
foreach ($candles->candles as $candle) {
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f | Vol: %s\n",
        $candle->timestamp->format('Y-m-d'),
        $candle->open, $candle->high, $candle->low, $candle->close,
        number_format($candle->volume)
    );
}
echo "\n";

// Multiple quotes
$quotes = $client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL']);
echo "Multiple Quotes:\n";
foreach ($quotes->quotes as $q) {
    echo "  {$q->symbol}: \${$q->last}\n";
}
echo "\n";

// Market status
$status = $client->markets->status();
echo "Market Status: {$status->statuses[0]->status}\n\n";

// Rate limits
$rl = $client->rate_limits;
echo "Rate Limits: {$rl->remaining}/{$rl->limit} remaining, resets {$rl->reset->format('Y-m-d g:i A')}\n";

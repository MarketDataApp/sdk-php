<?php

/**
 * Rate Limit Tracking - Run with: php examples/rate_limit_tracking.php
 *
 * Demonstrates how daily rate limits work and the difference between
 * free trial symbols (AAPL) and paid symbols.
 *
 * See rate_limit_tracking.md for full documentation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

echo "=== Daily Rate Limit Tracking ===\n\n";

// Show initial limits
if ($client->rate_limits !== null) {
    $rl = $client->rate_limits;
    echo "Your Daily Limits:\n";
    echo "  {$rl->remaining} / {$rl->limit} credits remaining\n";
    echo "  Resets: {$rl->reset->format('Y-m-d g:i A T')} (9:30 AM ET)\n\n";
}

echo "--- Stock Quotes ---\n";

// Free trial symbol - no credits consumed
$quote = $client->stocks->quote('AAPL');
echo "AAPL [FREE] @ \${$quote->last} | Credits: {$client->rate_limits->consumed}\n";

// Paid symbols - 1 credit each
foreach (['SPY', 'MSFT', 'GOOGL'] as $symbol) {
    $quote = $client->stocks->quote($symbol);
    echo "{$symbol} [PAID] @ \${$quote->last} | Credits: {$client->rate_limits->consumed}\n";
}

// Summary
echo "\n=== Summary ===\n";
$rl = $client->rate_limits;
$used = $rl->limit - $rl->remaining;
echo "Daily credits used: {$used} / {$rl->limit}\n";
echo "See rate_limit_tracking.md for more details.\n";

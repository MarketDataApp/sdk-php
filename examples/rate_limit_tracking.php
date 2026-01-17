<?php

/**
 * Example: Monitor Rate Limits During API Requests
 *
 * This example demonstrates how to:
 * 1. Access rate limit information from the client's automatic tracking
 * 2. Monitor rate limit consumption as you make API requests
 * 3. Check remaining requests before making additional calls
 *
 * The MarketData PHP SDK automatically tracks rate limits for you. After each
 * successful API request, the client's rate_limits property is automatically
 * updated with the latest information from the API response headers.
 *
 * Usage:
 *     php examples/rate_limit_tracking.php
 *
 * The token will be automatically read from MARKETDATA_TOKEN environment variable
 * or .env file. You can set it with:
 *     export MARKETDATA_TOKEN=your_token_here
 * Or create a .env file in the project root:
 *     MARKETDATA_TOKEN=your_token_here
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;

// Initialize the client
// Token will be automatically obtained from MARKETDATA_TOKEN environment variable or .env file
// Rate limits are automatically fetched during client construction
$client = new Client();

// Symbols to fetch quotes for
$symbols = ['SPY', 'QQQ', 'EWZ', 'AAPL', 'MSFT'];

echo "Rate Limit Tracking Example\n";
echo str_repeat("=", 80) . "\n\n";

// Display initial rate limits (automatically set during client construction)
if ($client->rate_limits !== null) {
    echo "Initial Rate Limits:\n";
    echo "  Total Limit:    {$client->rate_limits->limit} credits\n";
    echo "  Remaining:      {$client->rate_limits->remaining} credits\n";
    echo "  Reset Time:     {$client->rate_limits->reset->toDateTimeString()} UTC\n";
    echo "\n";
} else {
    echo "Note: Rate limits will be available after the first API request.\n\n";
}

// Track previous remaining count to show changes
$previousRemaining = $client->rate_limits?->remaining;

// Make API requests and monitor rate limit changes
foreach ($symbols as $index => $symbol) {
    echo "Fetching quote for {$symbol}...\n";
    
    try {
        // Make a request - rate limits are automatically updated after this call
        $quote = $client->stocks->quote($symbol);
        
        // Access the automatically updated rate limits
        if ($client->rate_limits === null) {
            echo "  ⚠️  Rate limit information not available\n";
            continue;
        }
        
        $rateLimits = $client->rate_limits;
        
        // Display current rate limit status
        echo "  Current Rate Limits:\n";
        echo "    Remaining: {$rateLimits->remaining} / {$rateLimits->limit} credits\n";
        echo "    Consumed in this request: {$rateLimits->consumed} credit(s)\n";
        echo "    Reset at: {$rateLimits->reset->toDateTimeString()} UTC\n";
        
        // Show change from previous request
        if ($previousRemaining !== null) {
            $change = $previousRemaining - $rateLimits->remaining;
            if ($change > 0) {
                echo "    Change: -{$change} credit(s) (request consumed {$change} credit(s))\n";
            } elseif ($change < 0) {
                echo "    Change: +" . abs($change) . " credit(s) (rate limit window may have reset)\n";
            } else {
                echo "    Change: 0 credits (no credits consumed - this may be a free symbol)\n";
            }
        }
        
        // Display quote information
        echo "  Quote: {$quote->symbol} - Last Price: $" . number_format($quote->last, 2) . "\n";
        
        // Update previous remaining for next iteration
        $previousRemaining = $rateLimits->remaining;
        
    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
        if ($e->getCode() === 401) {
            echo "  Authentication failed. Please check your API token.\n";
            exit(1);
        }
    }
    
    echo "\n";
    
    // Small delay between requests
    if ($index < count($symbols) - 1) {
        usleep(500000); // 0.5 second delay
    }
}

// Display final summary
echo str_repeat("=", 80) . "\n";
echo "Summary:\n";
if ($client->rate_limits !== null) {
    $rateLimits = $client->rate_limits;
    $used = $rateLimits->limit - $rateLimits->remaining;
    $percentage = ($used / $rateLimits->limit) * 100;
    
    echo "  Final Rate Limits: {$rateLimits->remaining} / {$rateLimits->limit} credits remaining\n";
    echo "  Credits Used: {$used} ({$percentage}%)\n";
    echo "  Next Reset: {$rateLimits->reset->toDateTimeString()} UTC\n";
} else {
    echo "  Rate limit information not available\n";
}

echo "\n";
echo "Tip: You can access rate limits at any time using \$client->rate_limits\n";
echo "     The rate limits are automatically updated after every API request.\n";

<?php

/**
 * Error Handling Examples - Run with: php examples/error_handling.php
 *
 * This example demonstrates how to handle exceptions from the Market Data SDK
 * and extract the information needed for support tickets.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\MarketDataException;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Exceptions\UnauthorizedException;
use Psr\Log\NullLogger;

echo "=== Market Data SDK Error Handling Examples ===\n\n";

// Create a client (using NullLogger to reduce noise in this example)
$client = new Client(logger: new NullLogger());

// =============================================================================
// Example 1: Formatted Block for Support Tickets (RECOMMENDED)
// =============================================================================
echo "--- Example 1: Formatted Block for Support Tickets ---\n";

try {
    $quote = $client->stocks->quote('INVALID_SYMBOL');
} catch (MarketDataException $e) {
    // Just call getSupportInfo() - it's ready to copy/paste into a support ticket!
    echo $e->getSupportInfo() . "\n";
}
echo "\n";

// =============================================================================
// Example 2: Structured Logging (for log aggregation systems)
// =============================================================================
echo "--- Example 2: Structured Logging ---\n";

try {
    $quote = $client->stocks->quote('NONEXISTENT');
} catch (MarketDataException $e) {
    // getSupportContext() returns an array - perfect for JSON logging
    $context = $e->getSupportContext();

    // Send to your logger (Monolog, CloudWatch, Datadog, etc.)
    echo json_encode(['level' => 'error', 'context' => $context], JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// =============================================================================
// Example 3: Handle Specific Exception Types
// =============================================================================
echo "--- Example 3: Specific Exception Types ---\n";

try {
    $quote = $client->stocks->quote('AAPL');
    echo "Quote retrieved successfully!\n";
} catch (UnauthorizedException $e) {
    // 401 errors - invalid or missing token
    echo "Authentication failed. Check your MARKETDATA_TOKEN.\n";
    echo $e->getSupportInfo() . "\n";
} catch (BadStatusCodeError $e) {
    // Other 4xx errors - client errors like invalid parameters
    echo "Client error:\n";
    echo $e->getSupportInfo() . "\n";
} catch (RequestError $e) {
    // 5xx errors or network failures
    echo "Server/network error (may be temporary):\n";
    echo $e->getSupportInfo() . "\n";
} catch (ApiException $e) {
    // API business logic errors - like "no data found"
    echo "API error:\n";
    echo $e->getSupportInfo() . "\n";
}
echo "\n";

// =============================================================================
// Example 4: Custom Timezone (getTimestamp returns UTC)
// =============================================================================
echo "--- Example 4: Custom Timezone ---\n";

try {
    $quote = $client->stocks->quote('BADSYMBOL');
} catch (MarketDataException $e) {
    // getTimestamp() returns UTC - convert to any timezone you need
    $utc = $e->getTimestamp();
    $eastern = $utc->setTimezone(new \DateTimeZone('America/New_York'));
    $pacific = $utc->setTimezone(new \DateTimeZone('America/Los_Angeles'));
    $tokyo = $utc->setTimezone(new \DateTimeZone('Asia/Tokyo'));

    echo "UTC:     " . $utc->format('Y-m-d H:i:s T') . "\n";
    echo "Eastern: " . $eastern->format('Y-m-d H:i:s T') . "\n";
    echo "Pacific: " . $pacific->format('Y-m-d H:i:s T') . "\n";
    echo "Tokyo:   " . $tokyo->format('Y-m-d H:i:s T') . "\n";
}
echo "\n";

// =============================================================================
// Example 5: Access Individual Properties
// =============================================================================
echo "--- Example 5: Individual Properties ---\n";

try {
    $quote = $client->stocks->quote('ANOTHERBAD');
} catch (MarketDataException $e) {
    echo "Message:    " . $e->getMessage() . "\n";
    echo "Request ID: " . ($e->getRequestId() ?? 'N/A') . "\n";
    echo "URL:        " . ($e->getRequestUrl() ?? 'N/A') . "\n";
    echo "Timestamp:  " . $e->getTimestamp()->format('c') . " (UTC)\n";
    echo "HTTP Code:  " . $e->getCode() . "\n";

    // Raw response available if needed
    if ($response = $e->getResponse()) {
        echo "Response:   " . $response->getBody() . "\n";
    }
}
echo "\n";

// =============================================================================
// Example 6: Full Stack Trace with Context
// =============================================================================
echo "--- Example 6: Full Stack Trace ---\n";

try {
    $quote = $client->stocks->quote('FAKESYMBOL');
} catch (MarketDataException $e) {
    // __toString() includes the full stack trace plus context
    echo $e . "\n";
}
echo "\n";

// === Summary ===
//
// The MarketDataException provides a simple, unified way to get all the context you need
// for debugging or support. Here are the main methods available:
//
//   $e->getSupportInfo()    // Returns a formatted block (with America/New_York timezone), ready to paste into a support ticket
//   $e->getSupportContext() // Returns an associative array of full context, useful for structured logging (also uses America/New_York timezone)
//   $e->getRequestId()      // Retrieves the Cloudflare cf-ray request ID header (helps Market Data support identify your issue)
//   $e->getRequestUrl()     // Returns the full URL of the API request that caused the exception
//   $e->getTimestamp()      // Returns a DateTimeImmutable in UTC; you can convert it to any timezone you want
//   $e->getResponse()       // The raw PSR-7 Response object (if available)
//
// Timezone conversion example:
//   $local = $e->getTimestamp()->setTimezone(new DateTimeZone('America/Los_Angeles'));
//
// These helpers make it easy to gather all the information required for debugging or submitting a support ticket.



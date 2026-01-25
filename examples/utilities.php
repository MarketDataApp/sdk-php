<?php

/**
 * Utilities
 *
 * @see utilities.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());

// API status
$apiStatus = $client->utilities->api_status();
echo "API Status: {$apiStatus->status}\n";
foreach ($apiStatus->services as $svc) {
    printf("  %-30s %s (30d: %.2f%%)\n", $svc->service, $svc->status, $svc->uptime_percentage_30d);
}
echo "\n";

// Individual service status
$quotesStatus = $client->utilities->getServiceStatus('/v1/stocks/quotes/');
$candlesStatus = $client->utilities->getServiceStatus('/v1/stocks/candles/');
echo "Service Status:\n";
echo "  Stock Quotes: {$quotesStatus->value}\n";
echo "  Stock Candles: {$candlesStatus->value}\n\n";

// Request headers (useful for debugging)
$headers = $client->utilities->headers();
echo "Request Headers:\n";
foreach (get_object_vars($headers) as $name => $value) {
    if (strtolower($name) === 'authorization' && strlen($value) > 15) {
        $value = substr($value, 0, 15) . '...[REDACTED]';
    }
    echo "  {$name}: {$value}\n";
}
echo "\n";

// User rate limits
$user = $client->utilities->user();
$rl = $user->rate_limits;
echo "Rate Limits:\n";
echo "  Limit: " . number_format($rl->limit) . " | Remaining: " . number_format($rl->remaining) . "\n";
echo "  Resets: {$rl->reset->format('Y-m-d g:i A T')}\n\n";

// Automatic rate limit tracking
echo "Rate Limit Tracking:\n";
echo "  Before: {$client->rate_limits->remaining}\n";
$client->stocks->quote('AAPL');
$client->stocks->quote('MSFT');
$client->stocks->quote('GOOGL');
echo "  After 3 requests: {$client->rate_limits->remaining} (last consumed: {$client->rate_limits->consumed})\n\n";

// Uptime monitoring
echo "Low Uptime Services (<99.9%):\n";
$hasIssues = false;
foreach ($apiStatus->services as $svc) {
    if ($svc->uptime_percentage_30d < 99.9) {
        printf("  %s: %.2f%%\n", $svc->service, $svc->uptime_percentage_30d);
        $hasIssues = true;
    }
}
if (!$hasIssues) echo "  All services have 99.9%+ uptime\n";

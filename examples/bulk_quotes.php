<?php

/**
 * Bulk Quotes
 *
 * @see bulk_quotes.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());

// Single quote
$quote = $client->stocks->quote('AAPL');
echo "AAPL: \${$quote->last} | Change: \${$quote->change} ({$quote->change_percent}%)\n";
echo "  Bid/Ask: \${$quote->bid}/\${$quote->ask} | Volume: " . number_format($quote->volume) . "\n\n";

// Multiple quotes
echo "Tech Stocks:\n";
$quotes = $client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META']);
printf("  %-6s %10s %10s %10s\n", "Symbol", "Price", "Change", "Volume");
foreach ($quotes->quotes as $q) {
    printf("  %-6s %10.2f %9.2f%% %10s\n", $q->symbol, $q->last, $q->change_percent, number_format($q->volume));
}
echo "\n";

// Quote with 52-week range
$q52 = $client->stocks->quote('AAPL', fifty_two_week: true);
$range = $q52->fifty_two_week_high - $q52->fifty_two_week_low;
$position = ($q52->last - $q52->fifty_two_week_low) / $range * 100;
echo "AAPL 52-Week: \${$q52->fifty_two_week_low} - \${$q52->fifty_two_week_high} (Currently: " . number_format($position, 1) . "% of range)\n\n";

// Real-time prices (SmartMid)
echo "SmartMid Prices:\n";
$prices = $client->stocks->prices(['AAPL', 'MSFT', 'GOOGL']);
for ($i = 0; $i < count($prices->symbols); $i++) {
    printf("  %s: \$%.2f (as of %s)\n", $prices->symbols[$i], $prices->mid[$i], $prices->updated[$i]->format('H:i:s'));
}
echo "\n";

// Portfolio watchlist
$portfolio = ['AAPL' => 100, 'MSFT' => 50, 'GOOGL' => 25, 'NVDA' => 30];
$watchlist = $client->stocks->quotes(array_keys($portfolio));
echo "Portfolio:\n";
printf("  %-6s %8s %10s %10s %12s\n", "Symbol", "Shares", "Price", "Value", "Day Change");
$totalValue = $totalChange = 0;
foreach ($watchlist->quotes as $q) {
    $shares = $portfolio[$q->symbol];
    $value = $shares * $q->last;
    $dayChange = $shares * $q->change;
    $totalValue += $value;
    $totalChange += $dayChange;
    printf("  %-6s %8d %10.2f %10s %+11.2f\n", $q->symbol, $shares, $q->last, '$' . number_format($value, 0), $dayChange);
}
echo "  " . str_repeat("-", 52) . "\n";
printf("  %-6s %8s %10s %10s %+11.2f\n", "TOTAL", "", "", '$' . number_format($totalValue, 0), $totalChange);
echo "\n";

// Extended vs regular hours
$ext = $client->stocks->prices('AAPL', extended: true);
$reg = $client->stocks->prices('AAPL', extended: false);
printf("AAPL Extended: \$%.2f | Regular: \$%.2f\n", $ext->mid[0], $reg->mid[0]);

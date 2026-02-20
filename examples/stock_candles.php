<?php

/**
 * Stock Candles (Historical Price Data)
 *
 * @see stock_candles.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());

// Daily candles with date range
echo "Daily Candles (Jan 2-10, 2024):\n";
$daily = $client->stocks->candles('AAPL', '2024-01-02', '2024-01-10', 'D');
foreach ($daily->candles as $c) {
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f | Vol: %s\n",
        $c->timestamp->format('Y-m-d'), $c->open, $c->high, $c->low, $c->close, number_format($c->volume));
}
echo "\n";

// 5-minute intraday candles
echo "5-Minute Candles (Jan 3, 2024 9:30-10:00):\n";
$intraday = $client->stocks->candles('AAPL', '2024-01-03 09:30', '2024-01-03 10:00', '5');
foreach ($intraday->candles as $c) {
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $c->timestamp->format('H:i'), $c->open, $c->high, $c->low, $c->close);
}
echo "\n";

// Hourly candles
echo "Hourly Candles (Jan 3, 2024):\n";
$hourly = $client->stocks->candles('AAPL', '2024-01-03', '2024-01-03', 'H');
foreach ($hourly->candles as $c) {
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $c->timestamp->format('H:i'), $c->open, $c->high, $c->low, $c->close);
}
echo "\n";

// Weekly candles
echo "Weekly Candles (Jan 2024):\n";
$weekly = $client->stocks->candles('AAPL', '2024-01-01', '2024-02-01', 'W');
foreach ($weekly->candles as $c) {
    printf("  Week of %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $c->timestamp->format('Y-m-d'), $c->open, $c->high, $c->low, $c->close);
}
echo "\n";

// Monthly candles
echo "Monthly Candles (H1 2024):\n";
$monthly = $client->stocks->candles('AAPL', '2024-01-01', '2024-06-30', 'M');
foreach ($monthly->candles as $c) {
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $c->timestamp->format('Y-m'), $c->open, $c->high, $c->low, $c->close);
}
echo "\n";

// Bulk candles (multiple symbols)
echo "Bulk Candles (Jan 3, 2024):\n";
$symbols = ['AAPL', 'MSFT', 'GOOGL'];
$bulk = $client->stocks->bulkCandles($symbols, 'D', '2024-01-03');
foreach ($bulk->candles as $i => $c) {
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $symbols[$i], $c->open, $c->high, $c->low, $c->close);
}
echo "\n";

// Extended hours
echo "Extended Hours (Pre-Market Jan 3, 2024):\n";
$extended = $client->stocks->candles('AAPL', '2024-01-03 04:00', '2024-01-03 09:45', '15', extended: true);
$count = 0;
foreach ($extended->candles as $c) {
    if ($count++ >= 5) { echo "  ...\n"; break; }
    printf("  %s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $c->timestamp->format('H:i'), $c->open, $c->high, $c->low, $c->close);
}

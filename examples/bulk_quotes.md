# Bulk Quotes

This example demonstrates efficiently fetching stock quotes for single or multiple symbols, including real-time prices and portfolio tracking.

## Running the Example

```bash
php examples/bulk_quotes.php
```

## What It Covers

- Single stock quotes
- Multiple quotes in one request
- 52-week high/low data
- Real-time midpoint prices (SmartMid)
- Building a portfolio watchlist
- Extended hours pricing

## Single Stock Quote

```php
$quote = $client->stocks->quote('AAPL');

echo "Symbol: {$quote->symbol}\n";
echo "Last: \${$quote->last}\n";
echo "Change: \${$quote->change} ({$quote->change_percent}%)\n";
echo "Bid: \${$quote->bid} x {$quote->bid_size}\n";
echo "Ask: \${$quote->ask} x {$quote->ask_size}\n";
echo "Volume: " . number_format($quote->volume) . "\n";
```

## Multiple Quotes (Single Request)

Fetch quotes for multiple symbols efficiently:

```php
$symbols = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META'];
$quotes = $client->stocks->quotes($symbols);

foreach ($quotes->quotes as $q) {
    printf(
        "%s: $%.2f (%+.2f%%)\n",
        $q->symbol,
        $q->last,
        $q->change_percent
    );
}
```

## Quote with 52-Week Range

```php
$quote = $client->stocks->quote('AAPL', fifty_two_week: true);

echo "Current: \${$quote->last}\n";
echo "52-Week High: \${$quote->fifty_two_week_high}\n";
echo "52-Week Low: \${$quote->fifty_two_week_low}\n";

// Calculate position in range
$range = $quote->fifty_two_week_high - $quote->fifty_two_week_low;
$position = $quote->last - $quote->fifty_two_week_low;
$percentInRange = ($position / $range) * 100;
echo "Position in Range: " . number_format($percentInRange, 1) . "%\n";
```

## Real-Time Prices (SmartMid)

The `prices()` method returns midpoint prices calculated using the SmartMid model:

```php
$prices = $client->stocks->prices(['AAPL', 'MSFT', 'GOOGL']);

// Prices response has parallel arrays
for ($i = 0; $i < count($prices->symbols); $i++) {
    printf(
        "%s: \$%.2f (updated %s)\n",
        $prices->symbols[$i],
        $prices->mid[$i],
        $prices->updated[$i]->format('H:i:s')
    );
}
```

## Portfolio Watchlist

Build a real-time portfolio tracker:

```php
$portfolio = [
    'AAPL' => 100,  // 100 shares
    'MSFT' => 50,
    'GOOGL' => 25,
    'NVDA' => 30,
];

$quotes = $client->stocks->quotes(array_keys($portfolio));

$totalValue = 0;
$totalChange = 0;

foreach ($quotes->quotes as $q) {
    $shares = $portfolio[$q->symbol];
    $value = $shares * $q->last;
    $dayChange = $shares * $q->change;

    $totalValue += $value;
    $totalChange += $dayChange;

    printf(
        "%s: %d shares @ \$%.2f = \$%s (%+.2f)\n",
        $q->symbol,
        $shares,
        $q->last,
        number_format($value, 0),
        $dayChange
    );
}

printf("Total: \$%s (%+.2f)\n", number_format($totalValue, 0), $totalChange);
```

## Extended Hours Pricing

Compare regular session vs extended hours prices:

```php
// Include extended hours (default)
$extendedPrice = $client->stocks->prices('AAPL', extended: true);

// Regular session only
$regularPrice = $client->stocks->prices('AAPL', extended: false);

printf("Extended Hours: \$%.2f\n", $extendedPrice->mid[0]);
printf("Regular Session: \$%.2f\n", $regularPrice->mid[0]);
```

## Quote Properties

| Property | Type | Description |
|----------|------|-------------|
| `symbol` | string | Stock symbol |
| `last` | float | Last traded price |
| `bid` | float | Bid price |
| `bid_size` | int | Bid size |
| `ask` | float | Ask price |
| `ask_size` | int | Ask size |
| `mid` | float | Midpoint price |
| `change` | float | Price change ($) |
| `change_percent` | float | Price change (%) |
| `volume` | int | Trading volume |
| `updated` | Carbon | Quote timestamp |
| `fifty_two_week_high` | float | 52-week high (if requested) |
| `fifty_two_week_low` | float | 52-week low (if requested) |

## Prices Properties

The `prices()` response uses parallel arrays:

| Property | Type | Description |
|----------|------|-------------|
| `symbols` | array | Requested symbols |
| `mid` | array | Midpoint prices (SmartMid) |
| `change` | array | Price changes ($) |
| `changepct` | array | Price changes (%) |
| `updated` | array | Timestamps (Carbon) |

## quotes() vs prices()

| Method | Best For | Returns |
|--------|----------|---------|
| `quote()` | Single symbol with full details | Quote object |
| `quotes()` | Multiple symbols with full details | Quotes collection |
| `prices()` | Fast midpoint prices (SmartMid) | Prices object |

## See Also

- [quick_start.md](quick_start.md) - Basic SDK usage
- [stock_candles.md](stock_candles.md) - Historical price data
- [output_formats.md](output_formats.md) - Export quotes to CSV

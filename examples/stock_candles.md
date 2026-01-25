# Stock Candles (Historical Price Data)

This example demonstrates fetching historical OHLCV (Open, High, Low, Close, Volume) price data with various resolutions and options.

## Running the Example

```bash
php examples/stock_candles.php
```

## What It Covers

- Daily, weekly, and monthly candles
- Intraday candles (5-minute, hourly)
- Date range queries
- Bulk candles for multiple symbols
- Extended hours (pre-market/after-hours)
- Split-adjusted pricing

## Available Resolutions

| Resolution | Code | Description |
|------------|------|-------------|
| 1 minute   | `1`  | One-minute candles |
| 5 minutes  | `5`  | Five-minute candles |
| 15 minutes | `15` | Fifteen-minute candles |
| 30 minutes | `30` | Thirty-minute candles |
| Hourly     | `H`  | One-hour candles |
| Daily      | `D`  | Daily candles |
| Weekly     | `W`  | Weekly candles |
| Monthly    | `M`  | Monthly candles |

## Daily Candles with Date Range

```php
$candles = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2024-01-02',
    to: '2024-01-10',
    resolution: 'D'
);

foreach ($candles->candles as $candle) {
    printf(
        "%s | O: %.2f | H: %.2f | L: %.2f | C: %.2f | Vol: %s\n",
        $candle->timestamp->format('Y-m-d'),
        $candle->open,
        $candle->high,
        $candle->low,
        $candle->close,
        number_format($candle->volume)
    );
}
```

## Intraday Candles

```php
// 5-minute candles for a specific time range
$intraday = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2024-01-03 09:30',
    to: '2024-01-03 10:00',
    resolution: '5'
);

// Hourly candles for a full trading day
$hourly = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2024-01-03',
    to: '2024-01-03',
    resolution: 'H'
);
```

## Weekly and Monthly Candles

```php
// Weekly candles
$weekly = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2024-01-01',
    to: '2024-02-01',
    resolution: 'W'
);

// Monthly candles
$monthly = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2024-01-01',
    to: '2024-06-30',
    resolution: 'M'
);
```

## Bulk Candles (Multiple Symbols)

Fetch daily candles for multiple symbols in a single API call:

```php
$symbols = ['AAPL', 'MSFT', 'GOOGL'];
$bulk = $client->stocks->bulkCandles(
    symbols: $symbols,
    resolution: 'D',
    date: '2024-01-03'
);

// Candles are returned in the same order as input symbols
foreach ($bulk->candles as $i => $candle) {
    printf("%s | Close: %.2f\n", $symbols[$i], $candle->close);
}
```

## Extended Hours Data

Include pre-market and after-hours trading data:

```php
$extended = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2024-01-03 04:00',  // Pre-market starts at 4:00 AM ET
    to: '2024-01-03 20:00',    // After-hours ends at 8:00 PM ET
    resolution: '15',
    extended: true
);
```

## Split-Adjusted Data

Daily candles are split-adjusted by default. The `adjust_splits` parameter controls this:

```php
// Split-adjusted (default for daily candles)
$adjusted = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2020-08-28',
    to: '2020-09-02',
    resolution: 'D',
    adjust_splits: true
);
```

## Flexible Date Formats

The SDK accepts various date formats:

```php
// Relative dates
$candles = $client->stocks->candles('AAPL', '-5 days', 'today', 'D');

// ISO format
$candles = $client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D');

// With time
$candles = $client->stocks->candles('AAPL', '2024-01-03 09:30', '2024-01-03 16:00', '5');
```

## Candle Properties

Each candle object contains:

| Property | Type | Description |
|----------|------|-------------|
| `open` | float | Opening price |
| `high` | float | Highest price |
| `low` | float | Lowest price |
| `close` | float | Closing price |
| `volume` | int | Trading volume |
| `timestamp` | Carbon | Candle timestamp |

## See Also

- [quick_start.md](quick_start.md) - Basic SDK usage
- [bulk_quotes.md](bulk_quotes.md) - Real-time quote data
- [output_formats.md](output_formats.md) - Export candles to CSV

# Quick Start Guide

This example demonstrates the basics of using the Market Data PHP SDK to fetch financial data.

## Running the Example

```bash
php examples/quick_start.php
```

## What It Covers

- Creating a client (automatic token resolution)
- Fetching a single stock quote
- Getting historical candle data
- Fetching multiple quotes at once
- Checking market status
- Viewing rate limit information

## Creating a Client

The SDK automatically resolves your API token from:

1. Explicit parameter: `new Client('your_token')`
2. Environment variable: `MARKETDATA_TOKEN`
3. `.env` file in project root

```php
use MarketDataApp\Client;

// Token is automatically loaded from environment
$client = new Client();
```

## Getting a Stock Quote

```php
$quote = $client->stocks->quote('AAPL');

echo "Price: \${$quote->last}\n";
echo "Bid: \${$quote->bid} x {$quote->bid_size}\n";
echo "Ask: \${$quote->ask} x {$quote->ask_size}\n";
echo "Volume: " . number_format($quote->volume) . "\n";
```

## Getting Historical Candles

```php
$candles = $client->stocks->candles(
    symbol: 'AAPL',
    from: '-5 days',
    to: 'today',
    resolution: 'D'  // Daily candles
);

foreach ($candles->candles as $candle) {
    printf(
        "%s | O: %.2f | H: %.2f | L: %.2f | C: %.2f\n",
        $candle->timestamp->format('Y-m-d'),
        $candle->open,
        $candle->high,
        $candle->low,
        $candle->close
    );
}
```

## Multiple Quotes

```php
$quotes = $client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL']);

foreach ($quotes->quotes as $q) {
    echo "{$q->symbol}: \${$q->last}\n";
}
```

## Market Status

```php
$status = $client->markets->status();
echo "US Market: {$status->statuses[0]->status}\n";  // 'open' or 'closed'
```

## Rate Limits

The SDK automatically tracks your rate limits:

```php
$rl = $client->rate_limits;
echo "Remaining: {$rl->remaining} / {$rl->limit}\n";
echo "Resets: {$rl->reset->format('Y-m-d g:i A')}\n";
```

## See Also

- [stock_candles.md](stock_candles.md) - Detailed candle examples with different resolutions
- [bulk_quotes.md](bulk_quotes.md) - More quote examples and portfolio tracking
- [market_status.md](market_status.md) - Market calendar and holiday detection

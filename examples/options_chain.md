# Options Chain

This example demonstrates working with options data including chains, expirations, strikes, quotes, and Greeks.

## Running the Example

```bash
php examples/options_chain.php
```

## What It Covers

- Fetching option expiration dates
- Getting available strikes for an expiration
- Exploring option chains with filters
- Filtering by ITM/OTM, volume, open interest
- Option symbol lookup (human-readable to OCC)
- Getting individual option quotes
- Working with Greeks (delta, gamma, theta, vega)

## Get Expiration Dates

```php
$expirations = $client->options->expirations('AAPL');

foreach ($expirations->expirations as $exp) {
    echo $exp->format('Y-m-d') . "\n";
}
```

## Get Available Strikes

```php
$strikes = $client->options->strikes(
    symbol: 'AAPL',
    expiration: '2024-02-16'
);

// Strikes are organized by expiration date
$strikeList = $strikes->dates['2024-02-16'];
echo "Strikes: " . implode(', ', $strikeList) . "\n";
```

## Basic Option Chain

Use `from` and `to` to filter by expiration date:

```php
use MarketDataApp\Enums\Side;

// Get calls for a specific expiration
$callChain = $client->options->option_chain(
    symbol: 'AAPL',
    from: '2024-02-16',   // Filter by expiration date
    to: '2024-02-16',
    side: Side::CALL,
    strike_limit: 5       // 5 strikes closest to the money
);

foreach ($callChain->getAllQuotes() as $option) {
    printf(
        "%s | Strike: $%.2f | Bid: $%.2f | Ask: $%.2f\n",
        $option->option_symbol,
        $option->strike,
        $option->bid,
        $option->ask
    );
}
```

**Note:** The `date` parameter is for historical queries only. Use `from`/`to` to filter current options by expiration.

## Filter by ITM/OTM

```php
use MarketDataApp\Enums\Range;

// In-the-money calls only
$itmCalls = $client->options->option_chain(
    symbol: 'AAPL',
    from: '2024-02-16',
    to: '2024-02-16',
    side: Side::CALL,
    range: Range::IN_THE_MONEY,
    strike_limit: 5
);

// Out-of-the-money puts only
$otmPuts = $client->options->option_chain(
    symbol: 'AAPL',
    from: '2024-02-16',
    to: '2024-02-16',
    side: Side::PUT,
    range: Range::OUT_OF_THE_MONEY,
    strike_limit: 5
);
```

## Filter by Volume and Open Interest

```php
$liquidOptions = $client->options->option_chain(
    symbol: 'AAPL',
    from: '2024-02-16',
    to: '2024-02-16',
    side: Side::CALL,
    min_volume: 100,
    min_open_interest: 1000,
    strike_limit: 10
);

foreach ($liquidOptions->getAllQuotes() as $option) {
    printf(
        "$%.2f | Vol: %d | OI: %d\n",
        $option->strike,
        $option->volume,
        $option->open_interest
    );
}
```

## Option Symbol Lookup

Convert human-readable descriptions to OCC format:

```php
$lookup = $client->options->lookup("AAPL 1/17/25 $200 Call");
echo $lookup->option_symbol;  // AAPL250117C00200000
```

Supported formats:
- `AAPL 1/17/25 $200 Call`
- `AAPL Jan 17 2025 200 Call`
- `AAPL 2025-01-17 200 C`

## Get Option Quote

```php
$quote = $client->options->quotes('AAPL250117C00200000');

$q = $quote->quotes[0];
printf("Bid: $%.2f x %d\n", $q->bid, $q->bid_size);
printf("Ask: $%.2f x %d\n", $q->ask, $q->ask_size);
printf("Last: $%.2f\n", $q->last);
printf("IV: %.1f%%\n", $q->implied_volatility * 100);
```

## Working with Greeks

Option chain quotes include Greeks:

```php
$chain = $client->options->option_chain(
    symbol: 'AAPL',
    from: '2024-02-16',
    to: '2024-02-16',
    side: Side::CALL,
    strike_limit: 5
);

foreach ($chain->getAllQuotes() as $option) {
    printf(
        "$%.2f | Delta: %.4f | Gamma: %.4f | Theta: %.4f | Vega: %.4f | IV: %.1f%%\n",
        $option->strike,
        $option->delta ?? 0,
        $option->gamma ?? 0,
        $option->theta ?? 0,
        $option->vega ?? 0,
        ($option->implied_volatility ?? 0) * 100
    );
}
```

## OptionQuote Properties

| Property | Type | Description |
|----------|------|-------------|
| `option_symbol` | string | OCC symbol |
| `underlying` | string | Underlying ticker |
| `expiration` | Carbon | Expiration date |
| `side` | Side | CALL or PUT |
| `strike` | float | Strike price |
| `bid` | float | Bid price |
| `ask` | float | Ask price |
| `last` | float | Last traded price |
| `volume` | int | Day's volume |
| `open_interest` | int | Open interest |
| `implied_volatility` | float | IV (as decimal) |
| `delta` | float | Delta Greek |
| `gamma` | float | Gamma Greek |
| `theta` | float | Theta Greek |
| `vega` | float | Vega Greek |
| `in_the_money` | bool | ITM status |
| `dte` | int | Days to expiration |

## Convenience Methods

The `OptionChains` response provides helper methods:

```php
$chain = $client->options->option_chain(...);

// Get all quotes as flat array
$allQuotes = $chain->getAllQuotes();

// Get only calls or puts
$calls = $chain->getCalls();
$puts = $chain->getPuts();

// Get by strike
$quotes = $chain->getByStrike(200.0);

// Get all unique strikes
$strikes = $chain->getStrikes();

// Get expiration dates in chain
$dates = $chain->getExpirationDates();

// Total count
$count = $chain->count();
```

## See Also

- [quick_start.md](quick_start.md) - Basic SDK usage
- [bulk_quotes.md](bulk_quotes.md) - Stock quote data
- [output_formats.md](output_formats.md) - Export options data to CSV

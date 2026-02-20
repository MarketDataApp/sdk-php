# Rate Limit Tracking in the Market Data PHP SDK

The SDK automatically tracks your API rate limits after each request.

## Key Concepts

- **Daily limits** - Rate limits reset at 9:30 AM Eastern (market open)
- **Free trial symbols** - Symbols like AAPL don't consume credits
- **Automatic tracking** - The `$client->rate_limits` property updates after every request

## Quick Start

```php
$client = new MarketDataApp\Client();

// Make any API request
$quote = $client->stocks->quote('SPY');

// Check your daily limits
echo $client->rate_limits->remaining;  // Credits left today
echo $client->rate_limits->limit;      // Your daily limit
echo $client->rate_limits->consumed;   // Credits used by last request
echo $client->rate_limits->reset;      // When limits reset (Carbon instance)
```

## Credit Consumption

| Symbol Type | Credits per Request |
|-------------|---------------------|
| Free trial (AAPL) | 0 |
| Paid symbols | 1 |

```php
// Free symbol - 0 credits
$client->stocks->quote('AAPL');
echo $client->rate_limits->consumed; // 0

// Paid symbol - 1 credit
$client->stocks->quote('SPY');
echo $client->rate_limits->consumed; // 1
```

## The RateLimits Object

After any API request, `$client->rate_limits` contains:

| Property   | Type   | Description                                    |
|------------|--------|------------------------------------------------|
| `limit`    | int    | Your total daily credit allowance              |
| `remaining`| int    | Credits remaining until reset                  |
| `consumed` | int    | Credits consumed by the last request           |
| `reset`    | Carbon | DateTime when limits reset (9:30 AM ET)        |

## Example Output

```
=== Daily Rate Limit Tracking ===

Your Daily Limits:
  99825 / 100000 credits remaining
  Resets: 2026-01-26 9:30 AM EST (9:30 AM ET)

--- Stock Quotes ---
AAPL [FREE] @ $222.68 | Credits: 0
SPY [PAID] @ $607.13 | Credits: 1
MSFT [PAID] @ $444.06 | Credits: 1
GOOGL [PAID] @ $198.41 | Credits: 1

=== Summary ===
Daily credits used: 3 / 100000
```

## See Also

- [rate_limit_tracking.php](rate_limit_tracking.php) - Runnable example
- [Market Data API Documentation](https://www.marketdata.app/docs/api)

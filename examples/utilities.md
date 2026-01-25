# Utilities

This example demonstrates the utility endpoints for API monitoring, debugging, and rate limit tracking.

## Running the Example

```bash
php examples/utilities.php
```

## What It Covers

- API status and service uptime
- Individual service status checks
- Request header debugging
- User rate limit information
- Automatic rate limit tracking

## Check API Status

Get the status and uptime of all API services:

```php
$apiStatus = $client->utilities->api_status();

echo "Overall Status: {$apiStatus->status}\n";

foreach ($apiStatus->services as $service) {
    printf(
        "%s: %s (30d: %.2f%%, 90d: %.2f%%)\n",
        $service->service,
        $service->status,
        $service->uptime_percentage_30d,
        $service->uptime_percentage_90d
    );
}
```

Output:
```
Overall Status: ok
/v1/stocks/quotes/: online (30d: 99.99%, 90d: 99.98%)
/v1/stocks/candles/: online (30d: 99.99%, 90d: 99.99%)
/v1/options/chain/: online (30d: 99.98%, 90d: 99.97%)
...
```

## Check Individual Service

Check the status of a specific endpoint:

```php
use MarketDataApp\Enums\ApiStatusResult;

$quotesStatus = $client->utilities->getServiceStatus('/v1/stocks/quotes/');

echo "Stock Quotes: {$quotesStatus->value}\n";  // 'online' or 'offline'

if ($quotesStatus === ApiStatusResult::ONLINE) {
    echo "Service is available\n";
}
```

## Debug Request Headers

See what headers your requests are sending (useful for debugging authentication):

```php
$headers = $client->utilities->headers();

foreach (get_object_vars($headers) as $name => $value) {
    // Redact sensitive values
    if (strtolower($name) === 'authorization') {
        $value = substr($value, 0, 15) . '...[REDACTED]';
    }
    echo "{$name}: {$value}\n";
}
```

Output:
```
accept: application/json
accept-encoding: gzip, br
authorization: Bearer ****...[REDACTED]
host: api.marketdata.app
user-agent: marketdata-sdk-php/1.0.0
```

## User Rate Limits

Get your current rate limit status:

```php
$user = $client->utilities->user();
$rl = $user->rate_limits;

echo "Daily Limit: " . number_format($rl->limit) . " credits\n";
echo "Remaining: " . number_format($rl->remaining) . " credits\n";
echo "Consumed This Request: {$rl->consumed} credits\n";
echo "Reset Time: {$rl->reset->format('Y-m-d g:i A T')}\n";
```

Output:
```
Daily Limit: 100,000 credits
Remaining: 99,847 credits
Consumed This Request: 0 credits
Reset Time: 2024-01-10 2:30 PM UTC
```

## Automatic Rate Limit Tracking

The SDK automatically tracks rate limits from response headers:

```php
// Rate limits are updated after every request
$client->stocks->quote('AAPL');
$client->stocks->quote('MSFT');
$client->stocks->quote('GOOGL');

// Check current rate limits
$rl = $client->rate_limits;
echo "Remaining: {$rl->remaining}\n";
echo "Last consumed: {$rl->consumed}\n";
```

## Monitoring Example

Check API health before making requests:

```php
function checkApiHealth(Client $client): bool
{
    $status = $client->utilities->api_status();

    // Check if any service has low uptime
    foreach ($status->services as $service) {
        if ($service->uptime_percentage_30d < 99.0) {
            error_log("Warning: {$service->service} uptime is {$service->uptime_percentage_30d}%");
            return false;
        }
    }

    // Check specific critical service
    $quotes = $client->utilities->getServiceStatus('/v1/stocks/quotes/');
    if ($quotes->value !== 'online') {
        error_log("Error: Stock quotes service is offline");
        return false;
    }

    return true;
}

if (checkApiHealth($client)) {
    // Safe to make requests
    $quote = $client->stocks->quote('AAPL');
}
```

## Rate Limit Properties

| Property | Type | Description |
|----------|------|-------------|
| `limit` | int | Total daily credits |
| `remaining` | int | Credits remaining |
| `consumed` | int | Credits used in last request |
| `reset` | Carbon | When limits reset |

## ServiceStatus Properties

| Property | Type | Description |
|----------|------|-------------|
| `service` | string | Endpoint path |
| `status` | string | 'online' or 'offline' |
| `online` | bool | Boolean status |
| `uptime_percentage_30d` | float | 30-day uptime % |
| `uptime_percentage_90d` | float | 90-day uptime % |
| `updated` | Carbon | Last status update |

## Best Practices

1. **Check rate limits before bulk operations**
   ```php
   if ($client->rate_limits->remaining < 100) {
       echo "Low on credits, waiting for reset\n";
   }
   ```

2. **Monitor uptime for critical services**
   ```php
   $status = $client->utilities->getServiceStatus('/v1/stocks/quotes/');
   if ($status->value !== 'online') {
       // Use fallback or notify
   }
   ```

3. **Log rate limit consumption**
   ```php
   $quote = $client->stocks->quote('AAPL');
   $logger->info("Credits remaining: {$client->rate_limits->remaining}");
   ```

## See Also

- [quick_start.md](quick_start.md) - Basic SDK usage
- [rate_limit_tracking.md](rate_limit_tracking.md) - Detailed rate limit examples
- [logging.md](logging.md) - SDK logging configuration

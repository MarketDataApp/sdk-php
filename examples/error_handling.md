# Error Handling in the Market Data PHP SDK

The SDK provides a unified exception hierarchy with built-in helpers for debugging and support ticket submission.

## Quick Start

Catch any SDK exception and get support-ready information instantly:

```php
use MarketDataApp\Client;
use MarketDataApp\Exceptions\MarketDataException;

$client = new Client();

try {
    $quote = $client->stocks->quote('INVALID');
} catch (MarketDataException $e) {
    // Formatted block ready to paste into a support ticket
    echo $e->getSupportInfo();
}
```

Output:
```
--- MARKET DATA SUPPORT INFO ---
Timestamp:    2026-01-24 10:30:45 EST
Request ID:   9c340f7d6be275f3-EZE
URL:          https://api.marketdata.app/v1/stocks/quotes/INVALID/?format=json
HTTP Code:    400
Error:        Bad parameters, please check API documentation.
--------------------------------
```

## Exception Hierarchy

All SDK exceptions extend `MarketDataException`, which provides common helper methods:

```
MarketDataException (base class)
├── ApiException         - API business logic errors (e.g., "no data found")
├── BadStatusCodeError   - HTTP 4xx client errors
│   └── UnauthorizedException - HTTP 401 authentication errors
└── RequestError         - HTTP 5xx server errors or network failures
```

## Handling Specific Exception Types

```php
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\MarketDataException;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Exceptions\UnauthorizedException;

try {
    $quote = $client->stocks->quote('AAPL');
} catch (UnauthorizedException $e) {
    // 401 errors - invalid or missing token
    echo "Authentication failed. Check your MARKETDATA_TOKEN.\n";
} catch (BadStatusCodeError $e) {
    // Other 4xx errors - client errors like invalid parameters
    echo "Client error: " . $e->getMessage() . "\n";
} catch (RequestError $e) {
    // 5xx errors or network failures - may be temporary
    echo "Server/network error. Consider retrying.\n";
} catch (ApiException $e) {
    // API business logic errors - like "no data found"
    echo "API error: " . $e->getMessage() . "\n";
}
```

## Support Ticket Helpers

### getSupportInfo()

Returns a formatted block with all context needed for a support ticket:

```php
try {
    $quote = $client->stocks->quote('BADSYMBOL');
} catch (MarketDataException $e) {
    echo $e->getSupportInfo();
}
```

Output:
```
--- MARKET DATA SUPPORT INFO ---
Timestamp:    2026-01-24 10:30:45 EST
Request ID:   9c340f7d6be275f3-EZE
URL:          https://api.marketdata.app/v1/stocks/quotes/BADSYMBOL/?format=json
HTTP Code:    400
Error:        Bad parameters, please check API documentation.
--------------------------------
```

The timestamp uses America/New_York timezone.

### getSupportContext()

Returns an associative array - perfect for structured logging:

```php
try {
    $quote = $client->stocks->quote('BADSYMBOL');
} catch (MarketDataException $e) {
    $context = $e->getSupportContext();

    // Send to your logger (Monolog, CloudWatch, Datadog, etc.)
    $logger->error('API request failed', $context);

    // Or encode as JSON
    echo json_encode($context, JSON_PRETTY_PRINT);
}
```

Output:
```json
{
    "exception": "ApiException",
    "message": "No data found",
    "request_id": "9c293d470aa6adae-EZE",
    "url": "https://api.marketdata.app/v1/stocks/quotes/BADSYMBOL/?format=json",
    "timestamp": "2026-01-24T10:30:45-05:00",
    "http_code": 200
}
```

## Accessing Individual Properties

```php
try {
    $quote = $client->stocks->quote('BADSYMBOL');
} catch (MarketDataException $e) {
    // Error message
    echo $e->getMessage();           // "No data found"

    // HTTP status code
    echo $e->getCode();              // 200 (or 401, 500, etc.)

    // Cloudflare request ID (for support)
    echo $e->getRequestId();         // "9c293d470aa6adae-EZE"

    // Full request URL
    echo $e->getRequestUrl();        // "https://api.marketdata.app/v1/..."

    // Timestamp (DateTimeImmutable in UTC)
    echo $e->getTimestamp()->format('c');

    // Raw PSR-7 response (if available)
    $response = $e->getResponse();
    if ($response) {
        echo $response->getBody();
    }
}
```

## Custom Timezone Handling

The `getTimestamp()` method returns a `DateTimeImmutable` in UTC. Convert to any timezone:

```php
try {
    $quote = $client->stocks->quote('BADSYMBOL');
} catch (MarketDataException $e) {
    $utc = $e->getTimestamp();

    $eastern = $utc->setTimezone(new \DateTimeZone('America/New_York'));
    $pacific = $utc->setTimezone(new \DateTimeZone('America/Los_Angeles'));
    $tokyo = $utc->setTimezone(new \DateTimeZone('Asia/Tokyo'));

    echo "UTC:     " . $utc->format('Y-m-d H:i:s T') . "\n";
    echo "Eastern: " . $eastern->format('Y-m-d H:i:s T') . "\n";
    echo "Pacific: " . $pacific->format('Y-m-d H:i:s T') . "\n";
    echo "Tokyo:   " . $tokyo->format('Y-m-d H:i:s T') . "\n";
}
```

## Full Stack Trace

The `__toString()` method includes the full stack trace plus context:

```php
try {
    $quote = $client->stocks->quote('BADSYMBOL');
} catch (MarketDataException $e) {
    // Includes stack trace and all context
    echo $e;
}
```

## Method Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `getSupportInfo()` | `string` | Formatted block for support tickets (EST timezone) |
| `getSupportContext()` | `array` | Associative array for structured logging |
| `getRequestId()` | `?string` | Cloudflare cf-ray header value |
| `getRequestUrl()` | `?string` | Full URL of the failed request |
| `getTimestamp()` | `DateTimeImmutable` | When the error occurred (UTC) |
| `getResponse()` | `?ResponseInterface` | Raw PSR-7 response object |
| `getMessage()` | `string` | Error message |
| `getCode()` | `int` | HTTP status code |

## Best Practices

1. **Catch `MarketDataException`** as a fallback to handle any SDK error
2. **Use `getSupportInfo()`** when filing support tickets - it includes everything Market Data support needs
3. **Use `getSupportContext()`** for production logging to capture structured data
4. **Include the Request ID** when contacting support - it helps identify your specific request in server logs

## See Also

- [error_handling.php](error_handling.php) - Runnable example demonstrating all error handling features
- [logging.md](logging.md) - SDK logging configuration

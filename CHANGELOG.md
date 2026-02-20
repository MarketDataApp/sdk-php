# Changelog

## [Unreleased]

---

## v1.0.0 (2026-02-20)

**🎉 First Stable Release** - Production-ready PHP SDK for Market Data API with full feature parity with the Python SDK.

### Highlights

- **PHP 8.2+ Required** - Modern PHP with strict typing
- **100% Test Coverage** - Comprehensive unit and integration tests across PHP 8.2, 8.3, 8.4, and 8.5
- **Full Feature Parity** - Complete feature parity with the official Python SDK
- **Production Ready** - Battle-tested with automatic retry, rate limiting, and comprehensive logging
- **50+ Bug Fixes** - Extensive testing and fixes for edge cases, error handling, and API compatibility

### Breaking Changes

#### PHP Version Requirement
- **Minimum PHP version is now 8.2** (was 8.1 in v0.6.x)

#### Removed: bulkQuotes Method
The `bulkQuotes()` method has been removed. Use `quotes()` instead, which now supports multiple symbols.

```php
// Before (v0.6.x)
$bulkQuotes = $client->stocks->bulkQuotes(['AAPL', 'MSFT']);

// After (v1.0.0)
$quotes = $client->stocks->quotes(['AAPL', 'MSFT']);
```

#### Unified Options Quote Classes
The `Quote` and `OptionChainStrike` classes have been consolidated into a single `OptionQuote` class:

```php
// Before (v0.6.x)
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\OptionChainStrike;

// After (v1.0.0)
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
```

#### Client Constructor Changes
- Token parameter is now **optional** (auto-resolves from `MARKETDATA_TOKEN` env var or `.env` file)
- Invalid tokens now throw `UnauthorizedException` **during construction** (not on first API call)
- New optional `$logger` parameter for custom PSR-3 logger injection

```php
// Token auto-resolution (new in v1.0.0)
$client = new Client(); // Reads from MARKETDATA_TOKEN env var

// Token validation is now immediate
try {
    $client = new Client('invalid_token');
} catch (UnauthorizedException $e) {
    echo "Invalid token";
}
```

#### Options - option_chain() Parameter Changes
- **Renamed `min_bid_ask_spread` to `max_bid_ask_spread`** - The previous parameter name was incorrect and silently ignored by the API
- **Removed default `expiration=all`** - No longer sends a default expiration filter
- **Removed default `nonstandard=true`** - Non-standard contracts are no longer included by default
- **Changed `delta` parameter type to `string`** - Now accepts range expressions like `"0.3-0.5"`

#### Options - expirations() Parameter Changes
- **Changed `strike` parameter type to `int|float`** - Now accepts decimal strikes like `12.5` for non-standard options

#### Removed Unsupported Parameters
The following parameters were present in v0.6.x but were never supported by the API (silently ignored):
- **Candles**: Removed `exchange`, `country`, `adjust_dividends` parameters from `candles()`, `bulkCandles()`, and concurrent candle methods
- **Earnings**: Removed `datekey` parameter from `earnings()`

### New Features

#### PSR-3 Logging System
Comprehensive logging with configurable levels:

```php
// Configure via environment variable
putenv('MARKETDATA_LOGGING_LEVEL=DEBUG');
$client = new Client();

// Or inject custom PSR-3 logger (Monolog, Laravel, etc.)
$client = new Client(logger: $customLogger);
```

Log levels: `DEBUG`, `INFO`, `NOTICE`, `WARNING`, `ERROR`, `CRITICAL`, `ALERT`, `EMERGENCY`

#### Automatic Rate Limit Tracking
Rate limits are automatically tracked and accessible after each request:

```php
$quote = $client->stocks->quote('AAPL');

echo $client->rate_limits->remaining;  // Credits remaining
echo $client->rate_limits->limit;      // Total credits
echo $client->rate_limits->reset;      // Carbon datetime of reset
echo $client->rate_limits->consumed;   // Credits used in last request
```

#### Automatic Retry with Exponential Backoff
Built-in retry logic for transient failures:
- 3 retry attempts maximum
- Exponential backoff (0.5s - 5s)
- Only retries on 5xx server errors
- Checks API service status before retrying

#### New Exception Hierarchy
More specific exception handling:

```php
use MarketDataApp\Exceptions\UnauthorizedException;  // 401 errors
use MarketDataApp\Exceptions\BadStatusCodeError;     // Other 4xx errors
use MarketDataApp\Exceptions\RequestError;           // Network errors

try {
    $client = new Client($token);
    $quote = $client->stocks->quote('AAPL');
} catch (UnauthorizedException $e) {
    // Invalid or expired token
} catch (BadStatusCodeError $e) {
    // Other client errors (400, 403, 404, etc.)
} catch (RequestError $e) {
    // Network errors, timeouts
}
```

#### Enhanced Exception Context for Support Tickets
All SDK exceptions now provide first-class access to request context, making it easier to gather information for support tickets:

```php
try {
    $quote = $client->stocks->quote('AAPL');
} catch (MarketDataException $e) {
    // One-liner for support tickets - ready to copy/paste!
    echo $e->getSupportInfo();

    // Or get structured data for logging systems
    $logger->error('API Error', $e->getSupportContext());
}
```

New convenience methods:
- `getSupportInfo()` - Returns a pre-formatted string ready to paste into support tickets
- `getSupportContext()` - Returns an array with all context (perfect for JSON logging)

Individual property accessors:
- `getRequestId()` - Cloudflare request ID (cf-ray header)
- `getRequestUrl()` - Full URL that was requested
- `getTimestamp()` - `DateTimeImmutable` in UTC (convert to your timezone as needed)
- `getResponse()` - Raw PSR-7 response object
- Enhanced `__toString()` now includes timestamp, request ID, and URL

Note: `getSupportInfo()` and `getSupportContext()` automatically convert timestamps to America/New_York to match API logs for support tickets.

New base exception class:
- `MarketDataException` - All SDK exceptions now extend this base class, allowing you to catch all SDK exceptions with a single catch block

See `examples/error_handling.php` for complete usage examples.

#### New Endpoints & Methods

**Stocks - prices()**: Get SmartMid model prices for single or multiple symbols
```php
$prices = $client->stocks->prices(['AAPL', 'MSFT']);
```

**Options - quotes() with multiple symbols**: Concurrent fetching for multiple option symbols
```php
$quotes = $client->options->quotes(['AAPL250117C00200000', 'AAPL250117P00200000']);
```

**Utilities - user()**: Get user account information
```php
$user = $client->utilities->user();
```

#### Settings & Configuration
New Settings class with `.env` file support:

```env
# .env file
MARKETDATA_TOKEN=your_token_here
MARKETDATA_OUTPUT_FORMAT=JSON
MARKETDATA_LOGGING_LEVEL=INFO
MARKETDATA_MODE=LIVE
```

#### Cache Freshness Control (maxage)
Control the maximum acceptable age for cached data when using `mode=CACHED`:

```php
use MarketDataApp\Enums\Mode;
use MarketDataApp\Endpoints\Requests\Parameters;

// Accept cached data up to 5 minutes old
$params = new Parameters(mode: Mode::CACHED, maxage: 300);
$quote = $client->stocks->quote('AAPL', parameters: $params);

// Also accepts DateInterval or CarbonInterval
$params = new Parameters(mode: Mode::CACHED, maxage: new DateInterval('PT5M'));
```

If cached data is older than `maxage`, the API returns 204 (no content) with no credit charge, enabling cost-efficient fallback strategies.

#### Extended Hours Control
New `extended` parameter on `quote()`, `quotes()`, and `prices()` methods:

```php
// Get primary session quote only (no extended hours)
$quote = $client->stocks->quote('AAPL', extended: false);

// Default is extended: true (includes extended hours when available)
$quote = $client->stocks->quote('AAPL');
```

#### Options - AM/PM Settlement Filtering
New `am` and `pm` parameters on `option_chain()` for filtering index options by settlement type:

```php
// Get only AM-settled SPX options
$chain = $client->options->option_chain('SPX', am: true);

// Get only PM-settled SPXW options
$chain = $client->options->option_chain('SPX', pm: true);
```

#### New Enums
- `ApiStatusResult` - Service status (ONLINE, OFFLINE, UNKNOWN)
- `DateFormat` - CSV date formatting (TIMESTAMP, UNIX, SPREADSHEET)
- `Mode` - Data feed mode (LIVE, CACHED, DELAYED)

#### Response Object Enhancements
- All response objects implement `__toString()` for human-readable output
- New `FormatsForDisplay` trait for formatting currency, percentages, volumes
- New `ValidatesInputs` trait for input validation

#### Concurrent Request Support
- Up to 50 concurrent requests for bulk operations
- Automatic date range splitting for large intraday candle requests
- Concurrent fetching for multi-symbol options quotes

#### OptionChains Convenience Methods
```php
$chain = $client->options->option_chain('AAPL', expiration: '2025-01-17');

$chain->toQuotes();                    // Flatten to Quotes object
$chain->getAllQuotes();                // Get all quotes as array
$chain->getExpirationDates();          // Get expiration dates
$chain->getQuotesByExpiration($date);  // Filter by expiration
$chain->getCalls();                    // Get call options only
$chain->getPuts();                     // Get put options only
$chain->getByStrike(200.0);            // Filter by strike
$chain->getStrikes();                  // Get all strike prices
$chain->count();                       // Total quote count
```

### Migration from v0.6.x

1. **Update PHP version** to 8.2 or higher
2. **Replace `bulkQuotes()` with `quotes()`** for multi-symbol stock quotes
3. **Update Options imports** - use `OptionQuote` instead of `Quote` or `OptionChainStrike`
4. **Update exception handling** - catch `UnauthorizedException` during client construction
5. **Update `option_chain()` calls**:
   - Rename `min_bid_ask_spread` to `max_bid_ask_spread`
   - Remove reliance on default `expiration=all` and `nonstandard=true` if you were depending on them
   - Update `delta` values to strings if using range expressions
6. **Remove unsupported parameters** - if you were passing `exchange`, `country`, `adjust_dividends` to candles or `datekey` to earnings, remove them (they were silently ignored)
7. **Update dependencies**: `composer update`

### Dependencies

New required dependencies:
- `psr/log: ^3.0` - PSR-3 logging interface
- `vlucas/phpdotenv: ^5.5` - Environment file support

Updated development dependencies:
- `phpunit/phpunit: ^11.5.50` (was ^10.3.2)

### Bug Fixes

This release includes 50+ bug fixes addressing:
- CSV/HTML format handling and error detection
- Empty array and missing field guards across all response types
- Date parsing and automatic date range splitting for large requests
- Symbol whitespace trimming and URL encoding
- Boolean parameter encoding for API compatibility
- Endpoint URL construction and trailing slashes
- Concurrent request error handling and partial failure tolerance

---

## v0.6.0-beta

Added universal parameters to all endpoints with the ability to change format to CSV and HTML (beta).

## v0.5.0-beta

Minor improvements and bug fixes.

## v0.4.4-beta

Update options->option_chain to use enum values rather than the enum itself.

## v0.4.3-beta

Small bug fixes found from initial beta test

- Typo fixed Range::OUT_THE_MONEY > Range::OUT_OF_THE_MONEY
- Corrected stocks->quotes() endpoint url
- Changes OptionChain response to group strikes under expiration date

## v0.4.2-beta

This library is now in **beta**. Feel free to try it out and report any bugs you find back here.

- Added integration tests for all endpoints except Market (unavailable)
- Added more tests for more complete code coverage
- Tweaks to data structures based on results of integration test

## v0.4.1-alpha

- Changed all Carbon date endpoints to receive a string rather than a Carbon instance.

## v0.4.0-alpha

- Completed remaining endpoints: 
  - Options
    - expirations
    - lookup
    - strikes
    - option_chain
    - quotes
  - Utilities
    - api_status
    - headers
  - Mutual Funds
    - candles
  - Markets
    - status

## v0.3.0-alpha

- Completed Stocks endpoints: earnings, news.
- Add stubs for the rest of the endpoints.

## v0.2.0-alpha

- Added Stocks endpoints: quote, quotes, bulkQuotes, candles, bulkCandles.
- Added custom ApiException class to handle status = 'error' messages.
- Moved Responses to new directory.

## v0.1.0-alpha

- Initial release.

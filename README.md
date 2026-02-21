<div align="center">

# Market Data PHP SDK v1.1
### Access Financial Data with Ease

>This is the official PHP SDK for [Market Data](https://www.marketdata.app). It provides developers with a powerful, easy-to-use interface to obtain real-time and historical financial data. Ideal for building financial applications, trading bots, and investment strategies.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/MarketDataApp/sdk-php.svg?style=flat-square)](https://packagist.org/packages/MarketDataApp/sdk-php)
[![Tests](https://img.shields.io/github/actions/workflow/status/MarketDataApp/sdk-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/MarketDataApp/sdk-php/actions/workflows/run-tests.yml)
[![Codecov](https://codecov.io/gh/MarketDataApp/sdk-php/graph/badge.svg?token=5W2IB9F6RU)](https://codecov.io/github/MarketDataApp/sdk-php)
[![Total Downloads](https://img.shields.io/packagist/dt/MarketDataApp/sdk-php.svg?style=flat-square)](https://packagist.org/packages/MarketDataApp/sdk-php)
[![PHP Version](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-blue.svg?style=flat-square)](https://www.php.net/)

#### Connect With The Market Data Community

[![Website](https://img.shields.io/badge/Website-marketdata.app-blue)](https://www.marketdata.app/)
[![Discord](https://img.shields.io/badge/Discord-join%20chat-7389D8.svg?logo=discord&logoColor=ffffff)](https://discord.com/invite/GmdeAVRtnT)
[![Twitter](https://img.shields.io/twitter/follow/MarketDataApp?style=social)](https://twitter.com/MarketDataApp)
[![Helpdesk](https://img.shields.io/badge/Support-Ticketing-ff69b4.svg?logo=TicketTailor&logoColor=white)](https://www.marketdata.app/dashboard/)

</div>

## Features

- **Real-time Stock Data**: Prices, quotes, candles (OHLCV), earnings, and news
- **Options Trading Data**: Complete options chains, expirations, strikes, quotes, and lookup
- **Mutual Funds**: Historical candles and pricing data
- **Market Status**: Real-time market open/closed status for multiple countries
- **Multiple Output Formats**: JSON, CSV, or HTML formats
- **Built-in Retry Logic**: Automatic retry with exponential backoff for reliable data fetching
- **Rate Limit Tracking**: Automatic rate limit monitoring with easy access via `$client->rate_limits`
- **Type-Safe**: Full type hints and strict typing (PHP 8.2+)
- **Zero Config**: Works out of the box with sensible defaults

## Requirements

- PHP >= 8.2

## Installation

You can install the package via composer:

```bash
composer require MarketDataApp/sdk-php
```

## Configuration

The SDK requires a MarketData authentication token. You can provide it in two ways:

### Option 1: Environment variable (recommended)

Create a `.env` file in the project root:

```env
MARKETDATA_TOKEN=your_token_here
```

Or set it as an environment variable:

```bash
export MARKETDATA_TOKEN=your_token_here
```

### Option 2: Pass token directly

You can pass the token when creating a client instance:

```php
$client = new MarketDataApp\Client('your_token_here');
```

**Note:** If you provide a token explicitly, it will take precedence over environment variables.

## Unsupported API features

The SDK intentionally does not support certain REST API options. These are design decisions, not oversights.

- **`token` query parameter** — The REST API may accept `token` as a query parameter. The SDK does not and will not support this. Authentication is sent only via the `Authorization: Bearer` header. This keeps tokens out of URLs (and thus out of logs, caches, and referrers) and centralizes auth in one place.

- **`limit` and `offset`** — The REST API supports `limit` and `offset` for pagination. The SDK does not and will not support these. The SDK uses concurrent parallel requests instead to fetch data in bulk, so limit/offset-style pagination is not part of the design.

## Usage

```php
// Token will be automatically obtained from MARKETDATA_TOKEN environment variable or .env file
$client = new MarketDataApp\Client();

// Or provide the token explicitly
$client = new MarketDataApp\Client('your_api_token');

// Stocks
$candles = $client->stocks->candles('AAPL');
$bulk_candles = $client->stocks->bulkCandles(['AAPL', 'MSFT']);
$quote = $client->stocks->quote('AAPL');
$quotes = $client->stocks->quotes(['AAPL', 'MSFT']);
$earnings = $client->stocks->earnings(symbol: 'AAPL', from: '2023-01-01');
$news = $client->stocks->news(symbol: 'AAPL', from: '2023-01-01');

// Markets
$status = $client->markets->status(date: '2023-01-01');

// Mutual Funds
$candles = $client->mutual_funds->candles(
    symbol: 'VFINX',
    from: '2022-09-01',
    to: '2022-09-05',
    resolution: 'D'
);

// Options
$expirations = $client->options->expirations('AAPL');
$lookup = $client->options->lookup('AAPL 7/28/23 $200 Call');
$strikes = $client->options->strikes(
    symbol: 'AAPL',
    expiration: '2023-01-20',
    date: '2023-01-03',
);
$option_chain = $client->options->option_chain(
    symbol: 'AAPL',
    expiration: '2028-12-15',
    side: Side::CALL,
);
$quotes = $client->options->quotes('AAPL281215C00400000');

// Utilities
$status = $client->utilities->api_status();
$headers = $client->utilities->headers();
```

### Universal Parameters

All endpoints (other than utilities) supports universal parameters. 

For instance, you can change the format to CSV

```
$option_chain = $client->options->option_chain(
    symbol: 'AAPL',
    expiration: '2028-12-15',
    side: Side::CALL,
    parameters: new Parameters(format: Format::CSV),
);
```

## Testing

### Running Tests Locally

Run all tests with PHPUnit:

```bash
./vendor/bin/phpunit
```

### Testing Across PHP Versions

To test the SDK across all supported PHP versions (8.2, 8.3, 8.4, 8.5), use the provided script:

```bash
# Test all PHP versions (8.2, 8.3, 8.4, 8.5) with both prefer-lowest and prefer-stable
./test-with-act.sh

# Quick test: Test a specific PHP version only (prefer-stable)
./test-with-act.sh 8.5
./test-with-act.sh 8.4
./test-with-act.sh 8.3
./test-with-act.sh 8.2
```

**Note:** This script uses [act](https://github.com/nektos/act) to run the GitHub Actions workflow locally. It requires:
- Docker installed and running
- `act` installed (`brew install act` on macOS, or see [act installation guide](https://github.com/nektos/act#installation))

**Integration Tests:** Set the `MARKETDATA_TOKEN` environment variable before running to include integration tests:

```bash
export MARKETDATA_TOKEN=your_token_here
./test-with-act.sh
```

## Contributing

Found a bug or want to contribute? See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:
- Reporting bugs with reproduction code
- Setting up a development environment
- Running tests
- Submitting pull requests

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [KerryJones](https://github.com/KerryJones)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

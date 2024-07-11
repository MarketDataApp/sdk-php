# PHP SDK for MarketData.app

[![Latest Version on Packagist](https://img.shields.io/packagist/v/MarketDataApp/sdk-php.svg?style=flat-square)](https://packagist.org/packages/MarketDataApp/sdk-php)
[![Tests](https://img.shields.io/github/actions/workflow/status/MarketDataApp/sdk-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/MarketDataApp/sdk-php/actions/workflows/run-tests.yml)
[![Codecov](https://codecov.io/gh/MarketDataApp/sdk-php/graph/badge.svg?token=5W2IB9F6RU)](https://codecov.io/github/MarketDataApp/sdk-php)
[![Total Downloads](https://img.shields.io/packagist/dt/MarketDataApp/sdk-php.svg?style=flat-square)](https://packagist.org/packages/MarketDataApp/sdk-php)

This is the official PHP SDK for [Market Data](https://marketdata.app). It provides developers with a powerful, easy-to-use interface to obtain
real-time and historical financial data. Ideal for building financial applications, trading bots, and investment
strategies.

## Installation

You can install the package via composer:

```bash
composer require MarketDataApp/sdk-php
```

## Usage

```php
$client = new MarketDataApp\Client('your_api_token');

// Indices
$quote = $client->indices->quote('DJI');
$candles = $client->indices->candles(
    symbol: "DJI",
    from: Carbon::parse('2022-09-01'),
    to: Carbon::parse('2022-09-05'),
    resolution: 'D'
);

// Stocks
$candles = $client->stocks->candles('AAPL');
$bulk_candles = $client->stocks->bulkCandles(['AAPL, MSFT']);
$quote = $client->stocks->quote('AAPL');
$quotes = $client->stocks->quotes(['AAPL', 'NFLX']);
$bulk_quotes = $client->stocks->bulk_quotes(['AAPL', 'NFLX']);
$earnings = $client->stocks->earnings(symbol: 'AAPL', from: Carbon::parse('2023-01-01'));
$news = $client->stocks->news(symbol: 'AAPL', from: Carbon::parse('2023-01-01'));
```

## Testing

```bash
./vendor/bin/phpunit
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [KerryJones](https://github.com/KerryJones)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

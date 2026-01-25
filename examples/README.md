# Examples

This directory contains example scripts demonstrating how to use the MarketData PHP SDK.

## Running Examples

All examples automatically read your MarketData API token from environment variables or `.env` file.

### Option 1: Environment Variable (Recommended)

Set the token as an environment variable:

```bash
export MARKETDATA_TOKEN=your_token_here
```

### Option 2: .env File

Create a `.env` file in the project root:

```env
MARKETDATA_TOKEN=your_token_here
```

Then run any example:

```bash
php examples/rate_limit_tracking.php
```

**Note:** You can also pass the token explicitly: `new Client('your_token_here')`

## Available Examples

### Getting Started

| Example | Description | Documentation |
|---------|-------------|---------------|
| [quick_start.php](quick_start.php) | Basic SDK usage - quotes, candles, market status | [quick_start.md](quick_start.md) |

### Stock Data

| Example | Description | Documentation |
|---------|-------------|---------------|
| [bulk_quotes.php](bulk_quotes.php) | Single/multiple quotes, 52-week range, SmartMid prices, portfolio tracking | [bulk_quotes.md](bulk_quotes.md) |
| [stock_candles.php](stock_candles.php) | Historical OHLCV data - daily, intraday, weekly, monthly, bulk, extended hours | [stock_candles.md](stock_candles.md) |

### Options Data

| Example | Description | Documentation |
|---------|-------------|---------------|
| [options_chain.php](options_chain.php) | Expirations, strikes, chains, ITM/OTM filtering, Greeks, symbol lookup | [options_chain.md](options_chain.md) |

### Market Information

| Example | Description | Documentation |
|---------|-------------|---------------|
| [market_status.php](market_status.php) | Market status, calendars, trading days, holiday detection | [market_status.md](market_status.md) |

### SDK Features

| Example | Description | Documentation |
|---------|-------------|---------------|
| [utilities.php](utilities.php) | API status, service monitoring, headers debugging, rate limits | [utilities.md](utilities.md) |
| [output_formats.php](output_formats.php) | JSON vs CSV output, custom columns, date formats | [output_formats.md](output_formats.md) |
| [rate_limit_tracking.php](rate_limit_tracking.php) | Automatic rate limit tracking and monitoring | [rate_limit_tracking.md](rate_limit_tracking.md) |
| [error_handling.php](error_handling.php) | Exception handling, support ticket helpers, logging | [error_handling.md](error_handling.md) |
| [logging.php](logging.php) | PSR-3 logging integration | [logging.md](logging.md) |

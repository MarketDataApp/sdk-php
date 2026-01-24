# Changelog

## v0.8.0-beta

**Added PHP 8.5 Support**

- Added official support for PHP 8.5
- Updated test matrix to include PHP 8.5 (8.2, 8.3, 8.4, 8.5)
- Fixed PHP 8.5 compatibility issues:
  - Resolved 64 implicit nullable parameter deprecations
  - Removed deprecated `ReflectionProperty::setAccessible()` and `ReflectionMethod::setAccessible()` calls
  - Added `#[\AllowDynamicProperties]` attribute to Headers class
- Fixed integration test skipping issue in PHP 8.5 (environment variable cleanup in SettingsTest)
- Updated GitHub Actions workflow to test on PHP 8.5
- Updated README badge to reflect PHP 8.5 support

**BREAKING CHANGE**: Unified Options Quote Classes

The `Quote` and `OptionChainStrike` classes have been consolidated into a single `OptionQuote` class:

- **`OptionChainStrike` renamed to `OptionQuote`** - The class now has a more accurate name reflecting that it represents an option quote
- **`Quote` class removed** - It was a redundant subset of `OptionQuote` and has been deleted
- **`Quotes` response now captures all fields** - Previously missing 6 fields are now parsed:
  - `underlying` - Ticker symbol of the underlying security
  - `expiration` - Option's expiration date
  - `side` - Call or put (using `Side` enum)
  - `strike` - Exercise price
  - `first_traded` - Date option was first traded
  - `dte` - Days to expiration
- **New `OptionChains::toQuotes()` method** - Flattens option chains into a `Quotes` object, enabling you to treat a chain as a simple collection of quotes

**Migration Guide:**
```php
// Before
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\OptionChainStrike;

// After
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
```

## v0.7.0-beta

**BREAKING CHANGE**: PHP 8.1 support has been dropped. The SDK now requires PHP 8.2 or higher.

**BREAKING CHANGE**: The bulkQuotes endpoint has been removed as it is no longer supported by the API.

- Updated minimum PHP requirement from ^8.1 to ^8.2
- Updated test matrix to test on PHP 8.2, 8.3, and 8.4
- Upgraded PHPUnit from ^10.3.2 to ^11.4.0
- Updated GitHub Actions workflows (actions/checkout to v4, create-pull-request to v7)
- Updated PHPUnit XML schema to 11.4
- Removed deprecated bulkQuotes endpoint from Stocks
- Removed rho property from Options models (no longer supported by API)
- Fixed nullable currency handling in Earnings response

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

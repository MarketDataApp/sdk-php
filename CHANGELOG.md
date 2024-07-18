# Changelog

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

- Completed Indices endpoints.
- Added Stocks endpoints: quote, quotes, bulkQuotes, candles, bulkCandles.
- Added custom ApiException class to handle status = 'error' messages.
- Moved Responses to new directory.

## v0.1.0-alpha

- Initial release with single Indices > quote endpoint.

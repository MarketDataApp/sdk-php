# Bug Reports

This document tracks bugs through their lifecycle: Reported → Review → Fixed/Rejected.

## Reported

New bugs found via [PROCESS.md](PROCESS.md) are added here awaiting review.


| Bug | Description |
|-----|-------------|
| BUG-026 | Stocks candles CSV without headers can drop duplicate first rows |

## Coverage Notes

### Codebase Areas Searched Well
- src/ClientBase.php response handling (CSV/HTML/JSON parsing, error payload detection, file writes)
- src/Endpoints/Stocks.php intraday candles splitting + CSV merge/header behavior
- src/Endpoints/Responses/Markets/Statuses.php human-readable parsing
- src/Endpoints/Responses/Options/Lookup.php CSV/HTML typed property initialization
- src/Traits/FormatsForDisplay.php formatting helpers (formatChange)

### Codebase Areas Needing More Review
- src/Endpoints/Responses/Options/* (Expirations, Strikes, Quotes, OptionChains) numeric timestamp parsing and array alignment
- src/Endpoints/Responses/Stocks/BulkCandles.php (symbol attribution now handled)
- src/Endpoints/Responses/Stocks/Quotes.php and Prices.php multi-symbol parsing edge cases
- src/Endpoints/Utilities.php + Responses/Utilities/* caching behavior and header parsing
- src/Endpoints/MutualFunds.php + Responses/MutualFunds/* handling (CSV/HTML/no_data)

### Concepts Reviewed
- CSV/HTML error detection vs JSON payloads
- Typed property initialization for non-JSON/no_data responses
- Concurrent merge behavior for split requests (headers, partial failures)
- Human-readable JSON key parsing
- Display formatting/sign handling

### Concepts Needing More Review
- Numeric timestamp vs date-string parsing across responses
- Human-readable array length mismatches and missing fields
- Multi-symbol error aggregation and partial failures in merged responses
- Filename interactions (auto-write vs saveToFile) across endpoints
- Mode/maxage/204 no_data handling consistency

## Fixed

Fixed with tests: 46 bugs.

| Bug | Description | Commit |
|-----|-------------|--------|
| #1 | URL encoding for options lookup with special characters | 0a71582 |
| #2 | Default expiration=all incorrectly set on option_chain | 0a71582 |
| #3 | Delta parameter type should be string for range expressions | 0a71582 |
| #4 | Empty symbols parameter sent in bulkCandles snapshot requests | 0a71582 |
| #5 | 50-chunk limit on intraday candle date range splitting | 0a71582 |
| #6 | Default nonstandard=true incorrectly set on option_chain | 0a71582 |
| #7 | Expirations strike parameter type should be float | 0a71582 |
| #8 | Filename validation too strict (required full path to exist) | 0a71582 |
| #9 | Check numeric before strtotime in date parsing | 617e927 |
| #10 | Allow explicit adjust_splits=false in candles methods | 7fb9331 |
| #11 | Preserve time-of-day when splitting candle date ranges | 6ccb877 |
| #12 | Add symbol validation to bulkCandles endpoint | 590ba8e |
| #13 | Support Format enum in Client::execute() methods | 1667af9 |
| #14 | Trim whitespace from symbols in single-symbol endpoints | a68ffa6 |
| #15 | Trim whitespace from symbols in MutualFunds::candles() | 754d86a |
| #16 | Add extended parameter to quote() and quotes() methods | 5a7e4b7 |
| #17 | Remove date range requirement from Stocks::news() | 6e206a1 |
| #18 | Remove date range requirement from Stocks::earnings() | 7d98b38 |
| #19 | Replace minBidAskSpread with maxBidAskSpread, add am/pm params | fbc085f |
| #20 | Remove unimplemented datekey parameter from earnings | 53fe588 |
| #21 | Remove unsupported exchange, country, adjust_dividends params | 105470f |
| #22 | Enforce 'to' requires either 'from' or 'countback' (not both) | 30628de |
| BUG-001 | Empty CSV responses misclassified as JSON | a9fa26e |
| BUG-002 | `_filename` leaks into query parameters | 26b9918 |
| BUG-003 | Multi-symbol CSV options quotes include JSON error payloads | f027808 |
| BUG-004 | 204 No Content causes TypeError in JSON responses | 6ee766b |
| BUG-005 | getCsv()/getHtml() throw PHP Error on JSON responses | ac547d8 |
| BUG-006 | filename silently ignored for multi-symbol options CSV quotes | ee4ce8c |
| BUG-007 | CSV/HTML requests do not surface JSON error bodies | c4d5a77 |
| BUG-008 | filename writes JSON error payloads to CSV/HTML files | c4d5a77 |
| BUG-009 | DateInterval maxage drops days/months/years | 0a7ee4f |
| BUG-010 | maxage dropped in CSV parallel requests | 8f53fa6 |
| BUG-011 | CSV/HTML JSON error detection misses leading whitespace | 269a372 |
| BUG-012 | CSV combined output drops headers when first request fails | d977701 |
| BUG-013 | Uninitialized typed properties on CSV/HTML or no_data responses | fbfdb48 |
| BUG-014 | formatChange() method loses negative sign - displays "-$1.25" as "$1.25" | 4b834f3 |
| BUG-016 | CSV candles combined output drops headers when first chunk fails | c899a28 |
| BUG-017 | Markets human-readable responses drop multiple dates | fe8e646 |
| BUG-015 | BulkCandles response loses symbol information - Candle objects have no symbol property | 1701dd1 |
| BUG-018 | Options lookup CSV responses leave typed properties uninitialized | 7ad0652 |
| BUG-019 | Mutual funds candles CSV responses leave typed properties uninitialized | 1d93113 |
| BUG-020 | Stocks candles CSV responses leave typed properties uninitialized | 205b25c |
| BUG-021 | BulkCandles CSV responses leave typed properties uninitialized | e7c4448 |
| BUG-022 | Options lookup does not trim leading/trailing whitespace | 569a968 |
| BUG-023 | Stocks candles crash when using unix timestamp strings with automatic splitting | b344242 |
| BUG-025 | Mutual funds candles ignore human-readable JSON responses | 2fc618b |
| BUG-024 | Options quotes CSV without headers can drop duplicate first rows | 6b635dc |

---

## Test Runs

- 2026-01-26: `php bug-reports/BUG-018-options-lookup-csv-uninitialized-properties.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-019-mutualfunds-candles-uninitialized-properties.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-019-mutualfunds-candles-uninitialized-properties.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-020-stocks-candles-csv-uninitialized-properties.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-020-stocks-candles-csv-uninitialized-properties.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-021-bulkcandles-csv-uninitialized-properties.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-021-bulkcandles-csv-uninitialized-properties.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-022-options-lookup-does-not-trim-input.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-022-options-lookup-does-not-trim-input.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-023-stocks-candles-unix-timestamps-crash-splitting.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-023-stocks-candles-unix-timestamps-crash-splitting.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-024-options-quotes-csv-no-headers-drops-duplicate-first-row.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-024-options-quotes-csv-no-headers-drops-duplicate-first-row.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-025-mutualfunds-candles-human-readable-not-parsed.php` → BUG PRESENT
- 2026-01-26: `php bug-reports/BUG-025-mutualfunds-candles-human-readable-not-parsed.php` → BUG FIXED
- 2026-01-26: `php bug-reports/BUG-026-stocks-candles-csv-no-headers-drops-duplicate-first-row.php` → BUG PRESENT

---

**Next bug: BUG-027**

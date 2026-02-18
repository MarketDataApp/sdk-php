# Bug Finding Workflow

This document defines a systematic process for proactively discovering bugs through codebase exploration and testing. Found bugs are reported via GitHub issues using the [bug template](https://github.com/MarketDataApp/sdk-php/issues/new?template=bug.yml).

## Overview

**Purpose**: Proactive bug discovery vs reactive bug processing

- **BUG_FINDING.md** (this document): Find bugs before users encounter them
- **ISSUE_WORKFLOW.md**: Process bug reports submitted by users

**Workflow**: Find Bug → Create Issue → [ISSUE_WORKFLOW.md] → Fix

**When to use this document**:
- QA passes before releases
- Pre-release validation
- Exploratory testing sessions
- After significant refactors
- When onboarding to understand edge cases

---

## Prerequisites

### Environment Setup

```bash
# Required
composer install
php -v  # Must be 8.2, 8.3, 8.4, or 8.5

# API token for integration tests
export MARKETDATA_TOKEN="your_token_here"
```

### Baseline Verification

Before hunting for bugs, confirm the test suite passes:

```bash
./test.sh unit
```

If tests fail, fix those issues first. Bug finding assumes a working baseline.

### Architecture Understanding

Familiarize yourself with key components:
- `Client` - Main entry point
- `ClientBase` - HTTP handling, retry logic, async support
- `Settings` - Configuration and token resolution
- Endpoint classes in `src/Endpoints/`
- Response classes in `src/Endpoints/Responses/`

---

## Exploration Areas

Prioritized by historical bug likelihood:

| Priority | Area | Bug Likelihood | Common Issues |
|----------|------|----------------|---------------|
| 1 | Response Format Handling | High | CSV/HTML typed property errors |
| 2 | Array Boundary Conditions | High | Index access on empty arrays |
| 3 | Concurrent Request Handling | Medium | Partial failures, header merging |
| 4 | Date/Time Parsing | Medium | Timestamp formats, boundaries |
| 5 | Multi-Symbol Operations | Medium | Empty arrays, deduplication |

---

## Area 1: Response Format Handling

### What Can Go Wrong

- Typed properties fail to initialize when response format is CSV or HTML
- Human-readable JSON has different key names than regular JSON
- Optional fields present in JSON but absent in CSV/HTML

### Test Scenarios

#### 1.1 Format Switching

Test each endpoint with all three formats:

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Enums\Format;

$client = new Client();

// JSON (default) - should work
$jsonResult = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', to: '2024-01-03');

// CSV - check typed property access
$csvResult = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', to: '2024-01-03', format: Format::CSV);

// HTML - check typed property access
$htmlResult = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', to: '2024-01-03', format: Format::HTML);

// Verify: Does accessing typed properties throw errors?
// Bug indicator: TypeError or uninitialized property errors
```

#### 1.2 Human-Readable JSON

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;

$client = new Client();

// Regular JSON
$params = new Parameters(human: false);
$regular = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', to: '2024-01-03', parameters: $params);

// Human-readable JSON
$params = new Parameters(human: true);
$human = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', to: '2024-01-03', parameters: $params);

// Verify: Are all properties correctly mapped in both modes?
// Bug indicator: Missing data in human-readable mode, wrong field mappings
```

### Red Flags

- `TypeError: Cannot access property` - Typed property not initialized
- `Error: Typed property must not be accessed before initialization`
- Missing data only in non-JSON formats
- Different results between `human: true` and `human: false`

### Pass/Fail Criteria

| Scenario | Pass | Fail |
|----------|------|------|
| JSON format | Returns typed response object | Exception thrown |
| CSV format | Returns string or typed response | Uninitialized property error |
| HTML format | Returns string or typed response | Uninitialized property error |
| Human-readable | Same data as regular JSON | Data missing or incorrect |

---

## Area 2: Array Boundary Conditions

### What Can Go Wrong

- Accessing `[0]` without checking if array exists or has elements
- Single item returned as scalar instead of array
- Missing optional fields causing null array access

### Test Scenarios

#### 2.1 Empty Results

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Request data for a date range with no trading (e.g., weekend)
$result = $client->stocks->candles('AAPL', '1D', from: '2024-01-06', to: '2024-01-07'); // Saturday-Sunday

// Verify: Does the SDK handle empty results gracefully?
// Bug indicator: "Undefined array key 0" or "Cannot access offset"

// Check all array access patterns:
// - Direct indexing: $result->candles[0]
// - First/last helpers: $result->first(), $result->last()
// - Iteration: foreach ($result->candles as $candle)
```

#### 2.2 Single Item Response

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Request exactly one item
$result = $client->stocks->quote('AAPL');

// Verify: Is the response consistently an array or object?
// Bug indicator: Single item treated as scalar, iteration fails
```

#### 2.3 Missing Optional Fields

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Earnings often have missing fields (confirmed, actual EPS, etc.)
$result = $client->stocks->earnings('AAPL', from: '2024-01-01');

// Verify: Are optional fields handled without throwing?
// Bug indicator: Null access errors when optional fields are absent
```

### Red Flags

- `Undefined array key 0`
- `Cannot access offset on null`
- `Trying to access array offset on value of type null`
- Different behavior with 0, 1, or 2+ results

### Pass/Fail Criteria

| Scenario | Pass | Fail |
|----------|------|------|
| Empty array | Empty collection, no error | Array access exception |
| Single item | Consistent array/collection type | Type changes based on count |
| Missing optional | Null or default, no error | Null access exception |

---

## Area 3: Concurrent Request Handling

### What Can Go Wrong

- Partial failures not properly reported
- Headers from multiple requests conflicting
- Chunk boundaries causing data loss or duplication
- Rate limiting not properly handled in parallel

### Test Scenarios

#### 3.1 Partial Failures

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Request multiple symbols, some valid, some invalid
$symbols = ['AAPL', 'INVALID_SYMBOL_XYZ', 'GOOGL'];
$result = $client->stocks->bulkCandles($symbols, '1D', from: '2024-01-02', to: '2024-01-03');

// Verify: How are partial failures reported?
// Bug indicator: Silent failures, missing data without errors
```

#### 3.2 Header Handling

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;

$client = new Client();

$params = new Parameters(add_headers: true);

// Multiple parallel requests
$symbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'META'];
$result = $client->stocks->bulkCandles($symbols, '1D', from: '2024-01-02', to: '2024-01-03', parameters: $params);

// Verify: Are headers correctly merged/deduplicated?
// Bug indicator: Duplicate headers, wrong rate limit info
```

#### 3.3 Large Dataset Chunking

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Request large number of symbols to trigger chunking
$symbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'META', 'NVDA', 'TSLA', 'JPM', 'V', 'JNJ'];
$result = $client->stocks->bulkCandles($symbols, '1D', from: '2024-01-02', to: '2024-01-03');

// Verify: Is data complete? Any gaps at chunk boundaries?
// Bug indicator: Missing symbols, duplicate data
```

### Red Flags

- Missing data without error messages
- Duplicate results
- Headers showing incorrect counts
- Rate limit exhaustion with few requests

### Pass/Fail Criteria

| Scenario | Pass | Fail |
|----------|------|------|
| Partial failure | Clear error for failed, data for successful | Silent data loss |
| Header merge | Correct aggregated values | Duplicate or incorrect headers |
| Chunking | Complete data, no duplicates | Data loss or duplication at boundaries |

---

## Area 4: Date/Time Parsing

### What Can Go Wrong

- Unix timestamps as strings vs integers handled differently
- Excel serial date numbers not parsed
- Year boundary edge cases
- Timezone assumptions

### Test Scenarios

#### 4.1 Timestamp Formats

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Test various date input formats
$formats = [
    '2024-01-02',           // ISO date string
    '2024-01-02T09:30:00',  // ISO datetime
    1704200400,             // Unix timestamp (int)
    '1704200400',           // Unix timestamp (string)
    'today',                // Relative date
    '-1 week',              // Relative date
];

foreach ($formats as $from) {
    try {
        $result = $client->stocks->candles('AAPL', '1D', from: $from, to: 'today');
        echo "Format '$from': OK\n";
    } catch (\Exception $e) {
        echo "Format '$from': FAILED - " . $e->getMessage() . "\n";
    }
}

// Verify: All reasonable date formats should work
// Bug indicator: Some formats fail unexpectedly
```

#### 4.2 Year Boundaries

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Year boundary - Dec 31 to Jan 1
$result = $client->stocks->candles('AAPL', '1D', from: '2023-12-29', to: '2024-01-02');

// Verify: Data spans year boundary correctly
// Bug indicator: Missing data around year change
```

#### 4.3 Market Hours

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Intraday data near market open/close
$result = $client->stocks->candles('AAPL', '5', from: '2024-01-02 09:30:00', to: '2024-01-02 10:00:00');

// Verify: Timestamps are in expected timezone
// Bug indicator: Data offset by hours, wrong trading session
```

### Red Flags

- Different results for equivalent date representations
- Gaps in data at year/month boundaries
- Timezone-related offsets
- Unix timestamp treated as string literal

### Pass/Fail Criteria

| Scenario | Pass | Fail |
|----------|------|------|
| Multiple formats | Consistent results | Format-dependent behavior |
| Year boundary | Continuous data | Gap in data |
| Timezone | Correct market hours | Offset data |

---

## Area 5: Multi-Symbol Operations

### What Can Go Wrong

- Empty symbol array causes error
- Duplicate symbols not deduplicated
- Single vs multiple symbols handled differently
- Symbol case sensitivity issues

### Test Scenarios

#### 5.1 Empty Symbol Array

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Empty array
try {
    $result = $client->stocks->bulkCandles([], '1D', from: '2024-01-02');
    echo "Empty array: Returned " . count($result) . " results\n";
} catch (\Exception $e) {
    echo "Empty array: " . $e->getMessage() . "\n";
}

// Verify: Should either return empty result or clear error
// Bug indicator: Crash, null pointer, or API error
```

#### 5.2 Duplicate Symbols

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Duplicates in array
$result = $client->stocks->bulkCandles(['AAPL', 'AAPL', 'GOOGL'], '1D', from: '2024-01-02', to: '2024-01-03');

// Verify: Duplicates should be deduplicated or handled gracefully
// Bug indicator: Duplicate data, API rate waste
```

#### 5.3 Case Sensitivity

```php
<?php
require 'vendor/autoload.php';

use MarketDataApp\Client;

$client = new Client();

// Mixed case
$upper = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', to: '2024-01-03');
$lower = $client->stocks->candles('aapl', '1D', from: '2024-01-02', to: '2024-01-03');
$mixed = $client->stocks->candles('AaPl', '1D', from: '2024-01-02', to: '2024-01-03');

// Verify: All cases should return same data
// Bug indicator: Case-dependent failures or different data
```

### Red Flags

- Empty array causes crash instead of graceful handling
- Duplicate data in results
- Different behavior for uppercase vs lowercase
- Single symbol returns different structure than multiple

### Pass/Fail Criteria

| Scenario | Pass | Fail |
|----------|------|------|
| Empty array | Empty result or clear error | Crash or ambiguous error |
| Duplicates | Deduplicated or single request | Duplicate data returned |
| Case handling | Consistent results | Case-dependent behavior |

---

## Bug Documentation

When you find a bug, capture these details:

### Required Information

1. **Minimal reproduction code** - Smallest code that demonstrates the bug
2. **Expected behavior** - What should happen
3. **Actual behavior** - What actually happens (include error messages)
4. **Environment**:
   - SDK version: `composer show marketdataapp/sdk-php`
   - PHP version: `php -v`
   - OS: macOS/Windows/Linux

### Submission

1. Go to [Create Bug Report](https://github.com/MarketDataApp/sdk-php/issues/new?template=bug.yml)
2. Fill out all fields with captured information
3. In "Additional Context", note: `Found via BUG_FINDING.md [Area N]`

### Example Bug Report

```markdown
**Endpoint**: stocks
**Method**: candles

**Reproduction Code**:
<?php
require 'vendor/autoload.php';
use MarketDataApp\Client;
use MarketDataApp\Enums\Format;

$client = new Client();
$result = $client->stocks->candles('AAPL', '1D', from: '2024-01-02', format: Format::CSV);
echo $result->high[0]; // Throws error

**Expected**: Access typed property without error
**Actual**: TypeError: Cannot access property high on string

**SDK Version**: 1.0.0
**PHP Version**: 8.2.4

**Additional Context**: Found via BUG_FINDING.md [Area 1 - Format Switching]
```

---

## Endpoint Checklists

Use these checklists for systematic testing of each endpoint.

### Stocks Endpoint

| Method | Area 1 (Formats) | Area 2 (Arrays) | Area 3 (Concurrent) | Area 4 (Dates) | Area 5 (Multi) |
|--------|------------------|-----------------|---------------------|----------------|----------------|
| `candles` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty [ ] Single | N/A | [ ] Formats [ ] Boundaries | [ ] Multi-symbol |
| `bulkCandles` | [ ] JSON | [ ] Empty [ ] Single | [ ] Partial [ ] Headers | [ ] Formats [ ] Boundaries | [ ] Empty [ ] Dupe [ ] Case |
| `quote` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | N/A | N/A | N/A |
| `bulkQuotes` | [ ] JSON | [ ] Empty | [ ] Partial [ ] Headers | N/A | [ ] Empty [ ] Dupe [ ] Case |
| `earnings` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty [ ] Optional | N/A | [ ] Formats | N/A |
| `news` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | N/A | [ ] Formats | [ ] Multi-symbol |

### Options Endpoint

| Method | Area 1 (Formats) | Area 2 (Arrays) | Area 3 (Concurrent) | Area 4 (Dates) | Area 5 (Multi) |
|--------|------------------|-----------------|---------------------|----------------|----------------|
| `expirations` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | N/A | [ ] Formats | N/A |
| `strikes` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | N/A | [ ] Formats | N/A |
| `option_chain` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | N/A | [ ] Formats | N/A |
| `quotes` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | [ ] Headers | N/A | [ ] Multi-option |
| `lookup` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | N/A | N/A | N/A |

### Markets Endpoint

| Method | Area 1 (Formats) | Area 2 (Arrays) | Area 4 (Dates) |
|--------|------------------|-----------------|----------------|
| `status` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty | [ ] Formats |

### Mutual Funds Endpoint

| Method | Area 1 (Formats) | Area 2 (Arrays) | Area 4 (Dates) |
|--------|------------------|-----------------|----------------|
| `candles` | [ ] JSON [ ] CSV [ ] HTML | [ ] Empty [ ] Single | [ ] Formats [ ] Boundaries |

### Utilities Endpoint

| Method | Area 1 (Formats) | Area 2 (Arrays) |
|--------|------------------|-----------------|
| `api_status` | [ ] JSON | N/A |
| `headers` | [ ] JSON | N/A |

---

## Quick Reference

### Common Test Commands

```bash
# Run a single exploration scenario
php exploration-test.php

# Save output for analysis
php exploration-test.php > output.txt 2>&1

# Run unit tests after finding potential bug
./test.sh unit
```

### Common Bug Indicators

| Error Message | Likely Area | Likely Cause |
|---------------|-------------|--------------|
| `Undefined array key 0` | Area 2 | Empty array access |
| `Cannot access property on null` | Area 1/2 | Uninitialized property or null response |
| `Typed property must not be accessed before initialization` | Area 1 | CSV/HTML format with typed response |
| `TypeError` | Area 1/2 | Type mismatch in response parsing |
| Data missing without error | Area 3 | Silent partial failure |
| Duplicate data | Area 3/5 | Chunk boundary or deduplication issue |

### Links

- [Bug Report Template](https://github.com/MarketDataApp/sdk-php/issues/new?template=bug.yml)
- [Issue Workflow (for processing bugs)](ISSUE_WORKFLOW.md)

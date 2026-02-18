# ADR-012: Automatic Date Range Splitting

## Status
Accepted

## Context

The Market Data API has practical limits on intraday candle data:
- **Maximum ~1 year** of intraday data per request
- **5-minute candles** for 5 years = ~262,000 candles (too many for one request)

Users requesting multi-year intraday data would face:
- Empty responses or errors
- Manual date range splitting
- Sequential API calls (slow)

The SDK needed to transparently handle large date ranges.

## Decision

We implemented **automatic date range splitting** for intraday candles:

1. **Detection**: Identify when splitting is needed
2. **Splitting**: Divide range into year-long chunks
3. **Parallel Execution**: Fetch chunks concurrently (ADR-009)
4. **Merging**: Combine responses into single result

### Splitting Criteria

Splitting occurs when ALL conditions are met:
- Resolution is **intraday** (minutely or hourly)
- Date range spans **more than 1 year**
- Both `from` and `to` dates are parseable
- `countback` is **not** specified

### Implementation

```php
// src/Endpoints/Stocks.php

protected function needsAutomaticSplitting(
    string $resolution,
    string $from,
    ?string $to,
    ?int $countback
): bool {
    // Can't split countback requests
    if ($countback !== null) return false;

    // Need a 'to' date to calculate range
    if ($to === null) return false;

    // Only split intraday resolutions
    if (!$this->isIntradayResolution($resolution)) return false;

    // Both dates must be parseable
    if (!$this->isParseableDate($from) || !$this->isParseableDate($to)) return false;

    // Check if range spans more than 1 year
    $fromDate = $this->parseUserDate($from);
    $toDate = $this->parseUserDate($to);
    $diffInDays = $fromDate->diffInDays($toDate);

    return $diffInDays > 365;
}

protected function splitDateRangeIntoYearChunks(string $from, string $to): array
{
    $fromDate = $this->parseUserDate($from);
    $toDate = $this->parseUserDate($to);

    $chunks = [];
    $currentStart = $fromDate->copy()->startOfDay();
    $isFirstChunk = true;

    while ($currentStart->lte($toDate)) {
        $currentEnd = $currentStart->copy()->addYear()->subDay()->endOfDay();

        // Preserve original timestamps for first/last chunks
        $chunkFrom = $isFirstChunk ? $from : $currentStart->toDateString();
        $isFirstChunk = false;

        if ($currentEnd->gte($toDate)) {
            $chunks[] = [$chunkFrom, $to];  // Last chunk uses original 'to'
            break;
        }

        $chunks[] = [$chunkFrom, $currentEnd->toDateString()];
        $currentStart = $currentEnd->copy()->addDay()->startOfDay();
    }

    return $chunks;
}

public function candles(...): Candles
{
    // Check if automatic splitting is needed
    if ($this->needsAutomaticSplitting($resolution, $from, $to, $countback)) {
        return $this->candlesConcurrent(...);
    }

    // Standard single request
    return new Candles($this->execute(...));
}
```

### Response Merging

```php
protected function mergeCandleResponses(array $responses, string $symbol): Candles
{
    $allCandles = [];

    foreach ($responses as $response) {
        $candlesResponse = new Candles($response, $symbol);
        if ($candlesResponse->status === 'ok') {
            foreach ($candlesResponse->candles as $candle) {
                $allCandles[] = $candle;
            }
        }
    }

    // Sort by timestamp
    usort($allCandles, fn($a, $b) => $a->timestamp->timestamp <=> $b->timestamp->timestamp);

    // Remove duplicates (boundary overlaps)
    $uniqueCandles = [];
    $seenTimestamps = [];
    foreach ($allCandles as $candle) {
        $ts = $candle->timestamp->timestamp;
        if (!isset($seenTimestamps[$ts])) {
            $seenTimestamps[$ts] = true;
            $uniqueCandles[] = $candle;
        }
    }

    return Candles::createMerged('ok', $uniqueCandles);
}
```

### Usage (Transparent to User)

```php
// Single API call - user doesn't know about splitting
$candles = $client->stocks->candles(
    symbol: 'AAPL',
    from: '2020-01-01',
    to: '2025-01-01',
    resolution: '5'  // 5-minute candles
);

// Behind the scenes:
// - Detects 5-year range with intraday resolution
// - Splits into 5 chunks: 2020, 2021, 2022, 2023, 2024-2025
// - Fetches all 5 concurrently (up to 50 parallel)
// - Merges into single Candles response
// - Returns unified result

foreach ($candles->candles as $candle) {
    echo "{$candle->timestamp->format('Y-m-d')}: {$candle->close}\n";
}
```

### CSV Format Handling

CSV responses require special handling to combine properly:

```php
protected function candlesConcurrentCsv(...): Candles
{
    // Request headers on ALL chunks (in case first fails)
    // Strip duplicate header rows when combining
    $combinedCsv = '';
    $headerRow = null;

    foreach ($responses as $response) {
        $csv = $response->csv;

        if ($headerRow === null) {
            // First response - capture header
            $headerRow = substr($csv, 0, strpos($csv, "\n"));
            $combinedCsv .= $csv . "\n";
        } else {
            // Subsequent - strip header if present
            $firstLine = substr($csv, 0, strpos($csv, "\n"));
            if ($firstLine === $headerRow) {
                $csv = substr($csv, strpos($csv, "\n") + 1);
            }
            $combinedCsv .= $csv . "\n";
        }
    }

    return new Candles((object)['csv' => $combinedCsv], $symbol);
}
```

## Consequences

### Positive
- **Transparent**: Users don't need to know about API limits
- **Efficient**: Parallel execution maximizes throughput
- **Complete Data**: Multi-year requests just work
- **Consistent Interface**: Same return type regardless of splitting

### Negative
- **Hidden Complexity**: Multiple requests behind single call
- **Memory Usage**: All responses held until merging
- **Cost**: Multiple API calls consume more credits

### Mitigations
- Documentation explains when splitting occurs
- Memory only significant for very large requests
- Concurrent execution minimizes latency impact

## Alternatives Considered

### Alternative 1: Error on Large Ranges
```php
if ($daysDiff > 365 && $this->isIntradayResolution($resolution)) {
    throw new \InvalidArgumentException('Date range too large for intraday data');
}
```

**Pros**: Simple, explicit about limits
**Cons**: Pushes complexity to user, poor experience

### Alternative 2: Manual Splitting Helper
```php
$chunks = $client->stocks->splitDateRange('2020-01-01', '2025-01-01');
foreach ($chunks as [$from, $to]) {
    $results[] = $client->stocks->candles('AAPL', $from, $to, '5');
}
```

**Pros**: User controls splitting
**Cons**: Verbose, sequential by default, complex merging

### Alternative 3: Pagination
```php
$page = $client->stocks->candles('AAPL', '2020-01-01', '2025-01-01', '5');
while ($page->hasMore()) {
    $page = $page->next();
}
```

**Pros**: Familiar pattern, memory efficient
**Cons**: API doesn't support pagination, sequential fetching

### Alternative 4: Warn and Proceed
```php
$candles = $client->stocks->candles(...);  // Returns partial data
// Warning logged about incomplete data
```

**Pros**: Simple implementation
**Cons**: Silent data loss, confusing results

## References

- `src/Endpoints/Stocks.php:47-74` - `isIntradayResolution()`
- `src/Endpoints/Stocks.php:176-206` - `splitDateRangeIntoYearChunks()`
- `src/Endpoints/Stocks.php:208-257` - `needsAutomaticSplitting()`
- `src/Endpoints/Stocks.php:259-312` - `mergeCandleResponses()`
- `src/Endpoints/Stocks.php:488-559` - `candlesConcurrent()`
- ADR-009 - Sliding Window Concurrency (parallel execution)

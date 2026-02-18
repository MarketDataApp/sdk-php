# ADR-009: Sliding Window Concurrency

## Status
Accepted

## Context

The Market Data API allows up to 50 concurrent requests. When fetching large datasets (e.g., historical candles for multiple symbols), sequential requests are inefficient. However, unbounded parallelism could:
- Overwhelm the API with too many simultaneous connections
- Exhaust system resources (file descriptors, memory)
- Trigger rate limiting or API protection mechanisms

We needed a concurrency model that maximizes throughput while respecting API limits.

## Decision

We implemented a **sliding window concurrency** model using Guzzle's `EachPromise`:

1. **Concurrent Limit**: Maximum 50 simultaneous requests
2. **Sliding Window**: New requests start as previous ones complete
3. **Optimal Throughput**: Maintains maximum concurrency continuously
4. **Failure Tolerance**: Optional partial failure handling

### Implementation

```php
// src/ClientBase.php
public function execute_in_parallel(array $calls, ?array &$failedRequests = null): array
{
    $maxConcurrent = Settings::MAX_CONCURRENT_REQUESTS;  // 50
    $results = [];
    $exceptions = [];
    $tolerateFailed = func_num_args() >= 2;

    // Generator yields promises with their original indices
    $promiseGenerator = function () use ($calls) {
        foreach ($calls as $index => $call) {
            yield $index => $this->async($call[0], $call[1]);
        }
    };

    // EachPromise maintains sliding window of concurrent requests
    $eachPromise = new EachPromise($promiseGenerator(), [
        'concurrency' => $maxConcurrent,
        'fulfilled' => function ($response, $index) use (&$results, $calls, $tolerateFailed) {
            $format = $calls[$index][1]['format'] ?? 'json';
            if ($tolerateFailed) {
                try {
                    $results[$index] = $this->processResponse($response, $format, ...);
                } catch (\Throwable $e) {
                    $exceptions[$index] = $e;
                }
            } else {
                $results[$index] = $this->processResponse($response, $format, ...);
            }
        },
        'rejected' => function ($reason, $index) use (&$exceptions) {
            $exceptions[$index] = $reason;
        },
    ]);

    // Wait for all promises to complete
    $eachPromise->promise()->wait();

    // Handle exceptions
    if (!empty($exceptions)) {
        ksort($exceptions);
        if ($tolerateFailed) {
            $failedRequests = $exceptions;
        } else {
            throw reset($exceptions);
        }
    }

    ksort($results);
    return $tolerateFailed ? $results : array_values($results);
}
```

### Visual Representation

```
Time ->
Request 1:  |======|
Request 2:  |========|
Request 3:  |====|
Request 4:       |=======|     (starts when 3 finishes)
Request 5:        |======|     (starts when 1 finishes)
...
            ^--50 concurrent--^
```

### Usage Examples

```php
// Parallel quotes for multiple symbols
$calls = [];
foreach (['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'META'] as $symbol) {
    $calls[] = ["v1/stocks/quotes/{$symbol}/", ['format' => 'json']];
}
$results = $client->execute_in_parallel($calls);

// With failure tolerance
$failedRequests = [];
$results = $client->execute_in_parallel($calls, $failedRequests);
if (!empty($failedRequests)) {
    foreach ($failedRequests as $index => $exception) {
        echo "Request $index failed: {$exception->getMessage()}\n";
    }
}
// $results contains successful responses keyed by original index
```

### Automatic Parallel Execution

The SDK automatically uses parallel execution for:
- **Date range splitting**: Multi-year intraday candles (ADR-012)
- **Bulk operations**: Multiple symbol requests

```php
// Automatic: 5-year intraday request splits into 5 parallel requests
$candles = $client->stocks->candles('AAPL', '2020-01-01', '2025-01-01', '5');
// Behind the scenes: 5 concurrent requests, one per year
```

## Consequences

### Positive
- **Maximum Throughput**: Always maintains maximum allowed concurrency
- **Efficient Resource Use**: No idle waiting between batches
- **Order Preservation**: Results returned in original request order
- **Partial Failure Handling**: Can continue despite individual failures

### Negative
- **Memory Usage**: All responses held in memory until completion
- **Complexity**: Generator pattern and promise handling
- **PHP Limitations**: Not true async (blocks on `wait()`)

### Mitigations
- Memory is only an issue for very large result sets
- Well-tested implementation with clear code comments
- `wait()` blocking is acceptable for PHP's execution model

## Alternatives Considered

### Alternative 1: Batch Processing
```php
$batches = array_chunk($calls, 50);
foreach ($batches as $batch) {
    $results = array_merge($results, $this->executeBatch($batch));
}
```

**Pros**: Simple to understand
**Cons**: Idle time between batches, suboptimal throughput

### Alternative 2: cURL Multi Handle
```php
$mh = curl_multi_init();
foreach ($calls as $call) {
    $ch = curl_init($url);
    curl_multi_add_handle($mh, $ch);
}
```

**Pros**: Lower-level control, potentially faster
**Cons**: Loses Guzzle features (middleware, retry), more code

### Alternative 3: ReactPHP/Amp Event Loop
```php
Loop::run(function () use ($calls) {
    $promises = array_map(fn($call) => $this->asyncRequest($call), $calls);
    yield Promise\all($promises);
});
```

**Pros**: True async, non-blocking
**Cons**: Requires event loop dependency, architectural change

### Alternative 4: Unlimited Concurrency
```php
Promise\all(array_map(fn($call) => $this->async($call), $calls));
```

**Pros**: Maximum parallelism
**Cons**: Could overwhelm API, exhaust file descriptors, rate limiting

## References

- `src/ClientBase.php:150-242` - `execute_in_parallel()` implementation
- `src/Settings.php:390` - `MAX_CONCURRENT_REQUESTS` constant
- [Guzzle EachPromise](https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests)
- ADR-012 - Uses parallel execution for date range splitting

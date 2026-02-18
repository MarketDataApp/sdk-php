# ADR-008: Intelligent Retry with API Status

## Status
Accepted

## Context

Network requests can fail for various reasons:
- **Transient failures** (5xx errors, timeouts): Should retry with backoff
- **Client errors** (4xx): Should not retry (fix the request)
- **Service offline**: Retrying wastes time and rate limit credits

The Market Data API provides a `/status` endpoint that reports the health of each service. Blindly retrying when a service is offline is wasteful and delays error feedback to users.

## Decision

We implemented **intelligent retry logic** that:

1. **Retries transient errors**: 5xx status codes, network timeouts
2. **Checks service status**: Before retrying, verify service isn't offline
3. **Caches status data**: Avoid excessive status endpoint calls
4. **Fails fast when offline**: Skip retries if service reports offline

### Implementation

```php
// src/Retry/RetryConfig.php
class RetryConfig
{
    public const MAX_RETRY_ATTEMPTS = 3;
    public const RETRY_BACKOFF = 0.5;      // Base backoff in seconds
    public const MIN_RETRY_BACKOFF = 0.5;
    public const MAX_RETRY_BACKOFF = 5.0;

    public static function isRetryableStatusCode(int $statusCode): bool
    {
        return $statusCode > 500;  // 501, 502, 503, etc.
    }
}

// src/ClientBase.php
protected function shouldSkipRetryDueToOfflineService(string $method): bool
{
    $servicePath = $this->getServicePath($method);

    if ($servicePath === null) {
        return false;  // Unknown service, allow retry
    }

    try {
        $apiStatusData = Utilities::getApiStatusData();

        // Skip blocking refresh during retry to avoid extra API calls
        $status = $apiStatusData->getApiStatus($this, $servicePath, skipBlockingRefresh: true);

        return $status === ApiStatusResult::OFFLINE;
    } catch (\Exception $e) {
        return false;  // Status check failed, allow retry
    }
}

// Retry logic in execute()
while ($attempt < $maxAttempts) {
    try {
        $response = $this->guzzle->get($method, [...]);
        $this->validateResponseStatusCode($response);
        return $this->processResponse($response);

    } catch (\GuzzleHttp\Exception\ServerException $e) {
        $statusCode = $e->getResponse()->getStatusCode();

        if (RetryConfig::isRetryableStatusCode($statusCode)) {
            // Check if service is offline before retrying
            if ($this->shouldSkipRetryDueToOfflineService($method)) {
                $this->logger->error('Service {service} is offline', ['service' => $method]);
                throw new RequestError(...);
            }

            $attempt++;
            if ($attempt < $maxAttempts) {
                $this->waitForRetry($attempt);
                continue;  // Retry
            }
        }
        throw new RequestError(...);
    }
}
```

### API Status Caching

```php
// src/Endpoints/Responses/Utilities/ApiStatusData.php
class ApiStatusData
{
    private ?Carbon $lastRefreshed = null;

    public function isValid(): bool
    {
        if ($this->lastRefreshed === null) {
            return false;
        }
        $age = Carbon::now()->diffInSeconds($this->lastRefreshed, true);
        return $age < Settings::API_STATUS_CACHE_VALIDITY;  // 5 minutes
    }

    public function inRefreshWindow(): bool
    {
        $age = Carbon::now()->diffInSeconds($this->lastRefreshed, true);
        // Between 4:30 and 5:00 - trigger async refresh
        return $age >= Settings::REFRESH_API_STATUS_INTERVAL
            && $age < Settings::API_STATUS_CACHE_VALIDITY;
    }

    public function getApiStatus(ClientBase $client, string $service, bool $skipBlockingRefresh = false): ApiStatusResult
    {
        // Fresh cache: return immediately
        if ($this->lastRefreshed !== null && !$this->inRefreshWindow() && $this->isValid()) {
            return $this->getServiceStatus($service);
        }

        // Refresh window: return cached + trigger async refresh
        if ($this->inRefreshWindow()) {
            $this->refreshAsync($client);
            return $this->getServiceStatus($service);
        }

        // Stale/empty cache
        if ($skipBlockingRefresh) {
            return ApiStatusResult::UNKNOWN;  // Allow retry
        }

        $this->refresh($client, blocking: true);
        return $this->getServiceStatus($service);
    }
}
```

### Exponential Backoff

```php
protected function calculateBackoffDelay(int $attempt): float
{
    // Exponential: 0.5s, 1s, 2s (capped at 5s)
    $delay = RetryConfig::RETRY_BACKOFF * (2 ** ($attempt - 1));
    return min(max($delay, RetryConfig::MIN_RETRY_BACKOFF), RetryConfig::MAX_RETRY_BACKOFF);
}
```

## Consequences

### Positive
- **Faster Failures**: Offline services detected without wasting retries
- **Resource Efficient**: No pointless retries when service is down
- **User Experience**: Clear error messages about service status
- **Rate Limit Preservation**: Failed retries don't consume credits

### Negative
- **Status Endpoint Dependency**: Extra API call if cache is empty
- **Complexity**: More code paths for retry logic
- **Edge Cases**: Status check itself could fail

### Mitigations
- Status cache reduces API calls (5-minute validity)
- Async refresh prevents blocking on cache refresh
- Status check failures default to allowing retry

## Alternatives Considered

### Alternative 1: Blind Retry
```php
for ($i = 0; $i < 3; $i++) {
    try {
        return $this->request(...);
    } catch (ServerException $e) {
        sleep($i * 2);
    }
}
```

**Pros**: Simple, no external dependency
**Cons**: Wastes time when service is offline, burns rate limit

### Alternative 2: Circuit Breaker Pattern
```php
$circuitBreaker = new CircuitBreaker($service);
if ($circuitBreaker->isOpen()) {
    throw new ServiceUnavailableException();
}
```

**Pros**: Local tracking, no API call
**Cons**: Can't detect recovery, requires local state

### Alternative 3: Health Check Before Every Request
```php
$status = $this->checkStatus($service);
if ($status !== 'online') {
    throw new ServiceOfflineException();
}
```

**Pros**: Always current status
**Cons**: Doubles API calls, latency impact

## References

- `src/Retry/RetryConfig.php` - Retry configuration
- `src/ClientBase.php:244-437` - Async retry implementation
- `src/ClientBase.php:451-616` - Sync execute with retry
- `src/Endpoints/Responses/Utilities/ApiStatusData.php` - Status caching
- `src/Enums/ApiStatusResult.php` - Status enum

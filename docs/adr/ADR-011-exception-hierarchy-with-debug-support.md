# ADR-011: Exception Hierarchy with Debug Support

## Status
Accepted

## Context

When API requests fail, users need:
1. **Clear error messages** explaining what went wrong
2. **Error categorization** for programmatic handling (retry vs. fix request)
3. **Debug context** for troubleshooting (request ID, URL, timestamp)
4. **Support ticket information** for reporting issues

Standard PHP exceptions lack context about the HTTP request that failed. The SDK needed an exception hierarchy that provides rich debugging information.

## Decision

We implemented a **custom exception hierarchy** with debug support:

1. **Base Exception**: `MarketDataException` with request context
2. **Specialized Exceptions**: Categorized by error type
3. **Support Methods**: Pre-formatted information for support tickets
4. **Request Tracking**: Cloudflare ray ID for server-side correlation

### Exception Hierarchy

```
MarketDataException (base)
├── ApiException          # Business logic errors (404, invalid symbol)
├── BadStatusCodeError    # Non-retryable HTTP errors (4xx except 401, 404)
├── RequestError          # Retryable errors (5xx, network timeouts)
└── UnauthorizedException # Authentication failures (401)
```

### Implementation

```php
// src/Exceptions/MarketDataException.php
class MarketDataException extends \Exception
{
    protected ?ResponseInterface $response;
    protected ?string $requestId;      // Cloudflare cf-ray header
    protected ?string $requestUrl;
    protected \DateTimeImmutable $timestamp;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null,
        ?string $requestUrl = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
        $this->requestUrl = $requestUrl;
        $this->requestId = $this->extractRequestId($response);
        $this->timestamp = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getRequestId(): ?string { return $this->requestId; }
    public function getRequestUrl(): ?string { return $this->requestUrl; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }

    /**
     * Get pre-formatted string for support tickets
     */
    public function getSupportInfo(): string
    {
        $supportTimestamp = $this->timestamp->setTimezone(new \DateTimeZone('America/New_York'));

        return implode("\n", [
            "--- MARKET DATA SUPPORT INFO ---",
            "Timestamp:    " . $supportTimestamp->format('Y-m-d H:i:s T'),
            "Request ID:   " . ($this->requestId ?? 'N/A'),
            "URL:          " . ($this->requestUrl ?? 'N/A'),
            "HTTP Code:    " . $this->getCode(),
            "Error:        " . $this->getMessage(),
            "--------------------------------",
        ]);
    }

    /**
     * Get structured context for logging
     */
    public function getSupportContext(): array
    {
        return [
            'timestamp' => $this->timestamp->format('c'),
            'request_id' => $this->requestId,
            'url' => $this->requestUrl,
            'http_code' => $this->getCode(),
            'message' => $this->getMessage(),
            'exception_type' => static::class,
        ];
    }
}
```

### Usage Examples

```php
try {
    $quote = $client->stocks->quote('INVALID');
} catch (ApiException $e) {
    // Business logic error (404, no data)
    echo "Error: " . $e->getMessage() . "\n";

} catch (UnauthorizedException $e) {
    // Authentication failed
    echo "Invalid API token. Please check your credentials.\n";

} catch (RequestError $e) {
    // Retryable error - should have been auto-retried
    echo "Server error after retries.\n";
    echo "Request ID for support: " . $e->getRequestId() . "\n";

} catch (BadStatusCodeError $e) {
    // Non-retryable client error (rate limit, validation)
    echo "Client error: " . $e->getMessage() . "\n";

} catch (MarketDataException $e) {
    // Any SDK exception - generic handler
    echo $e->getSupportInfo();
}

// Structured logging
catch (MarketDataException $e) {
    $logger->error('API Error', $e->getSupportContext());
}
```

### Support Info Output

```
--- MARKET DATA SUPPORT INFO ---
Timestamp:    2024-02-18 10:30:45 EST
Request ID:   8f7d6c5b4a3e2f1d-LAX
URL:          https://api.marketdata.app/v1/stocks/quotes/INVALID/
HTTP Code:    404
Error:        No data found for symbol: INVALID
--------------------------------
```

## Consequences

### Positive
- **Rich Context**: Request ID, URL, timestamp always available
- **Support Efficiency**: Pre-formatted info for support tickets
- **Error Categorization**: Programmatic handling based on exception type
- **Logging Integration**: Structured context for log aggregation

### Negative
- **Exception Proliferation**: Multiple exception types to handle
- **Memory Usage**: Response object stored in exception
- **Coupling**: Exceptions tied to PSR-7 response interface

### Mitigations
- Base exception catches all SDK errors when specificity isn't needed
- Response is nullable for non-HTTP errors
- Clear documentation on exception types and when they occur

## Alternatives Considered

### Alternative 1: Single Exception Class
```php
throw new MarketDataException($message, $code, ['type' => 'auth']);
```

**Pros**: Simple, one catch block
**Cons**: No type-safe handling, error type buried in data

### Alternative 2: Error Codes Only
```php
class MarketDataException extends \Exception
{
    public const ERR_AUTH = 1001;
    public const ERR_NOT_FOUND = 1002;
}
```

**Pros**: Familiar pattern
**Cons**: Magic numbers, less readable catch blocks

### Alternative 3: Result Object (No Exceptions)
```php
$result = $client->stocks->quote('AAPL');
if ($result->isError()) {
    $error = $result->getError();
}
```

**Pros**: Explicit error handling, no try/catch
**Cons**: Forces checking on every call, verbose code

### Alternative 4: Standard SPL Exceptions
```php
throw new \RuntimeException($message);
throw new \InvalidArgumentException($message);
```

**Pros**: Standard PHP, no custom classes
**Cons**: No request context, no support info, generic types

## References

- `src/Exceptions/MarketDataException.php` - Base exception
- `src/Exceptions/ApiException.php` - API/business errors
- `src/Exceptions/RequestError.php` - Retryable errors
- `src/Exceptions/BadStatusCodeError.php` - Non-retryable errors
- `src/Exceptions/UnauthorizedException.php` - Auth failures

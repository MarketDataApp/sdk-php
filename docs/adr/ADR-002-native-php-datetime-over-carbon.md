# ADR-002: Native PHP DateTime Over Carbon

## Status
Accepted (Partial - External Dependencies Still Use Carbon)

## Context

Early SDK versions (v0.1.0-v0.4.0) used Carbon extensively for all date/time handling. Carbon provides a fluent, expressive API for date manipulation. However, this introduced a significant external dependency for a relatively simple use case.

In v0.4.1, the SDK removed Carbon from public interfaces, preferring native PHP `DateTime`/`DateTimeImmutable` for user-facing code. Carbon remains internally for complex date calculations (date range splitting, cache timing) where its fluent API provides significant developer experience benefits.

## Decision

We adopted a **hybrid approach**:

1. **Public API**: Use native PHP `DateTime`/`DateTimeImmutable`/`DateTimeInterface` types
2. **Internal Implementation**: Use Carbon where its features significantly simplify code
3. **Response Objects**: Use `DateTimeImmutable` for immutability guarantees
4. **Exceptions**: Store timestamps as `DateTimeImmutable` in UTC

### Implementation

```php
// Response objects use native DateTimeImmutable
class Candle
{
    public \DateTimeImmutable $timestamp;

    public function __construct(object $response)
    {
        $this->timestamp = new \DateTimeImmutable('@' . $response->t);
    }
}

// Exception context uses native DateTimeImmutable
class MarketDataException extends \Exception
{
    protected \DateTimeImmutable $timestamp;

    public function __construct(...)
    {
        $this->timestamp = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

// Internal: Carbon still used for complex calculations
protected function splitDateRangeIntoYearChunks(string $from, string $to): array
{
    $fromDate = Carbon::parse($from);  // Internal use only
    $toDate = Carbon::parse($to);

    while ($currentStart->lte($toDate)) {
        $currentEnd = $currentStart->copy()->addYear()->subDay();
        // Carbon's fluent API simplifies complex date math
    }
}
```

### Where Carbon Remains

- `src/Endpoints/Stocks.php`: Date range splitting for intraday candles
- `src/Endpoints/Responses/Utilities/ApiStatusData.php`: Cache timing calculations
- `src/RateLimits.php`: Rate limit reset timestamp

## Consequences

### Positive
- **Reduced Public Dependency**: Users don't need Carbon knowledge
- **Interoperability**: Works with any DateTime-compatible library
- **Immutability**: `DateTimeImmutable` prevents accidental mutations
- **Standard Types**: PHP native types in function signatures

### Negative
- **Mixed Dependencies**: Carbon still required (via composer)
- **Developer Experience**: Internal code still relies on Carbon
- **Inconsistency**: Internal vs external date handling differs

### Mitigations
- Carbon remains a dev dependency for internal convenience
- Clear separation: public API never exposes Carbon types
- Documentation emphasizes native DateTime usage

## Alternatives Considered

### Alternative 1: Full Carbon Adoption
```php
public Carbon $timestamp;  // Expose Carbon in public API
```

**Pros**: Consistent, fluent API throughout
**Cons**: Forces Carbon dependency on users, version conflicts possible

### Alternative 2: Complete Carbon Removal
```php
// Replace all Carbon with native DateTime
$currentEnd = (clone $currentStart)->modify('+1 year -1 day');
```

**Pros**: Zero external date dependencies
**Cons**: Verbose, error-prone date calculations, harder to maintain

### Alternative 3: Chronos (CakePHP Immutable Alternative)
```php
use Cake\Chronos\Chronos;
```

**Pros**: Similar API to Carbon, immutable by default
**Cons**: Another dependency, less ecosystem support than Carbon

## References

- Commit history: Carbon removal in v0.4.1
- `src/Endpoints/Responses/Stocks/Candle.php` - Native DateTime usage
- `src/Exceptions/MarketDataException.php` - DateTimeImmutable for timestamps
- `src/Endpoints/Stocks.php:176-206` - Internal Carbon usage

# ADR-005: Parameter Object Pattern

## Status
Accepted

## Context

The Market Data API supports numerous "universal parameters" that can be applied to any endpoint:
- `format`: Response format (json, csv, html)
- `human`: Human-readable values
- `mode`: Data feed mode (live, cached, delayed)
- `maxage`: Cache freshness threshold
- `dateformat`: Date formatting for CSV/HTML
- `columns`: Column selection for CSV/HTML
- `headers`: Include headers in CSV/HTML

Passing these as individual method parameters would create unwieldy signatures:

```php
// Problematic: too many parameters
public function candles(
    string $symbol,
    string $from,
    ?string $to = null,
    string $resolution = 'D',
    ?int $countback = null,
    bool $extended = false,
    ?bool $adjust_splits = null,
    string $format = 'json',        // Universal params start here
    ?bool $human = null,
    ?string $mode = null,
    ?int $maxage = null,
    ?string $dateformat = null,
    ?array $columns = null,
    ?bool $headers = null,
    ?string $filename = null
): Candles;
```

## Decision

We implemented a **Parameter Object Pattern** where universal parameters are encapsulated in a `Parameters` class:

1. **Single Object**: All universal parameters in one typed object
2. **Constructor Validation**: Invalid combinations caught at construction time
3. **Optional Parameter**: Methods accept `?Parameters` with sensible defaults
4. **Client Defaults**: Global defaults set via `$client->default_params`

### Implementation

```php
// src/Endpoints/Requests/Parameters.php
class Parameters implements \Stringable
{
    public function __construct(
        public Format $format = Format::JSON,
        public ?bool $use_human_readable = null,
        public ?Mode $mode = null,
        int|\DateInterval|CarbonInterval|null $maxage = null,
        public ?DateFormat $date_format = null,
        public ?array $columns = null,
        public ?bool $add_headers = null,
        public ?string $filename = null,
    ) {
        // Validate maxage requires CACHED mode
        if ($this->maxage !== null && $mode !== Mode::CACHED) {
            throw new \InvalidArgumentException(
                'maxage parameter can only be used with CACHED mode.'
            );
        }

        // Validate CSV/HTML-only parameters
        if ($date_format !== null && $format !== Format::CSV && $format !== Format::HTML) {
            throw new \InvalidArgumentException(
                'date_format can only be used with CSV or HTML format.'
            );
        }
        // ... additional validation
    }
}

// Clean method signatures
public function candles(
    string $symbol,
    string $from,
    ?string $to = null,
    string $resolution = 'D',
    ?int $countback = null,
    bool $extended = false,
    ?bool $adjust_splits = null,
    ?Parameters $parameters = null    // All universal params here
): Candles;

// Usage examples
$candles = $client->stocks->candles('AAPL', '2024-01-01');

$candles = $client->stocks->candles('AAPL', '2024-01-01', parameters: new Parameters(
    format: Format::CSV,
    add_headers: true,
    columns: ['t', 'o', 'h', 'l', 'c', 'v']
));

$candles = $client->stocks->candles('AAPL', '2024-01-01', parameters: new Parameters(
    mode: Mode::CACHED,
    maxage: 300  // Accept data up to 5 minutes old
));
```

### Flexible maxage Input

The `maxage` parameter accepts multiple types for convenience:

```php
// Seconds as integer
new Parameters(mode: Mode::CACHED, maxage: 300);

// DateInterval
new Parameters(mode: Mode::CACHED, maxage: new \DateInterval('PT5M'));

// CarbonInterval
new Parameters(mode: Mode::CACHED, maxage: CarbonInterval::minutes(5));
```

## Consequences

### Positive
- **Clean Signatures**: Method signatures focus on endpoint-specific parameters
- **Validation**: Invalid parameter combinations caught early
- **Reusability**: Same Parameters object across all endpoints
- **IDE Support**: Full autocomplete for parameter options
- **Flexibility**: Named arguments allow partial specification

### Negative
- **Extra Object**: Users must construct Parameters object
- **Learning Curve**: Need to understand parameter grouping
- **Verbosity**: `new Parameters(...)` vs direct arguments

### Mitigations
- Parameters are optional with sensible defaults
- Named arguments make construction readable
- Client defaults reduce per-call configuration

## Alternatives Considered

### Alternative 1: Individual Method Parameters
```php
public function candles(..., string $format = 'json', ?bool $human = null): Candles;
```

**Pros**: Familiar pattern, no extra class
**Cons**: Unwieldy signatures, no grouped validation

### Alternative 2: Array Parameters
```php
$client->stocks->candles('AAPL', '2024-01-01', [
    'format' => 'csv',
    'headers' => true
]);
```

**Pros**: Flexible, familiar
**Cons**: No type safety, no validation, no IDE support

### Alternative 3: Fluent Builder
```php
$params = Parameters::create()
    ->format(Format::CSV)
    ->withHeaders()
    ->columns(['t', 'o', 'h', 'l', 'c']);
```

**Pros**: Expressive, chainable
**Cons**: More code, mutable state concerns

## References

- `src/Endpoints/Requests/Parameters.php` - Parameters implementation
- `src/Traits/UniversalParameters.php` - Parameter merging logic
- [Introduce Parameter Object](https://refactoring.guru/introduce-parameter-object) - Refactoring pattern

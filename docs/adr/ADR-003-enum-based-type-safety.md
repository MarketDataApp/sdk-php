# ADR-003: Enum-Based Type Safety

## Status
Accepted

## Context

The Market Data API has many parameters with fixed sets of valid values:
- Response formats: `json`, `csv`, `html`
- Data modes: `live`, `cached`, `delayed`
- Options sides: `call`, `put`
- Date formats: `timestamp`, `unix`, `spreadsheet`
- And more...

PHP 8.1 introduced native enums, providing compile-time type safety and IDE support. The SDK needed to decide how to handle these constrained value sets.

## Decision

We adopted **PHP 8.1+ backed enums** for all API parameters with fixed values. This provides:

1. **Type Safety**: Invalid values are caught at compile time
2. **IDE Support**: Autocomplete shows available options
3. **Documentation**: Enum cases are self-documenting
4. **Validation**: No runtime string comparison needed

### Implementation

```php
// src/Enums/Format.php
enum Format: string
{
    case JSON = 'json';
    case CSV = 'csv';
    case HTML = 'html';
}

// src/Enums/Mode.php
enum Mode: string
{
    case LIVE = 'live';
    case CACHED = 'cached';
    case DELAYED = 'delayed';
}

// src/Enums/Side.php
enum Side: string
{
    case CALL = 'call';
    case PUT = 'put';
}

// Usage in Parameters
class Parameters
{
    public function __construct(
        public Format $format = Format::JSON,
        public ?Mode $mode = null,
        // ...
    ) {}
}

// Usage
$params = new Parameters(format: Format::CSV, mode: Mode::LIVE);
```

### Complete Enum List

| Enum | Values | Usage |
|------|--------|-------|
| `Format` | JSON, CSV, HTML | Response format |
| `Mode` | LIVE, CACHED, DELAYED | Data freshness |
| `Side` | CALL, PUT | Options side |
| `Range` | ITM, OTM, ALL | Options moneyness |
| `DateFormat` | TIMESTAMP, UNIX, SPREADSHEET | CSV date format |
| `Expiration` | ALL, WEEKLY, MONTHLY, etc. | Options expiration type |
| `ApiStatusResult` | ONLINE, OFFLINE, UNKNOWN | Service status |

## Consequences

### Positive
- **Compile-Time Safety**: Invalid values fail at compile time, not runtime
- **IDE Autocomplete**: Full support in PhpStorm, VS Code, etc.
- **Self-Documenting**: Enum cases describe valid options
- **Refactoring Support**: Rename refactoring works correctly
- **No Magic Strings**: Values centralized in enum definitions

### Negative
- **PHP 8.1+ Required**: Cannot support older PHP versions
- **Serialization**: Need `->value` to get string for API calls
- **Learning Curve**: Users must know enum syntax

### Mitigations
- SDK requires PHP 8.2+ anyway (using other modern features)
- Backed enums provide `->value` for easy string conversion
- Clear documentation and IDE support help with learning

## Alternatives Considered

### Alternative 1: String Constants
```php
class Format
{
    public const JSON = 'json';
    public const CSV = 'csv';
    public const HTML = 'html';
}
```

**Pros**: Works on older PHP versions
**Cons**: No type safety, accepts any string, no IDE autocomplete

### Alternative 2: Value Objects
```php
class Format
{
    private string $value;

    private function __construct(string $value) { $this->value = $value; }

    public static function json(): self { return new self('json'); }
    public static function csv(): self { return new self('csv'); }
}
```

**Pros**: Works on PHP 7.4+, type-safe
**Cons**: Verbose, requires factory methods, more code to maintain

### Alternative 3: String Type Hints
```php
public function setFormat(string $format): void  // Accepts any string
```

**Pros**: Simple
**Cons**: No validation, runtime errors, no discoverability

## References

- `src/Enums/` - All enum definitions
- `src/Endpoints/Requests/Parameters.php` - Enum usage in parameters
- [PHP RFC: Enumerations](https://wiki.php.net/rfc/enumerations)
- PHP 8.1 Enum documentation

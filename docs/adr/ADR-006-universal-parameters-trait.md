# ADR-006: Universal Parameters Trait

## Status
Accepted

## Context

The Market Data API supports "universal parameters" that work across all endpoints. These parameters need to:
1. Be applied consistently to all API requests
2. Support client-level defaults (set once, apply everywhere)
3. Allow method-level overrides when needed
4. Handle format-specific parameters (CSV/HTML only)
5. Validate parameter combinations

Rather than duplicating this logic in every endpoint class, we needed a shared implementation.

## Decision

We implemented a **UniversalParameters trait** that encapsulates:

1. **Parameter Merging**: Method params override client defaults
2. **Validation**: Format-specific params checked against format
3. **Execute Methods**: Single and parallel execution with parameters
4. **Consistent Behavior**: All endpoints share the same logic

### Implementation

```php
// src/Traits/UniversalParameters.php
trait UniversalParameters
{
    /**
     * Merge method-level parameters with client default parameters.
     *
     * Priority order (highest to lowest):
     * 1. Method-level parameters (if provided)
     * 2. Client default parameters ($this->client->default_params)
     * 3. Default Parameters() values
     */
    protected function mergeParameters(?Parameters $methodParams): Parameters
    {
        // Start with client defaults
        $merged = clone $this->client->default_params;

        // Override with method-level parameters
        if ($methodParams !== null) {
            $merged->format = $methodParams->format;

            if ($methodParams->use_human_readable !== null) {
                $merged->use_human_readable = $methodParams->use_human_readable;
            }

            if ($methodParams->mode !== null) {
                $merged->mode = $methodParams->mode;
            }
            // ... more parameter merging
        }

        // Validate: CSV/HTML-only params cannot be used with JSON
        if ($merged->format !== Format::CSV && $merged->format !== Format::HTML) {
            if ($merged->date_format !== null) {
                throw new \InvalidArgumentException(
                    'date_format can only be used with CSV or HTML format.'
                );
            }
        }

        return $merged;
    }

    protected function execute(string $method, $arguments, ?Parameters $parameters): object
    {
        $parameters = $this->mergeParameters($parameters);

        $universalParams = ['format' => $parameters->format->value];

        if ($parameters->use_human_readable !== null) {
            $universalParams['human'] = $parameters->use_human_readable ? 'true' : 'false';
        }

        if ($parameters->mode !== null) {
            $universalParams['mode'] = $parameters->mode->value;
        }

        // ... more parameter conversion

        return $this->client->execute(
            self::BASE_URL . $method,
            array_merge($arguments, $universalParams)
        );
    }
}

// Usage in endpoint classes
class Stocks
{
    use UniversalParameters;
    use ValidatesInputs;

    public const BASE_URL = "v1/stocks/";

    public function quote(string $symbol, ?Parameters $parameters = null): Quote
    {
        return new Quote($this->execute("quotes/{$symbol}/", [], $parameters));
    }
}
```

### Parameter Priority

```php
// 1. Environment defaults (loaded at client construction)
// MARKETDATA_OUTPUT_FORMAT=csv in .env

// 2. Client defaults (can be modified)
$client->default_params->format = Format::JSON;
$client->default_params->mode = Mode::CACHED;

// 3. Method-level parameters (highest priority)
$client->stocks->quote('AAPL', parameters: new Parameters(
    format: Format::CSV  // Overrides client default
));
```

### Validation Examples

```php
// Invalid: date_format with JSON format
$params = new Parameters(
    format: Format::JSON,
    date_format: DateFormat::UNIX  // Throws InvalidArgumentException
);

// Invalid: maxage without CACHED mode
$params = new Parameters(
    mode: Mode::LIVE,
    maxage: 300  // Throws InvalidArgumentException
);

// Invalid: filename with parallel requests
$client->stocks->candles('AAPL', '2020-01-01', '2025-01-01', '5', parameters: new Parameters(
    format: Format::CSV,
    filename: 'output.csv'  // Throws InvalidArgumentException (multi-year split)
));
```

## Consequences

### Positive
- **DRY Principle**: Parameter logic not duplicated across endpoints
- **Consistency**: All endpoints behave identically
- **Maintainability**: Single place to update parameter handling
- **Testability**: Trait can be tested independently

### Negative
- **Hidden Logic**: Trait behavior less visible than direct code
- **Trait Dependencies**: Requires `$this->client` to exist
- **Testing Complexity**: Mock client needed for trait tests

### Mitigations
- Clear documentation of trait requirements
- Base class ensures client property exists
- Integration tests verify end-to-end behavior

## Alternatives Considered

### Alternative 1: Base Class Method
```php
abstract class BaseEndpoint
{
    protected function execute(string $method, array $args, ?Parameters $params): object
    {
        // ... parameter handling
    }
}
```

**Pros**: Standard OOP, clear inheritance
**Cons**: PHP single inheritance limits flexibility

### Alternative 2: Decorator Pattern
```php
class ParameterDecorator
{
    public function wrap(callable $execute): callable
    {
        return function(...$args) use ($execute) {
            // Apply parameters
            return $execute(...$args);
        };
    }
}
```

**Pros**: Composable, flexible
**Cons**: Complex, indirect execution path

### Alternative 3: Middleware Pattern
```php
$client->middleware->add(new UniversalParametersMiddleware());
```

**Pros**: Pluggable, testable
**Cons**: Overkill for this use case, adds complexity

## References

- `src/Traits/UniversalParameters.php` - Trait implementation
- `src/Endpoints/Stocks.php` - Example usage
- `src/ClientBase.php` - `default_params` property
- `src/Settings.php:166-191` - Environment-based defaults

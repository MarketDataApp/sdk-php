# ADR-001: Modular Endpoint Architecture

## Status
Accepted

## Context

The Market Data PHP SDK needs to provide access to multiple types of market data:
- **Stocks**: Quotes, candles, earnings, news, and bulk data
- **Options**: Chains, expirations, strikes, quotes, and lookups
- **Markets**: Market status and availability
- **Mutual Funds**: Candle data for mutual funds
- **Utilities**: API status and service health

Each domain has its own API endpoints, data types, and business logic. The SDK needed an architecture that would be scalable, maintainable, and provide a consistent interface for users.

## Decision

We implemented a **Modular Endpoint Architecture** where:

1. **Each domain is an independent endpoint class**: `Stocks`, `Options`, `Markets`, `MutualFunds`, `Utilities`
2. **Endpoints are injected into the main client**: The `Client` exposes each endpoint as a public property
3. **Each endpoint is responsible for its own methods**: Domain-specific logic stays with its endpoint class
4. **Common functionality is shared via traits and base classes**: `UniversalParameters`, `ValidatesInputs`

### Implementation

```php
// src/Client.php
class Client extends ClientBase
{
    public Stocks $stocks;
    public Options $options;
    public Markets $markets;
    public MutualFunds $mutual_funds;
    public Utilities $utilities;

    public function __construct(?string $token = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($token, $logger);

        $this->stocks = new Stocks($this);
        $this->options = new Options($this);
        $this->markets = new Markets($this);
        $this->mutual_funds = new MutualFunds($this);
        $this->utilities = new Utilities($this);
    }
}

// Usage
$client = new Client();
$quote = $client->stocks->quote('AAPL');
$status = $client->markets->status();
```

### Directory Structure

```
src/
├── Client.php                    # Main SDK entry point
├── ClientBase.php                # HTTP, retry, parallel execution
├── Endpoints/
│   ├── Stocks.php               # Stock methods
│   ├── Options.php              # Options methods
│   ├── Markets.php              # Market status methods
│   ├── MutualFunds.php          # Mutual fund methods
│   ├── Utilities.php            # API utilities
│   ├── Requests/                # Parameter objects
│   └── Responses/               # Typed response objects
└── Traits/
    ├── UniversalParameters.php  # Shared parameter handling
    └── ValidatesInputs.php      # Input validation
```

## Consequences

### Positive
- **Scalability**: New domains can be added without modifying existing code
- **Separation of Concerns**: Each endpoint handles only its domain
- **Discoverability**: IDE autocomplete shows available methods per domain
- **Testability**: Each endpoint can be tested independently
- **Consistent API**: Predictable `$client->domain->method()` pattern

### Negative
- **Constructor Complexity**: Client must instantiate all endpoints
- **Circular Reference**: Endpoints hold reference to parent client
- **Memory Usage**: All endpoints instantiated even if not used

### Mitigations
- Endpoints are lightweight (no state beyond client reference)
- PHP garbage collector handles circular references
- Future enhancement: lazy loading via `__get()` magic method

## Alternatives Considered

### Alternative 1: Direct Methods on Client
```php
$client->getStockQuote('AAPL');
$client->getMarketStatus();
```

**Pros**: Simpler initial implementation
**Cons**: Client becomes monolithic, difficult to scale, poor organization

### Alternative 2: Separate Clients per Domain
```php
$stocksClient = new StocksClient($token);
$marketsClient = new MarketsClient($token);
```

**Pros**: Maximum separation
**Cons**: User manages multiple clients, duplicated auth logic, no shared rate limits

### Alternative 3: Static Methods
```php
Stocks::quote('AAPL', $token);
Markets::status($token);
```

**Pros**: No instantiation needed
**Cons**: Cannot share state, difficult testing, no rate limit coordination

## References

- `src/Client.php` - Main client implementation
- `src/ClientBase.php` - Base functionality
- `src/Endpoints/` - All endpoint classes
- Pattern: [Dependency Injection](https://martinfowler.com/articles/injection.html)

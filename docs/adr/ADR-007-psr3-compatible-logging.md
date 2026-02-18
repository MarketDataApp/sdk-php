# ADR-007: PSR-3 Compatible Logging

## Status
Accepted

## Context

SDKs need logging for debugging, performance monitoring, and troubleshooting. The PHP ecosystem has standardized on PSR-3 (`Psr\Log\LoggerInterface`) for logging, enabling interoperability with popular frameworks:
- Monolog
- Laravel's Log facade
- Symfony's Logger
- Custom implementations

The SDK needed logging that:
1. Works standalone (no framework required)
2. Integrates with existing application loggers
3. Is configurable via environment variables
4. Doesn't pollute output by default

## Decision

We implemented **PSR-3 compatible logging** with:

1. **Default Logger**: Writes to STDERR with level filtering
2. **Logger Injection**: Accept any `LoggerInterface` implementation
3. **Environment Configuration**: `MARKETDATA_LOGGING_LEVEL` controls verbosity
4. **Factory Pattern**: Singleton logger for consistent behavior

### Implementation

```php
// src/Logging/LoggerFactory.php
class LoggerFactory
{
    private static ?LoggerInterface $instance = null;

    public static function getLogger(): LoggerInterface
    {
        if (self::$instance === null) {
            $level = Settings::getLogLevel();

            if (in_array(strtolower($level), ['none', 'off', 'disabled'], true)) {
                self::$instance = new NullLogger();
            } else {
                self::$instance = new DefaultLogger($level);
            }
        }
        return self::$instance;
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }
}

// src/Logging/DefaultLogger.php
class DefaultLogger extends AbstractLogger
{
    private const LOGGER_NAME = 'marketdata';

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $interpolated = $this->interpolate((string)$message, $context);

        // Format: [timestamp] marketdata.LEVEL: message
        @fwrite(STDERR, "[{$timestamp}] marketdata.{$levelUpper}: {$interpolated}\n");
    }
}

// Client usage
class Client extends ClientBase
{
    public function __construct(?string $token = null, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? LoggerFactory::getLogger();
        $this->logger->info('MarketDataClient initialized');
        // ...
    }
}
```

### Log Levels and Usage

| Level | Usage |
|-------|-------|
| DEBUG | Token info (obfuscated), internal requests |
| INFO | API requests, client initialization |
| WARNING | Retryable errors, cache misses |
| ERROR | Service offline, failed retries |

### Request Logging Format

```
[2024-02-18 10:30:45] marketdata.INFO: GET 200 45ms abc123-def456 https://api.marketdata.app/v1/stocks/quotes/AAPL/
[2024-02-18 10:30:46] marketdata.DEBUG: GET 200 12ms xyz789-ghi012 https://api.marketdata.app/user/
```

### Configuration

```bash
# Environment variable
export MARKETDATA_LOGGING_LEVEL=DEBUG   # All logs
export MARKETDATA_LOGGING_LEVEL=INFO    # Default
export MARKETDATA_LOGGING_LEVEL=NONE    # Silent

# Or in .env file
MARKETDATA_LOGGING_LEVEL=WARNING
```

### Framework Integration

```php
// Laravel
use Illuminate\Support\Facades\Log;

$client = new Client(logger: Log::channel('api'));

// Monolog
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$monolog = new Logger('marketdata');
$monolog->pushHandler(new StreamHandler('logs/api.log', Level::Debug));

$client = new Client(logger: $monolog);
```

## Consequences

### Positive
- **Standards Compliance**: Works with any PSR-3 logger
- **Zero Config**: Works out of the box with sensible defaults
- **Framework Agnostic**: No Laravel/Symfony dependency
- **Configurable**: Environment variable control
- **Silent by Default**: NullLogger when logging disabled

### Negative
- **STDERR Output**: Default logger writes to STDERR (not files)
- **Singleton Pattern**: LoggerFactory uses global state
- **No File Rotation**: Default logger doesn't handle log rotation

### Mitigations
- Users can inject production loggers with file handling
- Factory can be reset for testing
- Documentation recommends framework loggers for production

## Alternatives Considered

### Alternative 1: No Default Logger
```php
public function __construct(?LoggerInterface $logger = null)
{
    $this->logger = $logger ?? new NullLogger();  // Silent by default
}
```

**Pros**: Completely silent unless configured
**Cons**: Debugging difficult without explicit logger setup

### Alternative 2: File-Based Default Logger
```php
$this->logger = new FileLogger('/tmp/marketdata.log');
```

**Pros**: Persistent logs without configuration
**Cons**: Permission issues, disk space, non-standard location

### Alternative 3: Custom Logger Interface
```php
interface MarketDataLogger
{
    public function logRequest(Request $request, Response $response): void;
}
```

**Pros**: Domain-specific methods
**Cons**: Not PSR-3 compatible, reinventing the wheel

## References

- `src/Logging/LoggerFactory.php` - Factory implementation
- `src/Logging/DefaultLogger.php` - Default STDERR logger
- `src/Logging/LoggingUtilities.php` - Duration formatting
- `src/Client.php:76-88` - Logger initialization
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)

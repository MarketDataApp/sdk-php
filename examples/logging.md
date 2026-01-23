# Logging in the Market Data PHP SDK

The SDK includes built-in PSR-3 compatible logging for debugging and monitoring API requests.

## Quick Start

By default, the SDK logs at `INFO` level to STDERR:

```php
$client = new MarketDataApp\Client();
// Logs: [2026-01-23 15:08:56] marketdata.INFO: MarketDataClient initialized
// Logs: [2026-01-23 15:08:57] marketdata.INFO: GET 200 333ms cf-ray-id https://api.marketdata.app/v1/stocks/quotes/AAPL/?format=json
```

## Configuration

### Environment Variable

Set the log level via the `MARKETDATA_LOGGING_LEVEL` environment variable:

```bash
# In your shell
export MARKETDATA_LOGGING_LEVEL=DEBUG

# Or in .env file
MARKETDATA_LOGGING_LEVEL=DEBUG
```

### Log Levels

| Level       | What Gets Logged                                          |
|-------------|-----------------------------------------------------------|
| `DEBUG`     | Token (obfuscated), internal requests, all API requests   |
| `INFO`      | Client initialization, API request results **(default)**  |
| `NOTICE`    | Normal but significant events                             |
| `WARNING`   | Warnings only                                             |
| `ERROR`     | Errors only (e.g., service offline)                       |
| `CRITICAL`  | Critical errors only                                      |
| `ALERT`     | Alerts only                                               |
| `EMERGENCY` | Emergencies only                                          |
| `NONE`/`OFF`| Disable all logging                                       |

### Log Output Format

Each log line follows the format:
```
[TIMESTAMP] marketdata.LEVEL: MESSAGE
```

Request logs include:
```
[2026-01-23 15:08:57] marketdata.INFO: GET 200 333ms cf-ray-id https://api.marketdata.app/v1/stocks/quotes/AAPL/?format=json
```

- **METHOD** - HTTP method (GET)
- **STATUS** - HTTP status code (200)
- **DURATION** - Request duration (333ms)
- **REQUEST_ID** - Cloudflare ray ID for support requests
- **URL** - Full URL with query parameters

## Disable Logging

### Option 1: Environment Variable

```bash
export MARKETDATA_LOGGING_LEVEL=NONE
```

### Option 2: NullLogger

```php
use MarketDataApp\Client;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());
```

## Custom Loggers

The SDK accepts any PSR-3 compatible logger via the `logger` parameter.

### Using the Built-in Logger with a Custom Level

```php
use MarketDataApp\Client;
use MarketDataApp\Logging\DefaultLogger;

$client = new Client(logger: new DefaultLogger('debug'));
```

### Monolog Integration

```php
use MarketDataApp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$monolog = new Logger('marketdata');
$monolog->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
$monolog->pushHandler(new RotatingFileHandler('/var/log/marketdata.log', 7, Logger::INFO));

$client = new Client(logger: $monolog);
```

### Laravel Integration

```php
use MarketDataApp\Client;
use Illuminate\Support\Facades\Log;

// Use Laravel's default logger
$client = new Client(logger: Log::channel('single'));

// Or a dedicated channel (define in config/logging.php)
$client = new Client(logger: Log::channel('marketdata'));
```

Example Laravel channel configuration (`config/logging.php`):
```php
'marketdata' => [
    'driver' => 'daily',
    'path' => storage_path('logs/marketdata.log'),
    'level' => env('MARKETDATA_LOG_LEVEL', 'info'),
    'days' => 14,
],
```

### Custom File Logger

```php
use MarketDataApp\Client;
use Psr\Log\AbstractLogger;

class FileLogger extends AbstractLogger
{
    private $handle;

    public function __construct(string $filepath)
    {
        $this->handle = fopen($filepath, 'a');
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        fwrite($this->handle, "[{$timestamp}] {$level}: {$message}\n");
    }
}

$client = new Client(logger: new FileLogger('/var/log/marketdata.log'));
```

### JSON Logger for Log Aggregation

```php
use MarketDataApp\Client;
use Psr\Log\AbstractLogger;

class JsonLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
            'service' => 'marketdata-sdk',
        ];
        fwrite(STDERR, json_encode($entry) . "\n");
    }
}

$client = new Client(logger: new JsonLogger());
```

## Recommendations by Environment

| Environment | Recommended Level | Reason                              |
|-------------|-------------------|-------------------------------------|
| Development | `DEBUG`           | See all requests, tokens, timing    |
| Staging     | `INFO`            | General visibility                  |
| Production  | `WARNING`/`ERROR` | Reduce log noise, capture issues    |
| CI/Testing  | `NONE`            | Keep test output clean              |

## Example Output

### INFO Level (Default)

```
[2026-01-23 15:08:56] marketdata.INFO: MarketDataClient initialized
[2026-01-23 15:08:57] marketdata.INFO: GET 200 333ms 9c293d470aa6adae-EZE https://api.marketdata.app/v1/stocks/quotes/AAPL/?format=json
```

### DEBUG Level

```
[2026-01-23 15:08:56] marketdata.INFO: MarketDataClient initialized
[2026-01-23 15:08:56] marketdata.DEBUG: Token: ****************************************************HMD0
[2026-01-23 15:08:57] marketdata.DEBUG: GET 200 616ms 9c293d432876adae-EZE https://api.marketdata.app/user/
[2026-01-23 15:08:57] marketdata.INFO: GET 200 333ms 9c293d470aa6adae-EZE https://api.marketdata.app/v1/stocks/quotes/AAPL/?format=json
```

### Error Scenario

```
[2026-01-23 15:08:58] marketdata.INFO: GET 500 892ms 9c293d470aa6adae-EZE https://api.marketdata.app/v1/stocks/quotes/AAPL/?format=json
[2026-01-23 15:08:58] marketdata.ERROR: Service v1/stocks/quotes/AAPL is offline
```

## See Also

- [logging.php](logging.php) - Runnable example demonstrating all logging features
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)

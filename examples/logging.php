<?php

/**
 * Logging Examples - Run with: php examples/logging.php
 *
 * Try different log levels:
 *   MARKETDATA_LOGGING_LEVEL=DEBUG php examples/logging.php
 *   MARKETDATA_LOGGING_LEVEL=NONE php examples/logging.php
 *
 * See logging.md for full documentation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Logging\DefaultLogger;
use MarketDataApp\Logging\LoggerFactory;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

echo "=== Market Data SDK Logging Examples ===\n\n";

// Example 1: Default logging (uses MARKETDATA_LOGGING_LEVEL env var, default: INFO)
echo "--- Example 1: Default Logging ---\n";
echo "MARKETDATA_LOGGING_LEVEL: " . (getenv('MARKETDATA_LOGGING_LEVEL') ?: 'INFO') . "\n";
LoggerFactory::resetLogger();
$client = new Client();
echo "\n";

// Example 2: Disable logging with NullLogger
echo "--- Example 2: Disabled Logging ---\n";
$silentClient = new Client(logger: new NullLogger());
echo "Created client with NullLogger (no output)\n\n";

// Example 3: Custom log level via DefaultLogger
echo "--- Example 3: Custom Log Level ---\n";
$debugClient = new Client(logger: new DefaultLogger('debug'));
echo "\n";

// Example 4: Custom file logger
echo "--- Example 4: File Logger ---\n";

class FileLogger extends AbstractLogger
{
    private $handle;

    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'a');
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        fwrite($this->handle, "[" . date('Y-m-d H:i:s') . "] {$level}: {$message}\n");
    }
}

$logPath = sys_get_temp_dir() . '/marketdata-sdk.log';
$fileClient = new Client(logger: new FileLogger($logPath));
echo "Logs written to: {$logPath}\n\n";

// Example 5: JSON logger for log aggregation
echo "--- Example 5: JSON Logger ---\n";

class JsonLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        fwrite(STDERR, json_encode([
            'time' => date('c'),
            'level' => $level,
            'msg' => (string)$message,
            'ctx' => $context ?: null,
        ]) . "\n");
    }
}

$jsonClient = new Client(logger: new JsonLogger());
echo "\n";

// Example 6: Environment-based configuration
echo "--- Example 6: Environment-Based ---\n";

function createClient(): Client
{
    $env = getenv('APP_ENV') ?: 'development';

    return match ($env) {
        'production' => new Client(logger: new NullLogger()),
        'staging' => new Client(logger: new DefaultLogger('info')),
        'development' => new Client(logger: new DefaultLogger('debug')),
        default => new Client(),
    };
}

$envClient = createClient();
echo "APP_ENV: " . (getenv('APP_ENV') ?: 'development') . "\n\n";

echo "=== Done ===\n";
echo "See logging.md for Monolog and Laravel integration examples.\n";

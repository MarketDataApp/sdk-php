<?php

namespace MarketDataApp\Logging;

use MarketDataApp\Settings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating and managing logger instances.
 *
 * Provides singleton access to the configured logger, supporting:
 * - Environment variable configuration via MARKETDATA_LOGGING_LEVEL
 * - Custom logger injection for framework integration
 * - NullLogger for disabled logging (NONE/OFF levels)
 */
class LoggerFactory
{
    /**
     * @var LoggerInterface|null Singleton logger instance.
     */
    private static ?LoggerInterface $instance = null;

    /**
     * Get the configured logger instance.
     *
     * Creates a new logger on first call based on MARKETDATA_LOGGING_LEVEL.
     * Subsequent calls return the same instance.
     *
     * @return LoggerInterface The configured logger.
     */
    public static function getLogger(): LoggerInterface
    {
        if (self::$instance === null) {
            $level = Settings::getLogLevel();

            // If level is "none" or "off", return NullLogger (silent)
            if (in_array(strtolower($level), ['none', 'off', 'disabled'], true)) {
                self::$instance = new NullLogger();
            } else {
                self::$instance = new DefaultLogger($level);
            }
        }
        return self::$instance;
    }

    /**
     * Set a custom logger instance.
     *
     * Use this to inject a custom PSR-3 logger (Monolog, Laravel, etc.).
     *
     * @param LoggerInterface $logger The custom logger to use.
     *
     * @return void
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }

    /**
     * Reset the logger singleton.
     *
     * Clears the cached logger instance, causing the next getLogger()
     * call to create a new logger. Useful for testing.
     *
     * @return void
     */
    public static function resetLogger(): void
    {
        self::$instance = null;
    }
}

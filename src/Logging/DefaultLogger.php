<?php

namespace MarketDataApp\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Default PSR-3 compliant logger for MarketDataApp SDK.
 *
 * Writes log messages to STDERR with format: [timestamp] marketdata.LEVEL: message
 * Supports level filtering - only logs at or above the configured minimum level.
 */
class DefaultLogger extends AbstractLogger
{
    /**
     * Logger name used in output format.
     */
    private const LOGGER_NAME = 'marketdata';

    /**
     * @var string The minimum log level to output.
     */
    private string $minLevel;

    /**
     * @var resource|null Output stream (defaults to STDERR).
     */
    private $output;

    /**
     * @var array<string, int> Log level priority mapping (lower = less severe).
     */
    private array $levels = [
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /**
     * Create a new DefaultLogger instance.
     *
     * @param string        $minLevel The minimum log level to output (default: INFO).
     * @param resource|null $output   Output stream (default: STDERR). Pass a stream for testing.
     */
    public function __construct(string $minLevel = LogLevel::INFO, $output = null)
    {
        $this->minLevel = strtolower($minLevel);
        $this->output = $output;
    }

    /**
     * Log a message at the specified level.
     *
     * @param mixed              $level   The log level.
     * @param string|\Stringable $message The log message.
     * @param array              $context Context data for interpolation.
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $level = strtolower((string)$level);

        // Skip if level is below minimum
        if (!isset($this->levels[$level]) || !isset($this->levels[$this->minLevel])) {
            return;
        }

        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $interpolated = $this->interpolate((string)$message, $context);

        // Format: [timestamp] marketdata.LEVEL: message
        // Suppress errors on broken pipe (e.g., when STDERR is closed or piped)
        $stream = $this->output ?? STDERR;
        @fwrite($stream, "[{$timestamp}] " . self::LOGGER_NAME . ".{$levelUpper}: {$interpolated}\n");
    }

    /**
     * Interpolate context values into message placeholders.
     *
     * Replaces {key} placeholders with corresponding values from context array.
     *
     * @param string $message The message with placeholders.
     * @param array  $context The context values.
     *
     * @return string The interpolated message.
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string)$val;
            }
        }
        return strtr($message, $replace);
    }
}

<?php

namespace MarketDataApp;

use Dotenv\Dotenv;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;

/**
 * Settings class for MarketDataApp SDK.
 *
 * Handles configuration loading from environment variables and .env files.
 * Provides automatic token resolution with proper precedence order.
 */
class Settings
{
    /**
     * @var bool Flag to track if .env file has been loaded
     */
    private static bool $dotenvLoaded = false;

    /**
     * Get the API token with automatic resolution from multiple sources.
     *
     * Token resolution order (highest to lowest priority):
     * 1. Explicit token (passed as parameter)
     * 2. Environment variable (MARKETDATA_TOKEN)
     * 3. .env file (MARKETDATA_TOKEN)
     * 4. Empty string (fallback for free symbols)
     *
     * @param string|null $explicitToken The token explicitly passed to the constructor, if any.
     *
     * @return string The resolved token.
     */
    public static function getToken(?string $explicitToken = null): string
    {
        // Priority 1: Explicit token takes highest precedence (even if empty string)
        // If explicitly provided (not null), use it regardless of value
        if ($explicitToken !== null) {
            return $explicitToken;
        }

        // Priority 2: Check environment variable
        $envToken = self::getEnvToken();
        if ($envToken !== null && $envToken !== '') {
            return $envToken;
        }

        // Priority 3: Check .env file
        $dotenvToken = self::getDotenvToken();
        if ($dotenvToken !== null && $dotenvToken !== '') {
            return $dotenvToken;
        }

        // Priority 4: Fallback to empty string (allows free symbols)
        return '';
    }

    /**
     * Get token from environment variables.
     *
     * @return string|null The token from environment, or null if not found.
     */
    private static function getEnvToken(): ?string
    {
        // Try getenv() first (works in most environments)
        $token = getenv('MARKETDATA_TOKEN');
        if ($token !== false && $token !== '') {
            return $token;
        }

        // Try $_ENV as fallback (may not be populated if variables_order doesn't include 'E')
        if (isset($_ENV['MARKETDATA_TOKEN']) && $_ENV['MARKETDATA_TOKEN'] !== '') {
            return $_ENV['MARKETDATA_TOKEN'];
        }

        // Try $_SERVER as last resort (always available in CLI/web contexts)
        if (isset($_SERVER['MARKETDATA_TOKEN']) && $_SERVER['MARKETDATA_TOKEN'] !== '') {
            return $_SERVER['MARKETDATA_TOKEN'];
        }

        return null;
    }

    /**
     * Get token from .env file.
     *
     * Searches for .env file starting from current working directory,
     * then searches up the directory tree (max 5 levels).
     *
     * @return string|null The token from .env file, or null if not found.
     */
    private static function getDotenvToken(): ?string
    {
        // Only try to load .env file once
        if (!self::$dotenvLoaded) {
            self::loadDotenv();
            self::$dotenvLoaded = true;
        }

        // Check if Dotenv loaded the value
        $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        if ($token !== null && $token !== '') {
            return $token;
        }

        return null;
    }

    /**
     * Load .env file if it exists.
     *
     * Searches for .env file starting from current working directory,
     * then searches up the directory tree (max 5 levels).
     *
     * @return void
     */
    private static function loadDotenv(): void
    {
        $currentDir = getcwd();
        if ($currentDir === false) {
            return;
        }

        $maxLevels = 5;
        $dir = $currentDir;
        $levels = 0;

        // Search up directory tree for .env file
        while ($levels < $maxLevels) {
            $envFile = $dir . DIRECTORY_SEPARATOR . '.env';
            if (file_exists($envFile) && is_readable($envFile)) {
                try {
                    $dotenv = Dotenv::createImmutable($dir);
                    $dotenv->load();
                    return;
                } catch (\Exception $e) {
                    // Silently fail if .env file can't be loaded
                    // This allows graceful degradation
                    return;
                }
            }

            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                // Reached filesystem root
                break;
            }
            $dir = $parentDir;
            $levels++;
        }
    }

    /**
     * Get default universal parameters from environment variables and .env file.
     *
     * Reads universal parameters from environment variables with the following precedence:
     * 1. Environment variables (getenv, $_ENV, $_SERVER)
     * 2. .env file (loaded via Dotenv)
     * 3. Default values (null or Format::JSON for format)
     *
     * @return Parameters Parameters instance with values from environment, or defaults if not set.
     */
    public static function getDefaultParameters(): Parameters
    {
        $format = self::getEnvFormat();
        $useHumanReadable = self::getEnvBool('MARKETDATA_USE_HUMAN_READABLE');
        $mode = self::getEnvMode();

        // CSV/HTML-only parameters: only set if format is CSV or HTML
        $dateFormat = null;
        $columns = null;
        $addHeaders = null;

        if ($format === Format::CSV || $format === Format::HTML) {
            $dateFormat = self::getEnvDateFormat();
            $columns = self::getEnvColumns();
            $addHeaders = self::getEnvBool('MARKETDATA_ADD_HEADERS');
        }

        return new Parameters(
            format: $format,
            date_format: $dateFormat,
            columns: $columns,
            add_headers: $addHeaders,
            use_human_readable: $useHumanReadable,
            mode: $mode
        );
    }

    /**
     * Get format from environment variable MARKETDATA_OUTPUT_FORMAT.
     *
     * @return Format Format enum value, or Format::JSON if not set or invalid.
     */
    private static function getEnvFormat(): Format
    {
        $value = self::getEnvValue('MARKETDATA_OUTPUT_FORMAT');
        if ($value === null) {
            return Format::JSON;
        }

        $value = strtolower(trim($value));
        return match ($value) {
            'json' => Format::JSON,
            'csv' => Format::CSV,
            'html' => Format::HTML,
            default => Format::JSON, // Default on invalid value
        };
    }

    /**
     * Get date format from environment variable MARKETDATA_DATE_FORMAT.
     *
     * @return DateFormat|null DateFormat enum value, or null if not set or invalid.
     */
    private static function getEnvDateFormat(): ?DateFormat
    {
        $value = self::getEnvValue('MARKETDATA_DATE_FORMAT');
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        return match ($value) {
            'timestamp' => DateFormat::TIMESTAMP,
            'unix' => DateFormat::UNIX,
            'spreadsheet' => DateFormat::SPREADSHEET,
            default => null, // Invalid values return null
        };
    }

    /**
     * Get mode from environment variable MARKETDATA_MODE.
     *
     * @return Mode|null Mode enum value, or null if not set or invalid.
     */
    private static function getEnvMode(): ?Mode
    {
        $value = self::getEnvValue('MARKETDATA_MODE');
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        return match ($value) {
            'live' => Mode::LIVE,
            'cached' => Mode::CACHED,
            'delayed' => Mode::DELAYED,
            default => null, // Invalid values return null
        };
    }

    /**
     * Get columns array from environment variable MARKETDATA_COLUMNS.
     *
     * Expects comma-separated string, e.g., "symbol,ask,bid"
     *
     * @return array|null Array of column names, or null if not set or empty.
     */
    private static function getEnvColumns(): ?array
    {
        $value = self::getEnvValue('MARKETDATA_COLUMNS');
        if ($value === null || $value === '') {
            return null;
        }

        // Split by comma and trim each value
        $columns = array_map('trim', explode(',', $value));
        // Filter out empty strings
        $columns = array_filter($columns, fn($col) => $col !== '');

        return empty($columns) ? null : array_values($columns);
    }

    /**
     * Get boolean value from environment variable.
     *
     * Accepts: "true", "false", "1", "0" (case-insensitive)
     *
     * @param string $varName Environment variable name.
     *
     * @return bool|null Boolean value, or null if not set or invalid.
     */
    private static function getEnvBool(string $varName): ?bool
    {
        $value = self::getEnvValue($varName);
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        return match ($value) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => null, // Invalid values return null
        };
    }

    /**
     * Get environment variable value from multiple sources.
     *
     * Checks in order:
     * 1. getenv()
     * 2. $_ENV
     * 3. $_SERVER
     * 4. .env file (via Dotenv, which populates $_ENV/$_SERVER)
     *
     * @param string $varName Environment variable name.
     *
     * @return string|null Environment variable value, or null if not found.
     */
    private static function getEnvValue(string $varName): ?string
    {
        // Try getenv() first
        $value = getenv($varName);
        if ($value !== false && $value !== '') {
            return $value;
        }

        // Try $_ENV
        if (isset($_ENV[$varName]) && $_ENV[$varName] !== '') {
            return $_ENV[$varName];
        }

        // Try $_SERVER
        if (isset($_SERVER[$varName]) && $_SERVER[$varName] !== '') {
            return $_SERVER[$varName];
        }

        // Try loading .env file (if not already loaded)
        if (!self::$dotenvLoaded) {
            self::loadDotenv();
            self::$dotenvLoaded = true;

            // Check again after loading .env
            $value = getenv($varName);
            if ($value !== false && $value !== '') {
                return $value;
            }

            if (isset($_ENV[$varName]) && $_ENV[$varName] !== '') {
                return $_ENV[$varName];
            }

            if (isset($_SERVER[$varName]) && $_SERVER[$varName] !== '') {
                return $_SERVER[$varName];
            }
        }

        return null;
    }
}

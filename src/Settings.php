<?php

namespace MarketDataApp;

use Dotenv\Dotenv;

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
}
